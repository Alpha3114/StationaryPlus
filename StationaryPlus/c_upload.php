<?php
// ============================================================
//  c_upload.php — Customer: Upload files for printing
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';

$userId = $_SESSION['user_id'];

// ── Load customer's pending pre-orders for the selector ───────
// Pre-orders live in the unified `orders` table with order_type = 'PREORDER'
$stmt = $conn->prepare(
    "SELECT o.order_id, o.order_date
     FROM orders o
     WHERE o.user_id = ?
       AND o.order_type = 'PREORDER'
       AND o.order_status NOT IN ('CANCELLED')
       AND o.order_id NOT IN (
           SELECT DISTINCT order_id FROM print_files
           WHERE order_id IS NOT NULL
       )
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
            paper_size, binding_type, copies, estimated_price, file_status, upload_date
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
        'PENDING'  => ['#f59e0b','#fffbeb','Pending'],
        'PRINTING' => ['#3b82f6','#eff6ff','Printing'],
        'DONE'     => ['#10b981','#ecfdf5','Done'],
        'CANCELLED'=> ['#ef4444','#fef2f2','Cancelled'],
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
        .logout-link{display:flex;align-items:center;gap:10px;padding:10px 14px;background-color:rgba(168,53,53,0.06);color:var(--primary);border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;}
        .logout-link:hover{background-color:rgba(168,53,53,0.14);}

        /* ── Main ── */
        .main-content{flex-grow:1;margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column;}
        .top-header{background-color:var(--white);padding:20px 30px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10;}
        .page-title{font-size:24px;font-weight:700;color:var(--text-primary);}
        .page-subtitle{font-size:14px;color:var(--text-secondary);margin-top:4px;}
        .content-wrap{padding:28px 30px;flex-grow:1;display:flex;flex-direction:column;gap:24px;}

        /* ── Card ── */
        .card{background:var(--white);border-radius:12px;border:1px solid var(--border);box-shadow:var(--card-shadow);overflow:hidden;}
        .card-header{padding:18px 26px;border-bottom:1px solid var(--border);background:rgba(168,53,53,0.03);display:flex;align-items:center;justify-content:space-between;}
        .card-title{font-size:16px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:10px;}
        .card-body{padding:26px;}

        /* ── Form grid ── */
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
        .form-grid.three{grid-template-columns:1fr 1fr 1fr;}
        .full{grid-column:span 2;}
        .form-label{display:block;margin-bottom:7px;font-weight:600;font-size:13px;color:var(--text-primary);}
        .form-label small{font-weight:400;color:var(--text-secondary);}
        .form-select,.form-input{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;background:var(--accent);color:var(--text-primary);transition:all 0.2s;}
        .form-select:focus,.form-input:focus{outline:none;border-color:var(--primary);background:var(--white);box-shadow:0 0 0 3px rgba(168,53,53,0.08);}
        textarea.form-input{resize:vertical;min-height:80px;font-family:inherit;}

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
        .loading-text{font-size:14px;color:var(--text-secondary);font-weight:500;}

        /* ── Result panel ── */
        .result-panel{display:none;}
        .result-panel.show{display:block;}

        /* Page colour map */
        .page-map{display:flex;flex-wrap:wrap;gap:5px;margin:12px 0;}
        .page-dot{width:32px;height:32px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;cursor:default;transition:transform 0.1s;}
        .page-dot:hover{transform:scale(1.15);}
        .page-dot.colour{background:#fdf2f2;color:#A83535;border:1px solid #fca5a5;}
        .page-dot.bw{background:#f3f4f6;color:#6b7280;border:1px solid #E0E0E0;}

        /* Summary bar */
        .summary-bar{display:flex;gap:12px;flex-wrap:wrap;margin:14px 0;}
        .summary-pill{padding:6px 14px;border-radius:20px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px;}
        .pill-colour{background:#fdf2f2;color:#A83535;border:1px solid #fca5a5;}
        .pill-bw{background:#f3f4f6;color:#6b7280;border:1px solid #E0E0E0;}
        .pill-pages{background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe;}

        /* Price table */
        .price-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:4px;}
        .price-table td{padding:9px 4px;border-bottom:1px solid var(--border);}
        .price-table tr:last-child td{border-bottom:none;}
        .price-table .label{color:var(--text-secondary);}
        .price-table .val{text-align:right;font-weight:600;font-family:monospace;}
        .price-total{display:flex;justify-content:space-between;align-items:baseline;padding:14px 0 0;margin-top:6px;border-top:2px solid var(--primary);}
        .price-total .t-label{font-size:16px;font-weight:700;color:var(--text-primary);}
        .price-total .t-val{font-size:24px;font-weight:700;color:var(--primary);}

        /* Duration badge */
        .duration-badge{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;background:rgba(59,130,246,0.07);border:1px solid #bfdbfe;border-radius:8px;font-size:13px;color:#1d4ed8;font-weight:600;margin-top:14px;}

        /* Confirm button */
        .confirm-btn{width:100%;padding:13px;background:#10b981;color:white;border:none;border-radius:9px;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:background 0.2s;margin-top:16px;}
        .confirm-btn:hover{background:#059669;}
        .confirm-btn:disabled{background:#d1d5db;cursor:not-allowed;}

        /* Alert */
        .alert{padding:13px 18px;border-radius:8px;font-size:14px;display:flex;align-items:flex-start;gap:10px;margin-bottom:4px;}
        .alert-error{background:#fff0f0;color:#c62828;border:1px solid #ef9a9a;}
        .alert-success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;}
        .alert-warn{background:#fffbeb;color:#92400e;border:1px solid #fde68a;}

        /* History table */
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

        /* Footer */
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
        }
    </style>
</head>
<body>

<?php include 'c_sidebar.php'; ?>

<main class="main-content">
    <header class="top-header">
        <h1 class="page-title">Upload Files for Printing</h1>
        <p class="page-subtitle">AI automatically detects colour pages and calculates your print price</p>
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

                <!-- Error / success alerts -->
                <div id="alertBox" style="display:none;margin-bottom:16px;"></div>

                <div class="form-grid">

                    <!-- Preorder link -->
                    <div class="full">
                        <label class="form-label">
                            Link to Pre-order <small>(optional)</small>
                        </label>
                        <select id="preorderSelect" class="form-select">
                            <option value="">— No linked pre-order —</option>
                            <?php foreach ($preorders as $po): ?>
                                <option value="<?= htmlspecialchars($po['order_id']) ?>">
                                    <?= htmlspecialchars($po['order_id']) ?>
                                    — <?= date('d M Y', strtotime($po['order_date'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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

                <!-- Loading state (shown during AI analysis) -->
                <div class="loading-overlay" id="loadingOverlay">
                    <div class="spinner"></div>
                    <div class="loading-text">
                        <strong>Analysing your file…</strong><br>
                        <span style="font-size:12px;">AI is reading each page to detect colour content</span>
                    </div>
                </div>

                <!-- Analyse button -->
                <button class="analyse-btn" id="analyseBtn" disabled>
                    <i class="fas fa-magic"></i> Analyse &amp; Calculate Price
                </button>

            </div>
        </div>

        <!-- ── AI result panel (hidden until analysis complete) ── -->
        <div class="card result-panel" id="resultPanel">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-chart-pie"></i> AI Colour Analysis &amp; Price Estimate
                </div>
                <span id="resultFilename" style="font-size:13px;color:var(--text-secondary);font-weight:600;"></span>
            </div>
            <div class="card-body">

                <!-- AI parse warning -->
                <div class="alert alert-warn" id="parseWarn" style="display:none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>AI could not fully parse the document. Results default to Black &amp; White — staff will verify before printing.</div>
                </div>

                <!-- Page colour map -->
                <div style="margin-bottom:20px;">
                    <div style="font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:8px;">
                        Page colour map <span style="font-weight:400;color:var(--text-secondary);font-size:12px;">(hover to see page number)</span>
                    </div>
                    <div class="page-map" id="pageMap"></div>
                    <div style="display:flex;gap:14px;margin-top:6px;">
                        <span style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-secondary);">
                            <span style="width:12px;height:12px;border-radius:3px;background:#fdf2f2;border:1px solid #fca5a5;display:inline-block;"></span> Colour page
                        </span>
                        <span style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-secondary);">
                            <span style="width:12px;height:12px;border-radius:3px;background:#f3f4f6;border:1px solid #E0E0E0;display:inline-block;"></span> Black &amp; White page
                        </span>
                    </div>
                </div>

                <!-- Summary pills -->
                <div class="summary-bar" id="summaryBar"></div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:4px;">

                    <!-- Price breakdown -->
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:10px;">
                            Price Breakdown
                        </div>
                        <table class="price-table" id="priceTable"></table>
                        <div class="price-total">
                            <span class="t-label">Estimated Total</span>
                            <span class="t-val" id="priceTotal">RM 0.00</span>
                        </div>
                        <p style="font-size:11px;color:var(--text-secondary);margin-top:8px;">
                            <i class="fas fa-info-circle"></i>
                            Final price confirmed by staff before payment.
                        </p>
                    </div>

                    <!-- Duration + confirm -->
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

                        <button class="confirm-btn" id="confirmBtn">
                            <i class="fas fa-check-circle"></i> Confirm &amp; Submit Print Job
                        </button>
                    </div>

                </div>

                <!-- Success message after confirm -->
                <div class="alert alert-success" id="confirmSuccess" style="display:none;margin-top:16px;">
                    <i class="fas fa-check-circle" style="flex-shrink:0;"></i>
                    <div>
                        <strong>Print job submitted!</strong> Staff will process your file and update the status below.
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
                <?php if (empty($recentUploads)): ?>
                    <div class="empty-hist">
                        <i class="fas fa-file-upload"></i>
                        <p>No files uploaded yet.</p>
                    </div>
                <?php else: ?>
                <table class="hist-table">
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
                    <tbody>
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
                                · <?= $u['copies'] ?> cop<?= $u['copies'] > 1 ? 'ies':'y' ?>
                                <?= $u['binding_type'] !== 'NONE' ? '· '.htmlspecialchars($u['binding_type']) : '' ?>
                            </td>
                            <td style="font-weight:600;color:var(--primary);">
                                RM <?= number_format($u['estimated_price'], 2) ?>
                            </td>
                            <td style="font-size:12px;color:var(--text-secondary);">
                                <?= date('d M Y', strtotime($u['upload_date'])) ?>
                            </td>
                            <td><?= statusBadge($u['file_status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.content-wrap -->

    <footer class="page-footer">
        &copy; <?= date('Y') ?> StationaryPlus &mdash; Stationery &amp; Printing Management System
        <div class="footer-links">
            <a href="#">Help Center</a> | <a href="#">Contact Support</a> | <a href="#">Privacy Policy</a>
        </div>
    </footer>
</main>

<script>
// ── File selection ────────────────────────────────────────────
const fileInput    = document.getElementById('fileInput');
const dropZone     = document.getElementById('dropZone');
const fileChosen   = document.getElementById('fileChosen');
const fileChosenName = document.getElementById('fileChosenName');
const analyseBtn   = document.getElementById('analyseBtn');

let selectedFile = null;

function setFile(file) {
    if (!file) return;
    selectedFile = file;
    fileChosenName.textContent = file.name;
    fileChosen.classList.add('show');
    dropZone.classList.add('has-file');
    dropZone.querySelector('.drop-title').textContent = 'File selected';
    analyseBtn.disabled = false;
    hideAlert();
    document.getElementById('resultPanel').classList.remove('show');
}

function clearFile() {
    selectedFile = null;
    fileInput.value = '';
    fileChosen.classList.remove('show');
    dropZone.classList.remove('has-file');
    dropZone.querySelector('.drop-title').textContent = 'Click to browse or drag & drop';
    analyseBtn.disabled = true;
    document.getElementById('resultPanel').classList.remove('show');
}

fileInput.addEventListener('change', e => setFile(e.target.files[0]));
document.getElementById('fileClear').addEventListener('click', clearFile);

// Drag & drop
dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    const f = e.dataTransfer.files[0];
    if (f) { fileInput.files = e.dataTransfer.files; setFile(f); }
});

// ── Copies ±  ─────────────────────────────────────────────────
const copiesInput = document.getElementById('copies');
document.getElementById('decBtn').onclick = () => { if (copiesInput.value > 1)  copiesInput.value--; };
document.getElementById('incBtn').onclick = () => { if (copiesInput.value < 99) copiesInput.value++; };

// ── Alert helpers ─────────────────────────────────────────────
function showAlert(msg, type = 'error') {
    const box = document.getElementById('alertBox');
    box.innerHTML = `<div class="alert alert-${type}">
        <i class="fas fa-${type==='error'?'exclamation-circle':'check-circle'}" style="flex-shrink:0;margin-top:2px;"></i>
        <div>${msg}</div></div>`;
    box.style.display = 'block';
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function hideAlert() {
    document.getElementById('alertBox').style.display = 'none';
}

// ── Analyse button ────────────────────────────────────────────
analyseBtn.addEventListener('click', async () => {
    if (!selectedFile) { showAlert('Please select a file first.'); return; }

    const copies  = parseInt(copiesInput.value) || 1;
    const size    = document.getElementById('paperSize').value;
    const type    = document.getElementById('paperType').value;
    const binding = document.getElementById('binding').value;
    const preorder = document.getElementById('preorderSelect').value;

    // Show loading
    analyseBtn.style.display        = 'none';
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

    try {
        const res  = await fetch('upload_print.php', { method: 'POST', body: form });
        const data = await res.json();

        if (!data.success) {
            showAlert(data.error || 'Upload failed. Please try again.');
            return;
        }

        renderResult(data);

    } catch (err) {
        showAlert('Network error: ' + err.message);
    } finally {
        document.getElementById('loadingOverlay').classList.remove('show');
        analyseBtn.style.display = 'flex';
    }
});

// ── Render analysis result ────────────────────────────────────
function renderResult(d) {
    const b = d.breakdown;

    document.getElementById('resultFilename').textContent = d.filename;
    document.getElementById('parseWarn').style.display = d.ai_parse_ok ? 'none' : 'flex';

    // Page colour map
    const map = document.getElementById('pageMap');
    map.innerHTML = '';
    if (d.page_details && d.page_details.length > 0) {
        d.page_details.forEach(p => {
            const dot = document.createElement('span');
            dot.className = 'page-dot ' + (p.is_color ? 'colour' : 'bw');
            dot.textContent = p.page;
            dot.title = `Page ${p.page}: ${p.is_color ? 'Colour' : 'Black & White'} (${p.confidence} confidence)`;
            map.appendChild(dot);
        });
    } else {
        map.innerHTML = '<span style="font-size:13px;color:var(--text-secondary);">No page detail available.</span>';
    }

    // Summary pills
    document.getElementById('summaryBar').innerHTML = `
        <span class="summary-pill pill-pages"><i class="fas fa-file"></i> ${d.total_pages} page${d.total_pages !== 1 ? 's' : ''}</span>
        ${d.color_pages > 0 ? `<span class="summary-pill pill-colour"><i class="fas fa-palette"></i> ${d.color_pages} colour</span>` : ''}
        ${d.bw_pages > 0    ? `<span class="summary-pill pill-bw"><i class="fas fa-circle-half-stroke"></i> ${d.bw_pages} B&W</span>` : ''}
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
    const hrs  = Math.floor(d.duration_min / 60);
    const mins = Math.round(d.duration_min % 60);
    const durStr = hrs > 0
        ? `${hrs} hr${hrs > 1 ? 's' : ''} ${mins} min${mins !== 1 ? 's' : ''}`
        : `${mins} minute${mins !== 1 ? 's' : ''}`;
    document.getElementById('durationBadge').innerHTML =
        `<i class="fas fa-clock"></i> Estimated print time: <strong>${durStr}</strong>`;

    // Specs summary
    document.getElementById('specsSummary').innerHTML = `
        Paper: ${b.paper_size} · ${document.getElementById('paperType').value}<br>
        Binding: ${b.binding}<br>
        Copies: ${b.copies}`;

    // Show panel, wire confirm button
    document.getElementById('resultPanel').classList.add('show');
    document.getElementById('confirmSuccess').style.display = 'none';
    document.getElementById('resultPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });

    // Store file_id for confirm
    document.getElementById('confirmBtn').dataset.fileId = d.file_id;
    document.getElementById('confirmBtn').disabled = false;
}

// ── Confirm button ────────────────────────────────────────────
let sessionUploads = [];   // track files uploaded this session

document.getElementById('confirmBtn').addEventListener('click', async function () {
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';

    await new Promise(r => setTimeout(r, 500));

    // Record this upload in the session list
    const currentFileId  = this.dataset.fileId;
    const currentName    = document.getElementById('resultFilename').textContent;
    const currentPrice   = document.getElementById('priceTotal').textContent;
    sessionUploads.push({ fileId: currentFileId, name: currentName, price: currentPrice });

    // Update session upload list display
    renderSessionUploads();

    // Reset for next file — keep order selection
    clearFile();
    document.getElementById('resultPanel').classList.remove('show');
    document.getElementById('confirmSuccess').style.display = 'none';

    this.innerHTML   = '<i class="fas fa-check-circle"></i> Confirm & Submit Print Job';
    this.disabled    = false;

    // Scroll back up to the upload area
    document.getElementById('dropZone').scrollIntoView({ behavior: 'smooth', block: 'center' });
});

function renderSessionUploads() {
    let box = document.getElementById('sessionUploadBox');
    if (!box) {
        box = document.createElement('div');
        box.id = 'sessionUploadBox';
        box.style.cssText = 'margin-top:0;padding:16px 20px;background:#f0fdf4;border:1px solid #a7f3d0;border-radius:10px;';
        document.querySelector('.content-wrap').insertBefore(
            box,
            document.querySelector('.card:last-child')
        );
    }

    const rows = sessionUploads.map((u, i) => `
        <div style="display:flex;align-items:center;gap:10px;padding:7px 0;
                    border-bottom:1px solid #d1fae5;font-size:13px;">
            <i class="fas fa-check-circle" style="color:#10b981;flex-shrink:0;"></i>
            <span style="flex-grow:1;font-weight:600;color:#065f46;">${u.name}</span>
            <span style="color:#065f46;font-family:monospace;">${u.price}</span>
            <span style="font-family:monospace;font-size:11px;color:#6b7280;">${u.fileId}</span>
        </div>`).join('');

    const linkedOrder = document.getElementById('preorderSelect').value;
    const orderLabel  = linkedOrder
        ? `Linked to order <strong>${linkedOrder}</strong>`
        : 'No linked order (standalone print job)';

    box.innerHTML = `
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
            <i class="fas fa-layer-group" style="color:#10b981;font-size:18px;"></i>
            <div>
                <div style="font-size:14px;font-weight:700;color:#065f46;">
                    ${sessionUploads.length} file${sessionUploads.length > 1 ? 's' : ''} submitted this session
                </div>
                <div style="font-size:12px;color:#6b7280;">${orderLabel}</div>
            </div>
            <a href="c_orderstatus.php" style="margin-left:auto;padding:7px 14px;
               background:#10b981;color:white;border-radius:7px;text-decoration:none;
               font-size:13px;font-weight:600;">
                <i class="fas fa-eye"></i> View order status
            </a>
        </div>
        ${rows}
        <div style="font-size:12px;color:#6b7280;margin-top:10px;">
            <i class="fas fa-info-circle"></i>
            You can upload more files above. Staff will review all files before printing.
        </div>`;
}
</script>
</body>
</html>