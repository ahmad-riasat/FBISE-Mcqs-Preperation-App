<?php
declare(strict_types=1);
require __DIR__ . '/../../src/auth.php';

require_method('GET');
$user = require_login();

$stmt = db()->prepare(
    'SELECT a.id, a.score, a.total, a.submitted_at, a.auto_submitted, a.note,
            e.class_name, e.subject
     FROM attempts a
     JOIN exams e ON e.id = a.exam_id
     WHERE a.user_id = :user_id
     ORDER BY a.submitted_at DESC, a.id DESC'
);
$stmt->execute([':user_id' => (int)$user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$attempts = [];
foreach ($rows as $row) {
    $attempts[] = [
        'id' => (int)$row['id'],
        'class_name' => $row['class_name'],
        'subject' => $row['subject'],
        'score' => (int)$row['score'],
        'total' => (int)$row['total'],
        'submitted_at' => (int)$row['submitted_at'],
        'auto_submitted' => (int)$row['auto_submitted'],
        'note' => $row['note'] ?? ''
    ];
}

respond(200, [
    'ok' => true,
    'attempts' => $attempts
]);
