<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
corsHeaders();

if (!isLoggedIn()) {
    jsonResponse(['status' => 'error', 'message' => 'Authentication required'], 401);
}

$pendingFile = sys_get_temp_dir() . '/rfid_pending_scan.json';
$listenerFile = sys_get_temp_dir() . '/rfid_management_listener';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // The authenticated Add/Edit page polls while waiting for a card. This short
    // lease tells scan.php to capture a UID for management instead of attendance.
    file_put_contents($listenerFile, (string)time(), LOCK_EX);

    if (file_exists($pendingFile) && (time() - filemtime($pendingFile)) < 60) {
        header('Content-Type: application/json');
        echo file_get_contents($pendingFile);
    } else {
        jsonResponse(['uid' => null]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Explicitly arm management-card capture before starting the poll loop.
    file_put_contents($listenerFile, (string)time(), LOCK_EX);
    jsonResponse(['status' => 'listening']);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Consume a scanned UID but keep listening; an invalid card may be followed by another scan.
    if (file_exists($pendingFile)) unlink($pendingFile);
    jsonResponse(['status' => 'cleared']);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Stop management capture when Add/Edit has received a usable card.
    if (file_exists($listenerFile)) unlink($listenerFile);
    if (file_exists($pendingFile)) unlink($pendingFile);
    jsonResponse(['status' => 'stopped']);
}

jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
