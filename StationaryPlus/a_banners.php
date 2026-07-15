<?php
// ============================================================
//  a_banners.php — Admin Banner Management
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('ADMIN');
require_once 'db.php';

$banners = $conn->query(
    "SELECT banner_id, title, image_path, link_url, target_page, sort_order, is_active, starts_at, ends_at, last_updated
     FROM banners
     ORDER BY sort_order ASC, created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$totalBanners  = count($banners);
$activeBanners = count(array_filter($banners, fn($b) => (int)$b['is_active'] === 1));

function pageLabel(string $page): string {
    return match($page) {
        'ALL'          => 'All Pages',
        'INDEX'        => 'Homepage',
        'LOGIN'        => 'Login',
        'REGISTER'     => 'Registration',
        'C_DASHBOARD'  => 'Customer Dashboard',
        default        => $page,
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Banner Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/tokens.css">
    <script src="assets/js/theme.js"></script>
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        :root { --primary:#A83535;--secondary:#F4A261;--background:#FAFAFA;--accent:#F1EDE8;--text-primary:#2E2E2E;--text-secondary:#707070;--border:#E0E0E0;--white:#FFFFFF;--sidebar-width:260px;--card-shadow:0 4px 12px rgba(0,0,0,0.05); }
        * { margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif; }
        body { background-color:var(--background);color:var(--text-primary);min-height:100vh;display:flex; }
        .main-content { flex-grow:1;margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column; }
        .top-header { background-color:var(--white);padding:18px 28px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center; }
        .header-left h1 { font-size:22px;font-weight:700;margin-bottom:4px; }
        .header-left p { font-size:13px;color:var(--text-secondary); }
        .header-right { font-size:13px;color:var(--text-secondary); }
        .banner-management { flex-grow:1;padding:25px;display:grid;grid-template-columns:1fr 420px;gap:25px;height:calc(100vh - 90px);overflow:hidden; }
        .list-section { background:var(--white);border-radius:10px;box-shadow:var(--card-shadow);border:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden; }
        .list-header { padding:18px 20px;border-bottom:1px solid var(--border);background:var(--primary-tint-subtle);display:flex;justify-content:space-between;align-items:center; }
        .list-header h2 { font-size:16px;color:var(--primary);display:flex;align-items:center;gap:8px; }
        .add-new-btn { padding:8px 16px;background:var(--primary-tint-light);color:var(--primary);border:1.5px solid var(--primary);border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px; }
        .add-new-btn:hover { background:var(--primary-tint-active); }
        .list-container { flex-grow:1;overflow:auto;padding:14px; }
        .banner-row { display:flex;gap:14px;padding:14px;border:1px solid var(--border);border-radius:9px;margin-bottom:12px;cursor:pointer;transition:all 0.15s; }
        .banner-row:hover { border-color:var(--primary);background:var(--primary-tint-subtle); }
        .banner-row.selected { border-color:var(--primary);background:var(--primary-tint-subtle); }
        .banner-thumb { width:100px;height:64px;border-radius:6px;object-fit:cover;flex-shrink:0;background:var(--background);border:1px solid var(--border); }
        .banner-row-info { flex-grow:1;min-width:0; }
        .banner-row-title { font-weight:600;font-size:14px;margin-bottom:4px; }
        .banner-row-meta { font-size:12px;color:var(--text-secondary);display:flex;gap:10px;flex-wrap:wrap;align-items:center; }
        .page-tag { display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:rgba(37,99,235,0.1);color:#2563eb; }
        .status-tag { display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700; }
        .status-active { background:var(--success-bg);color:var(--success); }
        .status-inactive { background:rgba(158,158,158,0.15);color:#616161; }
        .empty-state { text-align:center;padding:50px 20px;color:var(--text-secondary); }
        .empty-state i { font-size:38px;opacity:0.2;margin-bottom:12px;display:block; }

        .form-section { background:var(--white);border-radius:10px;box-shadow:var(--card-shadow);border:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden; }
        .form-header { padding:16px 20px;border-bottom:1px solid var(--border);background:var(--primary-tint-subtle);display:flex;justify-content:space-between;align-items:center; }
        .form-header h2 { font-size:16px;color:var(--primary);display:flex;align-items:center;gap:8px; }
        .form-mode-badge { font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px; }
        .badge-edit { background:var(--primary-tint-medium);color:var(--primary); }
        .badge-new  { background:var(--success-bg);color:var(--success); }
        .form-container { flex-grow:1;padding:20px;overflow-y:auto;display:flex;flex-direction:column; }
        .form-placeholder { display:flex;flex-direction:column;align-items:center;justify-content:center;flex-grow:1;color:var(--text-secondary);text-align:center;padding:30px;gap:12px; }
        .form-placeholder i { font-size:38px;opacity:0.2; }
        .form-group { margin-bottom:16px; }
        .form-label { display:block;margin-bottom:6px;font-weight:600;font-size:13px; }
        .form-input, .form-select { width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;background:var(--white);color:var(--text-primary); }
        .form-input:focus, .form-select:focus { outline:none;border-color:var(--primary); }
        .form-row { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
        .checkbox-row { display:flex;align-items:center;gap:8px; }
        .form-actions { margin-top:auto;padding-top:16px;border-top:1px solid var(--border);display:flex;gap:10px; }
        .primary-btn { flex:1;padding:11px;background:var(--primary);color:var(--on-primary);border:none;border-radius:7px;font-weight:600;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px; }
        .primary-btn:hover { background:var(--primary-dark); }
        .status-btn { flex:1;padding:11px;border-radius:7px;font-weight:600;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px; }
        .status-btn-activate { background:var(--success-bg);color:var(--success);border:1.5px solid var(--success); }
        .status-btn-deactivate { background:var(--danger-bg);color:var(--danger);border:1.5px solid var(--danger); }
        .image-preview-wrap { width:100%;height:120px;border-radius:8px;background:var(--background);border:1.5px dashed var(--border);display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:10px; }
        .image-preview-wrap img { width:100%;height:100%;object-fit:cover;display:none; }
        /* ── Custom Dialog ── */
        .custom-dialog-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center; }
        .custom-dialog-overlay.show { display:flex; }
        .custom-dialog-box { background:var(--white);border-radius:12px;width:90%;max-width:400px;padding:28px 26px 22px;box-shadow:0 20px 60px rgba(0,0,0,0.2);text-align:center; }
        .custom-dialog-icon { width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:22px; }
        .custom-dialog-icon.dialog-info { background:#eff6ff;color:#1d4ed8; }
        .custom-dialog-icon.dialog-success { background:var(--success-bg);color:var(--success); }
        .custom-dialog-icon.dialog-error { background:var(--danger-bg);color:var(--danger); }
        .custom-dialog-icon.dialog-warning { background:var(--warning-bg);color:var(--warning); }
        .custom-dialog-message { font-size:14px;color:#2E2E2E;line-height:1.6;margin-bottom:22px; }
        .custom-dialog-actions { display:flex;gap:10px; }
        .custom-dialog-btn { flex:1;padding:11px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none; }
        .custom-dialog-cancel { background:#F1EDE8;color:#2E2E2E;border:1.5px solid #E0E0E0; }
        .custom-dialog-confirm { background:#A83535;color:var(--on-primary); }
        .custom-dialog-danger { background:var(--danger);color:var(--on-primary); }
        @media (max-width:1200px) {
            .banner-management { grid-template-columns:1fr;height:auto;overflow:visible; }
        }
    </style>
</head>
<body>

<?php include 'a_sidebar.php'; ?>

<main class="main-content">
    <header class="top-header">
        <div class="header-left">
            <h1>Banner Management</h1>
            <p>Manage promo banners shown on the homepage, login, registration, and customer dashboard</p>
        </div>
        <div class="header-right"><?= $activeBanners ?> active / <?= $totalBanners ?> total</div>
    </header>

    <div class="banner-management">
        <section class="list-section">
            <div class="list-header">
                <h2><i class="fas fa-images"></i> Banners</h2>
                <button type="button" class="add-new-btn" id="addNewBtn"><i class="fas fa-plus"></i> Add New</button>
            </div>
            <div class="list-container">
                <?php if (empty($banners)): ?>
                <div class="empty-state">
                    <i class="fas fa-images"></i>
                    <p>No banners yet. Click "Add New" to create one.</p>
                </div>
                <?php else: foreach ($banners as $b): ?>
                <div class="banner-row" data-id="<?= htmlspecialchars($b['banner_id']) ?>"
                     onclick="loadBanner(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)">
                    <img class="banner-thumb" src="<?= htmlspecialchars($b['image_path']) ?>" alt="">
                    <div class="banner-row-info">
                        <div class="banner-row-title"><?= htmlspecialchars($b['title']) ?></div>
                        <div class="banner-row-meta">
                            <span class="page-tag"><?= htmlspecialchars(pageLabel($b['target_page'])) ?></span>
                            <span class="status-tag <?= $b['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $b['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                            <span>Order: <?= (int)$b['sort_order'] ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <section class="form-section">
            <div class="form-header">
                <h2><i class="fas fa-edit"></i> <span id="formTitle">Banner Details</span></h2>
                <span class="form-mode-badge badge-new" id="modeBadge" style="display:none;">New</span>
            </div>
            <div class="form-container" id="formContainer">
                <div class="form-placeholder" id="formPlaceholder">
                    <i class="fas fa-image"></i>
                    <p>Select a banner from the list to edit,<br>or click <strong>Add New</strong> to create one.</p>
                </div>

                <div id="bannerFormFields" style="display:none;flex-direction:column;flex-grow:1;">
                    <input type="hidden" id="fieldBannerId">

                    <div class="form-group">
                        <label class="form-label">Banner Image <span style="font-weight:400;color:var(--text-secondary);">(JPG/PNG/WEBP, max 5MB)</span></label>
                        <div class="image-preview-wrap" id="imagePreviewWrap">
                            <i class="fas fa-image" style="color:var(--text-secondary);font-size:24px;" id="imagePlaceholderIcon"></i>
                            <img id="imagePreview" src="" alt="">
                        </div>
                        <input type="file" id="bannerImageInput" accept="image/jpeg,image/png,image/webp" style="font-size:12px;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-input" id="fieldTitle" placeholder="e.g. Back-to-school sale">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Link URL <span style="font-weight:400;color:var(--text-secondary);">(optional)</span></label>
                        <input type="text" class="form-input" id="fieldLinkUrl" placeholder="e.g. c_viewproducts.php?category=paper">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Show On</label>
                            <select class="form-select" id="fieldTargetPage">
                                <option value="ALL">All Pages</option>
                                <option value="INDEX">Homepage</option>
                                <option value="LOGIN">Login</option>
                                <option value="REGISTER">Registration</option>
                                <option value="C_DASHBOARD">Customer Dashboard</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sort Order</label>
                            <input type="number" class="form-input" id="fieldSortOrder" value="0" min="0">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Starts At <span style="font-weight:400;color:var(--text-secondary);">(optional)</span></label>
                            <input type="datetime-local" class="form-input" id="fieldStartsAt">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Ends At <span style="font-weight:400;color:var(--text-secondary);">(optional)</span></label>
                            <input type="datetime-local" class="form-input" id="fieldEndsAt">
                        </div>
                    </div>

                    <div class="form-group checkbox-row">
                        <input type="checkbox" id="fieldIsActive" checked style="width:16px;height:16px;">
                        <label class="form-label" style="margin:0;" for="fieldIsActive">Active</label>
                    </div>

                    <div class="form-actions">
                        <button class="primary-btn" id="saveBtn"><i class="fas fa-save"></i> <span id="submitLabel">Add Banner</span></button>
                        <button type="button" class="status-btn status-btn-deactivate" id="statusToggleBtn" style="display:none;" onclick="toggleBannerStatus()">
                            <i class="fas fa-ban" id="statusToggleIcon"></i> <span id="statusToggleLabel">Deactivate</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

<div id="customDialogOverlay" class="custom-dialog-overlay">
    <div class="custom-dialog-box">
        <div class="custom-dialog-icon" id="customDialogIcon"><i class="fas fa-info-circle"></i></div>
        <p class="custom-dialog-message" id="customDialogMessage"></p>
        <div class="custom-dialog-actions">
            <button class="custom-dialog-btn custom-dialog-cancel" id="customDialogCancelBtn" style="display:none;">Cancel</button>
            <button class="custom-dialog-btn custom-dialog-confirm" id="customDialogConfirmBtn">OK</button>
        </div>
    </div>
</div>

<script>
const ICONS = {
    info: '<i class="fas fa-info-circle"></i>', success: '<i class="fas fa-check-circle"></i>',
    error: '<i class="fas fa-exclamation-circle"></i>', warning: '<i class="fas fa-exclamation-triangle"></i>',
};
function customAlert(message, type = 'info') {
    return new Promise(resolve => {
        const overlay = document.getElementById('customDialogOverlay');
        const icon = document.getElementById('customDialogIcon');
        const msgEl = document.getElementById('customDialogMessage');
        const cancelBtn = document.getElementById('customDialogCancelBtn');
        const confirmBtn = document.getElementById('customDialogConfirmBtn');
        msgEl.textContent = message;
        icon.className = 'custom-dialog-icon dialog-' + type;
        icon.innerHTML = ICONS[type] || ICONS.info;
        cancelBtn.style.display = 'none';
        confirmBtn.textContent = 'OK';
        confirmBtn.className = 'custom-dialog-btn custom-dialog-confirm';
        overlay.classList.add('show');
        const onOk = () => { overlay.classList.remove('show'); confirmBtn.removeEventListener('click', onOk); resolve(); };
        confirmBtn.addEventListener('click', onOk);
    });
}
function customConfirm(message, options = {}) {
    return new Promise(resolve => {
        const overlay = document.getElementById('customDialogOverlay');
        const icon = document.getElementById('customDialogIcon');
        const msgEl = document.getElementById('customDialogMessage');
        const cancelBtn = document.getElementById('customDialogCancelBtn');
        const confirmBtn = document.getElementById('customDialogConfirmBtn');
        const type = options.danger ? 'warning' : 'info';
        msgEl.textContent = message;
        icon.className = 'custom-dialog-icon dialog-' + type;
        icon.innerHTML = options.danger ? ICONS.warning : '<i class="fas fa-question-circle"></i>';
        cancelBtn.style.display = 'inline-flex';
        cancelBtn.textContent = options.cancelText || 'Cancel';
        confirmBtn.textContent = options.confirmText || 'Confirm';
        confirmBtn.className = 'custom-dialog-btn ' + (options.danger ? 'custom-dialog-danger' : 'custom-dialog-confirm');
        overlay.classList.add('show');
        const cleanup = (result) => {
            overlay.classList.remove('show');
            confirmBtn.removeEventListener('click', onYes);
            cancelBtn.removeEventListener('click', onNo);
            resolve(result);
        };
        const onYes = () => cleanup(true);
        const onNo  = () => cleanup(false);
        confirmBtn.addEventListener('click', onYes);
        cancelBtn.addEventListener('click', onNo);
    });
}

function showForm(show) {
    document.getElementById('formPlaceholder').style.display = show ? 'none' : 'flex';
    document.getElementById('bannerFormFields').style.display = show ? 'flex' : 'none';
}

function showImagePreview(src) {
    const img = document.getElementById('imagePreview');
    const icon = document.getElementById('imagePlaceholderIcon');
    if (src) { img.src = src; img.style.display = 'block'; icon.style.display = 'none'; }
    else { img.src = ''; img.style.display = 'none'; icon.style.display = 'block'; }
}

document.getElementById('bannerImageInput').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => showImagePreview(e.target.result);
    reader.readAsDataURL(file);
});

function toDatetimeLocal(val) {
    if (!val) return '';
    return val.replace(' ', 'T').slice(0, 16);
}

function loadBanner(b) {
    showForm(true);
    document.getElementById('fieldBannerId').value = b.banner_id;
    document.getElementById('fieldTitle').value = b.title;
    document.getElementById('fieldLinkUrl').value = b.link_url || '';
    document.getElementById('fieldTargetPage').value = b.target_page;
    document.getElementById('fieldSortOrder').value = b.sort_order;
    document.getElementById('fieldStartsAt').value = toDatetimeLocal(b.starts_at);
    document.getElementById('fieldEndsAt').value = toDatetimeLocal(b.ends_at);
    document.getElementById('fieldIsActive').checked = !!parseInt(b.is_active);
    document.getElementById('bannerImageInput').value = '';
    showImagePreview(b.image_path);

    document.getElementById('formTitle').textContent = 'Edit Banner';
    document.getElementById('modeBadge').textContent = 'Edit';
    document.getElementById('modeBadge').className = 'form-mode-badge badge-edit';
    document.getElementById('modeBadge').style.display = 'inline-block';
    document.getElementById('submitLabel').textContent = 'Update Banner';

    const isActive = !!parseInt(b.is_active);
    document.getElementById('statusToggleLabel').textContent = isActive ? 'Deactivate' : 'Activate';
    document.getElementById('statusToggleIcon').className = isActive ? 'fas fa-ban' : 'fas fa-check-circle';
    document.getElementById('statusToggleBtn').className = 'status-btn ' + (isActive ? 'status-btn-deactivate' : 'status-btn-activate');
    document.getElementById('statusToggleBtn').style.display = 'flex';

    document.querySelectorAll('.banner-row').forEach(r => r.classList.remove('selected'));
    const row = document.querySelector(`.banner-row[data-id="${b.banner_id}"]`);
    if (row) row.classList.add('selected');
}

function clearForm() {
    showForm(true);
    document.getElementById('fieldBannerId').value = '';
    document.getElementById('fieldTitle').value = '';
    document.getElementById('fieldLinkUrl').value = '';
    document.getElementById('fieldTargetPage').value = 'ALL';
    document.getElementById('fieldSortOrder').value = '0';
    document.getElementById('fieldStartsAt').value = '';
    document.getElementById('fieldEndsAt').value = '';
    document.getElementById('fieldIsActive').checked = true;
    document.getElementById('bannerImageInput').value = '';
    showImagePreview('');

    document.getElementById('formTitle').textContent = 'Add New Banner';
    document.getElementById('modeBadge').textContent = 'New';
    document.getElementById('modeBadge').className = 'form-mode-badge badge-new';
    document.getElementById('modeBadge').style.display = 'inline-block';
    document.getElementById('submitLabel').textContent = 'Add Banner';
    document.getElementById('statusToggleBtn').style.display = 'none';

    document.querySelectorAll('.banner-row').forEach(r => r.classList.remove('selected'));
}

document.getElementById('addNewBtn').addEventListener('click', clearForm);

document.getElementById('saveBtn').addEventListener('click', async function () {
    const id = document.getElementById('fieldBannerId').value.trim();
    const title = document.getElementById('fieldTitle').value.trim();
    if (!title) { await customAlert('Title is required.', 'warning'); return; }

    const imageFile = document.getElementById('bannerImageInput').files[0];
    if (!id && !imageFile) { await customAlert('Please choose a banner image.', 'warning'); return; }

    const formData = new FormData();
    formData.append('banner_id', id);
    formData.append('title', title);
    formData.append('link_url', document.getElementById('fieldLinkUrl').value.trim());
    formData.append('target_page', document.getElementById('fieldTargetPage').value);
    formData.append('sort_order', document.getElementById('fieldSortOrder').value || '0');
    formData.append('is_active', document.getElementById('fieldIsActive').checked ? '1' : '0');
    formData.append('starts_at', document.getElementById('fieldStartsAt').value ? document.getElementById('fieldStartsAt').value.replace('T', ' ') + ':00' : '');
    formData.append('ends_at', document.getElementById('fieldEndsAt').value ? document.getElementById('fieldEndsAt').value.replace('T', ' ') + ':00' : '');
    if (imageFile) formData.append('banner_image', imageFile);

    try {
        const res = await fetch('save_banner.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            await customAlert('Banner saved successfully (' + (data.action || 'saved') + ').', 'success');
            window.location.reload();
        } else {
            await customAlert('Save failed: ' + (data.error || 'unknown error'), 'error');
        }
    } catch (err) {
        await customAlert('Request error: ' + err.message, 'error');
    }
});

async function toggleBannerStatus() {
    const id = document.getElementById('fieldBannerId').value;
    const title = document.getElementById('fieldTitle').value;
    const activating = document.getElementById('statusToggleLabel').textContent === 'Activate';
    const verb = activating ? 'Activate' : 'Deactivate';

    const ok = await customConfirm(`${verb} "${title}"?`, { danger: !activating, confirmText: verb });
    if (!ok) return;

    const formData = new FormData();
    formData.append('banner_id', id);

    try {
        const res = await fetch('toggle_banner_status.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            await customAlert(`Banner ${data.new_status == 1 ? 'activated' : 'deactivated'} successfully.`, 'success');
            window.location.reload();
        } else {
            await customAlert('Status change failed: ' + (data.error || 'unknown error'), 'error');
        }
    } catch (err) {
        await customAlert('Request error: ' + err.message, 'error');
    }
}
</script>

</body>
</html>
