<?php
/**
 * tests/run_tests.php
 *
 * Standalone, plain-PHP property/smoke test harness for the local-business
 * website engine (Feature: local-business-website-system).
 *
 * NO PHPUnit, NO Composer, NO npm. Run with:
 *
 *     php tests/run_tests.php
 *
 * The harness loads ONLY includes/functions.php (and, in an output buffer that
 * is immediately discarded, includes/header.php solely to make the pure
 * Schema_Generator helper `localbusiness_jsonld()` available). It NEVER touches
 * the database: it defines the UPLOAD_* / SITE_URL configuration constants to a
 * throwaway temp directory BEFORE requiring functions.php (so functions.php does
 * not pull in config.php) and stubs `setting()` BEFORE including header.php (so
 * header.php does not pull in settings.php / db.php).
 *
 * A tiny assert_true($cond, $label) harness counts pass/fail and exits non-zero
 * on any failure (so it is CI-friendly).
 *
 * ---------------------------------------------------------------------------
 * Property tests wired here (each runs >= 100 randomized iterations and is
 * tagged "Feature: local-business-website-system, Property N"):
 *
 *   Property 1  — e() escaping neutralizes markup            (Req 2.1, 6.5)
 *   Property 2  — slug() is URL-safe and idempotent          (Req 2.2)
 *   Property 4  — flash() is read-once                       (Req 2.5)
 *   Property 5  — csrf_verify() true iff equals session tok  (Req 2.6, 2.7, 6.1-6.3)
 *   Property 14 — is_open() reflects configured hours        (Req 15.1-15.3)
 *   Property 17 — localbusiness_jsonld() valid JSON + @type  (Req 20.2, 20.3)
 *
 * ---------------------------------------------------------------------------
 * Requirements traceability (validated by the properties above):
 *   2.1, 2.2, 2.5, 2.6, 2.7, 6.1, 6.2, 6.3, 6.5, 15.1, 15.2, 15.3, 20.2, 20.3
 *
 * The DB-touching properties (3, 8, 9, 10, 12, 13, 15, 16, 18) and the
 * fixture/rendering properties (6, 7, 11) are intentionally NOT wired here:
 * per design.md "Testing Strategy" they require a disposable MySQL test schema
 * or page-render fixtures and are covered separately. This harness covers the
 * PURE, side-effect-free helpers that can be exercised with zero infrastructure.
 *
 * ===========================================================================
 * MANUAL QA CHECKLIST (mirrors design.md "Testing Strategy" / 00 §8-§9)
 * ---------------------------------------------------------------------------
 * The following infrastructure / UI / integration concerns are NOT amenable to
 * property-based testing and MUST be verified by hand before each deploy:
 *
 *   [ ] Responsive — layout at 360 / 768 / 1024 / 1440 px; sticky header,
 *       hamburger nav, floating WhatsApp, 44px hit targets, visible focus,
 *       reduced-motion honored.
 *   [ ] Forms / CSRF — submit appointment + contact forms WITH and WITHOUT a
 *       valid CSRF token; confirm rejection (HTTP 400, no mutation) on
 *       missing/invalid token.
 *   [ ] Lead-capture dual-write — submit the appointment form; confirm exactly
 *       one appointments row AND one leads row appear in the admin inboxes.
 *   [ ] Validation — submit each form missing one required field; confirm a
 *       field-specific error and NO database write.
 *   [ ] Upload validation — attempt a non-image and an oversized file; confirm
 *       rejection; confirm a valid image is renamed (time()_slug.ext) and
 *       stored under /assets/uploads.
 *   [ ] SQL injection — enter quotes / SQL keywords in form fields; confirm
 *       verbatim storage and intact surrounding data (prepared statements).
 *   [ ] Schema validation — run a live page through Google's Rich Results Test;
 *       confirm LocalBusiness JSON-LD validity and correct @type.
 *   [ ] SSL / HTTPS — confirm http:// 301-redirects to https://; confirm the
 *       session cookie carries the Secure flag over HTTPS.
 *   [ ] Hardening — confirm /includes/* returns 403; confirm a PHP file dropped
 *       in /uploads does not execute.
 *   [ ] SEO — unique title/meta per page; sitemap.php lists active services;
 *       robots.txt references the sitemap.
 *   [ ] Admin auth — unauthenticated admin pages redirect to login; logout
 *       destroys the session.
 *   [ ] Deploy smoke — import schema.sql + seed.sql; confirm seed counts
 *       (5 services, 2 doctors, 6 FAQs, 4 testimonials, all settings, 1 admin
 *       user) and the first-login password change.
 * ===========================================================================
 */

declare(strict_types=1);

/*
 * Start a real session BEFORE any output so the session-backed helpers
 * (flash(), csrf_token(), csrf_verify()) find an already-active session and
 * ensure_session() becomes a no-op. The $_SESSION superglobal then serves as
 * our in-memory stub which the property tests manipulate directly.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/* -------------------------------------------------------------------------
 * Configuration constants — defined to a throwaway temp directory BEFORE
 * requiring functions.php so it does NOT load config.php (and so nothing here
 * ever touches the database).
 * ------------------------------------------------------------------------- */
$TMP_UPLOAD_DIR = sys_get_temp_dir() . '/lbws_test_uploads_' . getmypid();
if (!is_dir($TMP_UPLOAD_DIR)) {
    @mkdir($TMP_UPLOAD_DIR, 0700, true);
}

if (!defined('UPLOAD_DIR'))          define('UPLOAD_DIR', $TMP_UPLOAD_DIR);
if (!defined('UPLOAD_URL'))          define('UPLOAD_URL', '/assets/uploads');
if (!defined('UPLOAD_MAX_BYTES'))    define('UPLOAD_MAX_BYTES', 3 * 1024 * 1024);
if (!defined('UPLOAD_ALLOWED_MIME')) define('UPLOAD_ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
// Needed by header.php / localbusiness_jsonld() (Property 17). No trailing slash.
if (!defined('SITE_URL'))            define('SITE_URL', 'https://example.test');

require_once __DIR__ . '/../public_html/includes/functions.php';

/*
 * Make the Schema_Generator helper localbusiness_jsonld() (defined in
 * header.php) available WITHOUT rendering the page or touching the DB:
 *   1. stub setting() so header.php skips require settings.php (-> db.php),
 *   2. require header.php inside an output buffer that is discarded, which
 *      defines localbusiness_jsonld() (and hours_to_schema()) as a side effect.
 */
if (!function_exists('setting')) {
    /** Minimal in-memory stub so header.php does not load settings.php/db.php. */
    function setting(string $key): string
    {
        return '';
    }
}
if (!function_exists('localbusiness_jsonld')) {
    ob_start();
    require __DIR__ . '/../public_html/includes/header.php';
    ob_end_clean();
}

/* =========================================================================
 * Assertion harness
 * ========================================================================= */
$GLOBALS['__tests_passed'] = 0;
$GLOBALS['__tests_failed'] = 0;

/**
 * Count a single assertion. Prints only on failure to keep output readable.
 *
 * @param bool   $cond  The condition that must hold.
 * @param string $label A human-readable description of the assertion.
 */
function assert_true(bool $cond, string $label): void
{
    if ($cond) {
        $GLOBALS['__tests_passed']++;
        return;
    }
    $GLOBALS['__tests_failed']++;
    fwrite(STDERR, "  FAIL: {$label}\n");
}

/** Print a section banner for a property group. */
function group(string $title): void
{
    echo "\n== {$title} ==\n";
}

/* =========================================================================
 * Random generators (smart, constrained to the relevant input space)
 * ========================================================================= */

/** Random printable string that is always valid UTF-8 (ASCII subset + markup). */
function rand_text(int $maxLen = 24): string
{
    // Includes HTML-significant characters so escaping/slug paths are exercised.
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 .,-_!?/\\<>&"\'()[]{}@#%';
    $len = random_int(0, $maxLen);
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

/** Random hex token like csrf_token() produces (default 64 hex chars). */
function rand_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/** Random "HH:MM" 24h time string. */
function rand_hm(): string
{
    return sprintf('%02d:%02d', random_int(0, 23), random_int(0, 59));
}

/* =========================================================================
 * Property 1 — e() escaping neutralizes markup (Req 2.1, 6.5)
 * Feature: local-business-website-system, Property 1
 *
 * For any input string, e() output contains no unescaped HTML-significant
 * characters (< > & " '), and HTML-decoding the output yields the original.
 * ========================================================================= */
function test_property_1(): void
{
    group('Feature: local-business-website-system, Property 1: HTML escaping neutralizes markup');
    for ($i = 0; $i < 200; $i++) {
        $input  = rand_text();
        $output = e($input);

        // No raw angle brackets survive in the escaped output.
        assert_true(strpos($output, '<') === false, "P1: no raw '<' in escaped output (input=" . var_export($input, true) . ')');
        assert_true(strpos($output, '>') === false, "P1: no raw '>' in escaped output (input=" . var_export($input, true) . ')');

        // Decoding the escaped output round-trips to the original string,
        // which proves &, " and ' were encoded correctly too.
        $decoded = html_entity_decode($output, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        assert_true($decoded === $input, "P1: decode(escape(x)) === x (input=" . var_export($input, true) . ')');
    }
}

/* =========================================================================
 * Property 2 — slug() URL-safe + idempotent (Req 2.2)
 * Feature: local-business-website-system, Property 2
 *
 * Output contains only [a-z0-9-] with no leading/trailing/consecutive hyphen,
 * and slug(slug(x)) === slug(x).
 * ========================================================================= */
function test_property_2(): void
{
    group('Feature: local-business-website-system, Property 2: Slugs are URL-safe and idempotent');
    for ($i = 0; $i < 200; $i++) {
        $input = rand_text();
        $s     = slug($input);

        // Charset: only lowercase letters, digits and hyphen.
        assert_true(preg_match('/^[a-z0-9-]*$/', $s) === 1, "P2: charset [a-z0-9-] only (input=" . var_export($input, true) . " slug={$s})");

        // No leading, trailing, or doubled hyphens.
        assert_true(strpos($s, '--') === false, "P2: no consecutive hyphens (slug={$s})");
        if ($s !== '') {
            assert_true($s[0] !== '-', "P2: no leading hyphen (slug={$s})");
            assert_true($s[strlen($s) - 1] !== '-', "P2: no trailing hyphen (slug={$s})");
        }

        // Idempotence.
        assert_true(slug($s) === $s, "P2: slug(slug(x)) === slug(x) (slug={$s})");
    }
}

/* =========================================================================
 * Property 4 — flash() read-once (Req 2.5)
 * Feature: local-business-website-system, Property 4
 *
 * Setting a message then reading returns that exact message; an immediately
 * subsequent read returns empty. Uses the $_SESSION array as the stub store.
 * ========================================================================= */
function test_property_4(): void
{
    group('Feature: local-business-website-system, Property 4: Flash messages are read-once');
    for ($i = 0; $i < 150; $i++) {
        // Clean slate for each iteration.
        unset($_SESSION['flash']);

        $msg = rand_text();

        // Set, then first read returns the exact message.
        flash($msg);
        $first = flash();
        assert_true($first === $msg, "P4: first read returns the set message (msg=" . var_export($msg, true) . ')');

        // Immediate second read is empty (read-once semantics).
        $second = flash();
        assert_true($second === null, 'P4: second read is empty after read-once');
        assert_true(!isset($_SESSION['flash']), 'P4: session flash slot cleared after read');
    }
}

/* =========================================================================
 * Property 5 — csrf_verify() true iff equals session token (Req 2.6, 2.7, 6.1-6.3)
 * Feature: local-business-website-system, Property 5
 *
 * For any candidate token, csrf_verify() returns true iff the candidate equals
 * the current (non-empty) session token. Uses the $_SESSION array as the stub.
 * ========================================================================= */
function test_property_5(): void
{
    group('Feature: local-business-website-system, Property 5: CSRF verification matches only the session token');
    for ($i = 0; $i < 200; $i++) {
        $token = rand_token();
        $_SESSION['csrf'] = $token;

        // Pick a candidate that is sometimes the real token, sometimes a
        // mismatch, sometimes empty/null — to cover the whole truth table.
        $pick = random_int(0, 3);
        switch ($pick) {
            case 0:
                $candidate = $token;          // exact match -> expect true
                break;
            case 1:
                $candidate = rand_token();    // different token -> expect false
                break;
            case 2:
                $candidate = '';              // empty -> expect false
                break;
            default:
                $candidate = null;            // null -> expect false
                break;
        }

        $expected = ($candidate !== null && $candidate !== '' && hash_equals($token, $candidate));
        $actual   = csrf_verify($candidate);
        assert_true($actual === $expected, "P5: csrf_verify matches iff equals session token (candidate=" . var_export($candidate, true) . ')');
    }

    // Also: with NO session token set, every candidate must be rejected.
    for ($i = 0; $i < 50; $i++) {
        unset($_SESSION['csrf']);
        $candidate = (random_int(0, 1) === 0) ? rand_token() : '';
        assert_true(csrf_verify($candidate) === false, 'P5: no session token => always false');
    }
}

/* =========================================================================
 * Property 14 — is_open() reflects configured hours (Req 15.1-15.3)
 * Feature: local-business-website-system, Property 14
 *
 * For any hours config and reference date-time, is_open() reports open iff the
 * date-time falls within a configured opening interval for that weekday.
 * ========================================================================= */
function test_property_14(): void
{
    group('Feature: local-business-website-system, Property 14: Open/closed state reflects configured hours');

    $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    for ($i = 0; $i < 150; $i++) {
        // Build a random week of intervals (some days closed, some with 1-2 intervals).
        $map = [];
        foreach ($dayKeys as $dk) {
            $count = random_int(0, 2);
            $intervals = [];
            for ($j = 0; $j < $count; $j++) {
                $intervals[] = [rand_hm(), rand_hm()];
            }
            $map[$dk] = $intervals;
        }
        $hoursJson = json_encode($map);

        // Random reference moment within a plausible range.
        $ts  = random_int(strtotime('2020-01-01 00:00:00'), strtotime('2030-12-31 23:59:59'));
        $now = (new DateTimeImmutable())->setTimestamp($ts);

        // Independent expectation: minute-of-day within [open, close) for today's intervals.
        $dayKey = strtolower($now->format('D'));
        $curMin = ((int) $now->format('G')) * 60 + ((int) $now->format('i'));
        $expectedOpen = false;
        foreach (($map[$dayKey] ?? []) as $iv) {
            [$o, $c] = $iv;
            [$oh, $om] = array_map('intval', explode(':', $o));
            [$ch, $cm] = array_map('intval', explode(':', $c));
            $openMin  = $oh * 60 + $om;
            $closeMin = $ch * 60 + $cm;
            if ($curMin >= $openMin && $curMin < $closeMin) {
                $expectedOpen = true;
                break;
            }
        }

        $result = is_open($hoursJson, $now);
        assert_true(
            $result['open'] === $expectedOpen,
            "P14: open iff within an interval (day={$dayKey} min={$curMin} expected=" . var_export($expectedOpen, true) . ')'
        );
        // Label must agree with the boolean state.
        assert_true(
            ($result['open'] ? $result['label'] === 'Open now' : $result['label'] === 'Closed'),
            'P14: label agrees with open state'
        );
    }

    // Malformed JSON is treated as closed (Req 15 robustness).
    for ($i = 0; $i < 50; $i++) {
        $bad = is_open('not-json' . rand_text(), $now ?? new DateTimeImmutable());
        assert_true($bad['open'] === false, 'P14: malformed hours JSON => closed');
    }
}

/* =========================================================================
 * Property 17 — localbusiness_jsonld() valid JSON + @type (Req 20.2, 20.3)
 * Feature: local-business-website-system, Property 17
 *
 * For any settings state, the generated JSON-LD parses as valid JSON containing
 * name, telephone, address and url, and @type equals schema_type when set,
 * else 'Dentist'.
 * ========================================================================= */
function test_property_17(): void
{
    group('Feature: local-business-website-system, Property 17: JSON-LD is valid and resolves @type correctly');

    assert_true(function_exists('localbusiness_jsonld'), 'P17: localbusiness_jsonld() is available');

    $schemaTypes = ['Dentist', 'MedicalClinic', 'Physician', 'LocalBusiness', 'HealthAndBeautyBusiness'];

    for ($i = 0; $i < 150; $i++) {
        $hasType = random_int(0, 1) === 1;

        $S = [
            'site_name' => rand_text(),
            'phone'     => '+1 ' . random_int(200, 999) . '-' . random_int(1000000, 9999999),
            'address'   => rand_text() . ' St',
        ];
        if ($hasType) {
            $S['schema_type'] = $schemaTypes[random_int(0, count($schemaTypes) - 1)];
        }
        // Occasionally include an empty schema_type to confirm it falls back to Dentist.
        if (!$hasType && random_int(0, 1) === 1) {
            $S['schema_type'] = '';
        }

        $json    = localbusiness_jsonld($S);
        $decoded = json_decode($json, true);

        assert_true(is_array($decoded), 'P17: output is valid JSON object');
        if (!is_array($decoded)) {
            continue;
        }

        // Required fields present and faithful.
        assert_true(array_key_exists('name', $decoded) && $decoded['name'] === $S['site_name'], 'P17: name present and faithful');
        assert_true(array_key_exists('telephone', $decoded) && $decoded['telephone'] === $S['phone'], 'P17: telephone present and faithful');
        assert_true(array_key_exists('address', $decoded) && $decoded['address'] === $S['address'], 'P17: address present and faithful');
        assert_true(array_key_exists('url', $decoded) && $decoded['url'] === SITE_URL, 'P17: url present and equals SITE_URL');

        // @type resolution: schema_type when non-empty, else 'Dentist'.
        $expectedType = (isset($S['schema_type']) && $S['schema_type'] !== '') ? $S['schema_type'] : 'Dentist';
        assert_true(($decoded['@type'] ?? null) === $expectedType, "P17: @type resolves correctly (expected={$expectedType})");
    }
}

/* =========================================================================
 * Run everything
 * ========================================================================= */
echo "Running property tests for feature: local-business-website-system\n";

test_property_1();
test_property_2();
test_property_4();
test_property_5();
test_property_14();
test_property_17();

/* Clean up the throwaway upload directory. */
if (isset($TMP_UPLOAD_DIR) && is_dir($TMP_UPLOAD_DIR)) {
    @rmdir($TMP_UPLOAD_DIR);
}

$passed = $GLOBALS['__tests_passed'];
$failed = $GLOBALS['__tests_failed'];

echo "\n---------------------------------------------\n";
echo "Assertions passed: {$passed}\n";
echo "Assertions failed: {$failed}\n";

if ($failed > 0) {
    echo "RESULT: FAIL\n";
    exit(1);
}

echo "RESULT: PASS\n";
exit(0);
