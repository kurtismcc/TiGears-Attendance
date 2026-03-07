-- Migration: Add valid_from/valid_to to attendance_windows and load new schedule
-- Run this on an existing database to apply the schedule change effective 2026-03-03.

USE robotics_attendance;

-- Step 1: Add valid_from and valid_to columns
ALTER TABLE attendance_windows
    ADD COLUMN valid_from DATE NOT NULL DEFAULT '2000-01-01' AFTER end_time,
    ADD COLUMN valid_to   DATE NOT NULL DEFAULT '2040-01-01' AFTER valid_from;

-- Step 2: Set existing rows to expire before the new schedule takes effect
UPDATE attendance_windows SET valid_to = '2026-03-02';

-- Step 3: Insert new schedule effective 2026-03-03
-- Tues-Fri 4PM-7PM, Saturday 9AM-9PM
INSERT INTO attendance_windows (day_of_week, start_time, end_time, valid_from, valid_to) VALUES
(2, '16:00:00', '19:00:00', '2026-03-03', '2040-01-01'),  -- Tuesday
(3, '16:00:00', '19:00:00', '2026-03-03', '2040-01-01'),  -- Wednesday
(4, '16:00:00', '19:00:00', '2026-03-03', '2040-01-01'),  -- Thursday
(5, '16:00:00', '19:00:00', '2026-03-03', '2040-01-01'),  -- Friday
(6, '09:00:00', '21:00:00', '2026-03-03', '2040-01-01');  -- Saturday