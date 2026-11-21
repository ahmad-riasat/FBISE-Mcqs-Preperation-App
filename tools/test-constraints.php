<?php
require __DIR__ . '/../src/db.php';

$pdo = db();

try {
    $pdo->exec("INSERT INTO exams (class_name, subject, duration_seconds)
                VALUES ('Class 13', 'Physics', 900)");
    echo "Test 1: BAD - Class 13 was accepted\n";
} catch (PDOException $e) {
    echo "Test 1: OK - refused: ", $e->getMessage(), "\n";
}

try {
    $pdo->exec("INSERT INTO questions
                (exam_id, question_text, option_a, option_b, option_c, option_d, correct_option)
                VALUES (999, 'q', 'a', 'b', 'c', 'd', 0)");
    echo "Test 2: BAD - question for a missing exam was accepted\n";
} catch (PDOException $e) {
    echo "Test 2: OK - refused: ", $e->getMessage(), "\n";
}