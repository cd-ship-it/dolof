<?php
/**
 * JSON: live remaining capacity per box. Polled by the order form.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/boxes.php';

header('Content-Type: application/json');

try {
    $out = [];
    foreach (boxes_with_remaining($pdo) as $b) {
        $out[$b['code']] = [
            'remaining' => (int) $b['remaining'],
            'sold_out'  => (bool) $b['sold_out'],
        ];
    }
    echo json_encode(['ok' => true, 'open' => ordering_is_open($pdo), 'boxes' => $out]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not load availability.']);
}
