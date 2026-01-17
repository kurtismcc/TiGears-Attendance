-- Migration script to delete all attendance records before 2026
-- Run this once manually: mysql -u username -p database_name < delete_pre_2026.sql

-- Show count of records that will be deleted (for verification)
SELECT COUNT(*) AS records_to_delete
FROM attendance_log
WHERE timestamp < '2026-01-01 00:00:00';

-- Delete all attendance records before January 1, 2026
DELETE FROM attendance_log
WHERE timestamp < '2026-01-01 00:00:00';

-- Verify deletion
SELECT COUNT(*) AS remaining_records FROM attendance_log;
SELECT MIN(timestamp) AS earliest_record FROM attendance_log;