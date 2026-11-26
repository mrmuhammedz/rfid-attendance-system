<?php
$pageTitle = 'Edit Schedule';
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
$scheduleJson = json_encode($rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$periodJson = json_encode($periods, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>

<div id="alertContainer"></div>

<div class="card schedule-editor-card">
  <div class="card-header">
    <div>
      <div class="card-title">Add or Edit a Schedule Entry</div>
      <p class="text-dim text-sm">Choose a day and time slot, then enter the subject, instructor, and room.</p>
    </div>
    <a class="btn btn-ghost" href="/attendance/admin/schedule.php">▤ View Schedule</a>
  </div>
  <div class="card-body">
    <form id="lectureForm" class="schedule-form">
      <input type="hidden" id="lectureID">
      <div class="form-group">
        <label class="form-label" for="lectureDay">Day</label>
        <select class="form-input" id="lectureDay" required>
          <option value="0">Saturday</option>
          <option value="1">Sunday</option>
          <option value="2">Monday</option>
          <option value="3">Tuesday</option>
          <option value="4">Wednesday</option>
          <option value="5">Thursday</option>
          <option value="6">Friday</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="lecturePeriod">Time</label>
        <select class="form-input" id="lecturePeriod" required>
          <?php foreach ($periods as $index => $period): ?>
            <option value="<?= $index ?>"><?= $period['start'] ?>–<?= $period['end'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="lectureTitle">Subject</label>
        <input class="form-input" id="lectureTitle" type="text" maxlength="150" required placeholder="e.g. Windows Programming I">
      </div>
      <div class="form-group">
        <label class="form-label" for="lectureType">Type</label>
        <select class="form-input" id="lectureType" required>
          <option value="lecture">Lecture</option>
          <option value="section">Section</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="lectureInstructor">Doctor / Instructor</label>
        <input class="form-input" id="lectureInstructor" type="text" maxlength="150" required placeholder="e.g. Dr. Ayman Brisha">
      </div>
      <div class="form-group">
        <label class="form-label" for="lectureLocation">Room</label>
        <input class="form-input" id="lectureLocation" type="text" maxlength="100" placeholder="e.g. H2B">
      </div>
      <div class="schedule-form-actions">
        <button class="btn btn-primary" id="saveLectureButton" type="submit">＋ Add to Schedule</button>
        <button class="btn btn-ghost" id="cancelEditButton" type="button" style="display:none" onclick="resetLectureForm()">Cancel Edit</button>
      </div>
    </form>
  </div>
</div>

<div class="card schedule-list-card">
  <div class="card-header"><div class="card-title">Weekly Schedule</div></div>
  <?php $editable = true; require __DIR__ . '/_schedule_grid.php'; ?>
</div>

<?php
$extraScript = '<script>
const scheduleEntries = ' . ($scheduleJson ?: '[]') . ';
const schedulePeriods = ' . ($periodJson ?: '[]') . ';

function resetLectureForm() {
  document.getElementById("lectureForm").reset();
  document.getElementById("lectureID").value = "";
  document.getElementById("saveLectureButton").textContent = "＋ Add to Schedule";
  document.getElementById("cancelEditButton").style.display = "none";
}

function editLecture(id) {
  const lecture = scheduleEntries.find(item => Number(item.id) === id);
  if (!lecture) return;
  const periodIndex = schedulePeriods.findIndex(period =>
    period.start === lecture.start_time.slice(0, 5) && period.end === lecture.end_time.slice(0, 5));
  if (periodIndex < 0) {
    showAlert("error", "This entry uses a time outside the available schedule periods.");
    return;
  }

  document.getElementById("lectureID").value = lecture.id;
  document.getElementById("lectureDay").value = lecture.day_of_week;
  document.getElementById("lecturePeriod").value = periodIndex;
  document.getElementById("lectureTitle").value = lecture.title;
  document.getElementById("lectureType").value = lecture.class_type;
  document.getElementById("lectureInstructor").value = lecture.instructor_name || "";
  document.getElementById("lectureLocation").value = lecture.location || "";
  document.getElementById("saveLectureButton").textContent = "Save Changes";
  document.getElementById("cancelEditButton").style.display = "";
  document.getElementById("lectureForm").scrollIntoView({ behavior: "smooth", block: "center" });
  document.getElementById("lectureTitle").focus({ preventScroll: true });
}

async function deleteLecture(id) {
  if (!confirm("Delete this schedule entry?")) return;
  try {
    const response = await apiFetch("/attendance/api/schedule.php", {
      method: "DELETE",
      body: JSON.stringify({ id })
    });
    if (response.status !== "ok") throw new Error(response.message || "Could not delete schedule entry.");
    window.location.reload();
  } catch (error) {
    showAlert("error", error.message || "Could not delete schedule entry.");
  }
}

document.getElementById("lectureForm").addEventListener("submit", async event => {
  event.preventDefault();
  const id = document.getElementById("lectureID").value;
  const payload = {
    day_of_week: Number(document.getElementById("lectureDay").value),
    period_index: Number(document.getElementById("lecturePeriod").value),
    title: document.getElementById("lectureTitle").value.trim(),
    class_type: document.getElementById("lectureType").value,
    instructor_name: document.getElementById("lectureInstructor").value.trim(),
    location: document.getElementById("lectureLocation").value.trim()
  };

  try {
    const response = await apiFetch("/attendance/api/schedule.php" + (id ? `?id=${encodeURIComponent(id)}` : ""), {
      method: id ? "PUT" : "POST",
      body: JSON.stringify(payload)
    });
    if (response.status !== "ok") throw new Error(response.message || "Could not save schedule entry.");
    window.location.reload();
  } catch (error) {
    showAlert("error", error.message || "Could not save schedule entry.");
  }
});
</script>';
require_once __DIR__ . '/_footer.php';
?>
