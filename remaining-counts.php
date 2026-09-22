<?php
/**
 * JSON: live remaining capacity per box. Polled by the order form.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/boxes.php';

header('Content-Type: application/json');
// Must not be cached by SiteGround Dynamic Cache / CDN / browsers — this is
// polled live by the order form. A stale hit made Set B show "17 left" while
// admin/SQL correctly showed 0 remaining.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $out = [];
    foreach (boxes_with_remaining($pdo) as $b) {
        $out[$b['code']] = [
            'remaining' => (int) $b['remaining'],
            'sold_out'  => (bool) $b['sold_out'],
        ];
    }
    echo json_encode([
        'ok'            => true,
        'open'          => ordering_is_open($pdo),
        'checkout_open' => ordering_accepts_checkout($pdo),
        'boxes'         => $out,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not load availability.']);
}
