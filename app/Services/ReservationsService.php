<?php
/**
 * Restaurant POS & Management SaaS
 * Reservation and walk-in waitlist workflows.
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;
use App\Core\Auth;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class ReservationsService
{
    public static function schemaReady(): bool
    {
        try {
            $pdo = Database::getInstance();
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $rows = Database::fetchAll("SELECT name FROM sqlite_master WHERE type = 'table' AND name IN ('reservations', 'waitlist_entries', 'waitlist_public_links')");
                return count($rows) === 3;
            }
            $rows = Database::fetchAll(
                "SELECT table_name FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name IN ('reservations', 'waitlist_entries', 'waitlist_public_links')"
            );
            return count($rows) === 3;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Safe, additive migration. Existing restaurant and transaction data is untouched.
     */
    public static function ensureSchema(): void
    {
        $pdo = Database::getInstance();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS reservations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                restaurant_id INTEGER NOT NULL,
                customer_name TEXT NOT NULL,
                phone TEXT NULL,
                party_size INTEGER NOT NULL DEFAULT 1,
                reservation_at TEXT NOT NULL,
                duration_minutes INTEGER NOT NULL DEFAULT 90,
                table_id INTEGER NULL,
                status TEXT NOT NULL DEFAULT 'booked' CHECK (status IN ('booked','seated','cancelled','no_show')),
                notes TEXT NULL,
                created_by_user_id INTEGER NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reservations_tenant_time ON reservations (restaurant_id, reservation_at, status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reservations_tenant_table ON reservations (restaurant_id, table_id, status)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS waitlist_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                restaurant_id INTEGER NOT NULL,
                customer_name TEXT NOT NULL,
                phone TEXT NULL,
                party_size INTEGER NOT NULL DEFAULT 1,
                estimated_wait_minutes INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'waiting' CHECK (status IN ('waiting','seated','left','cancelled')),
                table_id INTEGER NULL,
                notes TEXT NULL,
                created_by_user_id INTEGER NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                seated_at TEXT NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_waitlist_tenant_status ON waitlist_entries (restaurant_id, status, created_at)");
            $columns = Database::fetchAll("PRAGMA table_info('waitlist_entries')");
            if (!in_array('public_tracking_token', array_column($columns, 'name'), true)) {
                $pdo->exec("ALTER TABLE waitlist_entries ADD COLUMN public_tracking_token TEXT NULL");
            }
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_waitlist_public_tracking_token ON waitlist_entries(public_tracking_token)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS waitlist_public_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                restaurant_id INTEGER NOT NULL UNIQUE,
                public_token TEXT NOT NULL UNIQUE,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            return;
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS reservations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            restaurant_id INT UNSIGNED NOT NULL,
            customer_name VARCHAR(120) NOT NULL,
            phone VARCHAR(30) NULL,
            party_size SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            reservation_at DATETIME NOT NULL,
            duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 90,
            table_id INT UNSIGNED NULL,
            status ENUM('booked','seated','cancelled','no_show') NOT NULL DEFAULT 'booked',
            notes VARCHAR(500) NULL,
            created_by_user_id INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_reservations_tenant_time (restaurant_id, reservation_at, status),
            KEY idx_reservations_tenant_table (restaurant_id, table_id, status),
            CONSTRAINT fk_reservations_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE RESTRICT,
            CONSTRAINT fk_reservations_table FOREIGN KEY (restaurant_id, table_id) REFERENCES restaurant_tables (restaurant_id, id) ON DELETE RESTRICT,
            CONSTRAINT fk_reservations_created_by FOREIGN KEY (restaurant_id, created_by_user_id) REFERENCES users (restaurant_id, id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS waitlist_entries (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            restaurant_id INT UNSIGNED NOT NULL,
            customer_name VARCHAR(120) NOT NULL,
            phone VARCHAR(30) NULL,
            party_size SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            estimated_wait_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('waiting','seated','left','cancelled') NOT NULL DEFAULT 'waiting',
            table_id INT UNSIGNED NULL,
            notes VARCHAR(500) NULL,
            created_by_user_id INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            seated_at DATETIME NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_waitlist_tenant_status (restaurant_id, status, created_at),
            CONSTRAINT fk_waitlist_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE RESTRICT,
            CONSTRAINT fk_waitlist_table FOREIGN KEY (restaurant_id, table_id) REFERENCES restaurant_tables (restaurant_id, id) ON DELETE RESTRICT,
            CONSTRAINT fk_waitlist_created_by FOREIGN KEY (restaurant_id, created_by_user_id) REFERENCES users (restaurant_id, id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $column = Database::fetchOne("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'waitlist_entries' AND column_name = 'public_tracking_token'");
        if ((int)($column['total'] ?? 0) === 0) {
            $pdo->exec("ALTER TABLE waitlist_entries ADD COLUMN public_tracking_token CHAR(64) NULL");
        }
        $index = Database::fetchOne("SELECT COUNT(*) AS total FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'waitlist_entries' AND index_name = 'uq_waitlist_public_tracking_token'");
        if ((int)($index['total'] ?? 0) === 0) {
            $pdo->exec("CREATE UNIQUE INDEX uq_waitlist_public_tracking_token ON waitlist_entries(public_tracking_token)");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS waitlist_public_links (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            restaurant_id INT UNSIGNED NOT NULL,
            public_token CHAR(64) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_waitlist_public_restaurant (restaurant_id),
            UNIQUE KEY uq_waitlist_public_token (public_token),
            CONSTRAINT fk_waitlist_public_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function getReservations(int $restaurantId): array
    {
        return Database::fetchAll(
            "SELECT r.*, t.table_number, t.name AS table_name
             FROM reservations r
             LEFT JOIN restaurant_tables t ON t.restaurant_id = r.restaurant_id AND t.id = r.table_id
             WHERE r.restaurant_id = ?
               AND r.reservation_at >= ?
               AND r.reservation_at < ?
             ORDER BY r.reservation_at ASC, r.id ASC
             LIMIT 250",
            [$restaurantId, date('Y-m-d H:i:s', time() - 86400), date('Y-m-d H:i:s', time() + 14 * 86400)]
        );
    }

    public static function getWaitlist(int $restaurantId): array
    {
        return Database::fetchAll(
            "SELECT w.*, t.table_number, t.name AS table_name
             FROM waitlist_entries w
             LEFT JOIN restaurant_tables t ON t.restaurant_id = w.restaurant_id AND t.id = w.table_id
             WHERE w.restaurant_id = ? AND w.status = 'waiting'
             ORDER BY w.created_at ASC, w.id ASC
             LIMIT 150",
            [$restaurantId]
        );
    }

    public static function createReservation(int $restaurantId, int $userId, array $input): int
    {
        $customerName = trim((string)($input['customer_name'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $partySize = filter_var($input['party_size'] ?? null, FILTER_VALIDATE_INT);
        $duration = filter_var($input['duration_minutes'] ?? 90, FILTER_VALIDATE_INT);
        $tableId = !empty($input['table_id']) ? filter_var($input['table_id'], FILTER_VALIDATE_INT) : null;
        $notes = trim((string)($input['notes'] ?? ''));
        $rawDate = trim((string)($input['reservation_at'] ?? ''));

        if ($customerName === '' || mb_strlen($customerName) > 120) {
            throw new InvalidArgumentException('Enter a customer name of up to 120 characters.');
        }
        if (!is_int($partySize) || $partySize < 1 || $partySize > 100) {
            throw new InvalidArgumentException('Party size must be between 1 and 100 guests.');
        }
        if (!is_int($duration) || $duration < 30 || $duration > 480) {
            throw new InvalidArgumentException('Reservation duration must be between 30 and 480 minutes.');
        }
        if (mb_strlen($phone) > 30 || mb_strlen($notes) > 500) {
            throw new InvalidArgumentException('Phone must be 30 characters or fewer and notes 500 characters or fewer.');
        }
        $reservationAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $rawDate);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!$reservationAt || ($dateErrors && ($dateErrors['warning_count'] || $dateErrors['error_count']))) {
            throw new InvalidArgumentException('Choose a valid reservation date and time.');
        }
        if ($reservationAt->getTimestamp() < time() - 60) {
            throw new InvalidArgumentException('Reservation time cannot be in the past.');
        }
        if ($tableId !== null && (!is_int($tableId) || $tableId <= 0)) {
            throw new InvalidArgumentException('Choose a valid table.');
        }

        if ($tableId !== null) {
            $table = self::getBookableTable($restaurantId, $tableId, $partySize, $reservationAt->getTimestamp());
            if (!$table) throw new InvalidArgumentException('The selected table is unavailable or too small for this party.');
            if (self::hasReservationConflict($restaurantId, $tableId, $reservationAt, $duration)) {
                throw new InvalidArgumentException('That table already has a reservation during this time.');
            }
        }

        Database::query(
            "INSERT INTO reservations
                (restaurant_id, customer_name, phone, party_size, reservation_at, duration_minutes, table_id, status, notes, created_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'booked', ?, ?)",
            [
                $restaurantId, $customerName, $phone !== '' ? $phone : null, $partySize,
                $reservationAt->format('Y-m-d H:i:s'), $duration, $tableId, $notes !== '' ? $notes : null, $userId
            ]
        );
        return (int)Database::lastInsertId();
    }

    public static function updateReservationStatus(int $restaurantId, int $reservationId, int $userId, string $targetStatus, ?int $tableId = null): void
    {
        if (!in_array($targetStatus, ['seated', 'cancelled', 'no_show'], true)) {
            throw new InvalidArgumentException('Choose a valid reservation action.');
        }
        $reservation = Database::fetchOne(
            "SELECT * FROM reservations WHERE restaurant_id = ? AND id = ?",
            [$restaurantId, $reservationId]
        );
        if (!$reservation || $reservation['status'] !== 'booked') {
            throw new InvalidArgumentException('Only booked reservations can be changed.');
        }

        if ($targetStatus === 'seated') {
            $chosenTableId = $tableId ?? (!empty($reservation['table_id']) ? (int)$reservation['table_id'] : null);
            if (!$chosenTableId) throw new InvalidArgumentException('Choose a table before seating this reservation.');
            $table = self::getBookableTable($restaurantId, $chosenTableId, (int)$reservation['party_size'], time(), true);
            if (!$table) throw new InvalidArgumentException('Choose a table that is available and large enough for the party.');
            $pdo = Database::getInstance();
            try {
                $pdo->beginTransaction();
                Database::query(
                    "UPDATE restaurant_tables SET status = 'occupied' WHERE restaurant_id = ? AND id = ? AND deleted_at IS NULL",
                    [$restaurantId, $chosenTableId]
                );
                Database::query(
                    "UPDATE reservations SET status = 'seated', table_id = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE restaurant_id = ? AND id = ? AND status = 'booked'",
                    [$chosenTableId, $restaurantId, $reservationId]
                );
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            return;
        }

        Database::query(
            "UPDATE reservations SET status = ?, updated_at = CURRENT_TIMESTAMP
             WHERE restaurant_id = ? AND id = ? AND status = 'booked'",
            [$targetStatus, $restaurantId, $reservationId]
        );
    }

    public static function addWaitlist(int $restaurantId, int $userId, array $input): int
    {
        $customerName = trim((string)($input['customer_name'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $partySize = filter_var($input['party_size'] ?? null, FILTER_VALIDATE_INT);
        $wait = filter_var($input['estimated_wait_minutes'] ?? 0, FILTER_VALIDATE_INT);
        $notes = trim((string)($input['notes'] ?? ''));

        if ($customerName === '' || mb_strlen($customerName) > 120) throw new InvalidArgumentException('Enter a customer name of up to 120 characters.');
        if (!is_int($partySize) || $partySize < 1 || $partySize > 100) throw new InvalidArgumentException('Party size must be between 1 and 100 guests.');
        if (!is_int($wait) || $wait < 0 || $wait > 360) throw new InvalidArgumentException('Estimated wait must be between 0 and 360 minutes.');
        if (mb_strlen($phone) > 30 || mb_strlen($notes) > 500) throw new InvalidArgumentException('Phone must be 30 characters or fewer and notes 500 characters or fewer.');

        Database::query(
            "INSERT INTO waitlist_entries
                (restaurant_id, customer_name, phone, party_size, estimated_wait_minutes, status, notes, created_by_user_id)
             VALUES (?, ?, ?, ?, ?, 'waiting', ?, ?)",
            [$restaurantId, $customerName, $phone !== '' ? $phone : null, $partySize, $wait, $notes !== '' ? $notes : null, $userId]
        );
        return (int)Database::lastInsertId();
    }

    public static function updateWaitlistStatus(int $restaurantId, int $entryId, string $targetStatus, ?int $tableId = null): void
    {
        if (!in_array($targetStatus, ['seated', 'left', 'cancelled'], true)) {
            throw new InvalidArgumentException('Choose a valid waiting-list action.');
        }
        $entry = Database::fetchOne(
            "SELECT * FROM waitlist_entries WHERE restaurant_id = ? AND id = ? AND status = 'waiting'",
            [$restaurantId, $entryId]
        );
        if (!$entry) throw new InvalidArgumentException('This guest is no longer waiting.');

        if ($targetStatus === 'seated') {
            if (!$tableId) throw new InvalidArgumentException('Choose a table before seating the guest.');
            if (!self::getBookableTable($restaurantId, $tableId, (int)$entry['party_size'], time(), true)) {
                throw new InvalidArgumentException('Choose an available table with enough seats.');
            }
            $pdo = Database::getInstance();
            try {
                $pdo->beginTransaction();
                Database::query(
                    "UPDATE restaurant_tables SET status = 'occupied' WHERE restaurant_id = ? AND id = ? AND deleted_at IS NULL",
                    [$restaurantId, $tableId]
                );
                Database::query(
                    "UPDATE waitlist_entries SET status = 'seated', table_id = ?, seated_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                     WHERE restaurant_id = ? AND id = ? AND status = 'waiting'",
                    [$tableId, $restaurantId, $entryId]
                );
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            return;
        }

        Database::query(
            "UPDATE waitlist_entries SET status = ?, updated_at = CURRENT_TIMESTAMP
             WHERE restaurant_id = ? AND id = ? AND status = 'waiting'",
            [$targetStatus, $restaurantId, $entryId]
        );
    }

    public static function getOrCreatePublicWaitlistLink(int $restaurantId): string
    {
        $row = Database::fetchOne(
            "SELECT public_token, is_active FROM waitlist_public_links WHERE restaurant_id = ? LIMIT 1",
            [$restaurantId]
        );
        if ($row && (int)$row['is_active'] === 1 && preg_match('/^[a-f0-9]{64}$/', (string)$row['public_token'])) {
            return (string)$row['public_token'];
        }
        $token = bin2hex(random_bytes(32));
        if ($row) {
            Database::query(
                "UPDATE waitlist_public_links SET public_token = ?, is_active = 1 WHERE restaurant_id = ?",
                [$token, $restaurantId]
            );
        } else {
            Database::query(
                "INSERT INTO waitlist_public_links (restaurant_id, public_token, is_active) VALUES (?, ?, 1)",
                [$restaurantId, $token]
            );
        }
        return $token;
    }

    public static function getPublicWaitlistRestaurant(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) return null;
        return Database::fetchOne(
            "SELECT r.id AS restaurant_id, r.name AS restaurant_name, r.logo_path
             FROM waitlist_public_links l
             JOIN restaurants r ON r.id = l.restaurant_id
             WHERE l.public_token = ? AND l.is_active = 1
               AND r.status = 'active' AND r.deleted_at IS NULL
             LIMIT 1",
            [strtolower($token)]
        );
    }

    public static function createPublicWaitlistEntry(string $linkToken, array $input): array
    {
        $restaurant = self::getPublicWaitlistRestaurant($linkToken);
        if (!$restaurant) throw new InvalidArgumentException('This waiting-list link is invalid or no longer active.');
        if (trim((string)($input['website'] ?? '')) !== '') {
            throw new InvalidArgumentException('Unable to submit the request. Please try again.');
        }

        $name = trim((string)($input['customer_name'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $partySize = filter_var($input['party_size'] ?? null, FILTER_VALIDATE_INT);
        $notes = trim((string)($input['notes'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) throw new InvalidArgumentException('Enter your name (up to 120 characters).');
        if (!is_int($partySize) || $partySize < 1 || $partySize > 30) throw new InvalidArgumentException('Party size must be between 1 and 30 guests.');
        if ($phone === '' || mb_strlen($phone) > 30) throw new InvalidArgumentException('Enter a valid contact number (up to 30 characters).');
        if (mb_strlen($notes) > 500) throw new InvalidArgumentException('Special requests must be 500 characters or fewer.');

        // Prevent accidental repeated submissions from creating duplicate queue entries.
        $recentSince = date('Y-m-d H:i:s', time() - 120);
        $recent = Database::fetchOne(
            "SELECT id FROM waitlist_entries
             WHERE restaurant_id = ? AND status = 'waiting' AND phone = ?
               AND created_at >= ?
             LIMIT 1",
            [(int)$restaurant['restaurant_id'], $phone, $recentSince]
        );
        if ($recent) throw new InvalidArgumentException('You already have an active waiting-list entry. Please ask staff if you need help.');

        $token = bin2hex(random_bytes(32));
        $ahead = Database::fetchOne(
            "SELECT COUNT(*) AS total FROM waitlist_entries WHERE restaurant_id = ? AND status = 'waiting'",
            [(int)$restaurant['restaurant_id']]
        );
        $estimate = min(360, max(0, (int)($ahead['total'] ?? 0) * 15));
        Database::query(
            "INSERT INTO waitlist_entries
                (restaurant_id, customer_name, phone, party_size, estimated_wait_minutes, status, notes, public_tracking_token)
             VALUES (?, ?, ?, ?, ?, 'waiting', ?, ?)",
            [(int)$restaurant['restaurant_id'], $name, $phone, $partySize, $estimate, $notes !== '' ? $notes : null, $token]
        );
        $id = (int)Database::lastInsertId();
        $position = (int)($ahead['total'] ?? 0) + 1;
        return ['tracking_token' => $token, 'position' => $position, 'estimated_wait_minutes' => $estimate, 'entry_id' => $id];
    }

    public static function getPublicWaitlistStatus(string $linkToken, string $trackingToken): ?array
    {
        $restaurant = self::getPublicWaitlistRestaurant($linkToken);
        if (!$restaurant || !preg_match('/^[a-f0-9]{64}$/i', $trackingToken)) return null;
        $entry = Database::fetchOne(
            "SELECT id, customer_name, party_size, status, estimated_wait_minutes, created_at, seated_at
             FROM waitlist_entries
             WHERE restaurant_id = ? AND public_tracking_token = ?
             LIMIT 1",
            [(int)$restaurant['restaurant_id'], strtolower($trackingToken)]
        );
        if (!$entry) return null;
        $position = 0;
        if ($entry['status'] === 'waiting') {
            $ahead = Database::fetchOne(
                "SELECT COUNT(*) AS total FROM waitlist_entries
                 WHERE restaurant_id = ? AND status = 'waiting'
                   AND (created_at < ? OR (created_at = ? AND id <= ?))",
                [(int)$restaurant['restaurant_id'], $entry['created_at'], $entry['created_at'], (int)$entry['id']]
            );
            $position = (int)($ahead['total'] ?? 0);
        }
        $estimatedWait = $entry['status'] === 'waiting'
            ? min(360, max(0, ($position - 1) * 15))
            : (int)$entry['estimated_wait_minutes'];
        return [
            'restaurant_name' => (string)$restaurant['restaurant_name'],
            'customer_name' => (string)$entry['customer_name'],
            'party_size' => (int)$entry['party_size'],
            'status' => (string)$entry['status'],
            'position' => $position,
            'estimated_wait_minutes' => $estimatedWait,
            'created_at' => (string)$entry['created_at'],
            'seated_at' => $entry['seated_at'] ?? null
        ];
    }

    private static function getBookableTable(int $restaurantId, int $tableId, int $partySize, int $when, bool $mustBeCurrentlyAvailable = false): ?array
    {
        $table = Database::fetchOne(
            "SELECT id, capacity, status, is_active FROM restaurant_tables
             WHERE restaurant_id = ? AND id = ? AND deleted_at IS NULL AND is_active = 1",
            [$restaurantId, $tableId]
        );
        if (!$table || (int)$table['capacity'] < $partySize) return null;
        if (in_array($table['status'], ['occupied', 'cleaning'], true)) return null;
        if ($mustBeCurrentlyAvailable && !in_array($table['status'], ['available', 'reserved'], true)) return null;

        // Do not allocate a currently occupied or cleaning table for a reservation starting soon.
        if (!$mustBeCurrentlyAvailable && $when <= time() + 2 * 3600 && in_array($table['status'], ['occupied', 'cleaning'], true)) return null;
        return $table;
    }

    private static function hasReservationConflict(int $restaurantId, int $tableId, DateTimeImmutable $start, int $durationMinutes): bool
    {
        $startTs = $start->getTimestamp();
        $endTs = $startTs + $durationMinutes * 60;
        $candidates = Database::fetchAll(
            "SELECT reservation_at, duration_minutes
             FROM reservations
             WHERE restaurant_id = ? AND table_id = ? AND status IN ('booked','seated')
               AND reservation_at < ? AND reservation_at >= ?",
            [
                $restaurantId, $tableId,
                date('Y-m-d H:i:s', $endTs),
                date('Y-m-d H:i:s', $startTs - 8 * 3600)
            ]
        );
        foreach ($candidates as $candidate) {
            $existingStart = strtotime((string)$candidate['reservation_at']);
            $existingEnd = $existingStart + (int)$candidate['duration_minutes'] * 60;
            if ($existingStart < $endTs && $existingEnd > $startTs) return true;
        }
        return false;
    }
}
