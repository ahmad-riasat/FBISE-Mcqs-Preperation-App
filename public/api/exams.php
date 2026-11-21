<?php
declare(strict_types=1);
require __DIR__ . '/../../src/auth.php';

require_method('GET');
require_login();

$className = trim((string)($_GET['class'] ?? ''));
$allowedClasses = ['Class 9', 'Class 10', 'Class 11', 'Class 12'];

if (!in_array($className, $allowedClasses, true)) {
    respond(422, [
        'ok' => false,
        'error' => 'Invalid class selected.'
    ]);
}

$stmt = db()->prepare(
    'SELECT e.id, e.class_name, e.subject, e.duration_seconds, COUNT(q.id) AS question_count
     FROM exams e
     LEFT JOIN questions q ON q.exam_id = e.id
     WHERE e.class_name = :class_name
     GROUP BY e.id, e.class_name, e.subject, e.duration_seconds
     ORDER BY e.id ASC'
);
$stmt->execute([':class_name' => $className]);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cast numeric columns to integers for clean JSON
foreach ($exams as &$exam) {
    $exam['id'] = (int)$exam['id'];
    $exam['duration_seconds'] = (int)$exam['duration_seconds'];
    $exam['question_count'] = (int)$exam['question_count'];
}
unset($exam);

respond(200, [
    'ok' => true,
    'exams' => $exams
]);