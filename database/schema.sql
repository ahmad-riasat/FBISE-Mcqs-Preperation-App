DROP TABLE IF EXISTS exams;

CREATE TABLE exams (
  id               INTEGER PRIMARY KEY AUTOINCREMENT,
  class_name       TEXT    NOT NULL
                   CHECK (class_name IN ('Class 9', 'Class 10', 'Class 11', 'Class 12')),
  subject          TEXT    NOT NULL,
  duration_seconds INTEGER NOT NULL CHECK (duration_seconds > 0),
  UNIQUE (class_name, subject)
);
CREATE TABLE questions (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  exam_id        INTEGER NOT NULL REFERENCES exams(id) ON DELETE CASCADE,
  question_text  TEXT    NOT NULL,
  option_a       TEXT    NOT NULL,
  option_b       TEXT    NOT NULL,
  option_c       TEXT    NOT NULL,
  option_d       TEXT    NOT NULL,
  correct_option INTEGER NOT NULL CHECK (correct_option IN (0, 1, 2, 3)),
  explanation    TEXT,
  source_type    TEXT
);