-- Schema update for attendance windows and on-time tracking
-- Run this on an existing database to add the new features

-- Add status column to attendance_log
ALTER TABLE attendance_log
ADD COLUMN status ENUM('on_time', 'late', 'outside_window') DEFAULT NULL;

-- Create attendance windows table
CREATE TABLE IF NOT EXISTS attendance_windows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week TINYINT NOT NULL,  -- 0=Sunday, 1=Monday, ..., 6=Saturday
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_day (day_of_week),
    CONSTRAINT chk_day CHECK (day_of_week BETWEEN 0 AND 6),
    CONSTRAINT chk_times CHECK (start_time < end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
