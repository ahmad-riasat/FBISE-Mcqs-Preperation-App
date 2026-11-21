<?php
declare(strict_types=1);
require __DIR__ . '/../src/db.php';

// 1. Read the JSON file into a PHP array
$json = file_get_contents(__DIR__ . '/quiz-data.json');
$data = json_decode($json, true);          // true = give me arrays, not objects
if ($data === null) {
    exit("Could not read quiz-data.json\n");
}

$pdo = db();

// 2. Prepare both INSERT statements ONCE, before any loop
$insertExam = $pdo->prepare(
    'INSERT INTO exams (class_name, subject, duration_seconds)
     VALUES (:class_name, :subject, :duration)'
);
$insertQuestion = $pdo->prepare(
    'INSERT INTO questions
        (exam_id, question_text, option_a, option_b, option_c, option_d,
         correct_option, explanation, source_type)
     VALUES
        (:exam_id, :question_text, :option_a, :option_b, :option_c, :option_d,
         :correct_option, :explanation, :source_type)'
);

// 3. Start a transaction: all inserts succeed together or none do
$pdo->beginTransaction();

try {
    // 4. Clear old data so the script can be re-run safely
    //    (ON DELETE CASCADE removes the questions of every deleted exam)
    $pdo->exec('DELETE FROM exams');

    // 5. Loop: class -> subject -> questions
    foreach ($data as $className => $subjects) {
        foreach ($subjects as $subjectName => $exam) {

            // 5a. Insert the exam
            $insertExam->execute([
                ':class_name' => $className,
                ':subject'    => $subjectName,
                ':duration'   => $exam['duration'],
            ]);

            // 5b. Ask the database which id it gave the new exam
            $examId = (int) $pdo->lastInsertId();

            // 5c. Insert every question of this exam
            foreach ($exam['questions'] as $q) {
                $insertQuestion->execute([
                    ':exam_id'        => $examId,
                    ':question_text'  => $q['question'],
                    ':option_a'       => $q['options'][0],
                    ':option_b'       => $q['options'][1],
                    ':option_c'       => $q['options'][2],
                    ':option_d'       => $q['options'][3],
                    ':correct_option' => $q['answer'],
                    ':explanation'    => $q['explanation'] ?? null,
                    ':source_type'    => $q['sourceType'] ?? null,
                ]);
            }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    exit("Seeding failed, nothing saved: " . $e->getMessage() . "\n");
}

// 6. Print the counts so you can verify them
$exams     = $pdo->query('SELECT COUNT(*) FROM exams')->fetchColumn();
$questions = $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
echo "Seeded $exams exams and $questions questions.\n";