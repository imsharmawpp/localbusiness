<?php
/**
 * includes/functions.php
 *
 * Helper_Library — the shared helper functions used across the whole site:
 *   e()           HTML output escaping                       (Req 2.1, 6.5)
 *   slug()        URL-safe slug generation                   (Req 2.2)
 *   upload()      validated image upload + safe rename        (Req 2.3, 2.4, 22.3)
 *   flash()       read-once session flash messages            (Req 2.5)
 *   csrf_token()  per-session CSRF token (created on demand)  (Req 2.6)
 *   csrf_verify() timing-safe CSRF token comparison           (Req 2.7, 6.2, 6.3)
 *   is_open()     Open_Closed_Calculator from the hours JSON   (Req 15.1-15.3)
 *
 * Requirements: 2.1-2.7, 6.2, 6.3, 6.5, 15.1, 15.2, 15.3, 22.3.
 */

declare(strict_types=1);

// Ensure configuration constants (UPLOAD_*) are available for upload().
if (!defined('UPLOAD_DIR')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Start a PHP session if one is not already active.
 *
 * Used by the session-backed helpers (flash, CSRF) so they work whether or not
 * the caller (e.g. admin/auth.php) has already started the session.
 */
function ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * Escape a string for safe HTML output (Req 2.1, 6.5).
 *
 * Uses ENT_QUOTES so both single and double quotes are encoded, and
 * ENT_SUBSTITUTE so invalid UTF-8 sequences are replaced rather than producing
 * an empty string.
 *
 * @param string|null $s Raw value (null is treated as an empty string).
 * @return string        HTML-safe value.
 */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Convert an arbitrary string into a URL-safe slug (Req 2.2).
 *
 * Transliterates to ASCII, lowercases, collapses every run of non-alphanumeric
 * characters into a single hyphen, and trims leading/trailing hyphens. The
 * result contains only [a-z0-9-] and is idempotent: slug(slug(x)) === slug(x).
 *
 * @param string $s Input string.
 * @return string   Slugified string (possibly empty when no usable characters).
 */
function slug(string $s): string
{
    $s = trim($s);

    // Transliterate accented/Unicode characters to their closest ASCII form.
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($converted !== false) {
            $s = $converted;
        }
    }

    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s); // collapse non-alphanumerics to single hyphen
    $s = trim((string) $s, '-');                // strip leading/trailing hyphens

    return $s;
}

/**
 * Set or read-and-clear a session flash message (Req 2.5).
 *
 * Called with a message it stores the message for the next request. Called with
 * no argument it returns the stored message (once) and clears it, so a flash is
 * displayed exactly once.
 *
 * @param string|null $msg Message to store, or null to read-and-clear.
 * @return string|null     The stored message when reading, otherwise null.
 */
function flash(?string $msg = null): ?string
{
    ensure_session();

    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }

    if (isset($_SESSION['flash'])) {
        $stored = (string) $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $stored;
    }

    return null;
}

/**
 * Return the current session CSRF token, creating one if absent (Req 2.6).
 *
 * @return string The 64-character hex CSRF token for this session.
 */
function csrf_token(): string
{
    ensure_session();

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf'];
}

/**
 * Verify a submitted CSRF token against the session token (Req 2.7, 6.2, 6.3).
 *
 * Uses hash_equals for a timing-safe comparison and returns true only when a
 * non-empty session token exists and the submitted token matches it exactly.
 *
 * @param string|null $token The token submitted with the request.
 * @return bool              True only on an exact match.
 */
function csrf_verify(?string $token): bool
{
    ensure_session();

    if ($token === null || $token === '') {
        return false;
    }
    if (empty($_SESSION['csrf'])) {
        return false;
    }

    return hash_equals((string) $_SESSION['csrf'], $token);
}

/**
 * Validate and store an uploaded image (Req 2.3, 2.4, 22.3).
 *
 * Validates the real MIME type (via finfo, not the client-supplied type)
 * against UPLOAD_ALLOWED_MIME and the size against UPLOAD_MAX_BYTES, then
 * renames the file to a "time()_slug.ext" pattern and moves it into UPLOAD_DIR.
 * Never throws: any problem is reported through the returned error array.
 *
 * @param array  $file A single entry from $_FILES.
 * @param string $base Base name used for the slug portion of the stored file.
 * @return array       ['ok' => true, 'filename' => string]
 *                     or ['ok' => false, 'error' => string].
 */
function upload(array $file, string $base = ''): array
{
    // Structural / transport checks.
    if (!isset($file['error'])) {
        return ['ok' => false, 'error' => 'No file was provided.'];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file was selected.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'The file could not be uploaded.'];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        return ['ok' => false, 'error' => 'The uploaded file is missing.'];
    }

    // Size validation (Req 2.4).
    $size = isset($file['size']) ? (int) $file['size'] : (int) @filesize($tmp);
    if ($size <= 0) {
        return ['ok' => false, 'error' => 'The uploaded file is empty.'];
    }
    if ($size > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'The file exceeds the maximum allowed size.'];
    }

    // Real MIME validation against the allowlist (Req 2.3, 22.3).
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) ($finfo->file($tmp) ?: '');
    if (!in_array($mime, UPLOAD_ALLOWED_MIME, true)) {
        return ['ok' => false, 'error' => 'That file type is not allowed.'];
    }

    // Resolve a safe extension from the validated MIME type.
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $ext = $extMap[$mime] ?? 'bin';

    // Build the "time()_slug.ext" filename (Req 2.3).
    $baseName = $base !== '' ? $base : pathinfo((string) ($file['name'] ?? 'file'), PATHINFO_FILENAME);
    $slugBase = slug($baseName);
    if ($slugBase === '') {
        $slugBase = 'file';
    }
    $filename = time() . '_' . $slugBase . '.' . $ext;

    // Ensure the destination directory exists.
    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0755, true);
    }
    $dest = rtrim(UPLOAD_DIR, '/') . '/' . $filename;

    // Move the file. Prefer move_uploaded_file for genuine HTTP uploads, and
    // fall back to rename so the function is usable outside the web request
    // path (e.g. test harness).
    $moved = @move_uploaded_file($tmp, $dest);
    if (!$moved) {
        $moved = @rename($tmp, $dest);
    }
    if (!$moved) {
        return ['ok' => false, 'error' => 'The uploaded file could not be stored.'];
    }

    return ['ok' => true, 'filename' => $filename];
}

/**
 * Convert an "HH:MM" string to minutes-since-midnight, or null if invalid.
 *
 * @param string $hm Time string such as "09:30".
 * @return int|null  Minutes since midnight, or null when malformed.
 */
function hm_to_min(string $hm): ?int
{
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($hm), $m)) {
        return null;
    }
    $h   = (int) $m[1];
    $min = (int) $m[2];
    if ($h > 23 || $min > 59) {
        return null;
    }
    return $h * 60 + $min;
}

/**
 * Compute the open/closed state from the hours setting (Req 15.1-15.3).
 *
 * The hours JSON maps a three-letter lowercase weekday (mon..sun) to a list of
 * [open, close] "HH:MM" intervals, e.g.
 *   {"mon":[["09:00","13:00"],["14:00","18:00"]],"sun":[]}
 * The clinic is open when $now falls within an interval for the current
 * weekday (inclusive of the open minute, exclusive of the close minute).
 *
 * @param string                  $hoursJson JSON hours map.
 * @param DateTimeInterface|null   $now       Reference time (defaults to now).
 * @return array                             ['open' => bool, 'label' => string].
 */
function is_open(string $hoursJson, ?DateTimeInterface $now = null): array
{
    $now = $now ?? new DateTimeImmutable('now');

    $map = json_decode($hoursJson, true);
    if (!is_array($map)) {
        return ['open' => false, 'label' => 'Closed'];
    }

    $dayKey    = strtolower($now->format('D')); // "Mon" -> "mon"
    $intervals = $map[$dayKey] ?? [];
    if (!is_array($intervals)) {
        $intervals = [];
    }

    $curMinutes = ((int) $now->format('G')) * 60 + ((int) $now->format('i'));

    foreach ($intervals as $iv) {
        if (!is_array($iv) || count($iv) < 2) {
            continue;
        }
        $open  = hm_to_min((string) $iv[0]);
        $close = hm_to_min((string) $iv[1]);
        if ($open === null || $close === null) {
            continue;
        }
        if ($curMinutes >= $open && $curMinutes < $close) {
            return ['open' => true, 'label' => 'Open now'];
        }
    }

    return ['open' => false, 'label' => 'Closed'];
}
