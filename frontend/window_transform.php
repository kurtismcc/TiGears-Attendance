<?php
/**
 * Window Transform System for TiGears Attendance Tracker
 *
 * This file transforms raw attendance data into window-based records.
 *
 * HOW IT WORKS:
 * 1. Generate all valid window occurrences between earliest and latest attendance records
 * 2. Transform each student's attendance into per-window records
 * 3. Handle edge cases: forgot to sign out (cap at window end), carry-over from previous day (ignore)
 * 4. Provide data structure for award calculations
 */

require_once '../backend/config.php';

/**
 * Generate all window occurrences between two dates
 *
 * @param array $windows The configured attendance windows (day_of_week, start_time, end_time)
 * @param string $startDate The earliest date to consider (Y-m-d format)
 * @param string $endDate The latest date to consider (Y-m-d format)
 * @return array Array of window occurrences, each with:
 *   - id: Sequential integer ID
 *   - date: The date (Y-m-d)
 *   - day_of_week: 0-6
 *   - start_time: H:i:s
 *   - end_time: H:i:s
 *   - start_datetime: Full datetime string
 *   - end_datetime: Full datetime string
 */
function generateWindowOccurrences($windows, $startDate, $endDate) {
    $occurrences = [];
    $id = 1;

    // Build lookup of windows by day of week
    $windowsByDay = [];
    foreach ($windows as $window) {
        $dow = (int)$window['day_of_week'];
        if (!isset($windowsByDay[$dow])) {
            $windowsByDay[$dow] = [];
        }
        $windowsByDay[$dow][] = $window;
    }

    // Iterate through each day from start to end
    $current = new DateTime($startDate);
    $end = new DateTime($endDate);

    while ($current <= $end) {
        $dayOfWeek = (int)$current->format('w');
        $dateStr = $current->format('Y-m-d');

        // Check if there are windows for this day
        if (isset($windowsByDay[$dayOfWeek])) {
            foreach ($windowsByDay[$dayOfWeek] as $window) {
                $occurrences[] = [
                    'id' => $id++,
                    'date' => $dateStr,
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $window['start_time'],
                    'end_time' => $window['end_time'],
                    'start_datetime' => $dateStr . ' ' . $window['start_time'],
                    'end_datetime' => $dateStr . ' ' . $window['end_time']
                ];
            }
        }

        $current->modify('+1 day');
    }

    return $occurrences;
}

/**
 * Check if a window occurrence is completed (in the past)
 *
 * @param array $windowOccurrence A single window occurrence
 * @return bool True if the window has ended
 */
function isWindowCompleted($windowOccurrence) {
    $endTime = strtotime($windowOccurrence['end_datetime']);
    return time() > $endTime;
}

/**
 * Filter window occurrences to only completed ones
 *
 * @param array $occurrences All window occurrences
 * @return array Only completed window occurrences
 */
function getCompletedWindows($occurrences) {
    return array_filter($occurrences, 'isWindowCompleted');
}

/**
 * Determine if a sign-in is on-time or late for a specific window occurrence
 *
 * @param string $signInTime The sign-in timestamp
 * @param array $windowOccurrence The window occurrence to check against
 * @return string 'on_time', 'late', or 'outside'
 */
function getSignInStatus($signInTime, $windowOccurrence) {
    $signInTs = strtotime($signInTime);
    $windowStart = strtotime($windowOccurrence['start_datetime']);
    $windowEnd = strtotime($windowOccurrence['end_datetime']);
    $graceEnd = $windowStart + (GRACE_PERIOD_MINUTES * 60);

    // Sign-in must be on the same day and before window end
    $signInDate = date('Y-m-d', $signInTs);
    if ($signInDate !== $windowOccurrence['date']) {
        return 'outside';
    }

    if ($signInTs > $windowEnd) {
        return 'outside';
    }

    // On-time: before or at grace period end
    if ($signInTs <= $graceEnd) {
        return 'on_time';
    }

    // Late: after grace but before window end
    return 'late';
}

/**
 * Find which window occurrence a sign-in belongs to
 *
 * @param string $signInTime The sign-in timestamp
 * @param array $occurrences All window occurrences
 * @return array|null The matching window occurrence, or null if none
 */
function findWindowForSignIn($signInTime, $occurrences) {
    $signInDate = date('Y-m-d', strtotime($signInTime));

    foreach ($occurrences as $occ) {
        if ($occ['date'] !== $signInDate) {
            continue;
        }

        $status = getSignInStatus($signInTime, $occ);
        if ($status !== 'outside') {
            return $occ;
        }
    }

    return null;
}

/**
 * Transform raw attendance data into per-window student records
 *
 * @param array $students All students
 * @param array $attendance All attendance records (sorted by timestamp ASC)
 * @param array $windowOccurrences All generated window occurrences
 * @return array Transformed data structure:
 *   [student_id => [
 *       window_id => [
 *           'signed_in' => true,
 *           'status' => 'on_time' | 'late',
 *           'total_seconds' => int,
 *           'sign_in_time' => string,
 *           'sign_out_time' => string | null
 *       ]
 *   ]]
 */
function transformAttendanceData($students, $attendance, $windowOccurrences) {
    $transformed = [];

    // Initialize empty records for all students
    foreach ($students as $student) {
        $transformed[$student['student_id']] = [];
    }

    // Group attendance by student
    $studentAttendance = [];
    foreach ($attendance as $record) {
        $sid = $record['student_id'];
        if (!isset($studentAttendance[$sid])) {
            $studentAttendance[$sid] = [];
        }
        $studentAttendance[$sid][] = $record;
    }

    // Process each student's attendance
    foreach ($studentAttendance as $sid => $records) {
        // Sort by timestamp (should already be sorted, but ensure it)
        usort($records, function($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });

        $pendingSignIn = null; // Tracks an unmatched sign-in
        $pendingWindow = null; // The window the pending sign-in belongs to

        foreach ($records as $record) {
            $timestamp = $record['timestamp'];
            $recordDate = date('Y-m-d', strtotime($timestamp));

            if ($record['action'] === 'in') {
                // Find which window this sign-in belongs to
                $window = findWindowForSignIn($timestamp, $windowOccurrences);

                if ($window !== null) {
                    // Check if there's a pending sign-in from a DIFFERENT day
                    // If so, that sign-in was never signed out - cap it at window end
                    if ($pendingSignIn !== null && $pendingWindow !== null) {
                        $pendingDate = date('Y-m-d', strtotime($pendingSignIn['timestamp']));
                        if ($pendingDate !== $recordDate) {
                            // Auto sign-out at window end for the previous day
                            finalizePendingSignIn($transformed, $sid, $pendingSignIn, $pendingWindow, null);
                            $pendingSignIn = null;
                            $pendingWindow = null;
                        }
                    }

                    // Start tracking this sign-in
                    $pendingSignIn = $record;
                    $pendingWindow = $window;
                }
            } elseif ($record['action'] === 'out') {
                if ($pendingSignIn !== null && $pendingWindow !== null) {
                    $signInDate = date('Y-m-d', strtotime($pendingSignIn['timestamp']));

                    // Only count sign-out if it's on the same day as sign-in
                    if ($recordDate === $signInDate) {
                        finalizePendingSignIn($transformed, $sid, $pendingSignIn, $pendingWindow, $record);
                    } else {
                        // Sign-out is on a different day - auto sign-out at window end
                        finalizePendingSignIn($transformed, $sid, $pendingSignIn, $pendingWindow, null);
                    }

                    $pendingSignIn = null;
                    $pendingWindow = null;
                }
            }
        }

        // Handle any remaining pending sign-in (student forgot to sign out)
        if ($pendingSignIn !== null && $pendingWindow !== null) {
            finalizePendingSignIn($transformed, $sid, $pendingSignIn, $pendingWindow, null);
        }
    }

    return $transformed;
}

/**
 * Finalize a pending sign-in by calculating time and storing the record
 *
 * @param array &$transformed Reference to transformed data structure
 * @param string $studentId The student ID
 * @param array $signIn The sign-in record
 * @param array $window The window occurrence
 * @param array|null $signOut The sign-out record, or null if auto sign-out
 */
function finalizePendingSignIn(&$transformed, $studentId, $signIn, $window, $signOut) {
    $signInTs = strtotime($signIn['timestamp']);
    $windowEnd = strtotime($window['end_datetime']);

    // Determine sign-out time
    if ($signOut !== null) {
        $signOutTs = strtotime($signOut['timestamp']);
        // Cap at window end if sign-out is after window
        $signOutTs = min($signOutTs, $windowEnd);
    } else {
        // Auto sign-out at window end
        $signOutTs = $windowEnd;
    }

    // Calculate time (ensure non-negative)
    $totalSeconds = max(0, $signOutTs - $signInTs);

    // Get status
    $status = getSignInStatus($signIn['timestamp'], $window);

    $windowId = $window['id'];

    // If student already has a record for this window, add to it
    // (handles multiple sign-in/out cycles in same window)
    if (isset($transformed[$studentId][$windowId])) {
        $transformed[$studentId][$windowId]['total_seconds'] += $totalSeconds;
    } else {
        $transformed[$studentId][$windowId] = [
            'signed_in' => true,
            'status' => $status,
            'total_seconds' => $totalSeconds,
            'sign_in_time' => $signIn['timestamp'],
            'sign_out_time' => $signOut ? $signOut['timestamp'] : $window['end_datetime'] . ' (auto)',
            'window_date' => $window['date']
        ];
    }
}

/**
 * Main function to load and transform all attendance data
 *
 * @param mysqli $conn Database connection
 * @return array [
 *   'students' => array of student records,
 *   'windows' => array of window configurations,
 *   'window_occurrences' => array of generated window occurrences (completed only),
 *   'all_window_occurrences' => array of all window occurrences,
 *   'student_windows' => transformed per-student per-window data
 * ]
 */
function loadTransformedData($conn) {
    // Load students
    $students = [];
    $result = $conn->query("SELECT student_id, name FROM students");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }

    // Load attendance windows configuration
    $windows = [];
    $result = $conn->query("SELECT day_of_week, start_time, end_time FROM attendance_windows");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $windows[] = $row;
        }
    }

    // Load attendance records
    $attendance = [];
    $result = $conn->query("SELECT student_id, timestamp, action FROM attendance_log ORDER BY timestamp ASC");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $attendance[] = $row;
        }
    }

    // Find date range from attendance records
    if (count($attendance) > 0) {
        $startDate = date('Y-m-d', strtotime($attendance[0]['timestamp']));
        $endDate = date('Y-m-d', strtotime($attendance[count($attendance) - 1]['timestamp']));

        // Extend end date to today if attendance goes up to today
        $today = date('Y-m-d');
        if ($endDate < $today) {
            $endDate = $today;
        }
    } else {
        // No attendance records - use today
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
    }

    // Generate all window occurrences
    $allOccurrences = generateWindowOccurrences($windows, $startDate, $endDate);

    // Filter to only completed windows for award calculations
    $completedOccurrences = array_values(getCompletedWindows($allOccurrences));

    // Transform attendance data
    $studentWindows = transformAttendanceData($students, $attendance, $allOccurrences);

    return [
        'students' => $students,
        'windows' => $windows,
        'window_occurrences' => $completedOccurrences,
        'all_window_occurrences' => $allOccurrences,
        'student_windows' => $studentWindows
    ];
}

/**
 * Build a lookup of student names by ID
 *
 * @param array $students Array of student records
 * @return array [student_id => name]
 */
function buildStudentNameLookup($students) {
    $lookup = [];
    foreach ($students as $student) {
        $lookup[$student['student_id']] = $student['name'];
    }
    return $lookup;
}
?>
