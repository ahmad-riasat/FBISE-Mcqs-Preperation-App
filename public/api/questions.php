<?php
declare(strict_types=1);
require __DIR__ . '/../../src/auth.php';

require_method('GET');
require_login();

$examId = filter_input(INPUT_GET, 'exam_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if ($examId === false || $examId === null) {
    respond(422, [
        'ok' => false,
        'error' => 'A valid exam_id is required.'
    ]);
}

$pdo = db();

// 1. Fetch exam metadata
$examStmt = $pdo->prepare(
    'SELECT id, class_name, subject, duration_seconds
     FROM exams
     WHERE id = :id'
);
$examStmt->execute([':id' => $examId]);
$exam = $examStmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    respond(404, [
        'ok' => false,
        'error' => 'Exam not found.'
    ]);
}

// 2. Fetch questions WITHOUT correct_option or explanation
$qStmt = $pdo->prepare(
    'SELECT id, question_text, option_a, option_b, option_c, option_d
     FROM questions
     WHERE exam_id = :exam_id
     ORDER BY id ASC'
);
$qStmt->execute([':exam_id' => $examId]);
$rows = $qStmt->fetchAll(PDO::FETCH_ASSOC);

$questions = [];
foreach ($rows as $row) {
    $questions[] = [
        'id' => (int)$row['id'],
        'question' => $row['question_text'],
        'options' => [
            $row['option_a'],
            $row['option_b'],
            $row['option_c'],
            $row['option_d']
        ]
    ];
}

respond(200, [
    'ok' => true,
    'exam' => [
        'id' => (int)$exam['id'],
        'class_name' => $exam['class_name'],
        'subject' => $exam['subject'],
        'duration_seconds' => (int)$exam['duration_seconds']
    ],
    'questions' => $questions
]);