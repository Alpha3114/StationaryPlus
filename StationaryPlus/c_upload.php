<?php
// ============================================================
//  c_upload.php — Customer: Upload files for printing
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';
require_once 'branch_browse.php';

$userId = $_SESSION['user_id'];

// ?link_order=PRE-xxx lets c_orderstatus.php send the customer here to re-upload
// a corrected file to a specific existing order.
$linkOrderId = trim($_GET['link_order'] ?? '');

// ── Load customer's pre-orders for the selector ───────────────
// FIX: Removed the NOT IN (print_files) restriction — customers can
//      now attach multiple print files to the same order, and can
//      re-upload to an order that had a rejected file.
$stmt = $conn->prepare(
    "SELECT o.order_id, o.order_date,
            COUNT(pf.file_id) AS file_count,
            SUM(CASE WHEN pf.file_status = 'REJECTED' THEN 1 ELSE 0 END) AS rejected_count
     FROM orders o
     LEFT JOIN print_files pf ON pf.order_id = o.order_id
     WHERE o.user_id = ?
       AND o.order_type = 'PREORDER'
       AND o.order_status NOT IN ('CANCELLED')
     GROUP BY o.order_id
     ORDER BY o.order_date DESC
     LIMIT 20"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$preorders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Load this customer's recent uploads ───────────────────────
$stmt = $conn->prepare(
    "SELECT file_id, file_name, total_pages, color_pages, bw_pages,
            paper_size, binding_type, copies, estimated_price,
            file_status, rejection_reason, upload_date
     FROM print_files
     WHERE user_id = ?
     ORDER BY upload_date DESC
     LIMIT 8"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$recentUploads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function statusBadge(string $s): string {
    $map = [
        'RECEIVED' => ['#3b82f6','#eff6ff','Under Review'],
        'REVIEWED' => ['#10b981','#ecfdf5','Approved'],
        'REJECTED' => ['#ef4444','#fef2f2','Rejected'],
    ];
    [$c,$bg,$l] = $map[$s] ?? ['#6b7280','#f3f4f6',$s];
    return "<span style='background:$bg;color:$c;border:1px solid {$c}55;
                padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;
                white-space:nowrap;'>$l</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus — Upload Files for Printing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary:#A83535; --secondary:#F4A261; --accent:#F1EDE8;
            --background:#FAFAFA; --text-primary:#2E2E2E; --text-secondary:#707070;
            --border:#E0E0E0; --white:#FFFFFF;
            --sidebar-width:260px; --card-shadow:0 4px 12px rgba(0,0,0,0.05);
        }
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif;}
        body{background-color:var(--background);color:var(--text-primary);min-height:100vh;display:flex;}

        /* ── Sidebar ── */
        .sidebar{width:var(--sidebar-width);background-color:var(--white);border-right:1px solid var(--border);height:100vh;position:fixed;left:0;top:0;display:flex;flex-direction:column;box-shadow:2px 0 10px rgba(0,0,0,0.03);overflow-y:auto;}
        .logo-area{padding:25px;border-bottom:1px solid var(--border);display:flex;align-items:center;flex-shrink:0;}
        .logo-icon{background-color:var(--primary);width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-right:12px;color:white;font-size:20px;}
        .logo-text{font-size:22px;font-weight:700;color:var(--primary);}
        .nav-section{padding:25px 0;border-bottom:1px solid var(--border);}
        .nav-title{font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.8px;padding:0 25px 12px 25px;}
        .nav-menu{list-style:none;}
        .nav-item{margin-bottom:2px;}
        .nav-link{display:flex;align-items:center;padding:13px 25px;color:var(--text-primary);text-decoration:none;transition:all 0.2s ease;border-left:4px solid transparent;}
        .nav-link:hover{background-color:rgba(168,53,53,0.05);color:var(--primary);border-left-color:rgba(168,53,53,0.3);}
        .nav-link.active{background-color:rgba(168,53,53,0.08);color:var(--primary);border-left-color:var(--primary);font-weight:600;}
        .nav-icon{width:22px;text-align:center;margin-right:14px;font-size:16px;}
        .nav-text{font-size:15px;}
        .user-section{margin-top:auto;padding:20px 25px;border-top:1px solid var(--border);}
        .user-info{display:flex;align-items:center;margin-bottom:14px;}
        .user-avatar{width:40px;height:40px;border-radius:50%;background-color:rgba(168,53,53,0.1);display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:700;font-size:16px;margin-right:12px;flex-shrink:0;}
        .user-name{font-weight:600;font-size:15px;color:var(--text-primary);}
        .user-role{font-size:12px;color:var(--text-secondary);margin-top:2px;}
        .logout-link{display:flex;align-items:center;gap:10px;padding:10px 14px;background-color:rgba(168,53,53,0.06);color:var(--primary);border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;transition:background-color 0.2s ease;}
        .logout-link:hover{background-color:rgba(168,53,53,0.14);}

        /* ── Main ── */
        .main-content{flex-grow:1;margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column;}
        .top-header{background-color:var(--white);padding:20px 30px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;}
        .page-title{font-size:24px;font-weight:700;}
        .page-subtitle{font-size:14px;color:var(--text-secondary);margin-top:4px;}
        .content-wrap{padding:24px 30px;flex-grow:1;}

        /* ── Card ── */
        .card{background:var(--white);border-radius:12px;box-shadow:var(--card-shadow);border:1px solid var(--border);margin-bottom:24px;overflow:hidden;}
        .card-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
        .card-title{font-size:16px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:8px;}
        .card-body{padding:24px;}

        /* ── Form ── */
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
        .form-grid.three{grid-template-columns:1fr 1fr 1fr;}
        .full{grid-column:span 2;}
        .form-label{display:block;margin-bottom:7px;font-weight:600;font-size:13px;color:var(--text-primary);}
        .form-label small{font-weight:400;color:var(--text-secondary);}
        .form-select,.form-input{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;background:var(--accent);color:var(--text-primary);transition:all 0.2s;}
        .form-select:focus,.form-input:focus{outline:none;border-color:var(--primary);background:var(--white);box-shadow:0 0 0 3px rgba(168,53,53,0.08);}
        textarea.form-input{resize:vertical;min-height:80px;font-family:inherit;}

        /* ── Print mode selector ── */
        .mode-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;}
        .mode-card{position:relative;cursor:pointer;}
        .mode-card input[type=radio]{position:absolute;opacity:0;width:0;height:0;}
        .mode-label{
            display:flex;flex-direction:column;align-items:center;gap:7px;
            padding:14px 10px;border:2px solid var(--border);border-radius:10px;
            background:var(--white);cursor:pointer;transition:all 0.2s;text-align:center;
            font-size:13px;font-weight:600;color:var(--text-secondary);
        }
        .mode-label:hover{border-color:var(--primary);color:var(--primary);}
        .mode-label .mode-icon{font-size:22px;line-height:1;transition:transform 0.2s;}
        .mode-label .mode-desc{font-size:11px;font-weight:400;color:var(--text-secondary);}
        .mode-card input:checked + .mode-label{border-color:var(--primary);background:rgba(168,53,53,0.06);color:var(--primary);}
        .mode-card input:checked + .mode-label .mode-icon{transform:scale(1.12);}
        .mode-card input:checked + .mode-label .mode-desc{color:rgba(168,53,53,0.7);}
        .mode-note{
            display:none;margin-top:10px;padding:9px 13px;
            background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;
            font-size:12px;color:#1d4ed8;align-items:center;gap:8px;
        }
        .mode-note.show{display:flex;}

        /* ── Drop zone ── */
        .drop-zone{border:2px dashed var(--border);border-radius:10px;padding:36px 20px;text-align:center;background:rgba(168,53,53,0.02);cursor:pointer;transition:all 0.2s;position:relative;}
        .drop-zone:hover,.drop-zone.drag-over{border-color:var(--primary);background:rgba(168,53,53,0.05);}
        .drop-zone.has-file{border-color:#10b981;background:rgba(16,185,129,0.04);border-style:solid;}
        .drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
        .drop-icon{font-size:36px;color:var(--primary);opacity:0.5;margin-bottom:10px;}
        .drop-title{font-size:15px;font-weight:600;color:var(--text-primary);margin-bottom:5px;}
        .drop-subtitle{font-size:13px;color:var(--text-secondary);}
        .file-chosen{display:none;align-items:center;gap:12px;padding:10px 14px;background:rgba(16,185,129,0.08);border-radius:8px;margin-top:14px;border:1px solid #a7f3d0;}
        .file-chosen.show{display:flex;}
        .file-chosen-name{font-size:13px;font-weight:600;color:#065f46;flex-grow:1;word-break:break-all;}
        .file-clear{background:none;border:none;color:#6b7280;cursor:pointer;font-size:16px;padding:2px 5px;border-radius:4px;flex-shrink:0;}
        .file-clear:hover{color:#dc2626;}

        /* ── Analyse button ── */
        .analyse-btn{width:100%;padding:14px;background:var(--primary);color:white;border:none;border-radius:9px;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:background 0.2s;margin-top:4px;}
        .analyse-btn:hover:not(:disabled){background:#8b2a2a;}
        .analyse-btn:disabled{background:#d1d5db;cursor:not-allowed;}

        /* ── Loading overlay ── */
        .loading-overlay{display:none;flex-direction:column;align-items:center;justify-content:center;padding:40px;gap:16px;}
        .loading-overlay.show{display:flex;}
        .spinner{width:36px;height:36px;border:3px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:spin 0.7s linear infinite;}
        @keyframes spin{to{transform:rotate(360deg);}}
        .loading-text{font-size:14px;color:var(--text-secondary);font-weight:500;text-align:center;}

        /* ── Result panel ── */
        .result-panel{display:none;}
        .result-panel.show{display:block;}

        .page-map{display:flex;flex-wrap:wrap;gap:5px;margin:12px 0;}
        .page-dot{width:32px;height:32px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;cursor:pointer;transition:transform 0.1s;border:none;}
        .page-dot:hover{transform:scale(1.15);}
        .page-map-hint{font-size:11px;color:var(--text-secondary);margin-top:2px;}
        .page-dot.colour{background:#fdf2f2;color:#A83535;border:1px solid #fca5a5;}
        .page-dot.bw{background:#f3f4f6;color:#6b7280;border:1px solid #E0E0E0;}

        .summary-bar{display:flex;gap:12px;flex-wrap:wrap;margin:14px 0;}
        .summary-pill{padding:6px 14px;border-radius:20px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px;}
        .pill-colour{background:#fdf2f2;color:#A83535;border:1px solid #fca5a5;}
        .pill-bw{background:#f3f4f6;color:#6b7280;border:1px solid #E0E0E0;}
        .pill-pages{background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe;}

        .price-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:4px;}
        .price-table td{padding:9px 4px;border-bottom:1px solid var(--border);}
        .price-table tr:last-child td{border-bottom:none;}
        .price-table .label{color:var(--text-secondary);}
        .price-table .val{text-align:right;font-weight:600;font-family:monospace;}
        .price-total{display:flex;justify-content:space-between;align-items:baseline;padding:14px 0 0;margin-top:6px;border-top:2px solid var(--primary);}
        .price-total .t-label{font-size:16px;font-weight:700;color:var(--text-primary);}
        .price-total .t-val{font-size:24px;font-weight:700;color:var(--primary);}

        .duration-badge{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;background:rgba(59,130,246,0.07);border:1px solid #bfdbfe;border-radius:8px;font-size:13px;color:#1d4ed8;font-weight:600;margin-top:14px;}

        .confirm-btn{width:100%;padding:13px;background:#10b981;color:white;border:none;border-radius:9px;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:background 0.2s;margin-top:16px;}
        .confirm-btn:hover{background:#059669;}
        .confirm-btn:disabled{background:#d1d5db;cursor:not-allowed;}

        /* ── Alerts ── */
        .alert{padding:13px 18px;border-radius:8px;font-size:14px;display:flex;align-items:flex-start;gap:10px;margin-bottom:4px;}
        .alert-error{background:#fff0f0;color:#c62828;border:1px solid #ef9a9a;}
        .alert-success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;}
        .alert-warn{background:#fffbeb;color:#92400e;border:1px solid #fde68a;}

        /* ── History table ── */
        .hist-table{width:100%;border-collapse:collapse;}
        .hist-table thead{background:rgba(168,53,53,0.04);border-bottom:2px solid var(--border);}
        .hist-table th{padding:11px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.4px;}
        .hist-table tbody tr{border-bottom:1px solid var(--border);transition:background 0.15s;}
        .hist-table tbody tr:last-child{border-bottom:none;}
        .hist-table tbody tr:hover{background:rgba(168,53,53,0.02);}
        .hist-table td{padding:12px 16px;font-size:13px;vertical-align:middle;}
        .file-id{font-family:monospace;font-size:11px;font-weight:700;color:var(--primary);}
        .empty-hist{text-align:center;padding:36px;color:var(--text-secondary);}
        .empty-hist i{font-size:32px;opacity:0.2;margin-bottom:10px;display:block;}

        /* ── Footer ── */
        .page-footer{text-align:center;padding:20px;color:var(--text-secondary);font-size:13px;border-top:1px solid var(--border);background:var(--white);}
        .footer-links a{color:var(--primary);text-decoration:none;margin:0 10px;}

        @media(max-width:1024px){
            :root{--sidebar-width:70px;}
            .logo-text,.nav-text,.user-details,.nav-title,.logout-link span{display:none;}
            .logo-area,.nav-section,.user-section{padding:18px 12px;}
            .nav-link{justify-content:center;padding:14px;border-left:none;border-right:4px solid transparent;}
            .nav-link:hover,.nav-link.active{border-left:none;border-right-color:var(--primary);}
            .nav-icon{margin-right:0;font-size:20px;}
            .logout-link{justify-content:center;padding:10px;}
        }
        @media(max-width:768px){
            .form-grid,.form-grid.three{grid-template-columns:1fr;}
            .full{grid-column:span 1;}
            .mode-grid{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>

<?php include 'c_sidebar.php'; ?>

<main class="main-content">
    <header class="top-header">
        <div>
            <h1 class="page-title">Upload Files for Printing</h1>
            <p class="page-subtitle">Choose your print mode — AI analysis only runs when you need it</p>
        </div>
        <?php render_browsing_branch_bar(); ?>
    </header>

    <div class="content-wrap">

        <!-- ── Upload & specs form ── -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-cloud-upload-alt"></i> New Print Job
                </div>
            </div>
            <div class="card-body">

                <div id="alertBox" style="display:none;margin-bottom:16px;"></div>

                <div class="form-grid">

                    <!-- Pre-order link -->
                    <div class="full">
                        <label class="form-label">Link to Pre-order <small>(optional)</small></label>
                        <select id="preorderSelect" class="form-select">
                            <option value="">— No linked pre-order (standalone print job) —</option>
                            <?php foreach ($preorders as $po):
                                $label = htmlspecialchars($po['order_id'])
                                       . ' — ' . date('d M Y', strtotime($po['order_date']));
                                if ((int)$po['rejected_count'] > 0) {
                                    $label .= ' ⚠ ' . $po['rejected_count'] . ' file(s) rejected';
                                } elseif ((int)$po['file_count'] > 0) {
                                    $label .= ' (' . $po['file_count'] . ' file' . ($po['file_count'] > 1 ? 's' : '') . ' attached)';
                                }
                                $isSelected = ($linkOrderId && $linkOrderId === $po['order_id']);
                            ?>
                                <option value="<?= htmlspecialchars($po['order_id']) ?>"
                                        <?= $isSelected ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($linkOrderId): ?>
                        <div style="margin-top:7px;padding:8px 13px;background:#fffbeb;border:1px solid #fde68a;
                                    border-radius:7px;font-size:12px;color:#92400e;display:flex;gap:7px;align-items:center;">
                            <i class="fas fa-info-circle"></i>
                            You're re-uploading a corrected file for order
                            <strong><?= htmlspecialchars($linkOrderId) ?></strong>.
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Print mode selector -->
                    <div class="full">
                        <label class="form-label">Print Mode</label>
                        <div class="mode-grid">
                            <label class="mode-card">
                                <input type="radio" name="print_mode" id="modeBW" value="BW">
                                <span class="mode-label">
                                    <span class="mode-icon">⬛</span>
                                    Black &amp; White
                                    <span class="mode-desc">All pages B&amp;W</span>
                                </span>
                            </label>
                            <label class="mode-card">
                                <input type="radio" name="print_mode" id="modeColor" value="COLOR">
                                <span class="mode-label">
                                    <span class="mode-icon">🎨</span>
                                    Full Colour
                                    <span class="mode-desc">All pages in colour</span>
                                </span>
                            </label>
                            <label class="mode-card">
                                <input type="radio" name="print_mode" id="modeMixed" value="MIXED" checked>
                                <span class="mode-label">
                                    <span class="mode-icon">🤖</span>
                                    Mixed (Auto-detect)
                                    <span class="mode-desc">AI reads each page</span>
                                </span>
                            </label>
                        </div>
                        <div class="mode-note show" id="modeNote">
                            <i class="fas fa-robot"></i>
                            AI will analyse your file page-by-page to detect colour content. This takes a few extra seconds.
                        </div>
                    </div>

                    <!-- File drop zone -->
                    <div class="full">
                        <label class="form-label">Print File <small>(PDF, JPG, PNG — max 20 MB)</small></label>
                        <div class="drop-zone" id="dropZone">
                            <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png">
                            <div class="drop-icon"><i class="fas fa-file-upload"></i></div>
                            <div class="drop-title">Click to browse or drag &amp; drop</div>
                            <div class="drop-subtitle">PDF, JPG, or PNG · max 20 MB</div>
                        </div>
                        <div class="file-chosen" id="fileChosen">
                            <i class="fas fa-file-alt" style="color:var(--primary);font-size:18px;flex-shrink:0;"></i>
                            <span class="file-chosen-name" id="fileChosenName"></span>
                            <button type="button" class="file-clear" id="fileClear" title="Remove file">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Paper size -->
                    <div>
                        <label class="form-label">Paper Size</label>
                        <select id="paperSize" class="form-select">
                            <option value="A4" selected>A4 (most common)</option>
                            <option value="A3">A3</option>
                            <option value="A5">A5</option>
                            <option value="A2">A2</option>
                            <option value="A1">A1</option>
                            <option value="A0">A0</option>
                        </select>
                    </div>

                    <!-- Paper type -->
                    <div>
                        <label class="form-label">Paper Type</label>
                        <select id="paperType" class="form-select">
                            <option value="80gsm Standard" selected>80gsm Standard</option>
                            <option value="70gsm Economy">70gsm Economy</option>
                            <option value="100gsm Premium">100gsm Premium</option>
                            <option value="Glossy Photo Paper">Glossy Photo Paper</option>
                            <option value="Art Paper">Art Paper</option>
                            <option value="Recycled Paper">Recycled Paper</option>
                        </select>
                    </div>

                    <!-- Binding -->
                    <div>
                        <label class="form-label">Binding</label>
                        <select id="binding" class="form-select">
                            <option value="None" selected>None</option>
                            <option value="Staple">Staple (RM 0.50)</option>
                            <option value="Spiral">Spiral (RM 3.00)</option>
                        </select>
                    </div>

                    <!-- Copies -->
                    <div>
                        <label class="form-label">Number of Copies</label>
                        <div style="display:flex;align-items:center;gap:0;">
                            <button type="button" id="decBtn"
                                style="width:38px;height:42px;border:1.5px solid var(--border);border-right:none;border-radius:8px 0 0 8px;background:var(--white);font-size:18px;cursor:pointer;color:var(--text-primary);">−</button>
                            <input type="number" id="copies" value="1" min="1" max="99"
                                style="width:60px;height:42px;border:1.5px solid var(--border);border-radius:0;text-align:center;font-size:14px;font-weight:700;background:var(--white);">
                            <button type="button" id="incBtn"
                                style="width:38px;height:42px;border:1.5px solid var(--border);border-left:none;border-radius:0 8px 8px 0;background:var(--white);font-size:18px;cursor:pointer;color:var(--text-primary);">+</button>
                        </div>
                    </div>

                    <!-- Special instructions -->
                    <div class="full">
                        <label class="form-label">Special Instructions <small>(optional)</small></label>
                        <textarea id="instructions" class="form-input"
                            placeholder="e.g. Double-sided, staple top-left, landscape orientation…"></textarea>
                    </div>

                </div><!-- /.form-grid -->

                <!-- Loading state -->
                <div class="loading-overlay" id="loadingOverlay">
                    <div class="spinner"></div>
                    <div class="loading-text">
                        <strong id="loadingTitle">Processing your file…</strong><br>
                        <span id="loadingSubtitle" style="font-size:12px;">Calculating price and print time</span>
                    </div>
                </div>

                <!-- Submit button -->
                <button class="analyse-btn" id="analyseBtn" disabled>
                    <i class="fas fa-magic" id="analyseBtnIcon"></i>
                    <span id="analyseBtnText">Calculate Price</span>
                </button>

            </div>
        </div>

        <!-- ── Result panel ── -->
        <div class="card result-panel" id="resultPanel">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-chart-pie"></i>
                    <span id="resultPanelTitle">Price Estimate</span>
                </div>
                <span id="resultFilename" style="font-size:13px;color:var(--text-secondary);font-weight:600;"></span>
            </div>
            <div class="card-body">

                <!-- AI parse warning (only shown for MIXED mode failures) -->
                <div class="alert alert-warn" id="parseWarn" style="display:none;">
                    <i class="fas fa-exclamation-triangle" style="flex-shrink:0;margin-top:2px;"></i>
                    <div>
                        AI could not fully parse the document.
                        A default B&amp;W estimate has been applied — staff will confirm the final price during review.
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:28px;">

                    <!-- Left: page map + price -->
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:6px;">
                            Page Breakdown
                        </div>
                        <div class="page-map" id="pageMap"></div>
                        <div class="page-map-hint"><i class="fas fa-hand-pointer"></i> Click a page to switch it between colour and B&amp;W</div>
                        <div class="summary-bar" id="summaryBar"></div>

                        <table class="price-table" id="priceTable"></table>
                        <div class="price-total">
                            <span class="t-label">Estimated Total</span>
                            <span class="t-val" id="priceTotal">RM 0.00</span>
                        </div>
                    </div>

                    <!-- Right: duration + confirm -->
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:10px;">
                            Estimated Print Time
                        </div>
                        <div class="duration-badge" id="durationBadge">
                            <i class="fas fa-clock"></i> Calculating…
                        </div>

                        <div style="margin-top:22px;padding:16px;background:var(--accent);border-radius:9px;border:1px solid var(--border);">
                            <div style="font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:6px;">
                                Specs Summary
                            </div>
                            <div id="specsSummary" style="font-size:12px;color:var(--text-secondary);line-height:1.8;"></div>
                        </div>

                        <button class="confirm-btn" id="confirmBtn" disabled>
                            <i class="fas fa-check-circle"></i> Confirm &amp; Submit Print Job
                        </button>
                    </div>
                </div>

                <!-- Success message after confirm -->
                <div class="alert alert-success" id="confirmSuccess" style="display:none;margin-top:16px;">
                    <i class="fas fa-check-circle" style="flex-shrink:0;"></i>
                    <div>
                        <strong>Print job submitted!</strong> Staff will review your file and update the status below.
                        The final price will be confirmed before payment.
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Upload history ── -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-history"></i> Your Upload History</div>
            </div>
            <div style="overflow-x:auto;">
                <div class="empty-hist" id="emptyHistState" style="<?= empty($recentUploads) ? '' : 'display:none;' ?>">
                    <i class="fas fa-file-upload"></i>
                    <p>No files uploaded yet.</p>
                </div>
                <table class="hist-table" id="uploadHistTable" style="<?= empty($recentUploads) ? 'display:none;' : '' ?>">
                    <thead>
                        <tr>
                            <th>File ID</th>
                            <th>Filename</th>
                            <th>Pages</th>
                            <th>Colour / B&W</th>
                            <th>Specs</th>
                            <th>Est. Price</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="uploadHistBody">
                        <?php foreach ($recentUploads as $u): ?>
                        <tr>
                            <td><span class="file-id"><?= htmlspecialchars($u['file_id']) ?></span></td>
                            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?= htmlspecialchars($u['file_name']) ?>
                            </td>
                            <td><?= $u['total_pages'] ?></td>
                            <td>
                                <?php if ($u['color_pages'] > 0): ?>
                                    <span style="color:#A83535;font-weight:600;"><?= $u['color_pages'] ?> C</span>
                                <?php endif; ?>
                                <?php if ($u['bw_pages'] > 0): ?>
                                    <span style="color:#6b7280;"> / <?= $u['bw_pages'] ?> B&W</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12px;color:var(--text-secondary);">
                                <?= htmlspecialchars($u['paper_size']) ?>
                                · <?= $u['copies'] ?> cop<?= $u['copies'] > 1 ? 'ies' : 'y' ?>
                                <?= $u['binding_type'] !== 'NONE' ? '· ' . htmlspecialchars($u['binding_type']) : '' ?>
                            </td>
                            <td style="font-weight:600;color:var(--primary);">
                                RM <?= number_format($u['estimated_price'], 2) ?>
                            </td>
                            <td style="font-size:12px;color:var(--text-secondary);">
                                <?= date('d M Y', strtotime($u['upload_date'])) ?>
                            </td>
                            <td>
                                <?= statusBadge($u['file_status']) ?>
                                <?php if (($u['file_status'] ?? '') === 'REJECTED' && !empty($u['rejection_reason'])): ?>
                                <div style="margin-top:6px;padding:6px 10px;background:#fef2f2;border:1px solid #fca5a5;
                                            border-radius:7px;font-size:11px;color:#991b1b;max-width:200px;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?= htmlspecialchars($u['rejection_reason']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /.content-wrap -->

    <footer class="page-footer">
        &copy; <?= date('Y') ?> StationaryPlus.
        <div class="footer-links">
            <a href="c_dashboard.php">Dashboard</a>
            <a href="c_orderstatus.php">Order Status</a>
            <a href="c_payment.php">Payments</a>
        </div>
    </footer>
</main>

<script>
// ── File handling ─────────────────────────────────────────────
const dropZone   = document.getElementById('dropZone');
const fileInput  = document.getElementById('fileInput');
const analyseBtn = document.getElementById('analyseBtn');
let selectedFile = null;

function setFile(f) {
    selectedFile = f;
    document.getElementById('fileChosenName').textContent = f.name;
    document.getElementById('fileChosen').classList.add('show');
    dropZone.classList.add('has-file');
    analyseBtn.disabled = false;
}

function clearFile() {
    selectedFile = null;
    fileInput.value = '';
    document.getElementById('fileChosen').classList.remove('show');
    dropZone.classList.remove('has-file');
    analyseBtn.disabled = true;
    document.getElementById('resultPanel').classList.remove('show');
    document.getElementById('confirmSuccess').style.display = 'none';
}

dropZone.addEventListener('click', () => fileInput.click());
fileInput.addEventListener('change', e => { if (e.target.files[0]) setFile(e.target.files[0]); });
document.getElementById('fileClear').addEventListener('click', clearFile);

dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    const f = e.dataTransfer.files[0];
    if (f) { fileInput.files = e.dataTransfer.files; setFile(f); }
});

// ── Copies ± ──────────────────────────────────────────────────
const copiesInput = document.getElementById('copies');
document.getElementById('decBtn').onclick = () => { if (copiesInput.value > 1)  copiesInput.value--; };
document.getElementById('incBtn').onclick = () => { if (copiesInput.value < 99) copiesInput.value++; };

// ── Print mode ────────────────────────────────────────────────
function getSelectedMode() {
    return document.querySelector('input[name="print_mode"]:checked')?.value ?? 'MIXED';
}

function updateModeUI() {
    const mode    = getSelectedMode();
    const note    = document.getElementById('modeNote');
    const btnText = document.getElementById('analyseBtnText');
    const btnIcon = document.getElementById('analyseBtnIcon');
    const title   = document.getElementById('resultPanelTitle');

    note.classList.toggle('show', mode === 'MIXED');

    if (mode === 'MIXED') {
        btnText.textContent = 'Analyse & Calculate Price';
        btnIcon.className   = 'fas fa-magic';
        if (title) title.textContent = 'AI Colour Analysis & Price Estimate';
    } else {
        btnText.textContent = 'Calculate Price';
        btnIcon.className   = 'fas fa-calculator';
        if (title) title.textContent = 'Price Estimate';
    }
}

document.querySelectorAll('input[name="print_mode"]').forEach(r =>
    r.addEventListener('change', updateModeUI)
);

// Run once on load to match the pre-checked MIXED option
updateModeUI();

// ── Alert helpers ─────────────────────────────────────────────
function showAlert(msg, type = 'error') {
    const box = document.getElementById('alertBox');
    box.innerHTML = `<div class="alert alert-${type}">
        <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}" style="flex-shrink:0;margin-top:2px;"></i>
        <div>${msg}</div></div>`;
    box.style.display = 'block';
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function hideAlert() {
    document.getElementById('alertBox').style.display = 'none';
}

// ── Analyse / calculate button ────────────────────────────────
analyseBtn.addEventListener('click', async () => {
    if (!selectedFile) { showAlert('Please select a file first.'); return; }

    const copies   = parseInt(copiesInput.value) || 1;
    const size     = document.getElementById('paperSize').value;
    const type     = document.getElementById('paperType').value;
    const binding  = document.getElementById('binding').value;
    const preorder = document.getElementById('preorderSelect').value;
    const mode     = getSelectedMode();

    // Update loading text based on mode
    document.getElementById('loadingTitle').textContent =
        mode === 'MIXED' ? 'Analysing your file…' : 'Processing your file…';
    document.getElementById('loadingSubtitle').textContent =
        mode === 'MIXED'
            ? 'AI is reading each page to detect colour content'
            : 'Calculating price and print time';

    analyseBtn.style.display = 'none';
    document.getElementById('loadingOverlay').classList.add('show');
    document.getElementById('resultPanel').classList.remove('show');
    hideAlert();

    const form = new FormData();
    form.append('print_file',  selectedFile);
    form.append('preorder_id', preorder);
    form.append('paper_size',  size);
    form.append('paper_type',  type);
    form.append('binding',     binding);
    form.append('copies',      copies);
    form.append('print_mode',  mode);

    try {
        const res  = await fetch('upload_print.php', { method: 'POST', body: form });
        const data = await res.json();

        if (!data.success) {
            showAlert(data.error || 'Upload failed. Please try again.');
            return;
        }

        renderResult(data, mode);

    } catch (err) {
        showAlert('Network error: ' + err.message);
    } finally {
        document.getElementById('loadingOverlay').classList.remove('show');
        analyseBtn.style.display = 'flex';
    }
});

// ── Render result ─────────────────────────────────────────────
let lastAnalysis = null;

function renderResult(d, mode) {
    lastAnalysis = d;
    const b = d.breakdown;

    document.getElementById('resultFilename').textContent = d.filename;

    // Parse warning only relevant for MIXED mode failures
    document.getElementById('parseWarn').style.display =
        (mode === 'MIXED' && !d.ai_parse_ok) ? 'flex' : 'none';

    // Page colour map — click a dot to manually override that page's colour mode
    const map = document.getElementById('pageMap');
    map.innerHTML = '';
    if (d.page_details && d.page_details.length > 0) {
        d.page_details.forEach(p => {
            const dot = document.createElement('button');
            dot.type        = 'button';
            dot.className   = 'page-dot ' + (p.is_color ? 'colour' : 'bw');
            dot.textContent = p.page;
            dot.title       = `Page ${p.page}: ${p.is_color ? 'Colour' : 'Black & White'} — click to toggle`;
            dot.addEventListener('click', () => togglePageColor(p.page));
            map.appendChild(dot);
        });
    } else {
        map.innerHTML = '<span style="font-size:13px;color:var(--text-secondary);">No page detail available.</span>';
    }

    // Summary pills
    document.getElementById('summaryBar').innerHTML = `
        <span class="summary-pill pill-pages"><i class="fas fa-file"></i> ${d.total_pages} page${d.total_pages !== 1 ? 's' : ''}</span>
        ${d.color_pages > 0 ? `<span class="summary-pill pill-colour"><i class="fas fa-palette"></i> ${d.color_pages} colour</span>` : ''}
        ${d.bw_pages    > 0 ? `<span class="summary-pill pill-bw"><i class="fas fa-circle-half-stroke"></i> ${d.bw_pages} B&W</span>` : ''}
        <span class="summary-pill" style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;">
            <i class="fas fa-copy"></i> ${b.copies} cop${b.copies !== 1 ? 'ies' : 'y'}
        </span>`;

    // Price table
    let rows = '';
    if (d.color_pages > 0) {
        rows += `<tr>
            <td class="label">Colour pages (${d.color_pages} × RM ${b.color_rate.toFixed(2)} × ${b.copies})</td>
            <td class="val">RM ${b.color_cost.toFixed(2)}</td></tr>`;
    }
    if (d.bw_pages > 0) {
        rows += `<tr>
            <td class="label">B&W pages (${d.bw_pages} × RM ${b.bw_rate.toFixed(2)} × ${b.copies})</td>
            <td class="val">RM ${b.bw_cost.toFixed(2)}</td></tr>`;
    }
    if (b.binding_cost > 0) {
        rows += `<tr>
            <td class="label">Binding — ${b.binding}</td>
            <td class="val">RM ${b.binding_cost.toFixed(2)}</td></tr>`;
    }
    document.getElementById('priceTable').innerHTML = rows;
    document.getElementById('priceTotal').textContent = 'RM ' + d.price.toFixed(2);

    // Duration
    const hrs    = Math.floor(d.duration_min / 60);
    const mins   = Math.round(d.duration_min % 60);
    const durStr = hrs > 0
        ? `${hrs} hr${hrs > 1 ? 's' : ''} ${mins} min${mins !== 1 ? 's' : ''}`
        : `${mins} minute${mins !== 1 ? 's' : ''}`;
    document.getElementById('durationBadge').innerHTML =
        `<i class="fas fa-clock"></i> Estimated print time: <strong>${durStr}</strong>`;

    // Specs summary
    document.getElementById('specsSummary').innerHTML = `
        Paper: ${b.paper_size} · ${document.getElementById('paperType').value}<br>
        Binding: ${b.binding}<br>
        Copies: ${b.copies}<br>
        Mode: ${{'BW':'Black & White','COLOR':'Full Colour','MIXED':'Mixed (AI detected)'}[mode] ?? mode}<br>
        Branch: <?= $currentBranch ? htmlspecialchars($currentBranch) : 'All branches (no branch selected)' ?>`;

    // Show panel, wire confirm button
    document.getElementById('resultPanel').classList.add('show');
    document.getElementById('confirmSuccess').style.display = 'none';
    document.getElementById('resultPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });

    document.getElementById('confirmBtn').dataset.fileId = d.file_id;
    document.getElementById('confirmBtn').disabled = false;
}

// ── Manual per-page colour override ────────────────────────────
async function togglePageColor(pageNum) {
    if (!lastAnalysis || !lastAnalysis.page_details) return;

    const pages = lastAnalysis.page_details.map(p =>
        p.page === pageNum ? { page: p.page, is_color: !p.is_color } : { page: p.page, is_color: p.is_color }
    );

    const form = new FormData();
    form.append('file_id', lastAnalysis.file_id);
    form.append('pages', JSON.stringify(pages));

    try {
        const res  = await fetch('update_page_colors.php', { method: 'POST', body: form });
        const data = await res.json();
        if (!data.success) {
            showAlert(data.error || 'Could not update that page. Please try again.');
            return;
        }
        renderResult(data, getSelectedMode());
    } catch (err) {
        showAlert('Network error while updating the page. Please try again.');
    }
}

// ── Confirm button ────────────────────────────────────────────
document.getElementById('confirmBtn').addEventListener('click', async function () {
    const btn    = this;
    const fileId = btn.dataset.fileId;

    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';

    try {
        const form = new FormData();
        form.append('file_id', fileId);

        const res  = await fetch('confirm_print.php', { method: 'POST', body: form });
        const data = await res.json();

        if (!data.success) {
            showAlert(data.error || 'Submission failed. Please try again.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm &amp; Submit Print Job';
            return;
        }

        // Prepend the newly submitted file into the real upload history
        // table, so it's visible immediately without a page reload.
        prependHistoryRow(lastAnalysis);

        // Reset UI for next upload, then scroll back to the top so the
        // customer sees the confirmation + updated history.
        clearFile();
        document.getElementById('resultPanel').classList.remove('show');
        window.scrollTo({ top: 0, behavior: 'smooth' });

    } catch (err) {
        showAlert('Network error. Please try again.');
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm &amp; Submit Print Job';
        return;
    }

    btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm &amp; Submit Print Job';
    btn.disabled  = false;
});

// ── Prepend a row into the real Upload History table ──────────
function prependHistoryRow(d) {
    if (!d) return;
    const b = d.breakdown;

    const today = new Date();
    const dateStr = today.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).replace(/ /g, ' ');

    let colourCell = '';
    if (d.color_pages > 0) colourCell += `<span style="color:#A83535;font-weight:600;">${d.color_pages} C</span>`;
    if (d.bw_pages    > 0) colourCell += `<span style="color:#6b7280;"> / ${d.bw_pages} B&W</span>`;

    const row = document.createElement('tr');
    row.innerHTML = `
        <td><span class="file-id">${esc(d.file_id)}</span></td>
        <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(d.filename)}</td>
        <td>${d.total_pages}</td>
        <td>${colourCell}</td>
        <td style="font-size:12px;color:var(--text-secondary);">
            ${esc(b.paper_size)} · ${b.copies} cop${b.copies > 1 ? 'ies' : 'y'}${b.binding && b.binding !== 'None' ? ' · ' + esc(b.binding) : ''}
        </td>
        <td style="font-weight:600;color:var(--primary);">RM ${d.price.toFixed(2)}</td>
        <td style="font-size:12px;color:var(--text-secondary);">${dateStr}</td>
        <td><span style="background:#eff6ff;color:#3b82f6;border:1px solid #3b82f655;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;">Under Review</span></td>
    `;

    document.getElementById('uploadHistBody').prepend(row);
    document.getElementById('uploadHistTable').style.display = '';
    document.getElementById('emptyHistState').style.display  = 'none';
}

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>