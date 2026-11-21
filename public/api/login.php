<?php
declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';

require_method('POST');
require_csrf();

// 1. Read JSON body
$body     = read_json_body();
$roll     = trim((string)($body['roll_number'] ?? ''));
$password = (string)($body['password'] ?? '');


// 2. Validate input
$errors = [];

if (!preg_match('/^[0-9]{6}$/', $roll)) {
    $errors['roll_number'] = 'Roll number must contain exactly six digits.';
}

if ($password === '') {
    $errors['password'] = 'Password is required.';
} elseif (strlen($password) > 72) {
    $errors['password'] = 'Password cannot exceed 72 characters.';
}


// 3. Return validation errors
if (!empty($errors)) {
    respond(422, [
        'ok' => false,
        'error' => 'Please fix the highlighted fields.',
        'fields' => $errors
    ]);
}


// 4. Find user using prepared statement
$stmt = db()->prepare(
    'SELECT id, full_name, roll_number, role, password_hash
     FROM users
     WHERE roll_number = :roll'
);

$stmt->execute([':roll' => $roll]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);


// 5. Verify user and password
if (!$user || !password_verify($password, $user['password_hash'])) {
    respond(401, [
        'ok' => false,
        'error' => 'Roll number or password is incorrect.'
    ]);
}


// 6. Login user
login_user((int)$user['id']);


// 7. Send successful response
respond(200, [
    'ok' => true,
    'user' => [
        'id' => (int)$user['id'],
        'full_name' => $user['full_name'],
        'roll_number' => $user['roll_number'],
        'role' => $user['role']
    ],
    'csrf_token' => csrf_token()
]);
