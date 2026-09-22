<?php
/**
 * One-time-use ordering links for staff_order.php. Each token is tied to one
 * staff.csv row and can back exactly one order — see staff_token_consume().
 */
require_once __DIR__ . '/boxes.php';

/** Thrown by create_staff_order() when the token is missing/already used. */
class StaffTokenUsedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This staff order link is invalid or has already been used.');
    }
}

function staff_token_generate(): string
{
    return bin2hex(random_bytes(24));
}

function staff_token_create(PDO $pdo, string $first, string $last, string $email): string
{
    $token = staff_token_generate();
    $pdo->prepare(
        'INSERT INTO ' . DOLOS_TBL_STAFF_TOKENS . '
            (token, first_name, last_name, email, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    )->execute([$token, $first, $last, $email]);
    return $token;
}

function staff_token_lookup(PDO $pdo, string $token): ?array
{
    if ($token === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM ' . DOLOS_TBL_STAFF_TOKENS . ' WHERE token = ?');
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** An existing, not-yet-used token for this email, if any (so re-running the invite script is safe). */
function staff_token_find_unused_for_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . DOLOS_TBL_STAFF_TOKENS . ' WHERE email = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Lock and re-verify an unused token inside an open transaction. Call this
 * FIRST, before create_staff_order(), so the row lock serializes concurrent
 * submits of the same link — a second request blocks until the first
 * transaction commits, then correctly sees used_at is no longer NULL.
 * Returns null if the token doesn't exist or was already used.
 */
function staff_token_lock_unused(PDO $pdo, string $token): ?array
{
    if ($token === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . DOLOS_TBL_STAFF_TOKENS . ' WHERE token = ? AND used_at IS NULL FOR UPDATE'
    );
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Mark a locked token used and link it to the order it produced. */
function staff_token_mark_used(PDO $pdo, int $id, int $orderId): void
{
    $pdo->prepare(
        'UPDATE ' . DOLOS_TBL_STAFF_TOKENS . ' SET used_at = NOW(), order_id = ? WHERE id = ?'
    )->execute([$orderId, $id]);
}
