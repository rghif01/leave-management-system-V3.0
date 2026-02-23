<?php
/**
 * APM - Admin Shifts & Teams Management
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['admin']);
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? '')) { setFlash('danger', 'Invalid token.'); header('Location: /APM/admin/shifts.php'); exit; }
    $act = $_POST['action'] ?? '';
    
    if ($act === 'add_shift') {
        $name = sanitize($_POST['name'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        $color = sanitize($_POST['color'] ?? '#007bff');
        if ($name) { $pdo->prepare("INSERT INTO shifts (name, description, color) VALUES (?,?,?)")->execute([$name,$desc,$color]); setFlash('success',"Shift '$name' added."); }
    } elseif ($act === 'edit_shift') {
        $id = (int)$_POST['shift_id'];
        $pdo->prepare("UPDATE shifts SET name=?, description=?, color=? WHERE id=?")->execute([sanitize($_POST['name']??''), sanitize($_POST['description']??''), sanitize($_POST['color']??'#007bff'), $id]);
        setFlash('success', 'Shift updated.');
    } elseif ($act === 'delete_shift') {
        $id = (int)$_POST['shift_id'];
        $pdo->prepare("UPDATE shifts SET active=0 WHERE id=?")->execute([$id]);
        setFlash('success', 'Shift deactivated.');
    } elseif ($act === 'add_team') {
        $name  = sanitize($_POST['name'] ?? '');
        $code  = strtoupper(sanitize($_POST['code'] ?? ''));
        $sid   = (int)$_POST['shift_id'];
        $max   = (int)($_POST['max_leave_per_day'] ?? 2);
        $desc  = sanitize($_POST['description'] ?? '');
        if ($name && $code && $sid) { $pdo->prepare("INSERT INTO teams (name, code, shift_id, max_leave_per_day, description) VALUES (?,?,?,?,?)")->execute([$name,$code,$sid,$max,$desc]); setFlash('success',"Team '$name' added."); }
    } elseif ($act === 'edit_team') {
        $id   = (int)$_POST['team_id'];
        $max  = (int)$_POST['max_leave_per_day'];
        $pdo->prepare("UPDATE teams SET name=?, code=?, shift_id=?, max_leave_per_day=?, description=? WHERE id=?")->execute([sanitize($_POST['name']??''), strtoupper(sanitize($_POST['code']??'')), (int)$_POST['shift_id'], $max, sanitize($_POST['description']??''), $id]);
        setFlash('success', 'Team updated.');
    } elseif ($act === 'delete_team') {
        $pdo->prepare("UPDATE teams SET active=0 WHERE id=?")->execute([(int)$_POST['team_id']]);
        setFlash('success', 'Team deactivated.');
    }
    header('Location: /APM/admin/shifts.php'); exit;
}

$shifts = $pdo->query("SELECT * FROM shifts WHERE active=1 ORDER BY id")->fetchAll();
$teams  = $pdo->query("SELECT t.*, s.name as shift_name, s.color as shift_color, (SELECT COUNT(*) FROM users WHERE team_id=t.id AND active=1) as member_count FROM teams t JOIN shifts s ON t.shift_id=s.id WHERE t.active=1 ORDER BY s.id, t.code")->fetchAll();

$pageTitle = 'Shifts & Teams';
$activePage = 'shifts';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Shifts & Teams</h4>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#shiftModal"><i class="bi bi-plus-circle me-1"></i>Add Shift</button>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#teamModal"><i class="bi bi-plus-circle me-1"></i>Add Team</button>
    </div>
</div>

<!-- Shifts -->
<div class="row g-3 mb-4">
    <?php foreach ($shifts as $s):
        $shiftTeams = array_filter($teams, fn($t) => $t['shift_id'] == $s['id']);
        $totalMembers = array_sum(array_column($shiftTeams, 'member_count'));
    ?>
    <div class="col-md-6 col-xl-3">
        <div class="card h-100" style="border-left: 4px solid <?= e($s['color']) ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="fw-bold mb-1" style="color:<?= e($s['color']) ?>"><?= e($s['name']) ?></h5>
                        <div class="text-muted small"><?= e($s['description'] ?? '') ?></div>
                    </div>
                    <div class="d-flex gap-1">
                        <button class="btn btn-xs btn-outline-primary py-0 px-1" onclick="editShift(<?= htmlspecialchars(json_encode($s)) ?>)"><i class="bi bi-pencil"></i></button>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                            <input type="hidden" name="action" value="delete_shift">
                            <input type="hidden" name="shift_id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-1" data-confirm="Deactivate <?= e($s['name']) ?>?"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
                <hr class="my-2">
                <div class="small text-muted mb-2"><i class="bi bi-people me-1"></i><?= $totalMembers ?> members &middot; <?= count($shiftTeams) ?> teams</div>
                <!-- Schedule examples -->
                <div class="d-flex flex-wrap gap-1 small">
                    <?php 
                    $today = date('Y-m-d');
                    $sched = getShiftSchedule($s['id'], $today);
                    ?>
                    <span class="badge shift-<?= $sched ?>"><?= ucfirst($sched) ?> (Today)</span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Teams Table -->
<div class="card">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-bold"><i class="bi bi-diagram-3 me-2"></i>All Teams</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Team Name</th>
                        <th>Code</th>
                        <th>Shift</th>
                        <th>Max Leave/Day</th>
                        <th>Members</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teams as $t): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($t['name']) ?></td>
                        <td><span class="badge bg-primary"><?= e($t['code']) ?></span></td>
                        <td><span class="badge" style="background:<?= e($t['shift_color']) ?>"><?= e($t['shift_name']) ?></span></td>
                        <td class="text-center"><span class="badge bg-warning text-dark"><?= $t['max_leave_per_day'] ?> / day</span></td>
                        <td><?= $t['member_count'] ?> <span class="text-muted small">members</span></td>
                        <td>
                            <button class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:0.75rem" onclick="editTeam(<?= htmlspecialchars(json_encode($t)) ?>)"><i class="bi bi-pencil"></i></button>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                <input type="hidden" name="action" value="delete_team">
                                <input type="hidden" name="team_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-2" style="font-size:0.75rem" data-confirm="Deactivate this team?"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Shift Modal -->
<div class="modal fade" id="shiftModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title" id="shiftModalTitle">Add Shift</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                <input type="hidden" name="action" id="shiftAction" value="add_shift">
                <input type="hidden" name="shift_id" id="shiftId" value="0">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label fw-semibold">Shift Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" id="shiftName" required placeholder="e.g. Shift A"></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Description</label><input type="text" class="form-control" name="description" id="shiftDesc"></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Color</label><input type="color" class="form-control form-control-color" name="color" id="shiftColor" value="#007bff"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Shift</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Team Modal -->
<div class="modal fade" id="teamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white"><h5 class="modal-title" id="teamModalTitle">Add Team</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                <input type="hidden" name="action" id="teamAction" value="add_team">
                <input type="hidden" name="team_id" id="teamId" value="0">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Shift <span class="text-danger">*</span></label>
                        <select class="form-select" name="shift_id" id="teamShiftId" required>
                            <option value="">-- Select Shift --</option>
                            <?php foreach ($shifts as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-8"><label class="form-label fw-semibold">Team Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" id="teamName" required placeholder="e.g. Remote Crane Controller - A"></div>
                        <div class="col-4"><label class="form-label fw-semibold">Code <span class="text-danger">*</span></label><input type="text" class="form-control" name="code" id="teamCode" required placeholder="RCC" maxlength="10"></div>
                    </div>
                    <div class="mt-3"><label class="form-label fw-semibold">Max Leave Per Day</label><input type="number" class="form-control" name="max_leave_per_day" id="teamMax" value="2" min="1" max="50"></div>
                    <div class="mt-3"><label class="form-label fw-semibold">Description</label><input type="text" class="form-control" name="description" id="teamDesc"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Save Team</button></div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = <<<JS
function editShift(s) {
    document.getElementById('shiftModalTitle').textContent = 'Edit Shift';
    document.getElementById('shiftAction').value = 'edit_shift';
    document.getElementById('shiftId').value = s.id;
    document.getElementById('shiftName').value = s.name;
    document.getElementById('shiftDesc').value = s.description || '';
    document.getElementById('shiftColor').value = s.color || '#007bff';
    new bootstrap.Modal(document.getElementById('shiftModal')).show();
}
function editTeam(t) {
    document.getElementById('teamModalTitle').textContent = 'Edit Team';
    document.getElementById('teamAction').value = 'edit_team';
    document.getElementById('teamId').value = t.id;
    document.getElementById('teamShiftId').value = t.shift_id;
    document.getElementById('teamName').value = t.name;
    document.getElementById('teamCode').value = t.code;
    document.getElementById('teamMax').value = t.max_leave_per_day;
    document.getElementById('teamDesc').value = t.description || '';
    new bootstrap.Modal(document.getElementById('teamModal')).show();
}
JS;

include BASE_PATH . '/includes/footer.php';
?>
