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

// Enforce ownership: only delete if both id AND user_id match
$stmt = db()->prepare(
    'DELETE FROM attempts
     WHERE id = :id AND user_id = :user_id'
);
$stmt->execute([
    ':id' => $attemptId,
    ':user_id' => (int)$user['id']
]);

if ($stmt->rowCount() === 0) {
    respond(404, ['ok' => false, 'error' => 'Attempt not found or access denied.']);
}

respond(200, ['ok' => true]);