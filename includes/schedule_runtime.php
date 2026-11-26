<?php
require_once __DIR__ . '/schedule_config.php';

function activeScheduleEntry(PDO $pdo, ?DateTimeImmutable $now = null): ?array {
    $now ??= new DateTimeImmutable('now', new DateTimeZone('Africa/Cairo'));
    $weekday = (int)$now->format('w'); // Sunday=0 through Saturday=6
    $scheduleDay = ($weekday + 1) % 7; // Saturday=0 through Friday=6
    $time = $now->format('H:i:s');

    $stmt = $pdo->prepare('SELECT id, day_of_week, start_time, end_time, title, instructor_name, class_type, location
                           FROM class_schedule
                           WHERE day_of_week = ? AND start_time <= ? AND end_time > ?
                           ORDER BY start_time LIMIT 1');
    $stmt->execute([$scheduleDay, $time, $time]);
    $entry = $stmt->fetch();
    return $entry ?: null;
}

function ensureClassSession(PDO $pdo, array $schedule, ?DateTimeImmutable $now = null): array {
    $now ??= new DateTimeImmutable('now', new DateTimeZone('Africa/Cairo'));
    $date = $now->format('Y-m-d');

    $stmt = $pdo->prepare('SELECT id, schedule_id, session_date, start_time, end_time,
                                  subject, class_type, instructor_name, location
                           FROM class_sessions WHERE schedule_id = ? AND session_date = ? LIMIT 1');
    $stmt->execute([$schedule['id'], $date]);
    $session = $stmt->fetch();

    if (!$session) {
        try {
            $insert = $pdo->prepare('INSERT INTO class_sessions
                (schedule_id, session_date, start_time, end_time, subject, class_type, instructor_name, location)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([
                $schedule['id'], $date, $schedule['start_time'], $schedule['end_time'],
                $schedule['title'], $schedule['class_type'], $schedule['instructor_name'], $schedule['location'],
            ]);
        } catch (PDOException $exception) {
            // A concurrent dashboard poll may have created this same occurrence.
            $stmt->execute([$schedule['id'], $date]);
            $session = $stmt->fetch();
            if (!$session) throw $exception;
        }
        if (!$session) {
            $stmt->execute([$schedule['id'], $date]);
            $session = $stmt->fetch();
        }
    }

    $session['id'] = (int)$session['id'];
    $session['schedule_id'] = (int)$session['schedule_id'];
    $session['day_of_week'] = (int)$schedule['day_of_week'];
    $session['subject'] = $schedule['title'];
    $session['title'] = $schedule['title'];
    $session['instructor_name'] = $schedule['instructor_name'];
    $session['class_type'] = $schedule['class_type'];
    $session['location'] = $schedule['location'];
    return $session;
}

function currentClassSession(PDO $pdo, ?DateTimeImmutable $now = null): ?array {
    $now ??= new DateTimeImmutable('now', new DateTimeZone('Africa/Cairo'));
    $schedule = activeScheduleEntry($pdo, $now);
    return $schedule ? ensureClassSession($pdo, $schedule, $now) : null;
}
