<?php
declare(strict_types=1);
// auth.php now handles the session check and redirects safely
require_once __DIR__ . '/auth.php';

$pdo = db();
$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

$errors = [];
$success = '';

// Helper to calculate your 600-hour progress
function computeHours(?string $in, ?string $out): float
{
    if (!$in || !$out) return 0.0;
    $timeIn = new DateTime($in);
    $timeOut = new DateTime($out);
    $seconds = max(0, $timeOut->getTimestamp() - $timeIn->getTimestamp());
    return round($seconds / 3600, 2);
}

// Verify the user still exists in the database
$stmtUser = $pdo->prepare("SELECT total_required_hours FROM users WHERE id = ? LIMIT 1");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch();

if (!$user) {
    // If the user was deleted from the DB, clear session and kick to login
    session_destroy();
    header('Location: login.php');
    exit;
}

$totalRequired = (float)$user['total_required_hours'];

// ... rest of your existing dashboard HTML and logic stays the same

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        $newRequired = trim($_POST['total_required_hours'] ?? '');

        if ($newRequired === '' || !is_numeric($newRequired) || (float)$newRequired < 0) {
            $errors[] = 'Total required hours must be a non-negative number.';
        } else {
            $totalRequired = (float)$newRequired;
        }

        if (!$errors) {
            $updateReq = $pdo->prepare('UPDATE users SET total_required_hours = ? WHERE id = ?');
            $updateReq->execute([(float)$newRequired, $userId]);
            $success = 'Settings updated.';
        }
    }

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

$stmtHistory = $pdo->prepare('SELECT id, time_in, time_out, hours_rendered FROM time_logs WHERE user_id = ? ORDER BY time_in DESC');
$stmtHistory->execute([$userId]);
$history = $stmtHistory->fetchAll();

$editingId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

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
        :root {
            --accent: #e10600;
            --accent-dark: #b80400;
        }

        body {
            font-family: "Netflix Sans", "Helvetica Neue", "Segoe UI", Arial, sans-serif;
            min-height: 100vh;
            background: radial-gradient(1200px 700px at 20% -20%, #2a0a0a 0%, #0b0b0b 45%, #050505 100%);
            color: #f5f5f1;
        }

        .theme-netflix .top-nav {
            backdrop-filter: blur(6px);
            background: rgba(8,8,8,.85);
            border-bottom: 1px solid rgba(229,9,20,.25);
        }

        .brand-title {
            margin: 0;
            line-height: 1;
        }

        .brand-logo {
            display: block;
            height: 52px;
            width: auto;
            object-fit: contain;
        }

        .brand-sub {
            margin: 0;
            font-size: .88rem;
            opacity: .9;
            color: #d1d5db;
        }

        .pill-btn {
            border-radius: 10px;
            padding: .45rem .95rem;
            font-weight: 700;
        }

        .btn-settings {
            border: 1px solid rgba(229,9,20,.5);
            background: #121212;
            color: #f5f5f1;
        }

        .btn-settings:hover {
            background: #1b1b1b;
            border-color: rgba(229,9,20,.85);
            color: #fff;
        }

        .btn-logout {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .btn-logout:hover {
            background: var(--accent-dark);
            border-color: var(--accent-dark);
            color: #fff;
        }

        .dashboard-wrap {
            padding-top: 1.5rem;
            padding-bottom: 2rem;
        }

        .glass-card {
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 16px;
            box-shadow: 0 10px 28px rgba(0,0,0,.35);
            overflow: hidden;
            background: linear-gradient(180deg, #171717 0%, #101010 100%);
        }

        .stat-head {
            font-size: .82rem;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
            opacity: .78;
            color: #d1d5db;
        }

        .stat-value {
            font-size: 1.9rem;
            font-weight: 900;
            margin-top: .35rem;
            margin-bottom: 0;
            color: #ffffff;
        }

        .section-title {
            font-weight: 800;
            margin-bottom: 1rem;
            letter-spacing: .2px;
            color: #ffffff;
        }

        .table-wrap {
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.12);
        }

        .kpi-row {
            border-radius: 12px;
            padding: 1rem 1.1rem;
            margin-bottom: .8rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0f0f0f;
            border: 1px solid rgba(255,255,255,.09);
        }

        .kpi-label {
            font-weight: 700;
            opacity: .9;
            color: #e5e7eb;
        }

        .kpi-value {
            font-size: 1.4rem;
            font-weight: 900;
            color: #fff;
        }

        .progress-shell {
            background: rgba(255,255,255,.15);
            border-radius: 999px;
            overflow: hidden;
            height: 28px;
        }

        .progress-fill {
            height: 100%;
            border-radius: 999px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-shadow: 0 1px 1px rgba(0,0,0,.35);
        }

        .small-input { min-width: 170px; }

        .form-label,
        .table th,
        .table td,
        .text-muted {
            color: #e5e5e5 !important;
        }

        .nf-input {
            background: #111 !important;
            border: 1px solid #333 !important;
            color: #fff !important;
        }

        .nf-input:focus {
            background: #111 !important;
            color: #fff !important;
            border-color: #e10600 !important;
            box-shadow: 0 0 0 .2rem rgba(225,6,0,.2) !important;
        }

        .modal-content {
            border-radius: 14px;
            background: #121212;
            color: #f5f5f1;
            border: 1px solid rgba(229,9,20,.35);
        }

        @media (max-width: 576px) {
            .top-nav .container {
                flex-direction: column;
                align-items: flex-start !important;
                gap: .55rem;
            }
            .brand-logo { height: 34px; }
            .brand-sub { font-size: .8rem; }
            .top-nav .gap-2 {
                width: 100%;
                gap: .5rem !important;
            }
            .top-nav .gap-2 .pill-btn {
                flex: 1;
                text-align: center;
                padding: .46rem .6rem;
                font-size: .8rem;
                white-space: nowrap;
            }
            .stat-value { font-size: 1.45rem; }
        }
    </style>
</head>
<body class="<?= $bodyClass ?>">
<nav class="navbar top-nav">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex flex-column">
            <p class="brand-title">
                <img src="OJTTracking.png" alt="OJT Tracking" class="brand-logo">
            </p>
            <p class="brand-sub">Hello, <?= htmlspecialchars($userName) ?></p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-settings pill-btn" data-bs-toggle="modal" data-bs-target="#settingsModal">⚙ Hour Settings</button>
            <a href="logout.php" class="btn btn-logout pill-btn">Logout</a>
        </div>
    </div>
</nav>

<div class="container dashboard-wrap">
    <?php if ($errors): ?>
        <div class="alert alert-danger rounded-4 shadow-sm">
            <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success rounded-4 shadow-sm"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="glass-card card h-100">
                <div class="card-body p-4">
                    <div class="stat-head">Hours Rendered</div>
                    <p class="stat-value"><?= number_format($hoursRendered, 2) ?></p>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="glass-card card h-100">
                <div class="card-body p-4">
                    <div class="stat-head">Hours Remaining</div>
                    <p class="stat-value"><?= number_format($hoursRemaining, 2) ?></p>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="glass-card card h-100">
                <div class="card-body p-4">
                    <div class="stat-head">Required Hours</div>
                    <p class="stat-value"><?= number_format($totalRequired, 2) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="glass-card card h-100">
                <div class="card-body p-4">
                    <h5 class="section-title">Manual Shift Entry</h5>
                    <form method="post" class="row g-2">
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Time In</label>
                            <input type="datetime-local" name="manual_time_in" class="<?= $inputClass ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Time Out (optional)</label>
                            <input type="datetime-local" name="manual_time_out" class="<?= $inputClass ?>">
                        </div>
                        <div class="col-12 mt-3">
                            <button type="submit" name="manual_add" class="btn btn-logout rounded-pill px-4">Add Shift</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="glass-card card mb-4">
        <div class="card-body p-4">
            <h5 class="section-title">Shift History</h5>
            <div class="table-wrap">
                <div class="table-responsive">
                    <table class="<?= $tableClass ?>">
                        <thead>
                            <tr>
                                <th>ID</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$history): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No shifts yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($history as $log): ?>
                                <?php if ($editingId === (int)$log['id']): ?>
                                    <tr>
                                        <form method="post">
                                            <td><?= (int)$log['id'] ?><input type="hidden" name="edit_log_id" value="<?= (int)$log['id'] ?>"></td>
                                            <td><input class="<?= $inputClass ?> small-input" type="datetime-local" name="edit_time_in" value="<?= date('Y-m-d\TH:i', strtotime((string)$log['time_in'])) ?>" required></td>
                                            <td><input class="<?= $inputClass ?> small-input" type="datetime-local" name="edit_time_out" value="<?= $log['time_out'] ? date('Y-m-d\TH:i', strtotime((string)$log['time_out'])) : '' ?>"></td>
                                            <td><?= number_format((float)$log['hours_rendered'], 2) ?></td>
                                            <td>
                                                <button type="submit" name="save_edit" class="btn btn-danger btn-sm rounded-pill px-3">Save</button>
                                                <a href="dashboard.php" class="btn btn-secondary btn-sm rounded-pill px-3">Cancel</a>
                                            </td>
                                        </form>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td><?= (int)$log['id'] ?></td>
                                        <td><?= htmlspecialchars((string)$log['time_in']) ?></td>
                                        <td><?= $log['time_out'] ? htmlspecialchars((string)$log['time_out']) : '<span class="badge bg-warning text-dark">Open</span>' ?></td>
                                        <td><strong><?= number_format((float)$log['hours_rendered'], 2) ?></strong></td>
                                        <td>
                                            <a href="dashboard.php?edit=<?= (int)$log['id'] ?>" class="btn btn-outline-light btn-sm rounded-pill px-3 mb-1">Edit</a>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this shift?');">
                                                <input type="hidden" name="delete_log_id" value="<?= (int)$log['id'] ?>">
                                                <button type="submit" name="delete_shift" class="btn btn-outline-danger btn-sm rounded-pill px-3 mb-1">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="glass-card card mb-4">
        <div class="card-body p-4">
            <h5 class="section-title">Progress</h5>
            <div class="progress-shell">
                <div class="progress-fill" style="width: <?= $progress ?>%; background-color: <?= htmlspecialchars($progressColorHex) ?>;">
                    <?= $progress ?>%
                </div>
            </div>
        </div>
    </div>

    <div class="glass-card card">
        <div class="card-body p-4">
            <h4 class="section-title mb-4">Quick Statistics</h4>
            <div class="kpi-row">
                <span class="kpi-label">Total Shifts Logged</span>
                <span class="kpi-value"><?= $totalShifts ?></span>
            </div>
            <div class="kpi-row">
                <span class="kpi-label">Average Hours/Shift</span>
                <span class="kpi-value"><?= number_format($avgHoursPerShift, 2) ?></span>
            </div>
            <div class="kpi-row mb-0">
                <span class="kpi-label">Completion Rate</span>
                <span class="kpi-value" style="color: var(--accent);"><?= $progress ?>%</span>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Hour Settings</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Required Hours</label>
                        <input type="number" step="0.01" min="0" name="total_required_hours" class="<?= $inputClass ?>" value="<?= htmlspecialchars((string)$totalRequired) ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="save_settings" class="btn btn-danger rounded-pill px-4">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
