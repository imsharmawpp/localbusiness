<?php
/**
 * includes/settings.php
 *
 * Settings_Loader — loads every row of the site_settings table into the global
 * $SETTINGS array keyed by setting_key (Req 3.1) and exposes a setting()
 * accessor that returns '' for absent keys so templates never trigger
 * undefined-index notices (Req 3.3).
 *
 * The following keys are expected to be available (Req 3.2):
 *   site_name, tagline, phone, whatsapp, email, address, map_embed, hours,
 *   logo, primary_color, facebook, instagram, google_business_url, meta_default,
 *   plus the added schema_type (default resolved to 'Dentist' at render time).
 *
 * Pages require includes/config.php and includes/db.php before this file (see
 * design.md request lifecycle). The db.php require below is a safety net.
 *
 * Requirements: 3.1, 3.2, 3.3.
 */

declare(strict_types=1);

if (!function_exists('db')) {
    require_once __DIR__ . '/db.php';
}

/**
 * @var array<string,string> $SETTINGS Site settings keyed by setting_key.
 */
$SETTINGS = [];
foreach (db()->query('SELECT setting_key, setting_value FROM site_settings') as $row) {
    $SETTINGS[$row['setting_key']] = $row['setting_value'];
}

if (!function_exists('setting')) {
    /**
     * Return a site setting value, or '' when the key is absent (Req 3.2, 3.3).
     *
     * @param string $key The setting_key to look up.
     * @return string      The stored value, or '' if the key is not present.
     */
    function setting(string $key): string
    {
        global $SETTINGS;
        return isset($SETTINGS[$key]) ? (string) $SETTINGS[$key] : '';
    }
}
