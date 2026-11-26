<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schedule_config.php';
corsHeaders();

if (!isLoggedIn()) {
    jsonResponse(['status' => 'error', 'message' => 'Authentication required'], 401);
}

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];

function scheduleInput(): array {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['status' => 'error', 'message' => 'Invalid request body'], 400);
    }

    $day = filter_var($input['day_of_week'] ?? null, FILTER_VALIDATE_INT);
    $periodIndex = filter_var($input['period_index'] ?? null, FILTER_VALIDATE_INT);
    $title = is_string($input['title'] ?? null) ? trim($input['title']) : '';
    $instructor = is_string($input['instructor_name'] ?? null) ? trim($input['instructor_name']) : '';
    $type = $input['class_type'] ?? '';
    $location = is_string($input['location'] ?? null) ? trim($input['location']) : '';
    $periods = schedulePeriods();

    if ($day === false || $day < 0 || $day > 6
        || $periodIndex === false || !isset($periods[$periodIndex])
        || $title === '' || strlen($title) > 150
        || $instructor === '' || strlen($instructor) > 150
        || !in_array($type, ['lecture', 'section'], true)
        || strlen($location) > 100) {
        jsonResponse(['status' => 'error', 'message' => 'Enter a valid day, time, subject, type, and instructor.'], 400);
    }

    return [
        'day_of_week' => $day,
        'period_index' => $periodIndex,
        'start_time' => $periods[$periodIndex]['start'],
        'end_time' => $periods[$periodIndex]['end'],
        'title' => $title,
        'instructor_name' => $instructor,
        'class_type' => $type,
        'location' => $location === '' ? null : $location,
    ];
}

function hasScheduleOverlap(PDO $pdo, array $entry, ?int $excludeId = null): bool {
    $sql = 'SELECT id FROM class_schedule
            WHERE day_of_week = ? AND start_time < ? AND end_time > ?';
    $params = [$entry['day_of_week'], $entry['end_time'], $entry['start_time']];
    if ($excludeId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

if ($method === 'GET') {
    $stmt = $pdo->query('SELECT id, day_of_week, start_time, end_time, title, instructor_name, class_type, location
                         FROM class_schedule ORDER BY day_of_week, start_time');
    jsonResponse(['status' => 'ok', 'schedule' => $stmt->fetchAll()]);
}

if ($method === 'POST' || $method === 'PUT') {
    $entry = scheduleInput();
    $id = null;

    if ($method === 'PUT') {
        // The update ID is carried in the query string; the request body contains the fields.
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            jsonResponse(['status' => 'error', 'message' => 'A valid lecture ID is required.'], 400);
        }
        $exists = $pdo->prepare('SELECT id FROM class_schedule WHERE id = ?');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) {
            jsonResponse(['status' => 'error', 'message' => 'Lecture not found.'], 404);
        }
    }

    if (hasScheduleOverlap($pdo, $entry, $id)) {
        jsonResponse(['status' => 'error', 'message' => 'This time overlaps another lecture on the selected day.'], 409);
    }

    if ($method === 'POST') {
        $stmt = $pdo->prepare('INSERT INTO class_schedule (day_of_week, start_time, end_time, title, instructor_name, class_type, location)
                               VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$entry['day_of_week'], $entry['start_time'], $entry['end_time'], $entry['title'], $entry['instructor_name'], $entry['class_type'], $entry['location']]);
        jsonResponse(['status' => 'ok', 'message' => 'Lecture added.', 'id' => (int)$pdo->lastInsertId()]);
    }

    $stmt = $pdo->prepare('UPDATE class_schedule
                           SET day_of_week = ?, start_time = ?, end_time = ?, title = ?, instructor_name = ?, class_type = ?, location = ?
                           WHERE id = ?');
    $stmt->execute([$entry['day_of_week'], $entry['start_time'], $entry['end_time'], $entry['title'], $entry['instructor_name'], $entry['class_type'], $entry['location'], $id]);
    jsonResponse(['status' => 'ok', 'message' => 'Lecture updated.']);
}

if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        jsonResponse(['status' => 'error', 'message' => 'A valid lecture ID is required.'], 400);
    }

    $pdo->beginTransaction();
    try {
        $exists = $pdo->prepare('SELECT id FROM class_schedule WHERE id = ? FOR UPDATE');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) {
            $pdo->rollBack();
            jsonResponse(['status' => 'error', 'message' => 'Lecture not found.'], 404);
        }

        $history = $pdo->prepare('SELECT COUNT(*) FROM attendance a
                                  INNER JOIN class_sessions cs ON cs.id = a.session_id
                                  WHERE cs.schedule_id = ?');
        $history->execute([$id]);
        if ((int)$history->fetchColumn() > 0) {
            $pdo->rollBack();
            jsonResponse(['status' => 'error', 'message' => 'This schedule entry has attendance records and cannot be deleted.'], 409);
        }

        $deleteSessions = $pdo->prepare('DELETE FROM class_sessions WHERE schedule_id = ?');
        $deleteSessions->execute([$id]);
        $deleteSchedule = $pdo->prepare('DELETE FROM class_schedule WHERE id = ?');
        $deleteSchedule->execute([$id]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    jsonResponse(['status' => 'ok', 'message' => 'Lecture deleted.']);
}

jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
