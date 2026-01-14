<?php
/**
 * Awards System for TiGears Attendance Tracker
 *
 * This file contains all the award calculation functions and the box population system.
 *
 * HOW IT WORKS:
 * 1. Data is loaded once from the database (students + attendance_log)
 * 2. Award functions calculate rankings from this data
 * 3. Box functions (populateLeftBox, populateMiddleBox, populateRightBox) call award functions
 * 4. To change what's displayed, just change which award function a box calls
 *
 * See docs/AddAwards.md for instructions on adding new awards.
 */

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Format seconds as hours:minutes (e.g., "2:30" for 2 hours 30 minutes)
 */
function formatTime($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    return sprintf("%d:%02d", $hours, $minutes);
}

/**
 * Render an award box with title and items
 *
 * @param string $title The title to display in the header
 * @param string $titleClass CSS class for the title (controls color)
 * @param array $items Array of items, each with 'name' and 'value' keys
 * @param string $emptyMessage Message to show when no data
 */
function renderAwardBox($title, $titleClass, $items, $emptyMessage = "No data yet") {
    echo '<div class="award-column">';
    echo '<h3 class="award-title ' . htmlspecialchars($titleClass) . '">' . htmlspecialchars($title) . '</h3>';
    echo '<div class="award-list">';

    if (count($items) > 0) {
        $rank = 1;
        foreach ($items as $item) {
            echo '<div class="award-item">';
            echo '<span class="award-rank">' . $rank . '</span>';
            echo '<span class="award-name">' . htmlspecialchars($item['name']) . '</span>';
            echo '<span class="award-value">' . htmlspecialchars($item['value']) . '</span>';
            echo '</div>';
            $rank++;
        }
    } else {
        echo '<p class="empty-award">' . htmlspecialchars($emptyMessage) . '</p>';
    }

    echo '</div>';
    echo '</div>';
}

// ============================================================================
// AWARD CALCULATION FUNCTIONS
// Each function takes $students and $attendance arrays and returns ranked results
// Returns: array of ['name' => string, 'value' => string]
// ============================================================================

/**
 * Calculate total signed-in time for all students (all-time)
 * Returns top 10 students by cumulative time spent signed in
 */
function awardTotalTime($students, $attendance) {
    $studentTimes = [];

    // Build a lookup of student names
    $studentNames = [];
    foreach ($students as $student) {
        $studentNames[$student['student_id']] = $student['name'];
        $studentTimes[$student['student_id']] = 0;
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

    // Calculate time for each student
    foreach ($studentAttendance as $sid => $records) {
        // Sort by timestamp
        usort($records, function($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });

        $signInTime = null;
        foreach ($records as $record) {
            if ($record['action'] === 'in') {
                $signInTime = strtotime($record['timestamp']);
            } elseif ($record['action'] === 'out' && $signInTime !== null) {
                $signOutTime = strtotime($record['timestamp']);
                $studentTimes[$sid] += ($signOutTime - $signInTime);
                $signInTime = null;
            }
        }

        // If still signed in, count time until now
        if ($signInTime !== null) {
            $studentTimes[$sid] += (time() - $signInTime);
        }
    }

    // Sort by time descending
    arsort($studentTimes);

    // Build result array (top 10)
    $result = [];
    $count = 0;
    foreach ($studentTimes as $sid => $seconds) {
        if ($seconds > 0 && $count < 10) {
            $result[] = [
                'name' => $studentNames[$sid] ?? 'Unknown',
                'value' => formatTime($seconds)
            ];
            $count++;
        }
    }

    return $result;
}

/**
 * Calculate most sign-ins for all students
 * Returns top 10 students by number of times they've signed in
 */
function awardMostSignIns($students, $attendance) {
    $signInCounts = [];

    // Build a lookup of student names
    $studentNames = [];
    foreach ($students as $student) {
        $studentNames[$student['student_id']] = $student['name'];
        $signInCounts[$student['student_id']] = 0;
    }

    // Count sign-ins
    foreach ($attendance as $record) {
        if ($record['action'] === 'in') {
            $sid = $record['student_id'];
            if (isset($signInCounts[$sid])) {
                $signInCounts[$sid]++;
            }
        }
    }

    // Sort by count descending
    arsort($signInCounts);

    // Build result array (top 10)
    $result = [];
    $count = 0;
    foreach ($signInCounts as $sid => $numSignIns) {
        if ($numSignIns > 0 && $count < 10) {
            $result[] = [
                'name' => $studentNames[$sid] ?? 'Unknown',
                'value' => (string)$numSignIns
            ];
            $count++;
        }
    }

    return $result;
}

/**
 * Calculate total signed-in time for today only
 * Returns top 10 students by time spent signed in today
 */
function awardTodayTime($students, $attendance) {
    $studentTimes = [];
    $today = date('Y-m-d');

    // Build a lookup of student names
    $studentNames = [];
    foreach ($students as $student) {
        $studentNames[$student['student_id']] = $student['name'];
        $studentTimes[$student['student_id']] = 0;
    }

    // Filter attendance to today only and group by student
    $studentAttendance = [];
    foreach ($attendance as $record) {
        $recordDate = date('Y-m-d', strtotime($record['timestamp']));
        if ($recordDate === $today) {
            $sid = $record['student_id'];
            if (!isset($studentAttendance[$sid])) {
                $studentAttendance[$sid] = [];
            }
            $studentAttendance[$sid][] = $record;
        }
    }

    // Calculate time for each student
    foreach ($studentAttendance as $sid => $records) {
        // Sort by timestamp
        usort($records, function($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });

        $signInTime = null;
        foreach ($records as $record) {
            if ($record['action'] === 'in') {
                $signInTime = strtotime($record['timestamp']);
            } elseif ($record['action'] === 'out' && $signInTime !== null) {
                $signOutTime = strtotime($record['timestamp']);
                $studentTimes[$sid] += ($signOutTime - $signInTime);
                $signInTime = null;
            }
        }

        // If still signed in today, count time until now
        if ($signInTime !== null) {
            $studentTimes[$sid] += (time() - $signInTime);
        }
    }

    // Sort by time descending
    arsort($studentTimes);

    // Build result array (top 10)
    $result = [];
    $count = 0;
    foreach ($studentTimes as $sid => $seconds) {
        if ($seconds > 0 && $count < 10) {
            $result[] = [
                'name' => $studentNames[$sid] ?? 'Unknown',
                'value' => formatTime($seconds)
            ];
            $count++;
        }
    }

    return $result;
}

/**
 * Calculate most consecutive days attended (only counting in-window sign-ins)
 * Returns top 10 students by longest streak of consecutive attendance days
 */
function awardConsecutiveDays($students, $attendance, $windows) {
    $studentNames = [];
    foreach ($students as $student) {
        $studentNames[$student['student_id']] = $student['name'];
    }

    // Group sign-ins by student, only counting in-window ones
    $studentDays = [];
    foreach ($attendance as $record) {
        if ($record['action'] !== 'in') continue;

        $sid = $record['student_id'];
        $timestamp = $record['timestamp'];

        // Check if this sign-in is within a window
        if (!isInWindow($timestamp, $windows)) continue;

        $date = date('Y-m-d', strtotime($timestamp));
        if (!isset($studentDays[$sid])) {
            $studentDays[$sid] = [];
        }
        $studentDays[$sid][$date] = true;
    }

    // Calculate max consecutive days for each student
    $maxStreaks = [];
    foreach ($studentDays as $sid => $days) {
        $dates = array_keys($days);
        sort($dates);

        $maxStreak = 0;
        $currentStreak = 0;
        $prevDate = null;

        foreach ($dates as $date) {
            if ($prevDate === null) {
                $currentStreak = 1;
            } else {
                $diff = (strtotime($date) - strtotime($prevDate)) / 86400;
                if ($diff == 1) {
                    $currentStreak++;
                } else {
                    $currentStreak = 1;
                }
            }
            $maxStreak = max($maxStreak, $currentStreak);
            $prevDate = $date;
        }

        $maxStreaks[$sid] = $maxStreak;
    }

    arsort($maxStreaks);

    $result = [];
    $count = 0;
    foreach ($maxStreaks as $sid => $streak) {
        if ($streak > 0 && $count < 10) {
            $result[] = [
                'name' => $studentNames[$sid] ?? 'Unknown',
                'value' => $streak . ' days'
            ];
            $count++;
        }
    }

    return $result;
}

/**
 * Calculate on-time percentage (only for in-window sign-ins)
 * Returns top 10 students by percentage of on-time arrivals
 */
function awardOnTimePercentage($students, $attendance, $windows) {
    $studentNames = [];
    foreach ($students as $student) {
        $studentNames[$student['student_id']] = $student['name'];
    }

    // Count on-time and total in-window sign-ins per student
    $stats = [];
    foreach ($attendance as $record) {
        if ($record['action'] !== 'in') continue;

        $sid = $record['student_id'];
        $timestamp = $record['timestamp'];

        // Check if in window and get status
        $status = getWindowStatus($timestamp, $windows);
        if ($status === 'outside_window') continue;

        if (!isset($stats[$sid])) {
            $stats[$sid] = ['on_time' => 0, 'total' => 0];
        }
        $stats[$sid]['total']++;
        if ($status === 'on_time') {
            $stats[$sid]['on_time']++;
        }
    }

    // Calculate percentages
    $percentages = [];
    foreach ($stats as $sid => $data) {
        if ($data['total'] > 0) {
            $percentages[$sid] = [
                'pct' => ($data['on_time'] / $data['total']) * 100,
                'total' => $data['total']
            ];
        }
    }

    // Sort by percentage descending, then by total descending
    uasort($percentages, function($a, $b) {
        if ($a['pct'] != $b['pct']) {
            return $b['pct'] <=> $a['pct'];
        }
        return $b['total'] <=> $a['total'];
    });

    $result = [];
    $count = 0;
    foreach ($percentages as $sid => $data) {
        if ($count < 10) {
            $result[] = [
                'name' => $studentNames[$sid] ?? 'Unknown',
                'value' => round($data['pct']) . '%'
            ];
            $count++;
        }
    }

    return $result;
}

/**
 * Calculate total in-window time only
 * Returns top 10 students by cumulative time spent signed in during valid windows
 */
function awardInWindowTime($students, $attendance, $windows) {
    $studentNames = [];
    $studentTimes = [];
    foreach ($students as $student) {
        $studentNames[$student['student_id']] = $student['name'];
        $studentTimes[$student['student_id']] = 0;
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

    // Calculate in-window time for each student
    foreach ($studentAttendance as $sid => $records) {
        usort($records, function($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });

        $signInTime = null;
        $signInTimestamp = null;
        foreach ($records as $record) {
            if ($record['action'] === 'in') {
                $signInTime = strtotime($record['timestamp']);
                $signInTimestamp = $record['timestamp'];
            } elseif ($record['action'] === 'out' && $signInTime !== null) {
                $signOutTime = strtotime($record['timestamp']);
                // Only count if the sign-in was in window
                if (isInWindow($signInTimestamp, $windows)) {
                    $studentTimes[$sid] += ($signOutTime - $signInTime);
                }
                $signInTime = null;
                $signInTimestamp = null;
            }
        }

        // If still signed in and it was in-window, count time until now
        if ($signInTime !== null && isInWindow($signInTimestamp, $windows)) {
            $studentTimes[$sid] += (time() - $signInTime);
        }
    }

    arsort($studentTimes);

    $result = [];
    $count = 0;
    foreach ($studentTimes as $sid => $seconds) {
        if ($seconds > 0 && $count < 10) {
            $result[] = [
                'name' => $studentNames[$sid] ?? 'Unknown',
                'value' => formatTime($seconds)
            ];
            $count++;
        }
    }

    return $result;
}

/**
 * Helper: Check if a timestamp falls within any attendance window
 */
function isInWindow($timestamp, $windows) {
    $dt = new DateTime($timestamp);
    $dayOfWeek = (int)$dt->format('w');
    $time = $dt->format('H:i:s');

    foreach ($windows as $window) {
        if ($window['day_of_week'] == $dayOfWeek &&
            $time >= $window['start_time'] &&
            $time <= $window['end_time']) {
            return true;
        }
    }
    return false;
}

/**
 * Helper: Get status (on_time, late, outside_window) for a timestamp
 */
function getWindowStatus($timestamp, $windows) {
    $dt = new DateTime($timestamp);
    $dayOfWeek = (int)$dt->format('w');
    $time = $dt->format('H:i:s');

    foreach ($windows as $window) {
        if ($window['day_of_week'] == $dayOfWeek &&
            $time >= $window['start_time'] &&
            $time <= $window['end_time']) {
            // Check grace period (uses GRACE_PERIOD_MINUTES from config)
            $graceEnd = date('H:i:s', strtotime($window['start_time'] . ' +' . GRACE_PERIOD_MINUTES . ' minutes'));
            if ($time <= $graceEnd) {
                return 'on_time';
            } else {
                return 'late';
            }
        }
    }
    return 'outside_window';
}

// ============================================================================
// BOX POPULATION FUNCTIONS
// These functions control what each box displays.
// To change an award, just change which award function is called here.
// ============================================================================

/**
 * Populate the LEFT award box
 * Currently shows: Most Consecutive Days
 */
function populateLeftBox($students, $attendance, $windows = []) {
    $items = awardConsecutiveDays($students, $attendance, $windows);
    renderAwardBox("Consecutive Days", "total-time-title", $items);
}

/**
 * Populate the MIDDLE award box
 * Currently shows: On-Time Percentage
 */
function populateMiddleBox($students, $attendance, $windows = []) {
    $items = awardOnTimePercentage($students, $attendance, $windows);
    renderAwardBox("On-Time %", "most-signins-title", $items);
}

/**
 * Populate the RIGHT award box
 * Currently shows: Total In-Window Time
 */
function populateRightBox($students, $attendance, $windows = []) {
    $items = awardInWindowTime($students, $attendance, $windows);
    renderAwardBox("Total Time", "today-time-title", $items);
}

// ============================================================================
// DATA LOADING FUNCTION
// ============================================================================

/**
 * Load all data needed for awards from the database
 * Returns: ['students' => array, 'attendance' => array, 'windows' => array]
 */
function loadAwardData($conn) {
    // Load all students
    $students = [];
    $result = $conn->query("SELECT student_id, name FROM students");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }

    // Load all attendance records
    $attendance = [];
    $result = $conn->query("SELECT student_id, timestamp, action FROM attendance_log ORDER BY timestamp ASC");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $attendance[] = $row;
        }
    }

    // Load attendance windows
    $windows = [];
    $result = $conn->query("SELECT day_of_week, start_time, end_time FROM attendance_windows");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $windows[] = $row;
        }
    }

    return [
        'students' => $students,
        'attendance' => $attendance,
        'windows' => $windows
    ];
}
?>
