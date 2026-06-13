<?php
/**
 * contact.php
 *
 * Healthcare Contact page (Contact_Form component). Renders the clinic contact
 * details and a lead-capture form inside the shared header/footer chrome, and
 * processes submissions into the `leads` table.
 *
 * Behaviour (Req 14.1, 14.2, 14.3, 20.5, 6.1, 6.2):
 *   - Renders contact info entirely from settings: address, click-to-call
 *     phone, mailto email, opening hours with an open/closed badge from
 *     is_open(setting('hours')), the admin-managed map_embed iframe, and a
 *     Google Business Profile link from setting('google_business_url') rendered
 *     only when that setting is non-empty (Req 20.5).
 *   - The contact form collects name, email, phone, and message.
 *   - POST handling runs BEFORE any output (Post/Redirect/Get): csrf_verify()
 *     first — an invalid/absent token yields HTTP 400 (Req 6.1, 6.2). Required
 *     fields are name, a contact method (email OR phone), and message; a missing
 *     required field re-renders the form with a field-specific validation error
 *     and performs NO insert (Req 14.2).
 *   - On a valid submission a single prepared INSERT writes the lead with
 *     source_page = 'contact' (Req 14.1), then the request PRG-redirects back to
 *     this page with a confirmation flash (Req 14.3).
 *
 * Per the design.md request lifecycle the page requires config/db/functions/
 * settings, handles POST before including header.php, then renders. Every DB
 * write uses a prepared statement (Req 6.4) and every dynamic value is escaped
 * with e() (Req 6.5); the admin-managed map embed is the single intentional
 * exception because it is raw iframe markup.
 *
 * Requirements: 14.1, 14.2, 14.3, 20.5, 6.1, 6.2.
 */

declare(strict_types=1);

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/settings.php';

/* --------------------------------------------------------------------------
   POST handling (before any output) — Post/Redirect/Get
   -------------------------------------------------------------------------- */

/** @var array<string,string> $errors  Field-specific validation errors. */
$errors = [];
/** @var array<string,string> $old     Submitted values for re-rendering. */
$old = ['name' => '', 'email' => '', 'phone' => '', 'message' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // CSRF first: an invalid or absent token is rejected with HTTP 400 (Req 6.1, 6.2).
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Invalid or missing security token.');
    }

    // Capture and trim the submitted values for validation + re-rendering.
    $old['name']    = trim((string) ($_POST['name'] ?? ''));
    $old['email']   = trim((string) ($_POST['email'] ?? ''));
    $old['phone']   = trim((string) ($_POST['phone'] ?? ''));
    $old['message'] = trim((string) ($_POST['message'] ?? ''));

    // Required-field validation (Req 14.2). A contact method is required, and we
    // accept either an email OR a phone so visitors can choose how to be reached.
    if ($old['name'] === '') {
        $errors['name'] = 'Please enter your name.';
    }
    if ($old['email'] === '' && $old['phone'] === '') {
        $errors['email'] = 'Please provide an email address or a phone number so we can reply.';
    } elseif ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($old['message'] === '') {
        $errors['message'] = 'Please enter a message.';
    }

    // Only insert when every required field is valid — no insert on error (Req 14.2).
    if ($errors === []) {
        $stmt = db()->prepare(
            'INSERT INTO leads (name, email, phone, message, source_page)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $old['name'],
            $old['email'],
            $old['phone'],
            $old['message'],
            'contact',
        ]);

        // PRG: flash a confirmation and redirect back to GET (Req 14.3).
        flash('Thank you for your message. We will be in touch soon.');
        header('Location: /contact', true, 303);
        exit;
    }
}

/* --------------------------------------------------------------------------
   Contact data from settings
   -------------------------------------------------------------------------- */
$address  = setting('address');
$phone    = setting('phone');
$email    = setting('email');
$mapEmbed = setting('map_embed');
$gbpUrl   = setting('google_business_url');

$openState = is_open(setting('hours'));
$isOpenNow = !empty($openState['open']);
$openClass = $isOpenNow ? 'open-badge--open' : 'open-badge--closed';
$openLabel = (string) ($openState['label'] ?? ($isOpenNow ? 'Open now' : 'Closed'));
$telHref   = $phone !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $phone) : '';

// Read-once confirmation flash for the success banner (Req 14.3).
$flashMsg = flash();

/* --------------------------------------------------------------------------
   SEO variables, then the shared chrome (design.md lifecycle)
   -------------------------------------------------------------------------- */
$page_title = (setting('site_name') !== '' ? setting('site_name') . ' | ' : '') . 'Contact us';
$page_desc  = setting('meta_default') !== ''
    ? setting('meta_default')
    : 'Get in touch with us — find our address, opening hours, phone, and send us a message.';

require __DIR__ . '/includes/header.php';
?>

  <section class="section">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow">Get in touch</span>
        <h1>Contact us</h1>
        <p>Send us a message or visit the clinic — we would love to hear from you.</p>
      </div>

<?php if ($flashMsg !== null): ?>
      <div class="flash flash--success" role="status"><?= e($flashMsg) ?></div>
<?php elseif ($errors !== []): ?>
      <div class="flash flash--error" role="alert">Please correct the highlighted fields and try again.</div>
<?php endif; ?>

      <div class="contact-block">

        <!-- Contact information from settings -->
        <div class="contact-info">
          <p>
            <span class="open-badge <?= e($openClass) ?>"><?= e($openLabel) ?></span>
          </p>

<?php if ($address !== ''): ?>
          <div class="contact-detail">
            <span class="contact-detail__icon" aria-hidden="true">&#128205;</span>
            <span>
              <span class="contact-detail__label">Address</span>
              <span class="contact-detail__value"><?= e($address) ?></span>
            </span>
          </div>
<?php endif; ?>

<?php if ($phone !== ''): ?>
          <div class="contact-detail">
            <span class="contact-detail__icon" aria-hidden="true">&#9742;</span>
            <span>
              <span class="contact-detail__label">Phone</span>
              <span class="contact-detail__value"><a href="<?= e($telHref) ?>"><?= e($phone) ?></a></span>
            </span>
          </div>
<?php endif; ?>

<?php if ($email !== ''): ?>
          <div class="contact-detail">
            <span class="contact-detail__icon" aria-hidden="true">&#9993;</span>
            <span>
              <span class="contact-detail__label">Email</span>
              <span class="contact-detail__value"><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></span>
            </span>
          </div>
<?php endif; ?>

<?php if ($gbpUrl !== ''): /* Google Business Profile link, only when configured (Req 20.5) */ ?>
          <div class="contact-detail">
            <span class="contact-detail__icon" aria-hidden="true">&#11088;</span>
            <span>
              <span class="contact-detail__label">Google Business Profile</span>
              <span class="contact-detail__value">
                <a href="<?= e($gbpUrl) ?>" rel="noopener noreferrer" target="_blank">View us on Google</a>
              </span>
            </span>
          </div>
<?php endif; ?>

<?php if (trim($mapEmbed) !== ''): ?>
          <div class="map-embed">
            <?= $mapEmbed /* admin-managed iframe embed markup; intentionally not escaped */ ?>
          </div>
<?php endif; ?>
        </div>

        <!-- Lead-capture form -->
        <form class="contact-form" method="post" action="/contact" novalidate>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

          <div class="form-field">
            <label for="contact-name">Name <span aria-hidden="true">*</span></label>
            <input type="text" id="contact-name" name="name" class="form-control" required
                   value="<?= e($old['name']) ?>"
                   <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="contact-name-error"' : '' ?>>
<?php if (isset($errors['name'])): ?>
            <p class="form-error" id="contact-name-error"><?= e($errors['name']) ?></p>
<?php endif; ?>
          </div>

          <div class="form-field">
            <label for="contact-email">Email</label>
            <input type="email" id="contact-email" name="email" class="form-control"
                   value="<?= e($old['email']) ?>"
                   <?= isset($errors['email']) ? 'aria-invalid="true" aria-describedby="contact-email-error"' : '' ?>>
<?php if (isset($errors['email'])): ?>
            <p class="form-error" id="contact-email-error"><?= e($errors['email']) ?></p>
<?php endif; ?>
          </div>

          <div class="form-field">
            <label for="contact-phone">Phone</label>
            <input type="tel" id="contact-phone" name="phone" class="form-control"
                   value="<?= e($old['phone']) ?>"
                   <?= isset($errors['phone']) ? 'aria-invalid="true" aria-describedby="contact-phone-error"' : '' ?>>
<?php if (isset($errors['phone'])): ?>
            <p class="form-error" id="contact-phone-error"><?= e($errors['phone']) ?></p>
<?php endif; ?>
          </div>

          <div class="form-field">
            <label for="contact-message">Message <span aria-hidden="true">*</span></label>
            <textarea id="contact-message" name="message" class="form-control" rows="5" required
                      <?= isset($errors['message']) ? 'aria-invalid="true" aria-describedby="contact-message-error"' : '' ?>><?= e($old['message']) ?></textarea>
<?php if (isset($errors['message'])): ?>
            <p class="form-error" id="contact-message-error"><?= e($errors['message']) ?></p>
<?php endif; ?>
          </div>

          <div class="form-field">
            <button type="submit" class="btn btn--primary btn--lg">Send message</button>
          </div>
        </form>

      </div>
    </div>
  </section>

<?php
require __DIR__ . '/includes/footer.php';
