<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantContext;
use App\Services\TelegramNotifier;

class ActivationController
{
    public function index(Request $request): void
    {
        Auth::requireLogin();
        if (Auth::isPlatformAdmin() || Auth::roleSlug() !== 'owner') {
            Response::forbidden('Only the restaurant owner can submit activation payment.');
        }
        $restaurantId = (int)Auth::getRestaurantId();
        $restaurant = Database::fetchOne(
            "SELECT id, name, slug, activation_status, activation_fee FROM restaurants WHERE id = ? AND deleted_at IS NULL",
            [$restaurantId]
        );
        if (!$restaurant) Response::notFound('Restaurant account not found.');
        $fee = Database::fetchOne("SELECT setting_value FROM platform_settings WHERE setting_key = 'activation_fee_php'");
        $instructions = Database::fetchOne("SELECT setting_value FROM platform_settings WHERE setting_key = 'activation_payment_instructions'");
        $submissions = Database::fetchAll(
            "SELECT id, amount, payment_method, transaction_reference, status, reviewer_note, created_at, reviewed_at
             FROM activation_payment_submissions WHERE restaurant_id = ? ORDER BY id DESC LIMIT 10",
            [$restaurantId]
        );
        Response::render('activation/index', [
            'restaurant' => $restaurant,
            'activation_fee' => (float)($restaurant['activation_fee'] ?? 399),
            'payment_instructions' => (string)($instructions['setting_value'] ?? ''),
            'submissions' => $submissions,
            'flashes' => Session::getFlashes()
        ], 'auth');
    }

    public function submit(Request $request): void
    {
        Auth::requireLogin();
        if (Auth::isPlatformAdmin() || Auth::roleSlug() !== 'owner') Response::forbidden();
        if (!CSRF::validate($request->post('csrf_token'))) {
            Session::setFlash('danger', 'Security token expired. Refresh and try again.');
            Response::redirect('/activation');
        }

        $restaurantId = (int)Auth::getRestaurantId();
        $restaurant = Database::fetchOne(
            "SELECT name, contact_phone, activation_status, activation_fee FROM restaurants WHERE id = ? AND deleted_at IS NULL",
            [$restaurantId]
        );
        if (!$restaurant || $restaurant['activation_status'] === 'activated') {
            Response::redirect('/dashboard');
        }

        $method = (string)$request->post('payment_method', '');
        $reference = trim((string)$request->post('transaction_reference', ''));
        $payer = trim((string)$request->post('payer_name', ''));
        $upload = $_FILES['payment_proof'] ?? null;
        if (!in_array($method, ['gcash','bank_transfer','cash_deposit','other'], true) ||
            $reference === '' || mb_strlen($reference) > 120 ||
            $payer === '' || mb_strlen($payer) > 120 ||
            !is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::setFlash('danger', 'Complete the payment details and attach proof of payment.');
            Response::redirect('/activation');
        }

        if ((int)$upload['size'] > 5 * 1024 * 1024) {
            Session::setFlash('danger', 'Payment proof must be 5 MB or smaller.');
            Response::redirect('/activation');
        }
        $tmp = (string)$upload['tmp_name'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
        if (!isset($allowed[$mime])) {
            Session::setFlash('danger', 'Upload a JPG, PNG, WEBP, or PDF payment receipt.');
            Response::redirect('/activation');
        }

        $relativeDir = 'activation-proofs';
        $absoluteDir = dirname(APP_ROOT) . '/serveflow-private/' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0750, true) && !is_dir($absoluteDir)) {
            Session::setFlash('danger', 'Payment proof storage is unavailable. Contact support.');
            Response::redirect('/activation');
        }
        // Disable script execution in the proof directory.
        $htaccess = $absoluteDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl)$\">\nRequire all denied\n</FilesMatch>\n");
        }
        $filename = $restaurantId . '-' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmp, $absoluteDir . '/' . $filename)) {
            Session::setFlash('danger', 'Could not store the payment proof. Please try again.');
            Response::redirect('/activation');
        }

        $amount = (float)($restaurant['activation_fee'] ?? 399);
        try {
            Database::query(
                "INSERT INTO activation_payment_submissions
                 (restaurant_id, amount, payment_method, transaction_reference, payer_name, proof_file_path, submitted_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$restaurantId, $amount, $method, $reference, $payer, $relativeDir . '/' . $filename, (int)Auth::id()]
            );
            Database::query("UPDATE restaurants SET activation_status = 'pending' WHERE id = ?", [$restaurantId]);

            // Send a best-effort Telegram alert after the payment record is saved.
            // A Telegram outage must never undo or block the owner's submission.
            try {
                $owner = Database::fetchOne(
                    "SELECT full_name, email FROM users WHERE id = ? AND restaurant_id = ? LIMIT 1",
                    [(int)Auth::id(), $restaurantId]
                );
                TelegramNotifier::sendActivationPaymentSubmitted([
                    'restaurant_name' => $restaurant['name'] ?? 'Unknown restaurant',
                    'owner_name' => $owner['full_name'] ?? $payer,
                    'owner_email' => $owner['email'] ?? '',
                    'contact_phone' => $restaurant['contact_phone'] ?? '',
                    'amount' => $amount,
                    'payment_method' => $method,
                    'transaction_reference' => $reference,
                ]);
            } catch (\Throwable $notificationError) {
                error_log('[ServeFlow Telegram] ' . $notificationError->getMessage());
            }

            Session::setFlash('success', 'Payment proof submitted. Your account will be activated after platform verification.');
        } catch (\Throwable $e) {
            @unlink($absoluteDir . '/' . $filename);
            error_log('[ServeFlow activation submission] ' . $e->getMessage());
            Session::setFlash('danger', 'Your payment submission could not be saved. Please try again.');
        }
        Response::redirect('/activation');
    }


    public function proof(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) Response::forbidden('Platform administrator access required.');
        $id = (int)$request->get('id', 0);
        $row = Database::fetchOne("SELECT proof_file_path FROM activation_payment_submissions WHERE id = ?", [$id]);
        if (!$row) Response::notFound('Payment proof not found.');
        $relative = (string)$row['proof_file_path'];
        if (!preg_match('/^activation-proofs\\/[0-9]+-[a-f0-9]{32}\\.(?:jpg|png|webp|pdf)$/', $relative)) {
            Response::notFound('Payment proof not found.');
        }
        $file = dirname(APP_ROOT) . '/serveflow-private/' . $relative;
        if (!is_file($file)) Response::notFound('Payment proof file is unavailable.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
        if (!in_array($mime, ['image/jpeg','image/png','image/webp','application/pdf'], true)) {
            Response::forbidden('Unsupported payment proof format.');
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string)filesize($file));
        header('Content-Disposition: inline; filename="activation-proof-' . $id . '.' . pathinfo($file, PATHINFO_EXTENSION) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($file);
        exit;
    }

    public function adminIndex(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) Response::forbidden('Platform administrator access required.');
        $rows = Database::fetchAll(
            "SELECT p.*, r.name AS restaurant_name, r.slug, u.full_name AS submitted_by_name
             FROM activation_payment_submissions p
             JOIN restaurants r ON r.id = p.restaurant_id
             JOIN users u ON u.id = p.submitted_by AND u.restaurant_id = p.restaurant_id
             ORDER BY CASE p.status WHEN 'pending' THEN 0 ELSE 1 END, p.created_at DESC"
        );
        Response::render('activation/admin', ['submissions' => $rows, 'flashes' => Session::getFlashes()], 'app');
    }

    public function review(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) Response::forbidden('Platform administrator access required.');
        if (!CSRF::validate($request->post('csrf_token'))) {
            Session::setFlash('danger', 'Security token expired.');
            Response::redirect('/platform-activation-payments');
        }
        $id = (int)$request->post('submission_id', 0);
        $decision = (string)$request->post('decision', '');
        $note = trim((string)$request->post('reviewer_note', ''));
        if ($id < 1 || !in_array($decision, ['approved','rejected'], true) || mb_strlen($note) > 500) {
            Session::setFlash('danger', 'Invalid review details.');
            Response::redirect('/platform-activation-payments');
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            $submission = Database::fetchOne("SELECT * FROM activation_payment_submissions WHERE id = ? FOR UPDATE", [$id]);
            if (!$submission || $submission['status'] !== 'pending') {
                throw new \RuntimeException('This submission has already been reviewed or no longer exists.');
            }
            Database::query(
                "UPDATE activation_payment_submissions SET status = ?, reviewed_by = ?, reviewed_at = NOW(), reviewer_note = ? WHERE id = ?",
                [$decision, (int)Auth::id(), $note !== '' ? $note : null, $id]
            );
            if ($decision === 'approved') {
                Database::query(
                    "UPDATE restaurants SET activation_status = 'activated', activated_at = NOW(), activation_verified_by = ? WHERE id = ?",
                    [(int)Auth::id(), (int)$submission['restaurant_id']]
                );
            } else {
                Database::query(
                    "UPDATE restaurants SET activation_status = 'rejected' WHERE id = ? AND activation_status <> 'activated'",
                    [(int)$submission['restaurant_id']]
                );
            }
            $pdo->commit();
            Session::setFlash('success', $decision === 'approved' ? 'Payment verified. Restaurant activation approved.' : 'Payment submission rejected.');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Session::setFlash('danger', $e->getMessage());
        }
        Response::redirect('/platform-activation-payments');
    }
}
