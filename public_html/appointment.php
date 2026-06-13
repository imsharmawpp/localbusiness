<?php
/**
 * appointment.php
 *
 * Appointment_Form — the public appointment-request page with an atomic
 * dual-write into `appointments` + `leads` (Req 13.1-13.6, 6.1, 6.2,
 * Assumption 2).
 *
 * Request lifecycle (per design.md): load the shared engine (config, db,
 * functions, settings), handle any POST BEFORE including the header so a
 * successful submission can Post/Redirect/Get cleanly, then render the form
 * (or a confirmation flash) using the main.css design-system classes, and
 * finally include the shared footer.
 *
 * Behaviour:
 *   - The form collects patient_name, phone, email, service (a dropdown of
 *     active services ordered by sort_order, plus a "no preference" option),
 *     preferred_date (date input), preferred_slot (a dropdown of fixed slots),
 *     and notes (Req 13.1, 13.2).
 *   - On POST: csrf_verify() runs first; a missing/mismatched token yields a
 *     400 with no data change (Req 6.1, 6.2). Required fields are patient_name,
 *     phone, preferred_date, and preferred_slot (email is optional). Any missing
 *     or invalid required field re-renders the form with a field-specific error
 *     and performs NO database writes (Req 13.4).
 *   - service_id is validated against the set of active services with a
 *     prepared statement; the "no preference" choice stores NULL (allowed by
 *     the schema's nullable service_id).
 *   - On a valid submission a single transaction inserts one `appointments`
 *     row (status 'new') and one corresponding `leads` row
 *     (source_page='appointment'); both succeed or neither does (Req 13.3,
 *     13.5, Assumption 2). Success flashes a confirmation and PRG-redirects
 *     (Req 13.6).
 *   - Every query uses prepared statements (Req 6.4) and every dynamic value is
 *     escaped through e() (Req 6.5); the form carries a csrf_token() hidden
 *     field (Req 6.1).
 *
 * Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 6.1, 6.2.
 */

declare(strict_types=1);

// --- Shared engine (request lifecycle order) --------------------------------
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/settings.php';

// The fixed set of bookable time slots offered by the dropdown (Req 13.2,
// Assumption 3 — no live availability). A submitted slot must be one of these.
const APPOINTMENT_SLOTS = [
    '09:00-10:00',
    '10:00-11:00',
    '11:00-12:00',
    '12:00-13:00',
    '14:00-15:00',
    '15:00-16:00',
    '16:00-17:00',
    '17:00-18:00',
];

/**
 * Validate a "YYYY-MM-DD" date string and confirm it is a real calendar date.
 *
 * @param string $value Raw submitted date.
 * @return bool         True when the value is a valid Y-m-d date.
 */
function appointment_valid_date(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
}

// Field values preserved across a failed submission so the visitor does not
// lose their input when the form re-renders.
$old = [
    'patient_name' => '',
    'phone'        => '',
    'email'        => '',
    'service_id'   => '',
    'preferred_date' => '',
    'preferred_slot' => '',
    'notes'        => '',
];
$errors = [];

// --------------------------------------------------------------------------
// POST: validate -> atomic dual-write -> PRG (handled before header.php).
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF first: a missing/mismatched token is rejected with no data change
    // (Req 6.1, 6.2, 6.3).
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad request');
    }

    // Normalise input. Required fields are trimmed; the optional fields keep
    // their trimmed form too for a tidy stored record.
    $old['patient_name']   = trim((string) ($_POST['patient_name'] ?? ''));
    $old['phone']          = trim((string) ($_POST['phone'] ?? ''));
    $old['email']          = trim((string) ($_POST['email'] ?? ''));
    $old['service_id']     = trim((string) ($_POST['service_id'] ?? ''));
    $old['preferred_date'] = trim((string) ($_POST['preferred_date'] ?? ''));
    $old['preferred_slot'] = trim((string) ($_POST['preferred_slot'] ?? ''));
    $old['notes']          = trim((string) ($_POST['notes'] ?? ''));

    // Required-field validation (Req 13.4): patient_name, phone, preferred_date,
    // preferred_slot. email is optional.
    if ($old['patient_name'] === '') {
        $errors['patient_name'] = 'Please enter your name.';
    }
    if ($old['phone'] === '') {
        $errors['phone'] = 'Please enter a phone number we can reach you on.';
    }
    if ($old['preferred_date'] === '') {
        $errors['preferred_date'] = 'Please choose a preferred date.';
    } elseif (!appointment_valid_date($old['preferred_date'])) {
        $errors['preferred_date'] = 'Please choose a valid date.';
    }
    if ($old['preferred_slot'] === '') {
        $errors['preferred_slot'] = 'Please choose a preferred time slot.';
    } elseif (!in_array($old['preferred_slot'], APPOINTMENT_SLOTS, true)) {
        $errors['preferred_slot'] = 'Please choose a valid time slot.';
    }

    // Optional email: only validated when provided.
    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address, or leave it blank.';
    }

    // Service is optional ("no preference" => NULL). When a service is chosen,
    // confirm it exists among the active services with a prepared statement so
    // a tampered/stale id can never be stored (Req 6.4). $serviceId / $serviceName
    // are resolved here for the insert and the lead summary.
    $serviceId   = null;
    $serviceName = '';
    if ($old['service_id'] !== '') {
        if (!ctype_digit($old['service_id'])) {
            $errors['service_id'] = 'Please choose a valid service.';
        } else {
            $check = db()->prepare('SELECT id, name FROM services WHERE id = ? AND is_active = 1');
            $check->execute([(int) $old['service_id']]);
            $svc = $check->fetch();
            if ($svc === false) {
                $errors['service_id'] = 'Please choose a valid service.';
            } else {
                $serviceId   = (int) $svc['id'];
                $serviceName = (string) $svc['name'];
            }
        }
    }

    // Only write when every validation passed. Otherwise fall through to
    // re-render the form with field-specific errors and NO database writes
    // (Req 13.4).
    if ($errors === []) {
        // Compose a readable lead message that captures the booking intent so
        // the enquiry is actionable directly from the Leads inbox (Assumption 2).
        $summaryParts = [];
        $summaryParts[] = 'Appointment request via website.';
        $summaryParts[] = 'Service: ' . ($serviceName !== '' ? $serviceName : 'No preference');
        $summaryParts[] = 'Preferred date: ' . $old['preferred_date'];
        $summaryParts[] = 'Preferred slot: ' . $old['preferred_slot'];
        if ($old['notes'] !== '') {
            $summaryParts[] = 'Notes: ' . $old['notes'];
        }
        $leadMessage = implode("\n", $summaryParts);

        $pdo = db();
        $pdo->beginTransaction();
        try {
            // 1) The appointment row, status 'new' (Req 13.3, 13.5).
            $insAppt = $pdo->prepare(
                'INSERT INTO appointments
                    (patient_name, phone, email, service_id, preferred_date, preferred_slot, notes, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insAppt->execute([
                $old['patient_name'],
                $old['phone'],
                $old['email'] !== '' ? $old['email'] : null,
                $serviceId,
                $old['preferred_date'],
                $old['preferred_slot'],
                $old['notes'] !== '' ? $old['notes'] : null,
                'new',
            ]);

            // 2) The corresponding lead, source_page='appointment' (Req 13.3,
            //    Assumption 2) — so every enquiry surfaces in the Leads inbox.
            $insLead = $pdo->prepare(
                'INSERT INTO leads (name, email, phone, message, source_page)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $insLead->execute([
                $old['patient_name'],
                $old['email'] !== '' ? $old['email'] : null,
                $old['phone'],
                $leadMessage,
                'appointment',
            ]);

            $pdo->commit();
        } catch (\Throwable $ex) {
            // Atomicity: if either insert fails, neither row persists.
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[appointment] dual-write failed: ' . $ex->getMessage());
            $errors['form'] = 'Sorry, something went wrong submitting your request. Please try again.';
        }

        if ($errors === []) {
            // PRG: flash a confirmation and redirect so a refresh does not
            // re-submit the booking (Req 13.6).
            flash('Thank you! Your appointment request has been received. We will contact you shortly to confirm.');
            header('Location: /appointment');
            exit;
        }
    }
}

// --------------------------------------------------------------------------
// GET (and re-render after a failed POST): build the page.
// --------------------------------------------------------------------------
$csrf    = csrf_token();
$success = flash(); // read-once confirmation set by a successful PRG redirect

// Active services for the dropdown, ordered by sort_order (Req 13.2).
$svcStmt = db()->prepare(
    'SELECT id, name FROM services WHERE is_active = 1 ORDER BY sort_order ASC, name ASC'
);
$svcStmt->execute();
$services = $svcStmt->fetchAll();

// --- Per-page SEO -----------------------------------------------------------
$site_name  = setting('site_name');
$page_title = 'Book an Appointment' . ($site_name !== '' ? ' | ' . $site_name : '');
$page_desc  = 'Request an appointment online'
    . ($site_name !== '' ? ' with ' . $site_name : '')
    . '. Choose a service, preferred date and time, and we will contact you to confirm.';

require __DIR__ . '/includes/header.php';
?>
  <section class="section">
    <div class="container">

      <div class="section__head">
        <span class="eyebrow">Book a visit</span>
        <h1>Request an Appointment</h1>
        <p>Tell us a little about what you need and your preferred time. We'll be
           in touch to confirm your appointment.</p>
      </div>

<?php if ($success !== null): ?>
      <p class="flash flash--success" role="status"><?= e($success) ?></p>
<?php endif; ?>

<?php if (isset($errors['form'])): ?>
      <p class="flash flash--error" role="alert"><?= e($errors['form']) ?></p>
<?php endif; ?>

      <form method="post" action="/appointment" novalidate>
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

        <div class="form-field<?= isset($errors['patient_name']) ? ' form-field--invalid' : '' ?>">
          <label for="patient_name">Your name <span aria-hidden="true">*</span></label>
          <input type="text" id="patient_name" name="patient_name" class="form-control"
                 required value="<?= e($old['patient_name']) ?>"
                 <?= isset($errors['patient_name']) ? 'aria-invalid="true" aria-describedby="err-patient_name"' : '' ?>>
<?php if (isset($errors['patient_name'])): ?>
          <p class="form-error" id="err-patient_name"><?= e($errors['patient_name']) ?></p>
<?php endif; ?>
        </div>

        <div class="form-field<?= isset($errors['phone']) ? ' form-field--invalid' : '' ?>">
          <label for="phone">Phone <span aria-hidden="true">*</span></label>
          <input type="tel" id="phone" name="phone" class="form-control"
                 required value="<?= e($old['phone']) ?>"
                 <?= isset($errors['phone']) ? 'aria-invalid="true" aria-describedby="err-phone"' : '' ?>>
<?php if (isset($errors['phone'])): ?>
          <p class="form-error" id="err-phone"><?= e($errors['phone']) ?></p>
<?php endif; ?>
        </div>

        <div class="form-field<?= isset($errors['email']) ? ' form-field--invalid' : '' ?>">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" class="form-control"
                 value="<?= e($old['email']) ?>"
                 <?= isset($errors['email']) ? 'aria-invalid="true" aria-describedby="err-email"' : '' ?>>
<?php if (isset($errors['email'])): ?>
          <p class="form-error" id="err-email"><?= e($errors['email']) ?></p>
<?php endif; ?>
        </div>

        <div class="form-field<?= isset($errors['service_id']) ? ' form-field--invalid' : '' ?>">
          <label for="service_id">Service</label>
          <select id="service_id" name="service_id" class="form-control"
                  <?= isset($errors['service_id']) ? 'aria-invalid="true" aria-describedby="err-service_id"' : '' ?>>
            <option value=""<?= $old['service_id'] === '' ? ' selected' : '' ?>>No preference</option>
<?php foreach ($services as $service): ?>
            <option value="<?= (int) $service['id'] ?>"<?= $old['service_id'] === (string) $service['id'] ? ' selected' : '' ?>>
              <?= e((string) $service['name']) ?>
            </option>
<?php endforeach; ?>
          </select>
<?php if (isset($errors['service_id'])): ?>
          <p class="form-error" id="err-service_id"><?= e($errors['service_id']) ?></p>
<?php endif; ?>
        </div>

        <div class="form-field<?= isset($errors['preferred_date']) ? ' form-field--invalid' : '' ?>">
          <label for="preferred_date">Preferred date <span aria-hidden="true">*</span></label>
          <input type="date" id="preferred_date" name="preferred_date" class="form-control"
                 required value="<?= e($old['preferred_date']) ?>"
                 <?= isset($errors['preferred_date']) ? 'aria-invalid="true" aria-describedby="err-preferred_date"' : '' ?>>
<?php if (isset($errors['preferred_date'])): ?>
          <p class="form-error" id="err-preferred_date"><?= e($errors['preferred_date']) ?></p>
<?php endif; ?>
        </div>

        <div class="form-field<?= isset($errors['preferred_slot']) ? ' form-field--invalid' : '' ?>">
          <label for="preferred_slot">Preferred time slot <span aria-hidden="true">*</span></label>
          <select id="preferred_slot" name="preferred_slot" class="form-control"
                  required
                  <?= isset($errors['preferred_slot']) ? 'aria-invalid="true" aria-describedby="err-preferred_slot"' : '' ?>>
            <option value=""<?= $old['preferred_slot'] === '' ? ' selected' : '' ?>>Select a time slot</option>
<?php foreach (APPOINTMENT_SLOTS as $slot): ?>
            <option value="<?= e($slot) ?>"<?= $old['preferred_slot'] === $slot ? ' selected' : '' ?>><?= e($slot) ?></option>
<?php endforeach; ?>
          </select>
<?php if (isset($errors['preferred_slot'])): ?>
          <p class="form-error" id="err-preferred_slot"><?= e($errors['preferred_slot']) ?></p>
<?php endif; ?>
        </div>

        <div class="form-field">
          <label for="notes">Notes</label>
          <textarea id="notes" name="notes" class="form-control" rows="4"
                    placeholder="Anything we should know before your visit?"><?= e($old['notes']) ?></textarea>
        </div>

        <button type="submit" class="btn btn--primary">Request Appointment</button>
      </form>

    </div>
  </section>
<?php
require __DIR__ . '/includes/footer.php';
