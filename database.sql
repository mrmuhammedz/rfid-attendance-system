-- ============================================
-- RFID Attendance System - Clean Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS attendance_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE attendance_db;

-- ============================================
-- Admins Table
-- ============================================
CREATE TABLE IF NOT EXISTS admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    mail       VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- Students Table
-- ============================================
CREATE TABLE IF NOT EXISTS students (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    uid         VARCHAR(20) NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    student_id  VARCHAR(50) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- Shared Weekly Class Schedule
-- day_of_week: 0=Saturday, 1=Sunday, ..., 6=Friday
-- ============================================
CREATE TABLE IF NOT EXISTS class_schedule (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week TINYINT UNSIGNED NOT NULL,
    start_time  TIME NOT NULL,
    end_time    TIME NOT NULL,
    title       VARCHAR(150) NOT NULL,
    instructor_name VARCHAR(150) NOT NULL,
    class_type  ENUM('lecture','section') NOT NULL DEFAULT 'lecture',
    location    VARCHAR(100) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_schedule_day_time (day_of_week, start_time)
);

-- ============================================
-- A dated occurrence of a recurring schedule entry
-- ============================================
CREATE TABLE IF NOT EXISTS class_sessions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id     INT NOT NULL,
    session_date    DATE NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    subject         VARCHAR(150) NOT NULL,
    class_type      ENUM('lecture','section') NOT NULL,
    instructor_name VARCHAR(150) NOT NULL,
    location        VARCHAR(100) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_schedule_session (schedule_id, session_date),
    FOREIGN KEY (schedule_id) REFERENCES class_schedule(id) ON DELETE RESTRICT
);

-- ============================================
-- Attendance Table (one attendance record per student per class session)
-- ============================================
CREATE TABLE IF NOT EXISTS attendance (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    session_id  INT NULL,
    date        DATE NOT NULL,
    status      ENUM('present','absent') DEFAULT 'present',
    scanned_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES class_sessions(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_attendance_session (student_id, session_id),
    INDEX idx_attendance_date (date)
);

-- ============================================
-- Sample Admin
-- ============================================
INSERT IGNORE INTO admins (mail, password) VALUES
('admin@admin.com', '$2y$10$JR/AUUWhwarPUWaPxnnSD.uWJ7E15xcFJEFuc.FYtRsn/.XTRYYEW');

-- ============================================
-- Sample Students
-- ============================================
INSERT IGNORE INTO students (uid, name, student_id) VALUES
('6377CAD9', 'Mohamed Mahmmoud', '23/121540'),
('BB1B3402', 'Mazen Mohammed',  '23/121534'),
('43715B06', 'Yassin Saeed',    '23/121256'),
('99EE6E06', 'Mohammed Bakr',   '23/121810'),
('F9F26E06', 'Youssef Ahmed',   '23/121324');

-- ============================================
-- Sample Attendance
-- ============================================
INSERT IGNORE INTO attendance (student_id, date, status) VALUES
(1, '2026-05-03', 'present'),
(1, '2026-05-04', 'present'),
(2, '2026-05-04', 'present'),
(1, '2026-05-05', 'present'),
(2, '2026-05-05', 'present'),
(4, '2026-05-05', 'present'),
(5, '2026-05-05', 'present');
