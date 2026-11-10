<?php
declare(strict_types=1);

namespace App\Services;

final class TelegramNotifier
{
    /**
     * Send an admin alert using the bot token stored outside the public web root.
     * Notification failures are logged and never interrupt a payment submission.
     */
    public static function sendActivationPaymentSubmitted(array $details): bool
    {
        $home = getenv('HOME');
        if (!$home) {
            // APP_ROOT is /home/account/domains/domain/public_html.
            $home = dirname(dirname(dirname(APP_ROOT)));
        }
        $tokenFile = rtrim($home, '/') . '/serveflow-private/telegram-token.txt';
        $chatId = '590967993';

        if (!is_file($tokenFile) || !is_readable($tokenFile)) {
            error_log('[ServeFlow Telegram] Token file is missing or unreadable.');
            return false;
        }

        $token = trim((string)file_get_contents($tokenFile));
        if ($token === '' || !preg_match('/^[0-9]+:[A-Za-z0-9_-]+$/', $token)) {
            error_log('[ServeFlow Telegram] Token file contains an invalid token.');
            return false;
        }

        $lines = [
            "🔔 SERVEFLOW POS — NEW ACTIVATION PAYMENT",
            "",
            "Restaurant: " . (string)($details['restaurant_name'] ?? 'Unknown'),
            "Owner: " . (string)($details['owner_name'] ?? 'Unknown'),
            "Email: " . (string)($details['owner_email'] ?? 'Not provided'),
            "Contact: " . (string)($details['contact_phone'] ?? 'Not provided'),
            "Amount: ₱" . number_format((float)($details['amount'] ?? 0), 2),
            "Payment method: " . strtoupper(str_replace('_', ' ', (string)($details['payment_method'] ?? ''))),
            "Transaction reference: " . (string)($details['transaction_reference'] ?? ''),
            "Submitted: " . date('M d, Y h:i A'),
            "",
            "Review payment: " . rtrim((string)BASE_URL, '/') . "/platform-activation-payments",
        ];
        $payload = http_build_query([
            'chat_id' => $chatId,
            'text' => implode("\n", $lines),
            'disable_web_page_preview' => 'true',
        ]);

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $response = false;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($response === false || $status < 200 || $status >= 300) {
                error_log('[ServeFlow Telegram] Send failed: ' . ($error ?: 'HTTP ' . $status));
                return false;
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $payload,
                    'timeout' => 10,
                    'ignore_errors' => true,
                ],
            ]);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                error_log('[ServeFlow Telegram] HTTP request failed.');
                return false;
            }
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded) || empty($decoded['ok'])) {
            error_log('[ServeFlow Telegram] API rejected the notification.');
            return false;
        }
        return true;
    }
}
