<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

final class PublicReservationService
{
    public static function ensureSchema(): void
    {
        $pdo = Database::getInstance();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS reservation_booking_links (id INTEGER PRIMARY KEY AUTOINCREMENT, restaurant_id INTEGER NOT NULL UNIQUE, public_token TEXT NOT NULL UNIQUE, is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS restaurant_reservation_hours (restaurant_id INTEGER NOT NULL, weekday INTEGER NOT NULL, is_closed INTEGER NOT NULL DEFAULT 0, open_time TEXT NOT NULL DEFAULT '09:00', close_time TEXT NOT NULL DEFAULT '21:00', slot_interval SMALLINT NOT NULL DEFAULT 30, duration_minutes SMALLINT NOT NULL DEFAULT 90, PRIMARY KEY (restaurant_id, weekday))");
            self::addSqliteColumn('reservations', 'customer_email', 'TEXT NULL');
            self::addSqliteColumn('reservations', 'public_token', 'TEXT NULL');
            self::addSqliteColumn('reservations', 'notification_email_sent', 'INTEGER NOT NULL DEFAULT 0');
            self::addSqliteColumn('reservations', 'notification_sms_sent', 'INTEGER NOT NULL DEFAULT 0');
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_reservations_public_token ON reservations(public_token)");
            return;
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS reservation_booking_links (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            restaurant_id INT UNSIGNED NOT NULL,
            public_token CHAR(64) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY uq_reservation_booking_restaurant (restaurant_id),
            UNIQUE KEY uq_reservation_booking_token (public_token),
            CONSTRAINT fk_reservation_booking_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS restaurant_reservation_hours (
            restaurant_id INT UNSIGNED NOT NULL,
            weekday TINYINT UNSIGNED NOT NULL,
            is_closed TINYINT(1) NOT NULL DEFAULT 0,
            open_time TIME NOT NULL DEFAULT '09:00:00',
            close_time TIME NOT NULL DEFAULT '21:00:00',
            slot_interval SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 90,
            PRIMARY KEY (restaurant_id, weekday),
            CONSTRAINT fk_reservation_hours_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $columns = Database::fetchAll("SHOW COLUMNS FROM reservations");
        $known = array_column($columns, 'Field');
        $add = [
            'customer_email' => 'VARCHAR(254) NULL',
            'public_token' => 'CHAR(64) NULL',
            'notification_email_sent' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'notification_sms_sent' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ];
        foreach ($add as $name => $definition) {
            if (!in_array($name, $known, true)) $pdo->exec("ALTER TABLE reservations ADD COLUMN {$name} {$definition}");
        }
        $pdo->exec("ALTER TABLE reservations MODIFY status ENUM('pending','booked','seated','cancelled','no_show') NOT NULL DEFAULT 'booked'");
        $index = Database::fetchOne("SELECT COUNT(*) AS total FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'reservations' AND index_name = 'uq_reservations_public_token'");
        if ((int)($index['total'] ?? 0) === 0) $pdo->exec("CREATE UNIQUE INDEX uq_reservations_public_token ON reservations(public_token)");
    }

    private static function addSqliteColumn(string $table, string $column, string $definition): void
    {
        $cols = Database::fetchAll("PRAGMA table_info(" . $table . ")");
        if (!in_array($column, array_column($cols, 'name'), true)) Database::getInstance()->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }

    public static function getOrCreateLink(int $restaurantId): string
    {
        $row = Database::fetchOne("SELECT public_token, is_active FROM reservation_booking_links WHERE restaurant_id = ? LIMIT 1", [$restaurantId]);
        if ($row && (int)$row['is_active'] === 1 && preg_match('/^[a-f0-9]{64}$/', (string)$row['public_token'])) return (string)$row['public_token'];
        $token = bin2hex(random_bytes(32));
        if ($row) Database::query("UPDATE reservation_booking_links SET public_token = ?, is_active = 1 WHERE restaurant_id = ?", [$token, $restaurantId]);
        else Database::query("INSERT INTO reservation_booking_links (restaurant_id, public_token, is_active) VALUES (?, ?, 1)", [$restaurantId, $token]);
        return $token;
    }

    public static function getRestaurantByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) return null;
        return Database::fetchOne("SELECT r.id AS restaurant_id, r.name AS restaurant_name, r.logo_path, r.tagline
            FROM reservation_booking_links l JOIN restaurants r ON r.id = l.restaurant_id
            WHERE l.public_token = ? AND l.is_active = 1 AND r.status = 'active' AND r.deleted_at IS NULL LIMIT 1", [strtolower($token)]);
    }

    public static function getHours(int $restaurantId): array
    {
        $rows = Database::fetchAll("SELECT weekday, is_closed, open_time, close_time, slot_interval, duration_minutes FROM restaurant_reservation_hours WHERE restaurant_id = ? ORDER BY weekday", [$restaurantId]);
        $byDay = [];
        foreach ($rows as $row) $byDay[(int)$row['weekday']] = $row;
        $out = [];
        for ($day=0; $day<7; $day++) $out[$day] = $byDay[$day] ?? ['weekday'=>$day,'is_closed'=>($day===0?1:0),'open_time'=>'09:00:00','close_time'=>'21:00:00','slot_interval'=>30,'duration_minutes'=>90];
        return $out;
    }

    public static function saveHours(int $restaurantId, array $input): void
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            for ($day=0; $day<7; $day++) {
                $row = is_array($input[$day] ?? null) ? $input[$day] : [];
                $closed = !empty($row['is_closed']) ? 1 : 0;
                $open = trim((string)($row['open_time'] ?? '09:00'));
                $close = trim((string)($row['close_time'] ?? '21:00'));
                $interval = filter_var($row['slot_interval'] ?? 30, FILTER_VALIDATE_INT);
                $duration = filter_var($row['duration_minutes'] ?? 90, FILTER_VALIDATE_INT);
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $open) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $close) || $close <= $open) throw new InvalidArgumentException('Each open day must have a valid closing time later than its opening time.');
                if (!is_int($interval) || !in_array($interval, [15,30,60], true)) throw new InvalidArgumentException('Booking intervals must be 15, 30, or 60 minutes.');
                if (!is_int($duration) || !in_array($duration, [60,90,120,180,240], true)) throw new InvalidArgumentException('Choose a valid reservation duration.');
                Database::query("INSERT INTO restaurant_reservation_hours (restaurant_id,weekday,is_closed,open_time,close_time,slot_interval,duration_minutes)
                    VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE is_closed=VALUES(is_closed),open_time=VALUES(open_time),close_time=VALUES(close_time),slot_interval=VALUES(slot_interval),duration_minutes=VALUES(duration_minutes)",
                    [$restaurantId,$day,$closed,$open.':00',$close.':00',$interval,$duration]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function availability(string $token, string $date, int $partySize): array
    {
        $restaurant = self::getRestaurantByToken($token);
        if (!$restaurant) throw new InvalidArgumentException('This booking link is invalid or no longer active.');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new InvalidArgumentException('Choose a valid date.');
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$day || $day->format('Y-m-d') !== $date || $day < new DateTimeImmutable('today') || $day > (new DateTimeImmutable('today'))->modify('+90 days')) return [];
        $hours = self::getHours((int)$restaurant['restaurant_id'])[(int)$day->format('w')];
        if ((int)$hours['is_closed'] === 1) return [];
        $duration = (int)$hours['duration_minutes'];
        $interval = (int)$hours['slot_interval'];
        $open = new DateTimeImmutable($date.' '.substr((string)$hours['open_time'],0,5));
        $close = new DateTimeImmutable($date.' '.substr((string)$hours['close_time'],0,5));
        $lastStart = $close->modify('-'.$duration.' minutes');
        if ($lastStart < $open) return [];
        $tables = Database::fetchAll("SELECT id, capacity FROM restaurant_tables WHERE restaurant_id = ? AND deleted_at IS NULL AND is_active = 1 AND capacity >= ? AND status NOT IN ('occupied','cleaning') ORDER BY capacity ASC", [(int)$restaurant['restaurant_id'],$partySize]);
        $slots = [];
        for ($slot=$open; $slot <= $lastStart; $slot=$slot->modify('+'.$interval.' minutes')) {
            if ($slot < new DateTimeImmutable('now')) continue;
            $end = $slot->modify('+'.$duration.' minutes');
            $free = 0;
            foreach ($tables as $table) {
                $conflict = Database::fetchOne("SELECT id FROM reservations WHERE restaurant_id = ? AND table_id = ? AND status IN ('pending','booked','seated')
                    AND reservation_at < ? AND DATE_ADD(reservation_at, INTERVAL duration_minutes MINUTE) > ? LIMIT 1",
                    [(int)$restaurant['restaurant_id'],(int)$table['id'],$end->format('Y-m-d H:i:s'),$slot->format('Y-m-d H:i:s')]);
                if (!$conflict) { $free++; break; }
            }
            if ($free > 0) $slots[] = $slot->format('H:i');
        }
        return $slots;
    }

    public static function createPublic(string $token, array $input): array
    {
        $restaurant = self::getRestaurantByToken($token);
        if (!$restaurant) throw new InvalidArgumentException('This booking link is invalid or no longer active.');
        if (trim((string)($input['website'] ?? '')) !== '') throw new InvalidArgumentException('Unable to submit this booking.');
        $name = trim((string)($input['customer_name'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $party = filter_var($input['party_size'] ?? null, FILTER_VALIDATE_INT);
        $date = trim((string)($input['reservation_date'] ?? ''));
        $time = trim((string)($input['reservation_time'] ?? ''));
        $notes = trim((string)($input['notes'] ?? ''));
        if ($name === '' || mb_strlen($name)>120) throw new InvalidArgumentException('Enter your name (up to 120 characters).');
        if ($phone === '' || mb_strlen($phone)>30) throw new InvalidArgumentException('Enter a valid mobile number.');
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($email)>254) throw new InvalidArgumentException('Enter a valid email address.');
        if (!is_int($party) || $party<1 || $party>30) throw new InvalidArgumentException('Party size must be between 1 and 30.');
        if (mb_strlen($notes)>500) throw new InvalidArgumentException('Notes must be 500 characters or fewer.');
        $slots = self::availability($token,$date,$party);
        if (!in_array($time,$slots,true)) throw new InvalidArgumentException('That time is no longer available. Please choose another time.');
        $restaurantId=(int)$restaurant['restaurant_id'];
        $hours=self::getHours($restaurantId)[(int)(new DateTimeImmutable($date))->format('w')];
        $duration=(int)$hours['duration_minutes'];
        $start=new DateTimeImmutable($date.' '.$time);
        $end=$start->modify('+'.$duration.' minutes');
        $pdo=Database::getInstance();
        try {
            $pdo->beginTransaction();
            // Serialize concurrent public bookings for this restaurant before the final conflict check.
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $pdo->query('SELECT id FROM restaurants WHERE id='.(int)$restaurantId.' FOR UPDATE')->fetch();
            $tables=Database::fetchAll("SELECT id,capacity FROM restaurant_tables WHERE restaurant_id=? AND deleted_at IS NULL AND is_active=1 AND capacity>=? AND status NOT IN ('occupied','cleaning') ORDER BY capacity ASC",[$restaurantId,$party]);
            $chosen=null;
            foreach($tables as $table) {
                $conflict=Database::fetchOne("SELECT id FROM reservations WHERE restaurant_id=? AND table_id=? AND status IN ('pending','booked','seated')
                    AND reservation_at < ? AND DATE_ADD(reservation_at, INTERVAL duration_minutes MINUTE) > ? LIMIT 1",
                    [$restaurantId,(int)$table['id'],$end->format('Y-m-d H:i:s'),$start->format('Y-m-d H:i:s')]);
                if(!$conflict){$chosen=(int)$table['id'];break;}
            }
            if(!$chosen) throw new InvalidArgumentException('That time was just booked by another customer. Please choose another available time.');
            $publicToken=bin2hex(random_bytes(32));
            Database::query("INSERT INTO reservations (restaurant_id,customer_name,phone,customer_email,party_size,reservation_at,duration_minutes,table_id,status,notes,created_by_user_id,public_token)
                VALUES (?,?,?,?,?,?,?,?,'pending',?,NULL,?)",
                [$restaurantId,$name,$phone,$email,$party,$start->format('Y-m-d H:i:s'),$duration,$chosen,$notes!==''?$notes:null,$publicToken]);
            $id=(int)Database::lastInsertId();
            $pdo->commit();
            return ['id'=>$id,'tracking_token'=>$publicToken,'restaurant_name'=>(string)$restaurant['restaurant_name'],'reservation_at'=>$start->format('Y-m-d H:i:s')];
        } catch(Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function getPublicStatus(string $linkToken,string $trackingToken): ?array
    {
        $restaurant=self::getRestaurantByToken($linkToken);
        if(!$restaurant || !preg_match('/^[a-f0-9]{64}$/i',$trackingToken)) return null;
        return Database::fetchOne("SELECT customer_name,party_size,reservation_at,duration_minutes,status FROM reservations WHERE restaurant_id=? AND public_token=? LIMIT 1",[(int)$restaurant['restaurant_id'],strtolower($trackingToken)]);
    }

    public static function notifyConfirmed(int $restaurantId,int $reservationId): array
    {
        $r=Database::fetchOne("SELECT r.*,s.name AS restaurant_name FROM reservations r JOIN restaurants s ON s.id=r.restaurant_id WHERE r.restaurant_id=? AND r.id=? AND r.status='booked' AND r.public_token IS NOT NULL LIMIT 1",[$restaurantId,$reservationId]);
        if(!$r) return ['email'=>false,'sms'=>false];
        $when=date('D, M j, Y \a\t g:i A',strtotime((string)$r['reservation_at']));
        $subject='Reservation confirmed - '.$r['restaurant_name'];
        $body="Hello {$r['customer_name']},\n\nYour reservation at {$r['restaurant_name']} is confirmed for {$when} for {$r['party_size']} guest(s).\n\nIf you need to make changes, please contact the restaurant.";
        $emailSent=false; $smsSent=false;
        if(!empty($r['customer_email']) && filter_var($r['customer_email'],FILTER_VALIDATE_EMAIL)) {
            $host=(string)(parse_url(defined('BASE_URL')?BASE_URL:'',PHP_URL_HOST) ?: 'localhost');
            $emailSent=@mail((string)$r['customer_email'],$subject,$body,"From: no-reply@{$host}\r\nContent-Type: text/plain; charset=UTF-8");
        }
        $sid=(string)getenv('SERVEFLOW_TWILIO_ACCOUNT_SID');
        $secret=(string)getenv('SERVEFLOW_TWILIO_AUTH_TOKEN');
        $from=(string)getenv('SERVEFLOW_TWILIO_FROM_NUMBER');
        if($sid!=='' && $secret!=='' && $from!=='' && !empty($r['phone']) && function_exists('curl_init')) {
            $payload=http_build_query(['To'=>(string)$r['phone'],'From'=>$from,'Body'=>$body]);
            $ch=curl_init('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($sid).'/Messages.json');
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_USERPWD=>$sid.':'.$secret,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
            $response=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
            $smsSent=$response!==false && $status>=200 && $status<300;
        }
        Database::query("UPDATE reservations SET notification_email_sent=?,notification_sms_sent=? WHERE restaurant_id=? AND id=?",[$emailSent?1:0,$smsSent?1:0,$restaurantId,$reservationId]);
        return ['email'=>$emailSent,'sms'=>$smsSent];
    }
}
