-- Schema update for attendance windows and on-time tracking
-- Run this on an existing database to add the new features

-- Create attendance windows table
CREATE TABLE IF NOT EXISTS attendance_windows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week TINYINT NOT NULL,  -- 0=Sunday, 1=Monday, ..., 6=Saturday
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    valid_from DATE NOT NULL DEFAULT '2000-01-01',  -- First date this window applies
    valid_to   DATE NOT NULL DEFAULT '2040-01-01',  -- Last date this window applies (inclusive)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_day (day_of_week),
    CONSTRAINT chk_day CHECK (day_of_week BETWEEN 0 AND 6),
    CONSTRAINT chk_times CHECK (start_time < end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Original schedule: Tues/Thurs/Fri 2PM-4PM, Saturday 9AM-1PM (through 2026-03-02)
INSERT INTO attendance_windows (day_of_week, start_time, end_time, valid_from, valid_to) VALUES
(2, '14:00:00', '16:00:00', '2000-01-01', '2026-03-02'),  -- Tuesday
(4, '14:00:00', '16:00:00', '2000-01-01', '2026-03-02'),  -- Thursday
(5, '14:00:00', '16:00:00', '2000-01-01', '2026-03-02'),  -- Friday
(6, '09:00:00', '13:00:00', '2000-01-01', '2026-03-02');  -- Saturday

-- New schedule effective 2026-03-03: Tues-Fri 4PM-7PM, Saturday 9AM-9PM
INSERT INTO attendance_windows (day_of_week, start_time, end_time, valid_from, valid_to) VALUES
(2, '16:00:00', '19:00:00', '2026-03-03', '2040-01-01'),  -- Tuesday
(3, '16:00:00', '19:00:00', '2026-03-03', '2040-01-01'),  -- Wednesday
(4, '16:00:00', '19:00:00', '2026-03-03', '2040-01-01'),  -- Thursday
(5, '16:00:00', '19:00:00', '2026-03-03', '2040-01-01'),  -- Friday
(6, '09:00:00', '21:00:00', '2026-03-03', '2040-01-01');  -- Saturday
