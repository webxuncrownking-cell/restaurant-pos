<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class SubscriptionPaymentController
{
    public function submit(Request $request): void
    {
        Auth::requireLogin();
        if (Auth::isPlatformAdmin() || Auth::roleSlug() !== 'owner') Response::forbidden('Only restaurant owners can submit subscription payments.');
        if (!CSRF::validate($request->post('csrf_token'))) {
            Session::setFlash('danger', 'Security token expired. Refresh and try again.');
            Response::redirect('/billing');
        }

        $restaurantId = (int)Auth::getRestaurantId();
        $planId = (int)$request->post('plan_id', 0);
        $cycle = (string)$request->post('billing_cycle', 'monthly');
        $method = (string)$request->post('payment_method', '');
        $reference = trim((string)$request->post('transaction_reference', ''));
        $payer = trim((string)$request->post('payer_name', ''));

        if (!in_array($cycle, ['monthly','annually'], true) ||
            !in_array($method, ['gcash','bank_transfer','cash_deposit','other'], true) ||
            $reference === '' || mb_strlen($reference) > 120 || $payer === '' || mb_strlen($payer) > 120) {
            Session::setFlash('danger', 'Please complete all subscription payment details.');
            Response::redirect('/billing');
        }

        $plan = Database::fetchOne("SELECT id, name, price_monthly, price_annually FROM subscription_plans WHERE id = ? AND is_active = 1", [$planId]);
        if (!$plan) {
            Session::setFlash('danger', 'The selected subscription plan is unavailable.');
            Response::redirect('/billing');
        }
        $amount = (float)($cycle === 'annually' ? $plan['price_annually'] : $plan['price_monthly']);
        if ($amount <= 0) {
            Session::setFlash('danger', 'This plan does not have a payable price configured. Contact support.');
            Response::redirect('/billing');
        }

        $upload = $_FILES['payment_proof'] ?? null;
        if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)$upload['size'] > 5 * 1024 * 1024) {
            Session::setFlash('danger', 'Attach a payment receipt no larger than 5 MB.');
            Response::redirect('/billing');
        }
        $tmp = (string)$upload['tmp_name'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
        if (!isset($allowed[$mime])) {
            Session::setFlash('danger', 'Upload a JPG, PNG, WEBP, or PDF receipt.');
            Response::redirect('/billing');
        }

        $directory = dirname(APP_ROOT) . '/serveflow-private/subscription-proofs';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            Session::setFlash('danger', 'Private receipt storage is unavailable. Contact support.');
            Response::redirect('/billing');
        }
        $filename = $restaurantId . '-' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmp, $directory . '/' . $filename)) {
            Session::setFlash('danger', 'Could not store the receipt. Please try again.');
            Response::redirect('/billing');
        }

        try {
            Database::query(
                "INSERT INTO subscription_payment_submissions
                 (restaurant_id, plan_id, billing_cycle, amount, payment_method, transaction_reference, payer_name, proof_file_path, submitted_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$restaurantId, $planId, $cycle, $amount, $method, $reference, $payer, 'subscription-proofs/' . $filename, (int)Auth::id()]
            );
            Session::setFlash('success', 'Subscription payment submitted. Premium features remain locked until the platform team verifies the payment.');
        } catch (Throwable $e) {
            @unlink($directory . '/' . $filename);
            error_log('[ServeFlow subscription payment] ' . $e->getMessage());
            Session::setFlash('danger', 'Your payment submission could not be saved. Please try again.');
        }
        Response::redirect('/billing');
    }

    public function adminIndex(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) Response::forbidden('Platform administrator access required.');
        $rows = Database::fetchAll(
            "SELECT p.*, r.name AS restaurant_name, r.slug, sp.name AS plan_name, u.full_name AS submitted_by_name
             FROM subscription_payment_submissions p
             JOIN restaurants r ON r.id = p.restaurant_id
             JOIN subscription_plans sp ON sp.id = p.plan_id
             JOIN users u ON u.id = p.submitted_by AND u.restaurant_id = p.restaurant_id
             ORDER BY CASE p.status WHEN 'pending' THEN 0 ELSE 1 END, p.created_at DESC"
        );
        Response::render('subscription-payments/admin', ['submissions' => $rows, 'flashes' => Session::getFlashes()], 'app');
    }

    public function proof(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) Response::forbidden('Platform administrator access required.');
        $id = (int)$request->get('id', 0);
        $row = Database::fetchOne("SELECT proof_file_path FROM subscription_payment_submissions WHERE id = ?", [$id]);
        if (!$row) Response::notFound('Payment proof not found.');
        $relative = (string)$row['proof_file_path'];
        if (!preg_match('/^subscription-proofs\/[0-9]+-[a-f0-9]{32}\.(?:jpg|png|webp|pdf)$/', $relative)) Response::notFound('Payment proof not found.');
        $file = dirname(APP_ROOT) . '/serveflow-private/' . $relative;
        if (!is_file($file)) Response::notFound('Payment proof file is unavailable.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
        if (!in_array($mime, ['image/jpeg','image/png','image/webp','application/pdf'], true)) Response::forbidden('Unsupported proof format.');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string)filesize($file));
        header('Content-Disposition: inline; filename="subscription-proof-' . $id . '.' . pathinfo($file, PATHINFO_EXTENSION) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($file);
        exit;
    }

    public function review(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) Response::forbidden('Platform administrator access required.');
        if (!CSRF::validate($request->post('csrf_token'))) {
            Session::setFlash('danger', 'Security token expired.');
            Response::redirect('/platform-subscription-payments');
        }
        $id = (int)$request->post('submission_id', 0);
        $decision = (string)$request->post('decision', '');
        $note = trim((string)$request->post('reviewer_note', ''));
        if ($id < 1 || !in_array($decision, ['approved','rejected'], true) || mb_strlen($note) > 500) {
            Session::setFlash('danger', 'Invalid review details.');
            Response::redirect('/platform-subscription-payments');
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            $submission = Database::fetchOne("SELECT * FROM subscription_payment_submissions WHERE id = ? FOR UPDATE", [$id]);
            if (!$submission || $submission['status'] !== 'pending') throw new \RuntimeException('This payment has already been reviewed.');
            Database::query(
                "UPDATE subscription_payment_submissions SET status = ?, reviewed_by = ?, reviewed_at = NOW(), reviewer_note = ? WHERE id = ?",
                [$decision, (int)Auth::id(), $note !== '' ? $note : null, $id]
            );

            if ($decision === 'approved') {
                $plan = Database::fetchOne("SELECT id FROM subscription_plans WHERE id = ? AND is_active = 1", [(int)$submission['plan_id']]);
                if (!$plan) throw new \RuntimeException('The selected plan is no longer active. Payment cannot be approved automatically.');
                $existing = Database::fetchOne("SELECT id, current_period_end FROM subscriptions WHERE restaurant_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE", [(int)$submission['restaurant_id']]);
                $now = new \DateTimeImmutable('now');
                $existingEnd = !empty($existing['current_period_end']) ? new \DateTimeImmutable($existing['current_period_end']) : $now;
                $periodStart = $existingEnd > $now ? $existingEnd : $now;
                $periodEnd = $periodStart->modify($submission['billing_cycle'] === 'annually' ? '+1 year' : '+1 month');
                $start = $periodStart->format('Y-m-d H:i:s');
                $end = $periodEnd->format('Y-m-d H:i:s');
                if ($existing) {
                    Database::query(
                        "UPDATE subscriptions SET plan_id = ?, status = 'active', billing_cycle = ?, current_period_start = ?, current_period_end = ?, trial_ends_at = NULL, cancel_at_period_end = 0, canceled_at = NULL, updated_at = NOW() WHERE id = ? AND restaurant_id = ?",
                        [(int)$submission['plan_id'], $submission['billing_cycle'], $start, $end, (int)$existing['id'], (int)$submission['restaurant_id']]
                    );
                } else {
                    Database::query(
                        "INSERT INTO subscriptions (restaurant_id, plan_id, status, billing_cycle, current_period_start, current_period_end, grace_period_days, created_at)
                         VALUES (?, ?, 'active', ?, ?, ?, 7, NOW())",
                        [(int)$submission['restaurant_id'], (int)$submission['plan_id'], $submission['billing_cycle'], $start, $end]
                    );
                }
            }
            $pdo->commit();
            Session::setFlash('success', $decision === 'approved' ? 'Payment verified and subscription activated.' : 'Subscription payment rejected.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Session::setFlash('danger', $e->getMessage());
        }
        Response::redirect('/platform-subscription-payments');
    }
}
