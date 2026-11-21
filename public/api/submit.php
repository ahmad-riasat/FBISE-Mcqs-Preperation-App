<?php
declare(strict_types=1);
require __DIR__ . '/../../src/auth.php';

require_method('POST');
$user = require_login();
require_csrf();

$body = read_json_body();

// 1. Validate exam_id and auto_submitted
$examId = filter_var($body['exam_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if ($examId === false || $examId === null) {
    respond(422, ['ok' => false, 'error' => 'A valid exam_id is required.']);
}

$autoSubmitted = !empty($body['auto_submitted']) ? 1 : 0;
$rawAnswers = $body['answers'] ?? null;

if (!is_array($rawAnswers)) {
    respond(422, ['ok' => false, 'error' => 'Answers must be provided as an object or array.']);
}

$pdo = db();

// 2. Confirm exam exists
$examStmt = $pdo->prepare('SELECT id, class_name, subject FROM exams WHERE id = :id');
$examStmt->execute([':id' => $examId]);
$exam = $examStmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    respond(404, ['ok' => false, 'error' => 'Exam not found.']);
}

// 3. Fetch all questions WITH correct_option and explanation for server-side grading
$qStmt = $pdo->prepare(
    'SELECT id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation
     FROM questions
     WHERE exam_id = :exam_id
     ORDER BY id ASC'
);
$qStmt->execute([':exam_id' => $examId]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    respond(422, ['ok' => false, 'error' => 'This exam has no questions to grade.']);
}

// 4. Normalize submitted answers into a [question_id => chosen_option] map
//    Expects answers as an array of { "question_id": 12, "chosen_option": 2 }
$chosenMap = [];
foreach ($rawAnswers as $entry) {
    if (!is_array($entry)) {
        respond(422, ['ok' => false, 'error' => 'Invalid answer format.']);
    }
    $qId = filter_var($entry['question_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $opt = $entry['chosen_option'] ?? null;

    if ($qId === false || $qId === null) {
        respond(422, ['ok' => false, 'error' => 'Invalid question_id in answers.']);
    }
    if ($opt !== null) {
        if (!is_int($opt) && !(is_string($opt) && ctype_digit($opt))) {
            respond(422, ['ok' => false, 'error' => 'Invalid chosen_option value.']);
        }
        $optInt = (int)$opt;
        if ($optInt < 0 || $optInt > 3) {
            respond(422, ['ok' => false, 'error' => 'chosen_option must be between 0 and 3.']);
        }
        $chosenMap[$qId] = $optInt;
    }
}

// 5. Grade every question of this exam and build the review payload
$score = 0;
$total = count($questions);
$review = [];
$validAnswersToSave = [];

foreach ($questions as $q) {
    $qId = (int)$q['id'];
    $correctOpt = (int)$q['correct_option'];
    $chosenOpt = $chosenMap[$qId] ?? null;
    $isCorrect = ($chosenOpt !== null && $chosenOpt === $correctOpt);

    if ($isCorrect) {
        $score++;
    }

    if ($chosenOpt !== null) {
        $validAnswersToSave[] = [
            'question_id' => $qId,
            'chosen_option' => $chosenOpt
        ];
    }

    $review[] = [
        'question_id' => $qId,
        'question' => $q['question_text'],
        'options' => [$q['option_a'], $q['option_b'], $q['option_c'], $q['option_d']],
        'chosen_option' => $chosenOpt,
        'correct_option' => $correctOpt,
        'is_correct' => $isCorrect,
        'explanation' => $q['explanation']
    ];
}

// 6. Save attempt + answered questions inside a single transaction
$now = time();
$pdo->beginTransaction();

try {
    $attemptStmt = $pdo->prepare(
        'INSERT INTO attempts (user_id, exam_id, started_at, submitted_at, score, total, auto_submitted)
         VALUES (:user_id, :exam_id, :started_at, :submitted_at, :score, :total, :auto_submitted)'
    );
    $attemptStmt->execute([
        ':user_id' => (int)$user['id'],
        ':exam_id' => $examId,
        ':started_at' => $now,
        ':submitted_at' => $now,
        ':score' => $score,
        ':total' => $total,
        ':auto_submitted' => $autoSubmitted
    ]);

    $attemptId = (int)$pdo->lastInsertId();

    $ansStmt = $pdo->prepare(
        'INSERT INTO attempt_answers (attempt_id, question_id, chosen_option)
         VALUES (:attempt_id, :question_id, :chosen_option)'
    );

    foreach ($validAnswersToSave as $ans) {
        $ansStmt->execute([
            ':attempt_id' => $attemptId,
            ':question_id' => $ans['question_id'],
            ':chosen_option' => $ans['chosen_option']
        ]);
    }

    $pdo->commit();
// } catch (Throwable $e) {
//     $pdo->rollBack();
//     respond(500, ['ok' => false, 'error' => 'Failed to save your exam attempt.']);
// }
} catch (Throwable $e) {
    $pdo->rollBack();
    respond(500, ['ok' => false, 'error' => 'DB Error: ' . $e->getMessage()]);
}

respond(200, [
    'ok' => true,
    'attempt_id' => $attemptId,
    'score' => $score,
    'total' => $total,
    'review' => $review
]);