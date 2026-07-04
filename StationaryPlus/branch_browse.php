<?php
// ============================================================
//  branch_browse.php — shared "Browsing Branch" selector
//  Include on any c_ page AFTER auth.php/db.php and BEFORE any
//  HTML output. Provides:
//    - handling for the switch_branch POST (session-only — never
//      touches the customer's permanent preferred_branch_id)
//    - $branchList, $activeBranchId, $currentBranch variables
//    - render_browsing_branch_bar() to output the selector widget
//
//  This is distinct from the "Preferred Branch" picker on
//  c_dashboard.php, which permanently saves to the customer's
//  account. "Browsing Branch" here is a temporary, session-only
//  view filter — switching it does NOT change the customer's
//  saved preference.
// ============================================================

$branchList = $conn->query(
    "SELECT branch_id, branch_name FROM branches WHERE status = 'ACTIVE' ORDER BY branch_name"
)->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_branch'])) {
    $newBranch = trim($_POST['branch_id'] ?? '');

    $chk = $conn->prepare("SELECT branch_id FROM branches WHERE branch_id = ? AND status = 'ACTIVE' LIMIT 1");
    $chk->bind_param('s', $newBranch);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $_SESSION['branch_id'] = $newBranch;
    } elseif ($newBranch === '') {
        unset($_SESSION['branch_id']);
    }
    $chk->close();

    header('Location: ' . basename($_SERVER['PHP_SELF']));
    exit;
}

$activeBranchId = $_SESSION['branch_id'] ?? null;
$currentBranch  = null;
if ($activeBranchId) {
    $stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ? LIMIT 1");
    $stmt->bind_param('s', $activeBranchId);
    $stmt->execute();
    $currentBranch = $stmt->get_result()->fetch_assoc()['branch_name'] ?? null;
    $stmt->close();
}

function render_browsing_branch_bar(): void {
    global $branchList, $activeBranchId;
    ?>
    <form method="POST" action="<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>"
          style="display:flex;align-items:center;gap:8px;" title="Browsing Branch — a temporary view filter for this session only. It does not change your saved preferred branch.">
        <input type="hidden" name="switch_branch" value="1">
        <label style="font-size:12px;color:var(--text-secondary);font-weight:600;white-space:nowrap;">
            <i class="fas fa-store" style="color:var(--primary);margin-right:4px;"></i> Browsing Branch:
        </label>
        <select name="branch_id" onchange="this.form.submit()"
                style="padding:7px 26px 7px 12px;border:1.5px solid var(--border);border-radius:20px;
                       font-size:12px;font-weight:600;color:var(--primary);
                       background:rgba(168,53,53,0.06);cursor:pointer;outline:none;
                       appearance:none;
                       background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23A83535' d='M6 8L1 3h10z'/%3E%3C/svg%3E\");
                       background-repeat:no-repeat;background-position:right 9px center;">
            <option value="" <?= !$activeBranchId ? 'selected' : '' ?>>All branches</option>
            <?php foreach ($branchList as $b): ?>
                <option value="<?= htmlspecialchars($b['branch_id']) ?>"
                    <?= $activeBranchId === $b['branch_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b['branch_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php
}
