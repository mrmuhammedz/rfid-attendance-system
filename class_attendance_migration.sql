-- Run once in attendance_db after the schedule and instructor migrations.
-- Existing attendance rows are preserved with session_id = NULL.
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

ALTER TABLE attendance
    DROP INDEX unique_attendance,
    ADD COLUMN session_id INT NULL AFTER student_id,
    ADD UNIQUE KEY unique_attendance_session (student_id, session_id),
    ADD INDEX idx_attendance_date (date),
    ADD CONSTRAINT fk_attendance_class_session
        FOREIGN KEY (session_id) REFERENCES class_sessions(id) ON DELETE RESTRICT;
