<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/schedule_runtime.php';
corsHeaders();

$pdo = getDB();
$now = new DateTimeImmutable('now', new DateTimeZone('Africa/Cairo'));
$today = $now->format('Y-m-d');
$total = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$currentSession = currentClassSession($pdo, $now);
$present = 0;
$students = [];
$attendance = [];
$sessionCount = 0;

if ($currentSession) {
    $sessionCountStmt = $pdo->prepare('SELECT COUNT(*) FROM class_sessions
                                       WHERE schedule_id = ? AND subject = ? AND class_type = ?');
    $sessionCountStmt->execute([
        $currentSession['schedule_id'],
        $currentSession['subject'],
        $currentSession['class_type'],
    ]);
    $sessionCount = (int)$sessionCountStmt->fetchColumn();

    $rows = $pdo->prepare('
        SELECT s.id, s.name, s.student_id,
               CASE WHEN current_attendance.id IS NULL THEN 0 ELSE 1 END AS present,
               COUNT(DISTINCT historical_session.id) AS attended_sessions
        FROM students s
        LEFT JOIN attendance current_attendance
               ON current_attendance.student_id = s.id AND current_attendance.session_id = ?
        LEFT JOIN attendance historical_attendance ON historical_attendance.student_id = s.id
        LEFT JOIN class_sessions historical_session
               ON historical_session.id = historical_attendance.session_id
              AND historical_session.schedule_id = ?
              AND historical_session.subject = ?
              AND historical_session.class_type = ?
        GROUP BY s.id, s.name, s.student_id, current_attendance.id
        ORDER BY s.name');
    $rows->execute([
        $currentSession['id'],
        $currentSession['schedule_id'],
        $currentSession['subject'],
        $currentSession['class_type'],
    ]);
    $students = $rows->fetchAll();

    foreach ($students as &$student) {
        $student['present'] = (int)$student['present'];
        $student['attendance_percentage'] = $sessionCount > 0
            ? round(((int)$student['attended_sessions'] / $sessionCount) * 100, 1)
            : 0;
        $present += $student['present'];
        $attendance[] = [
            'student_id' => $student['student_id'],
            'present_sessions' => (int)$student['attended_sessions'],
            'total_sessions' => $sessionCount,
            'attendance_percentage' => $student['attendance_percentage'],
        ];
        unset($student['id'], $student['attended_sessions']);
    }
    unset($student);
} else {
    $students = $pdo->query("SELECT name, student_id, 0 AS present FROM students ORDER BY name")->fetchAll();
}

$rate = $currentSession && $total > 0 ? round(($present / $total) * 100, 1) : 0;

jsonResponse([
    'total' => $total,
    'present' => $present,
    'absent' => $currentSession ? max(0, $total - $present) : 0,
    'rate' => $rate,
    'date' => $today,
    'current_session' => $currentSession,
    'session_total' => $sessionCount,
    'students' => $students,
    'attendance' => $attendance,
]);
