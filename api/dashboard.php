<?php
declare(strict_types=1);

// auth.php handles the session check and redirects safely
require_once __DIR__ . '/auth.php';

try {
    $pdo = db();
    $userId = (int)$_SESSION['user_id'];
    $userName = $_SESSION['user_name'] ?? 'User';

    // 1. Verify the user exists and fetch settings
    // If the table 'users' doesn't exist, this will throw an exception caught below
    $stmtUser = $pdo->prepare("SELECT total_required_hours FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();

    if (!$user) {
        // If the user was deleted from the DB, clear session and kick to login
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    // This stops the infinite redirect loop by showing the actual error
    die("Database Error: " . $e->getMessage() . ". Please check if your 'users' table exists in the database.");
}

$totalRequired = (float)$user['total_required_hours'];
$errors = [];
$success = '';

// Helper to calculate hours rendered
function computeHours(?string $in, ?string $out): float
{
    if (!$in || !$out) return 0.0;
    $timeIn = new DateTime($in);
    $timeOut = new DateTime($out);
    $seconds = max(0, $timeOut->getTimestamp() - $timeIn->getTimestamp());
    return round($seconds / 3600, 2);
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save Total Required Hours Settings
    if (isset($_POST['save_settings'])) {
        $newRequired = trim($_POST['total_required_hours'] ?? '');

        if ($newRequired === '' || !is_numeric($newRequired) || (float)$newRequired < 0) {
            $errors[] = 'Total required hours must be a non-negative number.';
        } else {
            $totalRequired = (float)$newRequired;
            $updateReq = $pdo->prepare('UPDATE users SET total_required_hours = ? WHERE id = ?');
            $updateReq->execute([$totalRequired, $userId]);
            $success = 'Settings updated.';
        }
    }

    // Add Manual Shift
    if (isset($_POST['manual_add'])) {
        $manualIn = trim($_POST['manual_time_in'] ?? '');
        $manualOut = trim($_POST['manual_time_out'] ?? '');
        $manualIn = $manualIn !== '' ? str_replace('T', ' ', $manualIn) . ':00' : null;
        $manualOut = $manualOut !== '' ? str_replace('T', ' ', $manualOut) . ':00' : null;

        if (!$manualIn) {
            $errors[] = 'Manual Time In is required.';
        } elseif ($manualOut && strtotime($manualOut) < strtotime($manualIn)) {
            $errors[] = 'Manual Time Out cannot be earlier than Time In.';
        } else {
            $hours = computeHours($manualIn, $manualOut);
            $ins = $pdo->prepare('INSERT INTO time_logs (user_id, time_in, time_out, hours_rendered) VALUES (?, ?, ?, ?)');
            $ins->execute([$userId, $manualIn, $manualOut, $hours]);
            header('Location: dashboard.php');
            exit;
        }
    }

    // Save Edited Shift
    if (isset($_POST['save_edit'])) {
        $logId = (int)($_POST['edit_log_id'] ?? 0);
        $editInRaw = trim($_POST['edit_time_in'] ?? '');
        $editOutRaw = trim($_POST['edit_time_out'] ?? '');

        $editIn = $editInRaw !== '' ? str_replace('T', ' ', $editInRaw) . ':00' : null;
        $editOut = $editOutRaw !== '' ? str_replace('T', ' ', $editOutRaw) . ':00' : null;

        if ($logId <= 0 || !$editIn) {
            $errors[] = 'Invalid shift edit payload.';
        } elseif ($editOut && strtotime($editOut) < strtotime($editIn)) {
            $errors[] = 'Edited Time Out cannot be earlier than Time In.';
        } else {
            $hours = computeHours($editIn, $editOut);
            $upd = $pdo->prepare('UPDATE time_logs SET time_in = ?, time_out = ?, hours_rendered = ? WHERE id = ? AND user_id = ?');
            $upd->execute([$editIn, $editOut, $hours, $logId, $userId]);
            header('Location: dashboard.php');
            exit;
        }
    }

    // Delete Shift
    if (isset($_POST['delete_shift'])) {
        $logId = (int)($_POST['delete_log_id'] ?? 0);
        if ($logId > 0) {
            $del = $pdo->prepare('DELETE FROM time_logs WHERE id = ? AND user_id = ?');
            $del->execute([$logId, $userId]);
            header('Location: dashboard.php');
            exit;
        }
    }
}

// Fetch Stats
$stmtSum = $pdo->prepare('
    SELECT COALESCE(SUM(hours_rendered), 0) AS rendered,
           COUNT(*) AS total_shifts,
           COALESCE(AVG(hours_rendered), 0) AS avg_hours
    FROM time_logs
    WHERE user_id = ?
');
$stmtSum->execute([$userId]);
$sumRow = $stmtSum->fetch();

$hoursRendered = (float)$sumRow['rendered'];
$totalShifts = (int)$sumRow['total_shifts'];
$avgHoursPerShift = (float)$sumRow['avg_hours'];
$hoursRemaining = max(0, $totalRequired - $hoursRendered);
$progress = $totalRequired > 0 ? min(100, round(($hoursRendered / $totalRequired) * 100, 2)) : 0;

// Fetch History
$stmtHistory = $pdo->prepare('SELECT id, time_in, time_out, hours_rendered FROM time_logs WHERE user_id = ? ORDER BY time_in DESC');
$stmtHistory->execute([$userId]);
$history = $stmtHistory->fetchAll();

$editingId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

// UI Config
$bodyClass = 'theme-netflix';
$tableClass = 'table table-dark table-hover align-middle mb-0';
$inputClass = 'form-control nf-input';
$progressColorHex = '#e10600';
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard - OJT Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --accent: #e10600; --accent-dark: #b80400; }
        body {
            font-family: "Netflix Sans", "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
            background: radial-gradient(1200px 700px at 20% -20%, #2a0a0a 0%, #0b0b0b 45%, #050505 100%);
            color: #f5f5f1;
        }
        .top-nav { backdrop-filter: blur(6px); background: rgba(8,8,8,.85); border-bottom: 1px solid rgba(229,9,20,.25); }
        .brand-logo { height: 52px; width: auto; object-fit: contain; }
        .pill-btn { border-radius: 10px; padding: .45rem .95rem; font-weight: 700; }
        .btn-settings { border: 1px solid rgba(229,9,20,.5); background: #121212; color: #f5f5f1; }
        .btn-logout { background: var(--accent); border-color: var(--accent); color: #fff; }
        .glass-card { border: 1px solid rgba(255,255,255,.08); border-radius: 16px; background: linear-gradient(180deg, #171717 0%, #101010 100%); }
        .stat-head { font-size: .82rem; font-weight: 700; text-transform: uppercase; opacity: .78; }
        .stat-value { font-size: 1.9rem; font-weight: 900; }
        .progress-shell { background: rgba(255,255,255,.15); border-radius: 999px; height: 28px; overflow: hidden; }
        .progress-fill { height: 100%; display: flex; align-items: center; justify-content: center; font-weight: 700; }
        .nf-input { background: #111 !important; border: 1px solid #333 !important; color: #fff !important; }
        .nf-input:focus { border-color: var(--accent) !important; box-shadow: 0 0 0 .2rem rgba(225,6,0,.2) !important; }
        .kpi-row { background: #0f0f0f; border: 1px solid rgba(255,255,255,.09); padding: 1rem; border-radius: 12px; display: flex; justify-content: space-between; margin-bottom: .8rem; }
        @media (max-width: 576px) { .brand-logo { height: 34px; } }
    </style>
</head>
<body>
<nav class="navbar top-nav py-3">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <img src="OJTTracking.png" alt="OJT Tracking" class="brand-logo">
            <p class="mb-0 small opacity-75 mt-1">Hello, <?= htmlspecialchars($userName) ?></p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-settings pill-btn" data-bs-toggle="modal" data-bs-target="#settingsModal">⚙ Settings</button>
            <a href="logout.php" class="btn btn-logout pill-btn">Logout</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <?php if ($errors): ?>
        <div class="alert alert-danger"><?php foreach ($errors as $e) echo "<div>$e</div>"; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="glass-card card p-4">
                <div class="stat-head">Hours Rendered</div>
                <div class="stat-value text-white"><?= number_format($hoursRendered, 2) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card card p-4">
                <div class="stat-head">Hours Remaining</div>
                <div class="stat-value text-white"><?= number_format($hoursRemaining, 2) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card card p-4">
                <div class="stat-head">Goal</div>
                <div class="stat-value text-white"><?= number_format($totalRequired, 0) ?></div>
            </div>
        </div>
    </div>

    <div class="glass-card card p-4 mb-4">
        <h5 class="mb-3">Log New Shift</h5>
        <form method="post" class="row g-3">
            <div class="col-sm-5">
                <label class="form-label small">Time In</label>
                <input type="datetime-local" name="manual_time_in" class="<?= $inputClass ?>" required>
            </div>
            <div class="col-sm-5">
                <label class="form-label small">Time Out</label>
                <input type="datetime-local" name="manual_time_out" class="<?= $inputClass ?>">
            </div>
            <div class="col-sm-2 d-flex align-items-end">
                <button type="submit" name="manual_add" class="btn btn-logout w-100 rounded-pill">Add</button>
            </div>
        </form>
    </div>

    <div class="glass-card card p-4 mb-4">
        <h5 class="mb-3">Progress - <?= $progress ?>%</h5>
        <div class="progress-shell">
            <div class="progress-fill" style="width: <?= $progress ?>%; background: <?= $progressColorHex ?>;">
                <?= $progress ?>%
            </div>
        </div>
    </div>

    <div class="glass-card card p-4">
        <h5>History</h5>
        <div class="table-responsive">
            <table class="<?= $tableClass ?>">
                <thead>
                    <tr><th>Time In</th><th>Time Out</th><th>Hrs</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $log): ?>
                        <tr>
                            <?php if ($editingId === (int)$log['id']): ?>
                                <form method="post">
                                    <input type="hidden" name="edit_log_id" value="<?= $log['id'] ?>">
                                    <td><input type="datetime-local" name="edit_time_in" class="<?= $inputClass ?> form-control-sm" value="<?= date('Y-m-d\TH:i', strtotime((string)$log['time_in'])) ?>"></td>
                                    <td><input type="datetime-local" name="edit_time_out" class="<?= $inputClass ?> form-control-sm" value="<?= $log['time_out'] ? date('Y-m-d\TH:i', strtotime((string)$log['time_out'])) : '' ?>"></td>
                                    <td>-</td>
                                    <td>
                                        <button name="save_edit" class="btn btn-danger btn-sm">Save</button>
                                        <a href="dashboard.php" class="btn btn-secondary btn-sm">X</a>
                                    </td>
                                </form>
                            <?php else: ?>
                                <td><?= date('M d, H:i', strtotime((string)$log['time_in'])) ?></td>
                                <td><?= $log['time_out'] ? date('H:i', strtotime((string)$log['time_out'])) : 'Active' ?></td>
                                <td><?= number_format((float)$log['hours_rendered'], 2) ?></td>
                                <td>
                                    <a href="?edit=<?= $log['id'] ?>" class="text-info me-2">Edit</a>
                                    <form method="post" class="d-inline"><input type="hidden" name="delete_log_id" value="<?= $log['id'] ?>"><button name="delete_shift" class="btn btn-link text-danger p-0" onclick="return confirm('Delete?')">Del</button></form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card">
            <form method="post">
                <div class="modal-header border-0"><h5 class="modal-title">Settings</h5></div>
                <div class="modal-body">
                    <label class="form-label">Total Required Hours</label>
                    <input type="number" name="total_required_hours" class="<?= $inputClass ?>" value="<?= $totalRequired ?>">
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" name="save_settings" class="btn btn-logout rounded-pill px-4">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
