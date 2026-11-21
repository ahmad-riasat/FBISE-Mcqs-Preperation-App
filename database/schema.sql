
DROP TABLE IF EXISTS attempt_answers;
DROP TABLE IF EXISTS attempts;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS users;

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
CREATE TABLE users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  full_name     TEXT    NOT NULL CHECK (length(full_name) BETWEEN 3 AND 40),
  roll_number   TEXT    NOT NULL UNIQUE
                CHECK (roll_number GLOB '[0-9][0-9][0-9][0-9][0-9][0-9]'),
  password_hash TEXT    NOT NULL,
  role          TEXT    NOT NULL DEFAULT 'student' CHECK (role IN ('student', 'teacher')),
  created_at    INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);
CREATE TABLE attempts (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  exam_id         INTEGER NOT NULL REFERENCES exams(id) ON DELETE CASCADE,
  started_at      INTEGER NOT NULL,
  submitted_at    INTEGER,
  score           INTEGER,
  total           INTEGER,
  auto_submitted  INTEGER NOT NULL DEFAULT 0
                  CHECK (auto_submitted IN (0, 1)),
  note            TEXT    
);


CREATE TABLE attempt_answers (
  attempt_id     INTEGER NOT NULL REFERENCES attempts(id) ON DELETE CASCADE,
  question_id    INTEGER NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
  chosen_option  INTEGER NOT NULL CHECK (chosen_option BETWEEN 0 AND 3),

  PRIMARY KEY (attempt_id, question_id)
);