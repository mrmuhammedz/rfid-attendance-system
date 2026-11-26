<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/schedule_runtime.php';
corsHeaders();

// GET: fetch the latest scan event for dashboard polling.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cacheFile = sys_get_temp_dir() . '/rfid_last_scan.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 30) {
        header('Content-Type: application/json');
        echo file_get_contents($cacheFile);
    } else {
        jsonResponse(['uid' => null]);
    }
    exit;
}

// POST: ESP8266 sends a UID. The server enforces the current schedule window.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $rawUid = is_array($input) ? ($input['uid'] ?? null) : null;
    $uid = is_string($rawUid) ? strtoupper(trim($rawUid)) : '';

    if ($uid === '') {
        jsonResponse(['status' => 'error', 'message' => 'UID required'], 400);
    }

    $pdo = getDB();
    $now = new DateTimeImmutable('now', new DateTimeZone('Africa/Cairo'));

    // Admin Add/Edit scans capture a UID only. This mode takes priority even
    // during a class so a new/edit card cannot accidentally mark attendance.
    $listenerFile = sys_get_temp_dir() . '/rfid_management_listener';
    if (file_exists($listenerFile) && (time() - filemtime($listenerFile)) <= 10) {
        $result = [
            'status' => 'management',
            'uid' => $uid,
            'timestamp' => $now->format('Y-m-d H:i:s'),
        ];
        file_put_contents(sys_get_temp_dir() . '/rfid_pending_scan.json', json_encode($result), LOCK_EX);
        jsonResponse($result);
    }
    if (file_exists($listenerFile)) unlink($listenerFile);

    $session = currentClassSession($pdo, $now);

    if (!$session) {
        jsonResponse([
            'status' => 'inactive',
            'message' => 'Attendance is closed because no lecture or section is scheduled now.',
            'timestamp' => $now->format('Y-m-d H:i:s'),
        ]);
    }

    $studentStmt = $pdo->prepare('SELECT id, uid, name, student_id FROM students WHERE uid = ?');
    $studentStmt->execute([$uid]);
    $student = $studentStmt->fetch();

    if (!$student) {
        $result = [
            'status' => 'unknown',
            'uid' => $uid,
            'message' => 'Card not registered',
            'session_id' => $session['id'],
            'class_title' => $session['subject'],
            'class_type' => $session['class_type'],
            'timestamp' => $now->format('Y-m-d H:i:s'),
        ];
        file_put_contents(sys_get_temp_dir() . '/rfid_last_scan.json', json_encode($result));
        jsonResponse($result);
    }

    $check = $pdo->prepare('SELECT id FROM attendance WHERE student_id = ? AND session_id = ? LIMIT 1');
    $check->execute([$student['id'], $session['id']]);
    $alreadyMarked = (bool)$check->fetchColumn();

    if (!$alreadyMarked) {
        try {
            $insert = $pdo->prepare("INSERT INTO attendance (student_id, session_id, date, status)
                                     VALUES (?, ?, ?, 'present')");
            $insert->execute([$student['id'], $session['id'], $now->format('Y-m-d')]);
        } catch (PDOException $exception) {
            // Treat a concurrent repeat scan as already recorded.
            $check->execute([$student['id'], $session['id']]);
            if (!$check->fetchColumn()) throw $exception;
            $alreadyMarked = true;
        }
    }

    $result = [
        'status' => 'success',
        'uid' => $uid,
        'name' => $student['name'],
        'student_id' => $student['student_id'],
        'known' => true,
        'already' => $alreadyMarked,
        'session_id' => $session['id'],
        'class_title' => $session['subject'],
        'class_type' => $session['class_type'],
        'timestamp' => $now->format('Y-m-d H:i:s'),
    ];

    file_put_contents(sys_get_temp_dir() . '/rfid_last_scan.json', json_encode($result));
    jsonResponse($result);
}

jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
