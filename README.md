# FBISE OMR Exam Simulator (Assignment 2 — PHP & Fetch Application)

## Project Overview & Assigned Idea
- **Assigned Idea # & Rule:** *(Insert your assigned idea # and instructor rule/data variant here)*
- **Problem Solved:** Simulates an authentic FBISE Section-A OMR bubble sheet exam with a countdown timer, server-side grading, persistent attempt history, and study notes.

## Tech Stack & Architecture
- **Backend:** PHP 8.5, PDO with SQLite, session-based authentication (`HttpOnly` cookies), and CSRF token protection (`X-CSRF-Token`).
- **Frontend:** Semantic HTML5, CSS3, and Vanilla JavaScript using `fetch()` with `async`/`await` and explicit `loading`, `success`, `empty`, and `error` UI states.
- **Database Tables:** `users`, `exams`, `questions`, `attempts` (including `note`), and `attempt_answers`.
- **Browser Storage:** `localStorage` is used strictly for a non-sensitive UI preference (`fbise_last_class` to remember the last viewed class). No passwords, session IDs, or CSRF tokens are stored in `localStorage`.

## Local Setup & Run Instructions
1. **Clone the repository** and open a terminal in the project root.
2. **Initialize the SQLite database schema and seed records:**
   ```bash
   php -r "require 'src/db.php'; db()->exec(file_get_contents('database/schema.sql'));"
   php database/seed.php
   php database/seed-users.php