<?php
/*
 * DriveBack - jobs.php
 * Handles job CRUD and log retrieval via AJAX
 */

$JOBS_DIR = "/boot/config/plugins/driveback/jobs";
$LOG_DIR  = "/var/log/driveback";

header('Content-Type: application/json');
mkdir($JOBS_DIR, 0755, true);

function json_ok($extra = [])  { echo json_encode(array_merge(['ok' => true],  $extra)); exit; }
function json_err($msg)        { echo json_encode(['ok' => false, 'error' => $msg]);     exit; }

function sanitize_id($name) {
  $id = preg_replace('/[^a-z0-9_-]/', '_', strtolower(trim($name)));
  return substr($id, 0, 64);
}

function write_job($path, $data) {
  $lines = [];
  foreach ($data as $k => $v) {
    $lines[] = "$k=" . escapeshellarg($v);
  }
  return file_put_contents($path, implode("\n", $lines) . "\n") !== false;
}

function rebuild_cron() {
  exec('/usr/local/sbin/driveback rebuild-cron');
}

// ── GET: log ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $action = $_GET['action'] ?? '';
  $id     = preg_replace('/[^a-z0-9_-]/', '', $_GET['id'] ?? '');

  if ($action === 'log') {
    global $LOG_DIR;
    $logfile = "$LOG_DIR/$id.log";
    if (file_exists($logfile)) {
      $content = file_get_contents($logfile);
      // Return last 200 lines max
      $lines = explode("\n", $content);
      $lines = array_slice($lines, -200);
      json_ok(['log' => implode("\n", $lines)]);
    } else {
      json_ok(['log' => '(no log file found — job may not have run yet)']);
    }
  }
  json_err('Unknown action');
}

// ── POST: save / toggle / delete ─────────────────────────────────────────────
$body = file_get_contents('php://input');
$req  = json_decode($body, true);
if (!$req) json_err('Invalid request');

$action = $req['action'] ?? '';

switch ($action) {

  // ── save (create or update) ────────────────────────────────────────────────
  case 'save': {
    $name     = trim($req['JOB_NAME'] ?? '');
    $source   = trim($req['SOURCE']   ?? '');
    $dest     = trim($req['DEST']     ?? '');
    $schedule = trim($req['SCHEDULE'] ?? '0 2 * * *');
    $enabled  = in_array($req['ENABLED'] ?? 'yes', ['yes','no']) ? $req['ENABLED'] : 'yes';
    $edit_id  = trim($req['id'] ?? '');

    if (!$name)   json_err('Job name required');
    if (!$source) json_err('Source required');
    if (!$dest)   json_err('Destination required');

    // Basic cron format validation (5 fields)
    if (count(explode(' ', $schedule)) !== 5) json_err('Invalid schedule format');

    $id = $edit_id ?: sanitize_id($name);

    // If new job and ID collision, append timestamp
    $path = "$JOBS_DIR/$id.conf";
    if (!$edit_id && file_exists($path)) {
      $id   = $id . '_' . time();
      $path = "$JOBS_DIR/$id.conf";
    }

    // Preserve LAST_STATUS and LAST_RUN if editing
    $existing = [];
    if ($edit_id && file_exists($path)) {
      $existing = parse_ini_file($path) ?: [];
    }

    $data = [
      'JOB_NAME'    => $name,
      'SOURCE'      => $source,
      'DEST'        => $dest,
      'SCHEDULE'    => $schedule,
      'ENABLED'     => $enabled,
      'LAST_STATUS' => $existing['LAST_STATUS'] ?? 'never',
      'LAST_RUN'    => $existing['LAST_RUN']    ?? '',
    ];

    if (!write_job($path, $data)) json_err('Failed to write job config');
    rebuild_cron();
    json_ok(['id' => $id]);
  }

  // ── toggle enabled ──────────────────────────────────────────────────────────
  case 'toggle': {
    $id      = preg_replace('/[^a-z0-9_-]/', '', $req['id'] ?? '');
    $enabled = in_array($req['enabled'] ?? '', ['yes','no']) ? $req['enabled'] : 'no';
    $path    = "$JOBS_DIR/$id.conf";

    if (!file_exists($path)) json_err('Job not found');

    $data = parse_ini_file($path);
    if (!$data) json_err('Failed to read job');

    $data['ENABLED'] = $enabled;
    if (!write_job($path, $data)) json_err('Failed to update job');
    rebuild_cron();
    json_ok();
  }

  // ── delete ──────────────────────────────────────────────────────────────────
  case 'delete': {
    $id   = preg_replace('/[^a-z0-9_-]/', '', $req['id'] ?? '');
    $path = "$JOBS_DIR/$id.conf";

    if (!file_exists($path)) json_err('Job not found');
    if (!unlink($path)) json_err('Failed to delete job');

    // Clean up log
    $logfile = "/var/log/driveback/$id.log";
    if (file_exists($logfile)) unlink($logfile);

    rebuild_cron();
    json_ok();
  }

  default:
    json_err('Unknown action');
}
