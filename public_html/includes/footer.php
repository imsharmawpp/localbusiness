<?php
/**
 * includes/footer.php
 *
 * Footer_Component — the shared footer markup plus closing scripts, included at
 * the bottom of every public page (it closes the <body>/<html> opened by
 * includes/header.php). Per the request lifecycle in design.md it renders:
 *
 *   - the clinic address from setting('address')                     (Req 4.3)
 *   - opening hours from the `hours` JSON setting, with a computed
 *     open/closed badge produced by is_open(setting('hours'))        (Req 15.4)
 *   - social links (facebook, instagram) when configured             (Req 4.3)
 *   - a Google Business Profile link, rendered ONLY when the
 *     `google_business_url` setting is non-empty                      (Req 4.4)
 *   - a copyright line carrying the site name and current year
 *
 * The floating WhatsApp action is emitted by includes/header.php (Req 4.2), so
 * it is intentionally NOT duplicated here. All dynamic output is escaped with
 * e(). The page finishes by loading /assets/js/main.js with `defer`.
 *
 * Requirements: 4.3, 4.4, 15.4.
 */

declare(strict_types=1);

// Safety nets: a page should already have loaded these, but guard so the footer
// is robust if included directly.
if (!function_exists('setting')) {
    require_once __DIR__ . '/settings.php';
}
if (!function_exists('is_open')) {
    require_once __DIR__ . '/functions.php';
}

// --- Footer data ------------------------------------------------------------
$footer_site_name = setting('site_name');
$footer_address   = setting('address');
$footer_hours     = setting('hours');
$footer_facebook  = setting('facebook');
$footer_instagram = setting('instagram');
$footer_gbp_url   = setting('google_business_url');
$footer_year      = (int) date('Y');

// Computed open/closed badge from the hours JSON (Req 15.4).
$footer_state       = is_open($footer_hours);
$footer_is_open     = !empty($footer_state['open']);
$footer_badge_class = $footer_is_open ? 'open-badge--open' : 'open-badge--closed';
$footer_badge_label = (string) ($footer_state['label'] ?? ($footer_is_open ? 'Open now' : 'Closed'));

// Parse the weekday -> intervals map for a readable hours list.
$footer_day_labels = [
    'mon' => 'Monday',
    'tue' => 'Tuesday',
    'wed' => 'Wednesday',
    'thu' => 'Thursday',
    'fri' => 'Friday',
    'sat' => 'Saturday',
    'sun' => 'Sunday',
];
$footer_hours_map = json_decode($footer_hours, true);
if (!is_array($footer_hours_map)) {
    $footer_hours_map = [];
}

/**
 * Format a day's intervals (e.g. [["09:00","13:00"],["14:00","18:00"]]) into a
 * human-readable string, or "Closed" when there are no intervals.
 */
$footer_format_intervals = static function ($intervals): string {
    if (!is_array($intervals) || $intervals === []) {
        return 'Closed';
    }
    $parts = [];
    foreach ($intervals as $iv) {
        if (is_array($iv) && isset($iv[0], $iv[1])) {
            $parts[] = (string) $iv[0] . '&ndash;' . (string) $iv[1];
        }
    }
    return $parts === [] ? 'Closed' : implode(', ', $parts);
};
?>
    </main>

    <footer class="site-footer">
      <div class="container">
        <div class="site-footer__grid">

          <div class="footer-col">
            <h4>Visit us</h4>
            <?php if ($footer_address !== ''): ?>
              <p><?= e($footer_address) ?></p>
            <?php endif; ?>
          </div>

          <div class="footer-col">
            <h4>Opening hours</h4>
            <p>
              <span class="open-badge <?= e($footer_badge_class) ?>"><?= e($footer_badge_label) ?></span>
            </p>
            <?php if ($footer_hours_map !== []): ?>
              <dl class="footer-hours">
                <?php foreach ($footer_day_labels as $footer_day_key => $footer_day_name): ?>
                  <?php $footer_day_value = $footer_format_intervals($footer_hours_map[$footer_day_key] ?? []); ?>
                  <div class="footer-hours__row">
                    <dt><?= e($footer_day_name) ?></dt>
                    <dd><?= $footer_day_value === 'Closed' ? e('Closed') : $footer_day_value ?></dd>
                  </div>
                <?php endforeach; ?>
              </dl>
            <?php endif; ?>
          </div>

          <div class="footer-col">
            <h4>Connect</h4>
            <?php if ($footer_facebook !== '' || $footer_instagram !== ''): ?>
              <div class="footer-social">
                <?php if ($footer_facebook !== ''): ?>
                  <a href="<?= e($footer_facebook) ?>" aria-label="Facebook" rel="noopener noreferrer" target="_blank">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="currentColor">
                      <path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z"/>
                    </svg>
                  </a>
                <?php endif; ?>
                <?php if ($footer_instagram !== ''): ?>
                  <a href="<?= e($footer_instagram) ?>" aria-label="Instagram" rel="noopener noreferrer" target="_blank">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="currentColor">
                      <path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.72 3.72 0 0 1-1.38-.9 3.72 3.72 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16Zm0 1.8c-3.15 0-3.52.01-4.76.07-.9.04-1.39.19-1.71.32-.43.17-.74.37-1.06.69-.32.32-.52.63-.69 1.06-.13.32-.28.81-.32 1.71-.06 1.24-.07 1.61-.07 4.76s.01 3.52.07 4.76c.04.9.19 1.39.32 1.71.17.43.37.74.69 1.06.32.32.63.52 1.06.69.32.13.81.28 1.71.32 1.24.06 1.61.07 4.76.07s3.52-.01 4.76-.07c.9-.04 1.39-.19 1.71-.32.43-.17.74-.37 1.06-.69.32-.32.52-.63.69-1.06.13-.32.28-.81.32-1.71.06-1.24.07-1.61.07-4.76s-.01-3.52-.07-4.76c-.04-.9-.19-1.39-.32-1.71a2.86 2.86 0 0 0-.69-1.06 2.86 2.86 0 0 0-1.06-.69c-.32-.13-.81-.28-1.71-.32-1.24-.06-1.61-.07-4.76-.07Zm0 3.06a4.98 4.98 0 1 1 0 9.96 4.98 4.98 0 0 1 0-9.96Zm0 1.8a3.18 3.18 0 1 0 0 6.36 3.18 3.18 0 0 0 0-6.36Zm6.34-2a1.16 1.16 0 1 1-2.32 0 1.16 1.16 0 0 1 2.32 0Z"/>
                    </svg>
                  </a>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($footer_gbp_url !== ''): ?>
              <a class="footer-gbp" href="<?= e($footer_gbp_url) ?>" rel="noopener noreferrer" target="_blank">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="currentColor">
                  <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z"/>
                </svg>
                <span>View us on Google</span>
              </a>
            <?php endif; ?>
          </div>

        </div>

        <div class="site-footer__bottom">
          <p>&copy; <?= e((string) $footer_year) ?> <?= e($footer_site_name) ?>. All rights reserved.</p>
        </div>
      </div>
    </footer>

    <script src="/assets/js/main.js" defer></script>
  </body>
</html>
