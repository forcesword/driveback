<?php
/*
 * DriveBack - exec.php
 * Triggers a backup job run asynchronously via AJAX
 */

header('Content-Type: application/json');

$JOBS_DIR = "/boot/config/plugins/driveback/jobs";

function json_ok($extra = [])  { echo json_encode(array_merge(['ok' => true],  $extra)); exit; }
function json_err($msg)        { echo json_encode(['ok' => false, 'error' => $msg]);     exit; }

$body = file_get_contents('php://input');
$req  = json_decode($body, true);
if (!$req) json_err('Invalid request');

$action = $req['action'] ?? '';
$id     = preg_replace('/[^a-z0-9_-]/', '', $req['id'] ?? '');

if ($action !== 'run') json_err('Unknown action');
if (!$id)              json_err('Job ID required');

$conf = "$JOBS_DIR/$id.conf";
if (!file_exists($conf)) json_err('Job not found');

// Launch async — redirect stdout/stderr to log, run in background
$cmd = "/usr/local/sbin/driveback run " . escapeshellarg($id)
     . " > /var/log/driveback/" . escapeshellarg($id) . "_run.log 2>&1 &";

exec($cmd);

json_ok(['message' => 'Job started']);
