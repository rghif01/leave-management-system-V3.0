<?php
/**
 * APM - Admin Holidays Management
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['admin']);
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? '')) { setFlash('danger', 'Invalid token.'); header('Location: /APM/admin/holidays.php'); exit; }
    $act = $_POST['action'] ?? '';
    
    if ($act === 'add' || $act === 'edit') {
        $name    = sanitize($_POST['name'] ?? '');
        $date    = sanitize($_POST['holiday_date'] ?? '');
        $type    = in_array($_POST['type'], ['national','religious','other']) ? $_POST['type'] : 'national';
        $recur   = (int)($_POST['recurring'] ?? 0);
        $reqAppr = (int)($_POST['requires_approval'] ?? 1);
        $id      = (int)($_POST['holiday_id'] ?? 0);
        
        if ($name && $date) {
            if ($act === 'add') {
                $pdo->prepare("INSERT INTO holidays (name, holiday_date, type, recurring, requires_approval) VALUES (?,?,?,?,?)")->execute([$name,$date,$type,$recur,$reqAppr]);
                setFlash('success', "Holiday '$name' added.");
            } else {
                $pdo->prepare("UPDATE holidays SET name=?, holiday_date=?, type=?, recurring=?, requires_approval=? WHERE id=?")->execute([$name,$date,$type,$recur,$reqAppr,$id]);
                setFlash('success', 'Holiday updated.');
            }
        }
    } elseif ($act === 'delete') {
        $pdo->prepare("UPDATE holidays SET active=0 WHERE id=?")->execute([(int)$_POST['holiday_id']]);
        setFlash('success', 'Holiday removed.');
    } elseif ($act === 'toggle_deduction') {
        $id   = (int)$_POST['holiday_id'];
        $val  = (int)$_POST['requires_approval'];
        $pdo->prepare("UPDATE holidays SET requires_approval=? WHERE id=?")->execute([$val, $id]);
        setFlash('success', 'Holiday updated.');
    }
    header('Location: /APM/admin/holidays.php'); exit;
}

$year = (int)($_GET['year'] ?? date('Y'));
$holidays = $pdo->prepare("SELECT * FROM holidays WHERE active=1 AND YEAR(holiday_date)=? ORDER BY holiday_date");
$holidays->execute([$year]);
$holidays = $holidays->fetchAll();

$pageTitle = 'Holidays Management';
$activePage = 'holidays';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-star text-primary me-2"></i>Moroccan Holidays Management</h4>
    <div class="d-flex gap-2 align-items-center">
        <select class="form-select form-select-sm" onchange="location.href='?year='+this.value">
            <?php for ($y = 2024; $y <= 2027; $y++): ?><option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
        </select>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#holidayModal"><i class="bi bi-plus me-1"></i>Add Holiday</button>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Holiday Name</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Recurring</th>
                        <th>Deduct Balance?</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($holidays)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">No holidays for <?= $year ?></td></tr>
                    <?php else: ?>
                    <?php foreach ($holidays as $h): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($h['name']) ?></td>
                        <td><?= formatDate($h['holiday_date']) ?></td>
                        <td>
                            <span class="badge <?= $h['type']==='national' ? 'bg-primary' : ($h['type']==='religious' ? 'bg-success' : 'bg-secondary') ?>">
                                <?= ucfirst($h['type']) ?>
                            </span>
                        </td>
                        <td><?= $h['recurring'] ? '<span class="badge bg-info">Yearly</span>' : '<span class="text-muted small">No</span>' ?></td>
                        <td>
                            <?php if ($h['requires_approval']): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Yes (needs admin OK)</span>
                            <?php else: ?>
                                <span class="badge bg-success"><i class="bi bi-check me-1"></i>No (auto excluded)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-xs btn-outline-primary py-0 px-2" onclick="editHoliday(<?= htmlspecialchars(json_encode($h)) ?>)"><i class="bi bi-pencil"></i></button>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="holiday_id" value="<?= $h['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-2" data-confirm="Remove this holiday?"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <h6 class="fw-bold"><i class="bi bi-info-circle text-info me-2"></i>How Holiday Leave Works</h6>
        <p class="text-muted small mb-0">
            Holidays marked as <strong>"No deduction (auto excluded)"</strong> are automatically excluded from leave day calculation. 
            If an employee takes leave that spans these dates, the holiday days are not counted against their balance.
            Holidays marked as <strong>"Yes (needs admin OK)"</strong> still require admin approval when an employee submits a leave request covering those dates.
        </p>
    </div>
</div>

<!-- Holiday Modal -->
<div class="modal fade" id="holidayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title" id="holidayModalTitle">Add Holiday</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                <input type="hidden" name="action" id="hAction" value="add">
                <input type="hidden" name="holiday_id" id="hId" value="0">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label fw-semibold">Holiday Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" id="hName" required placeholder="e.g. Throne Day"></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Date <span class="text-danger">*</span></label><input type="date" class="form-control" name="holiday_date" id="hDate" required></div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Type</label>
                            <select class="form-select" name="type" id="hType">
                                <option value="national">National</option>
                                <option value="religious">Religious</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Recurs Every Year?</label>
                            <select class="form-select" name="recurring" id="hRecur">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-semibold">Deduct from Balance?</label>
                        <select class="form-select" name="requires_approval" id="hDeduct">
                            <option value="0">No - Auto exclude from count</option>
                            <option value="1">Yes - Needs admin approval</option>
                        </select>
                        <div class="form-text">If "No", these days won't be deducted from employee balance automatically.</div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Holiday</button></div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = <<<JS
function editHoliday(h) {
    document.getElementById('holidayModalTitle').textContent = 'Edit Holiday';
    document.getElementById('hAction').value = 'edit';
    document.getElementById('hId').value = h.id;
    document.getElementById('hName').value = h.name;
    document.getElementById('hDate').value = h.holiday_date;
    document.getElementById('hType').value = h.type;
    document.getElementById('hRecur').value = h.recurring;
    document.getElementById('hDeduct').value = h.requires_approval;
    new bootstrap.Modal(document.getElementById('holidayModal')).show();
}
JS;

include BASE_PATH . '/includes/footer.php';
?>
