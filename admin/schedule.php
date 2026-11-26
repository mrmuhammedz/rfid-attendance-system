<?php
$pageTitle = 'Weekly Schedule';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/schedule_config.php';

$periods = schedulePeriods();
$rows = getDB()->query('SELECT id, day_of_week, start_time, end_time, title, instructor_name, class_type, location
                        FROM class_schedule ORDER BY day_of_week, start_time')->fetchAll();
$scheduleByCell = [];
foreach ($rows as $row) {
    foreach ($periods as $periodIndex => $period) {
        if (substr($row['start_time'], 0, 5) === $period['start'] && substr($row['end_time'], 0, 5) === $period['end']) {
            $scheduleByCell[(int)$row['day_of_week'] . ':' . $periodIndex] = $row;
            break;
        }
    }
}
?>

<div class="card schedule-intro">
  <div>
    <div class="card-title">Shared Weekly Schedule</div>
    <p class="text-dim text-sm">The schedule is shared by all students.</p>
  </div>
  <a class="btn btn-primary" href="/attendance/admin/schedule_edit.php">✎ Edit Schedule</a>
</div>

<?php require __DIR__ . '/_schedule_grid.php'; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
