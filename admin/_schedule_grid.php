<?php
$scheduleByCell = $scheduleByCell ?? [];
$editable = $editable ?? false;
$days = scheduleDays();
$periods = schedulePeriods();
?>
<div class="schedule-grid-wrap">
  <table class="schedule-grid-table">
    <thead>
      <tr>
        <th class="schedule-day-heading">Day</th>
        <?php foreach ($periods as $period): ?>
          <th><?= date('H:i', strtotime($period['start'])) ?>–<?= date('H:i', strtotime($period['end'])) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($days as $dayIndex => $day): ?>
        <tr>
          <th class="schedule-day-heading" scope="row"><?= htmlspecialchars($day) ?></th>
          <?php foreach ($periods as $periodIndex => $period): ?>
            <?php $lecture = $scheduleByCell[$dayIndex . ':' . $periodIndex] ?? null; ?>
            <td class="<?= $lecture ? 'schedule-cell-filled' : 'schedule-cell-empty' ?>">
              <?php if ($lecture): ?>
                <div class="schedule-cell-content">
                  <div class="schedule-cell-title"><?= htmlspecialchars($lecture['title']) ?></div>
                  <span class="schedule-cell-type">
                    <?= $lecture['class_type'] === 'section' ? 'Section' : 'Lecture' ?>
                  </span>
                  <div class="schedule-cell-instructor"><?= htmlspecialchars($lecture['instructor_name']) ?></div>
                  <?php if (!empty($lecture['location'])): ?>
                    <div class="schedule-cell-location"><?= htmlspecialchars($lecture['location']) ?></div>
                  <?php endif; ?>
                  <?php if ($editable): ?>
                    <div class="schedule-cell-actions">
                      <button class="schedule-icon-button" type="button" title="Edit" aria-label="Edit lecture" onclick="editLecture(<?= (int)$lecture['id'] ?>)">
                        <i class="fa-solid fa-pen"></i>
                      </button>
                      <button class="schedule-icon-button delete" type="button" title="Delete" aria-label="Delete lecture" onclick="deleteLecture(<?= (int)$lecture['id'] ?>)">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
