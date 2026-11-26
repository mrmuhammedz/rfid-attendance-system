<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/_header.php';
date_default_timezone_set('Africa/Cairo');
$today = date('l, F j, Y');
?>

<!-- Live scan banner -->
<div id="currentSessionWidget" class="current-session-widget pending">
  <div class="current-session-icon"><i class="fa-regular fa-clock"></i></div>
  <div class="current-session-copy">
    <div class="current-session-kicker">Current schedule</div>
    <div class="current-session-title">Checking the schedule…</div>
  </div>
</div>

<div id="scanBanner" class="scan-banner disabled">
  <div class="scan-dot"></div>
  <div class="scan-uid" style="color:var(--text-dim);font-size:.8rem;letter-spacing:3px">CHECKING CURRENT CLASS...</div>
  <span class="scan-badge idle">CHECKING</span>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card blue">
    <div class="stat-label">Total Students</div>
    <div class="stat-value" id="statTotal">—</div>
    <div class="stat-sub">Registered in DB</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Present in Session</div>
    <div class="stat-value" id="statPresent">—</div>
    <div class="stat-sub" id="statSessionSub">—</div>
  </div>
  <div class="stat-card red">
    <div class="stat-label">Absent in Session</div>
    <div class="stat-value" id="statAbsent">—</div>
    <div class="stat-sub">Not scanned in this class</div>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Today's Attendance</div>
    <div class="text-mono text-sm text-dim"><?= $today ?></div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student Name</th>
          <th style="text-align:center">Student ID</th>
          <th style="text-align:center">Date</th>
          <th style="text-align:center">Status</th>
          <th style="text-align:center">Course / Section %</th>
        </tr>
      </thead>
      <tbody id="attendanceTable">
        <tr>
          <td colspan="5" style="text-align:center;color:var(--text-dim);padding:40px">Loading...</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php
$extraScript = '<script>startDashboard(2000);</script>';
require_once __DIR__ . '/_footer.php';
?>
