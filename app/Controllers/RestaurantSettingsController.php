<?php
/**
 * Restaurant branding settings: logo and POS theme colors.
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantContext;
use PDO;
use Throwable;

class RestaurantSettingsController
{
    private const MAX_LOGO_BYTES = 2097152;

    /**
     * Apply non-destructive settings migrations on an existing installation.
     * Only missing restaurant branding columns are added; existing rows are preserved.
     */
    public function migrateSettingsSchema(Request $request): void
    {
        $this->authorizeBranding();

        $csrfToken = $request->post('csrf_token');
        if (!is_string($csrfToken)) $csrfToken = $request->post('_csrf_token');
        if (!is_string($csrfToken) || !CSRF::validate($csrfToken)) {
            Session::setFlash('danger', 'Your security token expired. Refresh the page and try again.');
            Response::redirect('/settings');
            return;
        }

        try {
            $this->addMissingRestaurantColumn('logo_path', 'VARCHAR(255) NULL');
            $this->addMissingRestaurantColumn('favicon_path', 'VARCHAR(255) NULL');
            $this->addMissingRestaurantColumn('tagline', 'VARCHAR(180) NULL');
            Session::setFlash('success', 'Settings database update complete. Missing branding fields were added without deleting restaurant, menu, or sales data.');
        } catch (Throwable $e) {
            error_log('[Restaurant Settings Migration] ' . $e->getMessage());
            Session::setFlash('danger', 'The settings database update could not be completed. Check database permissions and the PHP error log, then try again.');
        }

        Response::redirect('/settings');
    }

    public function settings(Request $request): void
    {
        $restaurantId = $this->authorizeBranding();
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant || (int)($restaurant['id'] ?? 0) !== $restaurantId) {
            Response::forbidden('The selected restaurant could not be loaded.');
        }

        $logoColumnReady = $this->hasLogoColumn();
        $faviconColumnReady = $this->hasFaviconColumn();
        $taglineColumnReady = $this->hasTaglineColumn();

        $logoPath = $logoColumnReady ? trim((string)($restaurant['logo_path'] ?? '')) : '';
        $faviconPath = $faviconColumnReady ? trim((string)($restaurant['favicon_path'] ?? '')) : '';
        $fallbackFaviconPath = $faviconPath !== '' ? $faviconPath : $logoPath;
        $logoUrl = $logoPath !== '' ? BASE_URL . '/' . ltrim($logoPath, '/') : '';
        $faviconUrl = $fallbackFaviconPath !== ''
            ? BASE_URL . '/' . ltrim($fallbackFaviconPath, '/')
            : BASE_URL . '/public/favicon.svg';
        $faviconType = match (strtolower(pathinfo($fallbackFaviconPath, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            default => 'image/svg+xml'
        };

        Response::render('settings/index', [
            'restaurant' => $restaurant,
            'flashes' => Session::getFlashes(),
            'logo_column_ready' => $logoColumnReady,
            'favicon_column_ready' => $faviconColumnReady,
            'tagline_column_ready' => $taglineColumnReady,
            'logo_url' => $logoUrl,
            'favicon_url' => $faviconUrl,
            'favicon_type' => $faviconType,
            'has_custom_favicon' => $faviconPath !== '',
            'title' => 'Settings - Restaurant POS'
        ], 'app');
    }

    public function saveSettings(Request $request): void
    {
        $restaurantId = $this->authorizeBranding();
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant || (int)($restaurant['id'] ?? 0) !== $restaurantId) {
            Response::forbidden('The selected restaurant could not be loaded.');
        }

        $csrfToken = $request->post('csrf_token');
        if (!is_string($csrfToken)) $csrfToken = $request->post('_csrf_token');
        if (!is_string($csrfToken) || !CSRF::validate($csrfToken)) {
            Session::setFlash('danger', 'Your security token expired. Refresh the page and try again.');
            Response::redirect('/settings');
            return;
        }

        $restaurantName = trim((string)$request->post('restaurant_name', $restaurant['name'] ?? ''));
        $tagline = trim((string)$request->post('tagline', $restaurant['tagline'] ?? ''));
        $primaryColor = trim((string)$request->post('pos_primary_color', $restaurant['pos_primary_color'] ?? '#2563eb'));
        $secondaryColor = trim((string)$request->post('pos_secondary_color', $restaurant['pos_secondary_color'] ?? '#059669'));

        if ($restaurantName === '' || mb_strlen($restaurantName) > 150) {
            Session::setFlash('danger', 'Enter a restaurant name of 1 to 150 characters.');
            Response::redirect('/settings');
            return;
        }
        if (mb_strlen($tagline) > 180) {
            Session::setFlash('danger', 'The tagline must be 180 characters or fewer.');
            Response::redirect('/settings');
            return;
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor) || !preg_match('/^#[0-9a-fA-F]{6}$/', $secondaryColor)) {
            Session::setFlash('danger', 'Choose valid six-digit HEX colors for the primary and secondary brand colors.');
            Response::redirect('/settings');
            return;
        }

        $logoColumnReady = $this->hasLogoColumn();
        $faviconColumnReady = $this->hasFaviconColumn();
        $taglineColumnReady = $this->hasTaglineColumn();
        $logoUpload = $_FILES['restaurant_logo'] ?? null;
        $faviconUpload = $_FILES['restaurant_favicon'] ?? null;
        $hasLogoUpload = is_array($logoUpload) && (int)($logoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $hasFaviconUpload = is_array($faviconUpload) && (int)($faviconUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $removeLogo = (string)$request->post('remove_logo', '') === '1';
        $removeFavicon = (string)$request->post('remove_favicon', '') === '1';

        if (!$logoColumnReady && ($hasLogoUpload || $removeLogo)) {
            Session::setFlash('warning', 'Logo storage is not available in this database yet. Your settings were not changed.');
            Response::redirect('/settings');
            return;
        }
        if (!$faviconColumnReady && ($hasFaviconUpload || $removeFavicon)) {
            Session::setFlash('warning', 'Favicon storage is not available in this database yet. Your settings were not changed.');
            Response::redirect('/settings');
            return;
        }

        $oldLogoPath = trim((string)($restaurant['logo_path'] ?? ''));
        $oldFaviconPath = trim((string)($restaurant['favicon_path'] ?? ''));
        $newLogoPath = $oldLogoPath !== '' ? $oldLogoPath : null;
        $newFaviconPath = $oldFaviconPath !== '' ? $oldFaviconPath : null;
        $uploadedLogoPath = null;
        $uploadedFaviconPath = null;
        $databaseCommitted = false;
        $pdo = null;

        try {
            if ($hasLogoUpload) {
                $uploadedLogoPath = $this->storeUploadedLogo($logoUpload, $restaurantId);
                $newLogoPath = $uploadedLogoPath;
            } elseif ($removeLogo) {
                $newLogoPath = null;
            }

            if ($hasFaviconUpload) {
                $uploadedFaviconPath = $this->storeUploadedFavicon($faviconUpload, $restaurantId);
                $newFaviconPath = $uploadedFaviconPath;
            } elseif ($removeFavicon) {
                $newFaviconPath = null;
            }

            $updates = [
                'name' => $restaurantName,
                'pos_primary_color' => $primaryColor,
                'pos_secondary_color' => $secondaryColor
            ];
            if ($logoColumnReady) $updates['logo_path'] = $newLogoPath;
            if ($faviconColumnReady) $updates['favicon_path'] = $newFaviconPath;
            if ($taglineColumnReady) $updates['tagline'] = $tagline;

            $sets = [];
            $values = [];
            foreach ($updates as $column => $value) {
                // Column names come only from this fixed allowlist, never from user input.
                $sets[] = $column . ' = ?';
                $values[] = $value;
            }
            $values[] = $restaurantId;

            $pdo = Database::getInstance();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE restaurants SET ' . implode(', ', $sets) . ' WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute($values);

            if ($stmt->rowCount() === 0) {
                $exists = Database::fetchOne('SELECT id FROM restaurants WHERE id = ? AND deleted_at IS NULL', [$restaurantId]);
                if (!$exists) {
                    throw new \RuntimeException('The restaurant record no longer exists.');
                }
            }

            $pdo->commit();
            $databaseCommitted = true;

            if ($oldLogoPath !== '' && $oldLogoPath !== $newLogoPath) {
                $this->deleteStoredLogo($oldLogoPath);
            }
            if ($oldFaviconPath !== '' && $oldFaviconPath !== $newFaviconPath) {
                $this->deleteStoredFavicon($oldFaviconPath);
            }

            Session::setFlash('success', 'Settings saved. Restaurant identity, branding, logo, colors, and favicon have been updated.');
            if (!$taglineColumnReady) {
                Session::setFlash('warning', 'The rest of your settings were saved, but tagline editing requires the one-time tagline database migration shown on this page.');
            }
        } catch (Throwable $e) {
            try {
                if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            } catch (Throwable $ignored) {
                // Preserve the original error below.
            }

            if (!$databaseCommitted && $uploadedLogoPath !== null && $uploadedLogoPath !== $oldLogoPath) {
                $this->deleteStoredLogo($uploadedLogoPath);
            }
            if (!$databaseCommitted && $uploadedFaviconPath !== null && $uploadedFaviconPath !== $oldFaviconPath) {
                $this->deleteStoredFavicon($uploadedFaviconPath);
            }

            error_log('[Restaurant Settings] ' . $e->getMessage());
            Session::setFlash('danger', $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Unable to save settings. Check folder permissions and the PHP error log, then try again.');
        }

        Response::redirect('/settings');
    }

    public function branding(Request $request): void
    {
        $this->authorizeBranding();
        Response::redirect('/settings');
    }

    public function saveBranding(Request $request): void
    {
        $restaurantId = $this->authorizeBranding();
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant || (int)($restaurant['id'] ?? 0) !== $restaurantId) {
            Response::forbidden('The selected restaurant could not be loaded.');
        }

        $csrfToken = $request->post('csrf_token');
        if (!is_string($csrfToken)) $csrfToken = $request->post('_csrf_token');
        if (!is_string($csrfToken) || !CSRF::validate($csrfToken)) {
            Session::setFlash('danger', 'Your security token expired. Refresh the page and try again.');
            Response::redirect('/settings/branding');
        }

        $primaryColor = trim((string)$request->post('pos_primary_color', $restaurant['pos_primary_color'] ?? '#2563eb'));
        $secondaryColor = trim((string)$request->post('pos_secondary_color', $restaurant['pos_secondary_color'] ?? '#059669'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor) || !preg_match('/^#[0-9a-fA-F]{6}$/', $secondaryColor)) {
            Session::setFlash('danger', 'Choose valid six-digit HEX colors for the primary and secondary brand colors.');
            Response::redirect('/settings/branding');
        }

        $logoColumnReady = $this->hasLogoColumn();
        $upload = $_FILES['restaurant_logo'] ?? null;
        $hasUpload = is_array($upload) && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $removeLogo = (string)$request->post('remove_logo', '') === '1';

        if (!$logoColumnReady && ($hasUpload || $removeLogo)) {
            Session::setFlash('warning', 'Logo uploads need the one-time database update shown on this page. Your color selections were not changed.');
            Response::redirect('/settings/branding');
        }

        $oldLogoPath = (string)($restaurant['logo_path'] ?? '');
        $newLogoPath = $oldLogoPath !== '' ? $oldLogoPath : null;
        $uploadedPath = null;
        $databaseCommitted = false;

        try {
            if ($hasUpload) {
                $uploadedPath = $this->storeUploadedLogo($upload, $restaurantId);
                $newLogoPath = $uploadedPath;
            } elseif ($removeLogo) {
                $newLogoPath = null;
            }

            $pdo = Database::getInstance();
            $pdo->beginTransaction();

            if ($logoColumnReady) {
                $stmt = $pdo->prepare('UPDATE restaurants SET pos_primary_color = ?, pos_secondary_color = ?, logo_path = ? WHERE id = ? AND deleted_at IS NULL');
                $stmt->execute([$primaryColor, $secondaryColor, $newLogoPath, $restaurantId]);
            } else {
                $stmt = $pdo->prepare('UPDATE restaurants SET pos_primary_color = ?, pos_secondary_color = ? WHERE id = ? AND deleted_at IS NULL');
                $stmt->execute([$primaryColor, $secondaryColor, $restaurantId]);
            }

            if ($stmt->rowCount() === 0) {
                // MySQL reports zero for an unchanged row, so verify it still exists.
                $exists = Database::fetchOne('SELECT id FROM restaurants WHERE id = ? AND deleted_at IS NULL', [$restaurantId]);
                if (!$exists) {
                    throw new \RuntimeException('The restaurant record no longer exists.');
                }
            }

            $pdo->commit();
            $databaseCommitted = true;

            if ($oldLogoPath !== '' && $oldLogoPath !== $newLogoPath) {
                $this->deleteStoredLogo($oldLogoPath);
            }

            Session::setFlash('success', $newLogoPath !== null && $newLogoPath !== $oldLogoPath
                ? 'Branding saved. Your restaurant logo is now updated.'
                : ($removeLogo ? 'Brand colors saved and the restaurant logo was removed.' : 'Restaurant branding settings saved.'));
        } catch (Throwable $e) {
            try {
                $pdo = Database::getInstance();
                if ($pdo->inTransaction()) $pdo->rollBack();
            } catch (Throwable $ignored) {
                // Preserve the original error below.
            }

            if (!$databaseCommitted && $uploadedPath !== null && $uploadedPath !== $oldLogoPath) {
                $this->deleteStoredLogo($uploadedPath);
            }

            error_log('[Restaurant Branding] ' . $e->getMessage());
            Session::setFlash('danger', $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Unable to save branding. Check the PHP error log and try again.');
        }

        Response::redirect('/settings/branding');
    }

    public function favicon(Request $request): void
    {
        $this->authorizeBranding();
        Response::redirect('/settings');
    }

    public function saveFavicon(Request $request): void
    {
        $restaurantId = $this->authorizeBranding();
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant || (int)($restaurant['id'] ?? 0) !== $restaurantId) {
            Response::forbidden('The selected restaurant could not be loaded.');
        }

        $csrfToken = $request->post('csrf_token');
        if (!is_string($csrfToken)) $csrfToken = $request->post('_csrf_token');
        if (!is_string($csrfToken) || !CSRF::validate($csrfToken)) {
            Session::setFlash('danger', 'Your security token expired. Refresh the page and try again.');
            Response::redirect('/settings/favicon');
        }

        $faviconColumnReady = $this->hasFaviconColumn();
        $upload = $_FILES['restaurant_favicon'] ?? null;
        $hasUpload = is_array($upload) && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $removeFavicon = (string)$request->post('remove_favicon', '') === '1';

        if (!$faviconColumnReady && ($hasUpload || $removeFavicon)) {
            Session::setFlash('warning', 'Separate favicon settings need the one-time database update shown on this page. Your current logo and brand colors were not changed.');
            Response::redirect('/settings/favicon');
        }

        $oldPath = trim((string)($restaurant['favicon_path'] ?? ''));
        $newPath = $oldPath !== '' ? $oldPath : null;
        $uploadedPath = null;
        $databaseCommitted = false;

        try {
            if ($hasUpload) {
                $uploadedPath = $this->storeUploadedFavicon($upload, $restaurantId);
                $newPath = $uploadedPath;
            } elseif ($removeFavicon) {
                $newPath = null;
            }

            $pdo = Database::getInstance();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE restaurants SET favicon_path = ? WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$newPath, $restaurantId]);

            if ($stmt->rowCount() === 0) {
                $exists = Database::fetchOne('SELECT id FROM restaurants WHERE id = ? AND deleted_at IS NULL', [$restaurantId]);
                if (!$exists) {
                    throw new \RuntimeException('The restaurant record no longer exists.');
                }
            }

            $pdo->commit();
            $databaseCommitted = true;

            if ($oldPath !== '' && $oldPath !== $newPath) {
                $this->deleteStoredFavicon($oldPath);
            }

            Session::setFlash('success', $newPath !== null && $newPath !== $oldPath
                ? 'Favicon saved. Refresh the browser tab to see the new icon.'
                : ($removeFavicon ? 'Custom favicon removed. The restaurant logo will be used instead.' : 'Favicon settings saved.'));
        } catch (Throwable $e) {
            try {
                $pdo = Database::getInstance();
                if ($pdo->inTransaction()) $pdo->rollBack();
            } catch (Throwable $ignored) {
                // Preserve the original error below.
            }

            if (!$databaseCommitted && $uploadedPath !== null && $uploadedPath !== $oldPath) {
                $this->deleteStoredFavicon($uploadedPath);
            }

            error_log('[Restaurant Favicon] ' . $e->getMessage());
            Session::setFlash('danger', $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Unable to save the favicon. Check the PHP error log and try again.');
        }

        Response::redirect('/settings/favicon');
    }

    private function authorizeBranding(): int
    {
        Auth::requireLogin();

        if (!Auth::isPlatformAdmin() && Auth::roleSlug() !== 'owner') {
            Response::forbidden('Only the restaurant owner or platform administrator can manage restaurant branding.');
        }

        $restaurantId = Auth::getRestaurantId();
        if (!$restaurantId) {
            Response::forbidden('Select a restaurant workspace before opening Branding & Logo.');
        }

        return (int)$restaurantId;
    }

    private function addMissingRestaurantColumn(string $column, string $definition): void
    {
        $exists = match ($column) {
            'logo_path' => $this->hasLogoColumn(),
            'favicon_path' => $this->hasFaviconColumn(),
            'tagline' => $this->hasTaglineColumn(),
            default => throw new \InvalidArgumentException('Unsupported restaurant settings column.')
        };

        if ($exists) return;

        $pdo = Database::getInstance();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $afterClause = '';
        if ($driver !== 'sqlite') {
            $afterClause = match ($column) {
                'logo_path' => ' AFTER name',
                'favicon_path' => ' AFTER logo_path',
                'tagline' => ' AFTER name',
                default => ''
            };
        }

        // $column, $definition, and the column position are internal allowlisted values.
        $pdo->exec('ALTER TABLE restaurants ADD COLUMN ' . $column . ' ' . $definition . $afterClause);
    }

    private function hasTaglineColumn(): bool
    {
        try {
            $pdo = Database::getInstance();
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $columns = Database::fetchAll('PRAGMA table_info(restaurants)');
                return in_array('tagline', array_column($columns, 'name'), true);
            }

            return Database::fetchOne("SHOW COLUMNS FROM restaurants LIKE 'tagline'") !== null;
        } catch (Throwable $e) {
            error_log('[Restaurant Settings] Unable to inspect tagline schema: ' . $e->getMessage());
            return false;
        }
    }

    private function hasLogoColumn(): bool
    {
        try {
            $pdo = Database::getInstance();
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $columns = Database::fetchAll('PRAGMA table_info(restaurants)');
                return in_array('logo_path', array_column($columns, 'name'), true);
            }

            return Database::fetchOne("SHOW COLUMNS FROM restaurants LIKE 'logo_path'") !== null;
        } catch (Throwable $e) {
            error_log('[Restaurant Branding] Unable to inspect restaurant logo schema: ' . $e->getMessage());
            return false;
        }
    }

    private function hasFaviconColumn(): bool
    {
        try {
            $pdo = Database::getInstance();
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $columns = Database::fetchAll('PRAGMA table_info(restaurants)');
                return in_array('favicon_path', array_column($columns, 'name'), true);
            }

            return Database::fetchOne("SHOW COLUMNS FROM restaurants LIKE 'favicon_path'") !== null;
        } catch (Throwable $e) {
            error_log('[Restaurant Favicon] Unable to inspect favicon schema: ' . $e->getMessage());
            return false;
        }
    }

    private function storeUploadedFavicon(array $upload, int $restaurantId): string
    {
        $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new \RuntimeException('The favicon file is too large. Choose an image under 512 KB.');
            }
            throw new \RuntimeException('The favicon upload did not complete. Please choose the image again.');
        }

        $tmpPath = (string)($upload['tmp_name'] ?? '');
        $size = (int)($upload['size'] ?? 0);
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new \RuntimeException('The uploaded favicon could not be verified. Please choose the file again.');
        }
        if ($size < 1 || $size > 524288) {
            throw new \RuntimeException('The favicon file must be 512 KB or smaller.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!is_string($mime) || !isset($extensions[$mime])) {
            throw new \RuntimeException('Use a PNG, JPG, or WebP favicon image. SVG files are not accepted.');
        }

        $imageInfo = @getimagesize($tmpPath);
        if (!$imageInfo || ($imageInfo['mime'] ?? '') !== $mime) {
            throw new \RuntimeException('The uploaded favicon is not a valid image.');
        }
        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);
        if ($width < 16 || $height < 16 || $width > 512 || $height > 512) {
            throw new \RuntimeException('Favicon dimensions must be between 16 × 16 and 512 × 512 pixels.');
        }

        $relativeDirectory = 'public/uploads/restaurant-favicons';
        $absoluteDirectory = APP_ROOT . '/' . $relativeDirectory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0755, true) && !is_dir($absoluteDirectory)) {
            throw new \RuntimeException('The favicon upload directory could not be created. Check folder permissions.');
        }
        if (!is_writable($absoluteDirectory)) {
            throw new \RuntimeException('The favicon upload folder is not writable by the XAMPP Apache/PHP process.');
        }

        $filename = 'favicon-' . $restaurantId . '-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        $relativePath = $relativeDirectory . '/' . $filename;
        if (!move_uploaded_file($tmpPath, APP_ROOT . '/' . $relativePath)) {
            throw new \RuntimeException('The favicon could not be saved. Check write permission for public/uploads.');
        }

        return $relativePath;
    }

    private function deleteStoredFavicon(string $relativePath): void
    {
        $prefix = 'public/uploads/restaurant-favicons/';
        if (!str_starts_with($relativePath, $prefix)) return;

        $filename = substr($relativePath, strlen($prefix));
        if (!preg_match('/^favicon-[0-9]+-[a-f0-9]{24}\.(?:jpg|png|webp)$/i', $filename)) return;

        $absolutePath = APP_ROOT . '/' . $prefix . $filename;
        if (is_file($absolutePath)) @unlink($absolutePath);
    }

    private function storeUploadedLogo(array $upload, int $restaurantId): string
    {
        $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new \RuntimeException('The logo file is too large. Choose an image under 2 MB.');
            }
            throw new \RuntimeException('The logo upload did not complete. Please choose the image again.');
        }

        $tmpPath = (string)($upload['tmp_name'] ?? '');
        $size = (int)($upload['size'] ?? 0);
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new \RuntimeException('The uploaded logo could not be verified. Please choose the file again.');
        }
        if ($size < 1 || $size > self::MAX_LOGO_BYTES) {
            throw new \RuntimeException('The logo file must be 2 MB or smaller.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];
        if (!is_string($mime) || !isset($extensions[$mime])) {
            throw new \RuntimeException('Use a JPG, PNG, or WebP image. SVG files are not accepted.');
        }

        $imageInfo = @getimagesize($tmpPath);
        if (!$imageInfo || ($imageInfo['mime'] ?? '') !== $mime) {
            throw new \RuntimeException('The uploaded file is not a valid image.');
        }
        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);
        if ($width < 1 || $height < 1 || $width > 5000 || $height > 5000) {
            throw new \RuntimeException('Image dimensions must not exceed 5000 × 5000 pixels.');
        }

        $relativeDirectory = 'public/uploads/restaurant-logos';
        $absoluteDirectory = APP_ROOT . '/' . $relativeDirectory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0755, true) && !is_dir($absoluteDirectory)) {
            throw new \RuntimeException('The logo upload directory could not be created. Check that the parent public/uploads directory exists and is writable by PHP.');
        }
        if (!is_writable($absoluteDirectory)) {
            throw new \RuntimeException('The logo upload folder exists but is not writable by the XAMPP Apache/PHP process. Grant write permission to this folder, then try again.');
        }

        $filename = 'restaurant-' . $restaurantId . '-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        $relativePath = $relativeDirectory . '/' . $filename;
        if (!move_uploaded_file($tmpPath, APP_ROOT . '/' . $relativePath)) {
            throw new \RuntimeException('The logo could not be saved. Check write permission for public/uploads.');
        }

        return $relativePath;
    }

    private function deleteStoredLogo(string $relativePath): void
    {
        $prefix = 'public/uploads/restaurant-logos/';
        if (!str_starts_with($relativePath, $prefix)) return;

        $filename = substr($relativePath, strlen($prefix));
        if (!preg_match('/^restaurant-[0-9]+-[a-f0-9]{24}\.(?:jpg|png|webp)$/i', $filename)) return;

        $absolutePath = APP_ROOT . '/' . $prefix . $filename;
        if (is_file($absolutePath)) @unlink($absolutePath);
    }
}
