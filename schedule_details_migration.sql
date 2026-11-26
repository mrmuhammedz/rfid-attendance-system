-- Run once on an existing attendance_db installation created with the first schedule migration.
ALTER TABLE class_schedule
    ADD COLUMN instructor_name VARCHAR(150) NOT NULL DEFAULT '' AFTER title,
    ADD COLUMN class_type ENUM('lecture','section') NOT NULL DEFAULT 'lecture' AFTER instructor_name;
