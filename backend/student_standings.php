<?php
/**
 * API endpoint to get a student's current standings in all awards
 *
 * GET /student_standings.php?student_id=12345
 *
 * Returns JSON with rank and value for each award category
 */

require_once 'db.php';
require_once '../frontend/window_transform.php';
require_once '../frontend/awards.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$studentId = $_GET['student_id'] ?? '';

if (empty($studentId)) {
    echo json_encode(['success' => false, 'message' => 'Student ID required']);
    exit;
}

// Load transformed data
$transformedData = loadTransformedData($conn);

// Get all award results
$consecutiveResults = awardConsecutiveWindows($transformedData);
$scoreResults = awardAttendanceScore($transformedData);
$timeResults = awardInWindowTimeTransformed($transformedData);

// Find student's name
$studentName = null;
foreach ($transformedData['students'] as $student) {
    if ($student['student_id'] === $studentId) {
        $studentName = $student['name'];
        break;
    }
}

if (!$studentName) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit;
}

// Find student's rank and value in each category
function findStudentRank($results, $studentName) {
    $rank = 1;
    foreach ($results as $item) {
        if ($item['name'] === $studentName) {
            return ['rank' => $rank, 'value' => $item['value']];
        }
        $rank++;
    }
    // Not in top 10, calculate actual rank
    return ['rank' => null, 'value' => null];
}

// Get student's actual values even if not in top 10
$studentWindows = $transformedData['student_windows'][$studentId] ?? [];
$completedWindowIds = array_flip(array_column($transformedData['window_occurrences'], 'id'));
$windowIds = array_column($transformedData['window_occurrences'], 'id');
sort($windowIds);

// Calculate consecutive meetings
$attendedIds = array_keys($studentWindows);
sort($attendedIds);
$attendedIds = array_filter($attendedIds, function($id) use ($completedWindowIds) {
    return isset($completedWindowIds[$id]);
});
$attendedIds = array_values($attendedIds);

$maxStreak = 0;
if (!empty($attendedIds)) {
    $positions = [];
    foreach ($attendedIds as $wid) {
        $pos = array_search($wid, $windowIds);
        if ($pos !== false) {
            $positions[] = $pos;
        }
    }
    sort($positions);

    $maxStreak = count($positions) > 0 ? 1 : 0;
    $currentStreak = 1;
    for ($i = 1; $i < count($positions); $i++) {
        if ($positions[$i] == $positions[$i - 1] + 1) {
            $currentStreak++;
            $maxStreak = max($maxStreak, $currentStreak);
        } else {
            $currentStreak = 1;
        }
    }
}

// Calculate attendance score
$score = 0;
$onTimeCount = 0;
$lateCount = 0;
foreach ($studentWindows as $windowId => $record) {
    if (!isset($completedWindowIds[$windowId])) continue;
    if ($record['status'] === 'on_time') {
        $score += 3;
        $onTimeCount++;
    } else {
        $score += 2;
        $lateCount++;
    }
}

// Calculate total time
$totalSeconds = 0;
foreach ($studentWindows as $windowId => $record) {
    if (!isset($completedWindowIds[$windowId])) continue;
    $totalSeconds += $record['total_seconds'];
}

// Format time
$hours = floor($totalSeconds / 3600);
$minutes = floor(($totalSeconds % 3600) / 60);
$timeFormatted = sprintf("%d:%02d", $hours, $minutes);

// Find ranks (search all students, not just top 10)
function calculateRank($studentValue, $allValues) {
    $rank = 1;
    foreach ($allValues as $value) {
        if ($value > $studentValue) {
            $rank++;
        }
    }
    return $rank;
}

// Get all student scores for ranking
$allScores = [];
$allTimes = [];
$allStreaks = [];

foreach ($transformedData['student_windows'] as $sid => $windows) {
    $s = 0;
    $t = 0;
    foreach ($windows as $wid => $rec) {
        if (!isset($completedWindowIds[$wid])) continue;
        $s += ($rec['status'] === 'on_time') ? 3 : 2;
        $t += $rec['total_seconds'];
    }
    $allScores[$sid] = $s;
    $allTimes[$sid] = $t;

    // Calculate streak for this student
    $aids = array_keys($windows);
    sort($aids);
    $aids = array_filter($aids, function($id) use ($completedWindowIds) {
        return isset($completedWindowIds[$id]);
    });
    $aids = array_values($aids);

    $streak = 0;
    if (!empty($aids)) {
        $pos = [];
        foreach ($aids as $wid) {
            $p = array_search($wid, $windowIds);
            if ($p !== false) $pos[] = $p;
        }
        sort($pos);
        $streak = count($pos) > 0 ? 1 : 0;
        $cs = 1;
        for ($i = 1; $i < count($pos); $i++) {
            if ($pos[$i] == $pos[$i - 1] + 1) {
                $cs++;
                $streak = max($streak, $cs);
            } else {
                $cs = 1;
            }
        }
    }
    $allStreaks[$sid] = $streak;
}

$consecutiveRank = calculateRank($maxStreak, $allStreaks);
$scoreRank = calculateRank($score, $allScores);
$timeRank = calculateRank($totalSeconds, $allTimes);

$totalStudents = count($transformedData['students']);

echo json_encode([
    'success' => true,
    'student_name' => $studentName,
    'standings' => [
        'consecutive' => [
            'rank' => $consecutiveRank,
            'value' => $maxStreak . ' meetings',
            'total' => $totalStudents
        ],
        'score' => [
            'rank' => $scoreRank,
            'value' => $score . ' pts',
            'total' => $totalStudents
        ],
        'time' => [
            'rank' => $timeRank,
            'value' => $timeFormatted,
            'total' => $totalStudents
        ]
    ]
]);

$conn->close();
?>
