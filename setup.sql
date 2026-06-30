CREATE DATABASE IF NOT EXISTS edusystem_php CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edusystem_php;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
  status ENUM('active','locked') NOT NULL DEFAULT 'active',
  class_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS schools (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  address VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS school_grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  name VARCHAR(80) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS school_classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grade_id INT NOT NULL,
  name VARCHAR(80) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (grade_id) REFERENCES school_grades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT NOT NULL,
  name VARCHAR(220) NOT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT NOT NULL,
  lesson_id INT NOT NULL,
  type ENUM('mc','tf','sa','essay') NOT NULL,
  content TEXT NOT NULL,
  image_path TEXT NULL,
  answer TEXT NULL,
  difficulty ENUM('easy','medium','hard','unknown') NOT NULL DEFAULT 'unknown',
  explanation TEXT NULL,
  needs_review TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS question_options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  label CHAR(1) NOT NULL,
  content TEXT NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS true_false_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  label CHAR(1) NOT NULL,
  content TEXT NOT NULL,
  answer ENUM('true','false') NOT NULL DEFAULT 'true',
  difficulty ENUM('easy','medium','hard','unknown') NOT NULL DEFAULT 'unknown',
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL,
  title VARCHAR(220) NOT NULL,
  school_name VARCHAR(220) NOT NULL,
  subject_id INT NOT NULL,
  duration INT NOT NULL DEFAULT 45,
  total_points DECIMAL(6,2) NOT NULL DEFAULT 10.00,
  status ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exam_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  question_id INT NOT NULL,
  position INT NOT NULL,
  points DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  user_id INT NOT NULL,
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  submitted_at TIMESTAMP NULL,
  score DECIMAL(6,2) DEFAULT 0,
  max_score DECIMAL(6,2) DEFAULT 0,
  status ENUM('doing','submitted') NOT NULL DEFAULT 'doing',
  FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attempt_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT NOT NULL,
  question_id INT NOT NULL,
  answer TEXT NULL,
  is_correct TINYINT(1) NULL,
  score DECIMAL(6,2) DEFAULT 0,
  FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exam_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  target VARCHAR(180) NOT NULL,
  target_role ENUM('teacher','student','group','class') NOT NULL DEFAULT 'group',
  target_user_id INT NULL,
  target_class_id INT NULL,
  start_at DATETIME NULL,
  due_at DATETIME NULL,
  show_score TINYINT(1) NOT NULL DEFAULT 1,
  show_answers TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('scheduled','open','closed') NOT NULL DEFAULT 'open',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(220) NOT NULL,
  content TEXT NOT NULL,
  sender_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notification_recipients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  notification_id INT NOT NULL,
  user_id INT NOT NULL,
  read_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_notification_user (notification_id,user_id),
  FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  role VARCHAR(30) NULL,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id INT NULL,
  description VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_created_at (created_at),
  INDEX idx_activity_user_id (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO users (id,name,email,password,role,status) VALUES
(1,'Admin','admin@test.com','admin123','admin','active'),
(2,'Giáo viên Demo','teacher@test.com','teacher123','teacher','active'),
(3,'Học sinh Demo','student@test.com','student123','student','active');
INSERT IGNORE INTO subjects (id,name) VALUES (1,'Toán'),(2,'Vật lý'),(3,'Hóa học'),(4,'Tin học');


