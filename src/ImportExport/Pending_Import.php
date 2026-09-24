<?php
/**
 * Uploaded import files waiting for confirmation.
 *
 * Between the preview and the import, an uploaded file is kept in a
 * plugin-owned uploads directory under a random name. Only the name is
 * stored, in the uploader's user meta, so a request can reach the file only
 * through the user who uploaded it, never through a path it supplies.
 *
 * The directory denies web access where the server honors .htaccess, but
 * that cannot be relied on: nginx, for one, ignores it. The file is
 * therefore encrypted with a key derived from the site's secret salts and
 * the random name, so even a file served by the web server is unreadable.
 * Files are removed after the import, on cancel, and by the hourly upload
 * cleanup once they expire.
 *
 * @package ElzoForms\ImportExport
 */

namespace ElzoForms\ImportExport;

use ElzoForms\Upload\File_Upload_Handler;

// Exit if accessed directly
defined('ABSPATH') || exit;

final class Pending_Import {

    public const TYPE_FORMS = 'forms';
    public const TYPE_SUBMISSIONS = 'submissions';

    /** Directory below uploads/elzo-forms. */
    private const DIRECTORY = 'imports';

    /** User meta key prefix. */
    private const META_PREFIX = '_elzo_forms_pending_import_';

    /** Seconds an uploaded file waits for confirmation. */
    private const LIFETIME = 3600;

    /** Random file name without extension: 32 hexadecimal characters. */
    private const TOKEN_PATTERN = '/^[a-f0-9]{32}$/';

    /** Extension of stored files. */
    private const EXTENSION = '.bin';

    private function __construct() {
    }

    /**
     * Store an uploaded file for the current user, replacing an earlier one.
     *
     * @param string $type TYPE_FORMS or TYPE_SUBMISSIONS.
     * @param string $contents File contents.
     * @param string $name Original file name, for display.
     * @return true|\WP_Error
     */
    public static function store(string $type, string $contents, string $name) {
        $error = new \WP_Error('elzo_forms_import_storage', __('The file could not be stored for import.', 'elzo-forms'));

        if (!self::is_type($type) || get_current_user_id() <= 0) {
            return $error;
        }

        self::discard($type);

        $directory = File_Upload_Handler::ensure_private_directory(self::DIRECTORY);
        if ($directory === '') {
            return $error;
        }

        try {
            $token = bin2hex(random_bytes(16));
        } catch (\Exception $exception) {
            unset($exception);
            return $error;
        }

        $payload = self::encrypt($contents, $token);
        unset($contents);

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes an encrypted, randomly named file into the protected plugin-owned uploads directory.
        if ($payload === null || file_put_contents($directory . '/' . $token . self::EXTENSION, $payload, LOCK_EX) === false) {
            return $error;
        }

        update_user_meta(get_current_user_id(), self::META_PREFIX . $type, [
            'token' => $token,
            'name' => sanitize_file_name($name),
            'created' => time(),
        ]);

        return true;
    }

    /**
     * Get the file the current user uploaded, if it is still waiting.
     *
     * @param string $type TYPE_FORMS or TYPE_SUBMISSIONS.
     * @return array{name: string, created: int, path: string, token: string}|null
     */
    public static function get(string $type): ?array {
        if (!self::is_type($type) || get_current_user_id() <= 0) {
            return null;
        }

        $meta = get_user_meta(get_current_user_id(), self::META_PREFIX . $type, true);
        if (!is_array($meta) || !isset($meta['token']) || !is_string($meta['token']) || !preg_match(self::TOKEN_PATTERN, $meta['token'])) {
            return null;
        }

        $created = isset($meta['created']) ? (int) $meta['created'] : 0;
        $path = self::path($meta['token']);

        if ($created + self::LIFETIME < time() || $path === '' || !is_file($path)) {
            self::discard($type);
            return null;
        }

        return [
            'name' => isset($meta['name']) && is_string($meta['name']) ? $meta['name'] : '',
            'created' => $created,
            'path' => $path,
            'token' => $meta['token'],
        ];
    }

    /**
     * Read the file the current user uploaded.
     *
     * @param string $type TYPE_FORMS or TYPE_SUBMISSIONS.
     * @return string|null Null when there is none, or it cannot be decrypted.
     */
    public static function read(string $type): ?string {
        $pending = self::get($type);
        if (!$pending) {
            return null;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a file from the protected plugin-owned uploads directory; the path is built from a validated token.
        $payload = file_get_contents($pending['path']);

        return is_string($payload) ? self::decrypt($payload, $pending['token']) : null;
    }

    /**
     * Remove the file the current user uploaded.
     *
     * @param string $type TYPE_FORMS or TYPE_SUBMISSIONS.
     * @return void
     */
    public static function discard(string $type): void {
        if (!self::is_type($type) || get_current_user_id() <= 0) {
            return;
        }

        $meta = get_user_meta(get_current_user_id(), self::META_PREFIX . $type, true);
        if (is_array($meta) && isset($meta['token']) && is_string($meta['token']) && preg_match(self::TOKEN_PATTERN, $meta['token'])) {
            $path = self::path($meta['token']);
            if ($path !== '' && is_file($path)) {
                wp_delete_file($path);
            }
        }

        delete_user_meta(get_current_user_id(), self::META_PREFIX . $type);
    }

    /**
     * Remove every expired file, whoever uploaded it.
     *
     * @return void
     */
    public static function cleanup_expired(): void {
        $directory = self::directory();
        if ($directory === '' || !is_dir($directory)) {
            return;
        }

        $cutoff = time() - self::LIFETIME;
        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $entry) {
            if (
                $entry->isFile()
                && !$entry->isLink()
                && preg_match('/^[a-f0-9]{32}\.bin$/', $entry->getFilename())
                && $entry->getMTime() < $cutoff
            ) {
                wp_delete_file($entry->getPathname());
            }
        }
    }

    /**
     * Encrypt a payload for storage.
     *
     * WordPress provides sodium through sodium_compat when PHP lacks the
     * extension, so the functions are always available.
     *
     * @param string $contents Plain payload.
     * @param string $token Random file name.
     * @return string|null Nonce and ciphertext, or null on failure.
     */
    private static function encrypt(string $contents, string $token): ?string {
        try {
            $nonce = random_bytes(self::nonce_bytes());

            return $nonce . sodium_crypto_secretbox($contents, $nonce, self::key($token));
        } catch (\Throwable $throwable) {
            unset($throwable);
            return null;
        }
    }

    /**
     * Decrypt a stored payload.
     *
     * @param string $payload Nonce and ciphertext.
     * @param string $token Random file name.
     * @return string|null Plain payload, or null when it is not authentic.
     */
    private static function decrypt(string $payload, string $token): ?string {
        $nonce_bytes = self::nonce_bytes();
        if (strlen($payload) <= $nonce_bytes) {
            return null;
        }

        try {
            $contents = sodium_crypto_secretbox_open(substr($payload, $nonce_bytes), substr($payload, 0, $nonce_bytes), self::key($token));
        } catch (\Throwable $throwable) {
            unset($throwable);
            return null;
        }

        return is_string($contents) ? $contents : null;
    }

    /**
     * Derive the key of one stored file from the site's secret salts.
     *
     * @param string $token Random file name.
     * @return string 32-byte key.
     */
    private static function key(string $token): string {
        return hash_hmac('sha256', 'elzo_forms_pending_import|' . $token, wp_salt('auth'), true);
    }

    /**
     * @return int Nonce length of the secretbox construction.
     */
    private static function nonce_bytes(): int {
        return defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES') ? (int) SODIUM_CRYPTO_SECRETBOX_NONCEBYTES : 24;
    }

    /**
     * Whether a type is known.
     *
     * @param string $type Type.
     * @return bool
     */
    private static function is_type(string $type): bool {
        return in_array($type, [self::TYPE_FORMS, self::TYPE_SUBMISSIONS], true);
    }

    /**
     * Get the storage directory without creating it.
     *
     * @return string
     */
    private static function directory(): string {
        $upload_dir = wp_upload_dir();
        $basedir = isset($upload_dir['basedir']) ? (string) $upload_dir['basedir'] : '';

        return $basedir !== '' ? rtrim($basedir, '/\\') . '/elzo-forms/' . self::DIRECTORY : '';
    }

    /**
     * Get the path of a stored file.
     *
     * @param string $token Validated token.
     * @return string
     */
    private static function path(string $token): string {
        $directory = self::directory();

        return $directory !== '' && preg_match(self::TOKEN_PATTERN, $token) ? $directory . '/' . $token . self::EXTENSION : '';
    }
}
