<?php
/**
 * Generate one-time-use staff order links from staff.csv (First,Last,email
 * columns) and email them via send_staff_invite_email().
 *
 * Usage:
 *   php scripts/generate-staff-invites.php                        # dry run: prints recipients + a sample email, no DB writes, no sends
 *   php scripts/generate-staff-invites.php --only=someone@x.org    # dry run limited to one address
 *   php scripts/generate-staff-invites.php --only=someone@x.org --send  # create a token + send a real email to just that address
 *   php scripts/generate-staff-invites.php --send                  # create tokens + send real emails to everyone in staff.csv
 *
 * Re-running is safe: a person who already has an unused token gets that
 * same link resent rather than a new one.
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/logger.php';
require_once dirname(__DIR__) . '/includes/boxes.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_once dirname(__DIR__) . '/includes/staff_tokens.php';

if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}

$send = in_array('--send', $argv, true);
$only = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = strtolower(trim(substr($arg, strlen('--only='))));
    }
}

$csvPath = dirname(__DIR__) . '/staff.csv';
if (!is_file($csvPath)) {
    fwrite(STDERR, "staff.csv not found at {$csvPath}\n");
    exit(1);
}

$recipients = [];
if (($fh = fopen($csvPath, 'r')) !== false) {
    while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        $first = trim((string) ($row[0] ?? ''));
        $last  = trim((string) ($row[1] ?? ''));
        $email = strtolower(trim((string) ($row[2] ?? '')));
        if (strcasecmp($first, 'First') === 0 && strcasecmp($last, 'Last') === 0) {
            continue; // header row
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue; // blank/malformed row
        }
        if ($only !== null && $email !== $only) {
            continue;
        }
        $recipients[$email] = ['first' => $first, 'last' => $last, 'email' => $email];
    }
    fclose($fh);
}

if ($recipients === []) {
    echo "No matching recipients found in staff.csv.\n";
    exit(0);
}

echo ($send ? 'SENDING' : 'DRY RUN') . ' — ' . count($recipients) . " recipient(s):\n";

$sampleShown = false;
foreach ($recipients as $person) {
    $name = trim($person['first'] . ' ' . $person['last']);

    if (!$send) {
        echo "  - {$name} <{$person['email']}>\n";
        if (!$sampleShown) {
            $sampleShown = true;
            $sampleLink = APP_URL . '/staff-order?key=SAMPLE_TOKEN_NOT_SAVED';
            echo "\n--- Sample email (not sent, no token created) ---\n";
            echo "To: {$person['email']}\n";
            echo "Link: {$sampleLink}\n\n";
        }
        continue;
    }

    $existing = staff_token_find_unused_for_email($pdo, $person['email']);
    $token = $existing['token'] ?? staff_token_create($pdo, $person['first'], $person['last'], $person['email']);
    $link  = APP_URL . '/staff-order?key=' . $token;

    $ok = send_staff_invite_email($pdo, $person['email'], $person['first'], $link);
    echo '  - ' . ($ok ? 'sent' : 'FAILED') . " to {$name} <{$person['email']}>\n";
}

if (!$send) {
    echo "\nDry run only — no tokens created, no emails sent. Re-run with --send to commit.\n";
}
