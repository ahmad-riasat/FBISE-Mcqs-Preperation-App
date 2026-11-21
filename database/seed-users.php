<?php
declare(strict_types=1);
require __DIR__ . '/../src/db.php';

$demoPassword = 'Demo@1234';        // fake demo password, documented in README

$users = [
    ['full_name' => 'Ahmad',        'roll_number' => '100001', 'role' => 'student'],
    ['full_name' => 'Ali',          'roll_number' => '100002', 'role' => 'student'],
    ['full_name' => 'Hammad',       'roll_number' => '100003', 'role' => 'student'],
    ['full_name' => 'Abdullah',     'roll_number' => '100004', 'role' => 'student'],
    ['full_name' => 'Zoya',         'roll_number' => '100005', 'role' => 'student'],
    ['full_name' => 'Zoha',         'roll_number' => '100006', 'role' => 'student'],
    ['full_name' => 'Demo Teacher', 'roll_number' => '000000', 'role' => 'teacher'],
];

$pdo = db();
$insert = $pdo->prepare(
    'INSERT INTO users (full_name, roll_number, password_hash, role)
     VALUES (:full_name, :roll_number, :password_hash, :role)'
);

$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM users');
    foreach ($users as $u) {
        $insert->execute([
            ':full_name'     => $u['full_name'],
            ':roll_number'   => $u['roll_number'],
            ':password_hash' => password_hash($demoPassword, PASSWORD_DEFAULT),
            ':role'          => $u['role'],
        ]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    exit("Seeding users failed: " . $e->getMessage() . "\n");
}

echo "Seeded " . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . " users.\n";