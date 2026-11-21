<?php
declare(strict_types=1);
require __DIR__ . '/../../src/auth.php';

require_method('POST');
$user = require_login();
require_csrf();

$body = read_json_body();

$attemptId = filter_var($body['attempt_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if ($attemptId === false || $attemptId === null) {
    respond(422, ['ok' => false, 'error' => 'A valid attempt_id is required.']);
}

if (!isset($body['note']) || !is_string($body['note'])) {
    respond(422, ['ok' => false, 'error' => 'Note must be a string.']);
}

$note = trim($body['note']);
if (mb_strlen($note) > 250) {
    respond(422, ['ok' => false, 'error' => 'Note cannot exceed 250 characters.']);
}

$pdo = db();

// 1. Verify the attempt exists AND belongs to the logged-in user
//    (Checking SELECT first distinguishes "not found / not yours" from "saved with identical text")
$checkStmt = $pdo->prepare(
    'SELECT id FROM attempts WHERE id = :id AND user_id = :user_id'
);
$checkStmt->execute([
    ':id' => $attemptId,
    ':user_id' => (int)$user['id']
]);

if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
    respond(404, ['ok' => false, 'error' => 'Attempt not found or access denied.']);
}

// 2. Update the note using a prepared statement
$updateStmt = $pdo->prepare(
    'UPDATE attempts
     SET note = :note
     WHERE id = :id AND user_id = :user_id'
);
$updateStmt->execute([
    ':note' => $note === '' ? null : $note,
    ':id' => $attemptId,
    ':user_id' => (int)$user['id']
]);

respond(200, [
    'ok' => true,
    'attempt_id' => $attemptId,
    'note' => $note
]);