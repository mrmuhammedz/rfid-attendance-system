-- Run this once in phpMyAdmin for an existing attendance_db installation.
-- day_of_week: 0=Saturday, 1=Sunday, ..., 6=Friday
USE attendance_db;

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
