const API = {
    scan: "/attendance/api/scan.php",
    stats: "/attendance/api/stats.php",
    students: "/attendance/api/students.php",
    pending: "/attendance/api/pending_scan.php",
};

// ── Generic fetch helpers ────────────────────────────────────
async function apiFetch(url, options = {}) {
    const res = await fetch(url, {
        headers: { "Content-Type": "application/json" },
        ...options,
    });
    return res.json();
}

// ── Format timestamp ─────────────────────────────────────────
function fmtTime(ts) {
    if (!ts) return "";
    const d = new Date(ts);
    return d.toLocaleTimeString("en-US", {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
    });
}

// ── Update scan banner ───────────────────────────────────────
let lastUID = null;
let currentSessionActive = false;
let currentSessionId = null;

function escapeHTML(value) {
    return String(value ?? "").replace(/[&<>"']/g, (char) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
    })[char]);
}

function formatScheduleTime(value) {
    return value ? value.slice(0, 5) : "";
}

function updateCurrentSession(session) {
    const widget = document.getElementById("currentSessionWidget");
    const nextSessionId = session ? Number(session.id) : null;
    if (nextSessionId !== currentSessionId) lastUID = null;
    currentSessionId = nextSessionId;
    currentSessionActive = Boolean(session);

    if (!widget) return;
    if (!session) {
        widget.className = "current-session-widget inactive";
        widget.innerHTML = `
          <div class="current-session-icon"><i class="fa-regular fa-clock"></i></div>
          <div class="current-session-copy">
            <div class="current-session-kicker">No active class</div>
            <div class="current-session-title">No lecture or section is scheduled right now</div>
            <div class="current-session-meta">Attendance scanning is paused until the next scheduled time.</div>
          </div>`;
        return;
    }

    const type = session.class_type === "section" ? "Section" : "Lecture";
    const location = session.location ? ` · ${escapeHTML(session.location)}` : "";
    const instructor = session.instructor_name ? ` · ${escapeHTML(session.instructor_name)}` : "";
    widget.className = "current-session-widget active";
    widget.innerHTML = `
      <div class="current-session-icon"><i class="fa-solid fa-book-open"></i></div>
      <div class="current-session-copy">
        <div class="current-session-kicker">Now in progress · ${type}</div>
        <div class="current-session-title">${escapeHTML(session.subject)}</div>
        <div class="current-session-meta">${formatScheduleTime(session.start_time)}–${formatScheduleTime(session.end_time)}${instructor}${location}</div>
      </div>
      <span class="current-session-live">LIVE</span>`;
}

function updateScanBanner(data) {
    const banner = document.getElementById("scanBanner");
    if (!banner) return;

    if (!currentSessionActive) {
        banner.className = "scan-banner disabled";
        banner.innerHTML = `
      <div class="scan-dot"></div>
      <div class="scan-uid" style="color:var(--text-dim);font-size:.8rem;letter-spacing:3px">
        CARD SCANNING IS DISABLED OUTSIDE SCHEDULED CLASS TIMES
      </div>
      <span class="scan-badge idle">DISABLED</span>`;
        return;
    }

    if (!data || Number(data.session_id) !== currentSessionId) data = null;

    if (!data || !data.uid) {
        banner.className = "scan-banner";
        banner.innerHTML = `
      <div class="scan-dot"></div>
      <div class="scan-uid" style="color:var(--text-dim);font-size:.8rem;letter-spacing:3px">
        WAITING FOR CARD SCAN...
      </div>
      <span class="scan-badge idle">IDLE</span>`;
        return;
    }

    if (data.uid === lastUID) return;
    lastUID = data.uid;

    const ok = data.status === "success";
    banner.className = "scan-banner " + (ok ? "success" : "unknown");
    banner.innerHTML = `
    <div class="scan-dot"></div>
    <div class="scan-uid">${escapeHTML(data.uid)}</div>
    <div class="scan-info">
      <div class="scan-name">${escapeHTML(data.name || "Unknown Card")}</div>
      <div class="scan-meta">${escapeHTML(data.student_id || "")} ${data.timestamp ? "· " + fmtTime(data.timestamp) : ""}</div>
    </div>
    <span class="scan-badge ${ok ? "ok" : "bad"}">${ok ? "AUTHORIZED" : "UNKNOWN"}</span>`;

    // Reset after 5s
    setTimeout(() => {
        if (banner) updateScanBanner(null);
    }, 5000);
}

// ── Update stats ─────────────────────────────────────────────
function updateStats(data) {
    const set = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    };
    set("statTotal", data.total ?? "—");
    set("statPresent", data.present ?? "—");
    set("statAbsent", data.absent ?? "—");
    const session = data.current_session;
    set(
        "statSessionSub",
        session
            ? `${session.subject} · ${formatScheduleTime(session.start_time)}–${formatScheduleTime(session.end_time)}`
            : "No active class",
    );
}

// ── Render student attendance table ─────────────────────────
function renderTable(students, attendance) {
    const tbody = document.getElementById("attendanceTable");
    if (!tbody) return;
    const today = new Date().toLocaleDateString("en-GB");

    // Create a map of attendance percentages by student_id
    const attendanceMap = {};
    if (attendance && attendance.length > 0) {
        attendance.forEach((a) => {
            attendanceMap[a.student_id] = a.attendance_percentage;
        });
    }

    tbody.innerHTML = students
        .map(
            (s) => {
                const present = currentSessionActive && Boolean(Number(s.present));
                const percentage = currentSessionActive ? attendanceMap[s.student_id] : undefined;
                const color = (percentage ?? 0) >= 70 ? "#10b981" : "#ef4444";
                return `
    <tr>
      <td>${escapeHTML(s.name)}</td>
      <td class="mono" style="text-align:center">${escapeHTML(s.student_id)}</td>
      <td class="mono text-dim" style="text-align:center">${today}</td>
      <td style="text-align:center">
        <span class="badge ${currentSessionActive ? (present ? "present" : "absent") : "inactive"} ${currentSessionActive ? "dot" : ""}">
          ${currentSessionActive ? (present ? "Present" : "Absent") : "No Class"}
        </span>
      </td>
      <td style="text-align:center">
        ${percentage !== undefined ? `<div style="display:flex;align-items:center;gap:8px;justify-content:center">
          <span style="min-width:40px;color:${color};font-weight:bold">${percentage}%</span>
          <div style="width:100px;height:5px;background:#eee;border-radius:4px;overflow:hidden">
            <div style="height:100%;width:${percentage}%;background:${color};transition:width 0.3s ease"></div>
          </div>
        </div>` : "—"}
      </td>
    </tr>`;
            }
        )
        .join("");
}

// ── Poll scan & stats ────────────────────────────────────────
async function pollDashboard() {
    try {
        const [scanData, statsData] = await Promise.all([
            apiFetch(API.scan),
            apiFetch(API.stats),
        ]);
        updateCurrentSession(statsData.current_session);
        updateScanBanner(scanData);
        updateStats(statsData);
        renderTable(statsData.students || [], statsData.attendance || []);
    } catch (e) {
        console.warn("Poll error:", e);
    }
}

// ── Poll pending scan (for Admin Add/Edit pages) ─────────────
let pendingCallback = null;
let pendingPollInFlight = false;
let pendingPollTimer = null;
let pendingListenerGeneration = 0;

async function pollPendingScan() {
    // Only one poll may handle a pending scan at a time. Otherwise overlapping
    // requests can deliver the same cached UID more than once.
    if (pendingPollInFlight || !pendingCallback) return;
    pendingPollInFlight = true;
    const generation = pendingListenerGeneration;
    try {
        const data = await apiFetch(API.pending);
        if (data.uid && pendingCallback && generation === pendingListenerGeneration) {
            await apiFetch(API.pending, { method: "DELETE" });
            if (generation === pendingListenerGeneration && pendingCallback) {
                pendingCallback(data.uid);
            }
        }
    } catch (e) {
        console.warn("Pending scan poll error:", e);
    } finally {
        pendingPollInFlight = false;
    }
}

function onPendingScan(cb) {
    const generation = ++pendingListenerGeneration;
    pendingCallback = cb;
    if (pendingPollTimer !== null) clearInterval(pendingPollTimer);
    pendingPollTimer = null;

    // Clear stale cards, explicitly arm management capture, then begin polling.
    apiFetch(API.pending, { method: "DELETE" })
        .then(() => apiFetch(API.pending, { method: "POST" }))
        .then((response) => {
            if (response.status !== "listening") throw new Error(response.message || "Could not start card listening.");
            if (generation === pendingListenerGeneration && pendingCallback) {
                pendingPollTimer = setInterval(pollPendingScan, 1000);
            }
        })
        .catch((error) => {
            console.warn("Could not start pending scan listener:", error);
            showAlert("error", "Could not start card scanning. Refresh the page and sign in again.");
        });
}

async function stopPendingScan() {
    pendingListenerGeneration++;
    pendingCallback = null;
    if (pendingPollTimer !== null) clearInterval(pendingPollTimer);
    pendingPollTimer = null;
    try {
        await apiFetch(API.pending, { method: "PUT" });
    } catch (error) {
        console.warn("Could not stop pending scan listener:", error);
    }
}

window.addEventListener("pagehide", () => {
    if (pendingCallback) {
        fetch(API.pending, { method: "PUT", keepalive: true }).catch(() => {});
    }
});

// ── Delete selected students ─────────────────────────────────
async function deleteSelected() {
    const checks = [...document.querySelectorAll(".student-check:checked")];
    if (!checks.length) return alert("Select at least one student.");

    const ids = checks.map((c) => parseInt(c.value));
    if (!confirm(`Delete ${ids.length} student(s)? This cannot be undone.`))
        return;

    try {
        const res = await apiFetch(API.students, {
            method: "DELETE",
            body: JSON.stringify({ ids }),
        });
        if (res.status === "ok") {
            showAlert("success", res.message);
            checks.forEach((c) => c.closest("tr").remove());
            // update count
            const remaining =
                document.querySelectorAll(".student-check").length;
            const el = document.getElementById("statTotal");
            if (el) el.textContent = remaining;
        } else {
            showAlert("error", res.message);
        }
    } catch (e) {
        showAlert("error", "Request failed");
    }
}

// ── Select all checkbox ──────────────────────────────────────
function toggleSelectAll(master) {
    document
        .querySelectorAll(".student-check")
        .forEach((c) => (c.checked = master.checked));
}

// ── Show alert ───────────────────────────────────────────────
function showAlert(type, msg) {
    const container = document.getElementById("alertContainer");
    if (!container) return;
    const div = document.createElement("div");
    div.className = `alert alert-${type}`;
    div.textContent = msg;
    container.prepend(div);
    setTimeout(() => div.remove(), 4000);
}

// ── Start dashboard polling ──────────────────────────────────
function startDashboard(intervalMs = 2000) {
    pollDashboard();
    setInterval(pollDashboard, intervalMs);
}
