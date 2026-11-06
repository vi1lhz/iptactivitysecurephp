CREATE DATABASE IF NOT EXISTS notes_lab CHARACTER SET utf8mb4;
USE notes_lab;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'student',
  CHECK (role IN ('student', 'admin'))
);

CREATE TABLE notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  body TEXT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notes_user (user_id)
);

-- Synthetic accounts for local use only.
-- Hashes made with PHP password_hash() (bcrypt), checked with password_verify().
-- Same throwaway passwords as the README.
INSERT INTO users (id, email, password_hash, role) VALUES
  (1, 'alex@example.test', '$2y$10$3Rzl7ICv35UTrISvvr1ywOi8dCosdbxn02YnfDlqRs6SbXo94wxQ2', 'student'),
  (2, 'sam@example.test', '$2y$10$ORuA9YWIio4nQeGH0IHAuO7wNibn3BGd.48JuksSJ6tvOj72d9KJi', 'student');

-- Least-privilege app account (run as admin, pick your own password, do not commit it):
-- CREATE USER 'notes_app'@'localhost' IDENTIFIED BY '<your local password>';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON notes_lab.* TO 'notes_app'@'localhost';

INSERT INTO notes (user_id, title, body) VALUES
  (1, 'Alex: course reminder', 'Review the security reading before class.'),
  (1, 'Alex: project idea', 'Build a small student directory.'),
  (2, 'Sam: study plan', 'Compare authentication and authorization.'),
  (2, 'Sam: meeting', 'Prepare questions for the next group discussion.');
