<?php
/**
 * Calculate attendance status for a given timestamp
 * Returns 'on_time', 'late', or 'outside_window'
 *
 * On-time logic: A sign-in is "on_time" if it occurs ANY TIME on the same day
 * before the window start, OR within GRACE_PERIOD_MINUTES after the start.
 * It's "late" if after the grace period but still within the window.
 *
 * @param mysqli $conn Database connection
 * @param string $timestamp The timestamp to check (format: Y-m-d H:i:s)
 * @return string|null The status, or null if not a sign-in
 */
function calculateAttendanceStatus($conn, $timestamp) {
    $dt = new DateTime($timestamp);
    $dayOfWeek = (int)$dt->format('w'); // 0=Sunday, 6=Saturday
    $time = $dt->format('H:i:s');
    $timeSeconds = strtotime($time);

    // Find windows for that day where sign-in is before window end,
    // and the window's date range covers the sign-in date
    $date = $dt->format('Y-m-d');
    $stmt = $conn->prepare("
        SELECT start_time, end_time
        FROM attendance_windows
        WHERE day_of_week = ?
        AND end_time >= ?
        AND valid_from <= ?
        AND valid_to >= ?
        ORDER BY start_time ASC
        LIMIT 1
    ");
    $stmt->bind_param("isss", $dayOfWeek, $time, $date, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return 'outside_window';
    }

    $window = $result->fetch_assoc();
    $stmt->close();

    $startSeconds = strtotime($window['start_time']);
    $endSeconds = strtotime($window['end_time']);
    $graceEndSeconds = $startSeconds + (GRACE_PERIOD_MINUTES * 60);

    // On-time: any time before window start, or within grace period after start
    if ($timeSeconds <= $graceEndSeconds) {
        return 'on_time';
    }

    // Late: after grace period but still within window
    if ($timeSeconds > $graceEndSeconds && $timeSeconds <= $endSeconds) {
        return 'late';
    }

    return 'outside_window';
}
?>
