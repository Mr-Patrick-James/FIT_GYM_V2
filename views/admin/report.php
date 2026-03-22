<?php
require_once '../../api/session.php';
requireAdmin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | FitPay Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1.7">
    <style>
        /* ── Report-specific styles ── */
        .report-tabs { display: flex; gap: 4px; background: var(--dark-card); padding: 4px; border-radius: 12px; border: 1px solid var(--dark-border); margin-bottom: 28px; }
        .report-tab  { flex: 1; padding: 10px 16px; border: none; background: transparent; color: var(--dark-text-secondary); font-weight: 600; font-size: 0.78rem; border-radius: 9px; cursor: pointer; transition: all .2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .report-tab.active { background: var(--primary); color: var(--dark-bg); }
        .report-tab:hover:not(.active) { background: rgba(255,255,255,.06); color: #fff; }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* Date range bar */
        .range-bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; background: var(--dark-card); border: 1px solid var(--dark-border); border-radius: 14px; padding: 16px 20px; margin-bottom: 28px; }
        .range-bar label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--dark-text-secondary); white-space: nowrap; }
        .range-bar select, .range-bar input[type="date"] { background: var(--dark-bg-secondary); border: 1px solid var(--dark-border); color: var(--dark-text); border-radius: 8px; padding: 8px 12px; font-size: 0.8rem; font-family: inherit; cursor: pointer; }
        .range-bar .sep { color: var(--dark-text-secondary); font-size: 0.8rem; }
        .range-bar .custom-dates { display: none; align-items: center; gap: 10px; flex-wrap: wrap; }
        .range-bar .custom-dates.visible { display: flex; }
        .apply-btn { padding: 8px 18px; background: var(--primary); color: var(--dark-bg); border: none; border-radius: 8px; font-weight: 700; font-size: 0.78rem; cursor: pointer; transition: opacity .2s; }
        .apply-btn:hover { opacity: .85; }

        /* Summary cards */
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .summary-card { background: var(--dark-card); border: 1px solid var(--dark-border); border-radius: 14px; padding: 20px; }
        .summary-card .sc-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--dark-text-secondary); margin-bottom: 8px; }
        .summary-card .sc-value { font-size: 1.6rem; font-weight: 800; color: var(--dark-text); line-height: 1; }
        .summary-card .sc-sub { font-size: 0.72rem; color: var(--dark-text-secondary); margin-top: 6px; }
        .summary-card.highlight { border-color: rgba(255,255,255,.2); background: linear-gradient(135deg, rgba(255,255,255,.07), rgba(255,255,255,.02)); }

        /* Sales table */
        .data-table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid var(--dark-border); }
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .data-table thead th { background: rgba(255,255,255,.04); padding: 12px 16px; text-align: left; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--dark-text-secondary); border-bottom: 1px solid var(--dark-border); white-space: nowrap; }
        .data-table tbody td { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,.04); color: var(--dark-text); vertical-align: middle; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover td { background: rgba(255,255,255,.03); }
        .data-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        .data-table .bold { font-weight: 700; }

        /* Package rank badge */
        .rank-badge { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 8px; font-size: 0.72rem; font-weight: 800; }
        .rank-1 { background: rgba(245,158,11,.2); color: #f59e0b; }
        .rank-2 { background: rgba(148,163,184,.2); color: #94a3b8; }
        .rank-3 { background: rgba(180,83,9,.2); color: #b45309; }
        .rank-n { background: rgba(255,255,255,.06); color: var(--dark-text-secondary); }

        /* Progress bar */
        .prog-wrap { height: 6px; background: rgba(255,255,255,.08); border-radius: 20px; overflow: hidden; min-width: 80px; }
        .prog-fill  { height: 100%; background: linear-gradient(90deg, #fff, rgba(255,255,255,.6)); border-radius: 20px; transition: width .8s cubic-bezier(.4,0,.2,1); }

        /* Status pill */
        .pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; }
        .pill-verified { background: rgba(34,197,94,.15); color: #22c55e; }
        .pill-pending  { background: rgba(245,158,11,.15); color: #f59e0b; }
        .pill-rejected { background: rgba(239,68,68,.15); color: #ef4444; }

        /* Report builder */
        .builder-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .builder-grid { grid-template-columns: 1fr; } }
        .builder-section { background: var(--dark-card); border: 1px solid var(--dark-border); border-radius: 14px; padding: 20px; }
        .builder-section h4 { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--dark-text-secondary); margin: 0 0 14px; }
        .check-list { display: flex; flex-direction: column; gap: 10px; }
        .check-item { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; cursor: pointer; }
        .check-item input[type="checkbox"] { width: 16px; height: 16px; accent-color: #fff; cursor: pointer; }
        .format-btns { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 6px; }
        .fmt-btn { flex: 1; min-width: 80px; padding: 10px; border: 1px solid var(--dark-border); background: var(--dark-bg-secondary); color: var(--dark-text); border-radius: 10px; font-size: 0.78rem; font-weight: 600; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 6px; transition: all .2s; }
        .fmt-btn:hover, .fmt-btn.selected { border-color: rgba(255,255,255,.4); background: rgba(255,255,255,.08); }
        .fmt-btn i { font-size: 1.2rem; }
        .generate-btn { width: 100%; padding: 14px; background: var(--primary); color: var(--dark-bg); border: none; border-radius: 12px; font-weight: 800; font-size: 0.9rem; cursor: pointer; margin-top: 20px; transition: opacity .2s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .generate-btn:hover { opacity: .88; }

        /* Empty state */
        .empty-state { text-align: center; padding: 60px 20px; color: var(--dark-text-secondary); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 12px; opacity: .4; }

        /* Chart row */
        .chart-row { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 28px; }
        @media (max-width: 900px) { .chart-row { grid-template-columns: 1fr; } }

        /* Notification */
        .rpt-toast { position: fixed; top: 90px; right: 28px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
        .rpt-toast-item { background: var(--dark-card); border: 1px solid var(--dark-border); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; font-size: 0.82rem; font-weight: 600; box-shadow: 0 8px 24px rgba(0,0,0,.3); pointer-events: all; animation: toastIn .3s ease; }
        .rpt-toast-item.success { border-color: rgba(34,197,94,.4); }
        .rpt-toast-item.error   { border-color: rgba(239,68,68,.4); }
        .rpt-toast-item.info    { border-color: rgba(59,130,246,.4); }
        @keyframes toastIn { from { opacity:0; transform: translateX(20px); } to { opacity:1; transform: translateX(0); } }

        .light-mode .range-bar select,
        .light-mode .range-bar input[type="date"] { color: #111; }
    </style>
    <script>
        (function() {
            const t = localStorage.getItem('theme') || 'dark';
            if (t === 'light') document.documentElement.classList.add('light-mode');
        })();
    </script>
</head>
<body>
    <button class="mobile-menu-btn" id="mobileMenuToggle"><i class="fas fa-bars"></i></button>

    <aside class="sidebar">
        <div class="logo"><h1>FitPay</h1><p>GYM MANAGEMENT</p></div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-chart-line"></i><span>Dashboard</span></a></li>
            <li><a href="bookings.php"><i class="fas fa-calendar-check"></i><span>Bookings</span><span class="badge" id="bookingsBadge"></span></a></li>
            <li><a href="payments.php"><i class="fas fa-money-check"></i><span>Payments</span></a></li>
            <li><a href="members.php"><i class="fas fa-users"></i><span>Members</span></a></li>
            <li><a href="trainers.php"><i class="fas fa-user-tie"></i><span>Trainers</span></a></li>
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i><span>Packages</span></a></li>
            <li><a href="equipment.php"><i class="fas fa-tools"></i><span>Equipment</span></a></li>
            <li><a href="exercises.php"><i class="fas fa-running"></i><span>Exercises</span></a></li>
            <li><a href="report.php" class="active"><i class="fas fa-file-chart-line"></i><span>Reports</span></a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i><span>Settings</span></a></li>
        </ul>
        <div class="admin-profile">
            <div class="admin-avatar"><?php
                $n = $user['name'] ?? 'Admin';
                $i = '';
                foreach (explode(' ', $n) as $w) if ($w) $i .= strtoupper($w[0]);
                echo htmlspecialchars(substr($i, 0, 2));
            ?></div>
            <div class="admin-info">
                <h4><?php echo htmlspecialchars($user['name'] ?? 'Admin'); ?></h4>
                <p>Gym Owner / Manager</p>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <!-- Top bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Reports</h1>
                <p>Track sales, package performance, and generate custom reports</p>
            </div>
            <div class="header-actions">
                <button class="action-btn notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge"></span>
                </button>
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="report-tabs" style="margin-top:28px;">
            <button class="report-tab active" onclick="switchTab('sales')" id="tab-sales">
                <i class="fas fa-chart-bar"></i> Sales Overview
            </button>
            <button class="report-tab" onclick="switchTab('packages')" id="tab-packages">
                <i class="fas fa-dumbbell"></i> Package Sales
            </button>
            <button class="report-tab" onclick="switchTab('builder')" id="tab-builder">
                <i class="fas fa-file-export"></i> Report Builder
            </button>
        </div>

        <!-- Date Range Bar (shared) -->
        <div class="range-bar" id="rangeBar">
            <label>Period</label>
            <select id="periodPreset" onchange="onPresetChange()">
                <option value="7">Last 7 Days</option>
                <option value="30" selected>Last 30 Days</option>
                <option value="90">Last 3 Months</option>
                <option value="180">Last 6 Months</option>
                <option value="365">Last Year</option>
                <option value="custom">Custom Range</option>
                <option value="all">All Time</option>
            </select>
            <div class="custom-dates" id="customDates">
                <label>From</label>
                <input type="date" id="startDate">
                <span class="sep">→</span>
                <label>To</label>
                <input type="date" id="endDate">
            </div>
            <button class="apply-btn" onclick="loadAll()"><i class="fas fa-sync-alt"></i> Apply</button>
        </div>

        <!-- ══ TAB: Sales Overview ══ -->
        <div class="tab-panel active" id="panel-sales">
            <!-- Summary cards -->
            <div class="summary-grid" id="salesSummary">
                <div class="summary-card highlight">
                    <div class="sc-label">Total Sales</div>
                    <div class="sc-value" id="s-total-sales">—</div>
                    <div class="sc-sub">bookings in period</div>
                </div>
                <div class="summary-card">
                    <div class="sc-label">Total Revenue</div>
                    <div class="sc-value" id="s-total-rev">—</div>
                    <div class="sc-sub">all bookings</div>
                </div>
                <div class="summary-card">
                    <div class="sc-label">Verified Revenue</div>
                    <div class="sc-value" id="s-verified-rev">—</div>
                    <div class="sc-sub">confirmed payments</div>
                </div>
                <div class="summary-card">
                    <div class="sc-label">Unique Clients</div>
                    <div class="sc-value" id="s-clients">—</div>
                    <div class="sc-sub">members + walk-ins</div>
                </div>
                <div class="summary-card">
                    <div class="sc-label">Walk-in Bookings</div>
                    <div class="sc-value" id="s-walkin">—</div>
                    <div class="sc-sub">of total bookings</div>
                </div>
                <div class="summary-card">
                    <div class="sc-label">Pending</div>
                    <div class="sc-value" id="s-pending">—</div>
                    <div class="sc-sub">awaiting verification</div>
                </div>
            </div>

            <!-- Charts -->
            <div class="chart-row">
                <div class="content-card">
                    <div class="card-header">
                        <h3>Daily Sales</h3>
                        <div class="card-actions">
                            <select id="salesChartMode" class="card-btn" style="padding:6px 12px;cursor:pointer;" onchange="renderSalesChart()">
                                <option value="revenue">Revenue (₱)</option>
                                <option value="count">Number of Sales</option>
                            </select>
                        </div>
                    </div>
                    <div class="chart-container" style="height:300px;margin-top:16px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                <div class="content-card">
                    <div class="card-header"><h3>Booking Status</h3></div>
                    <div class="chart-container" style="height:300px;margin-top:16px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Sales by date table -->
            <div class="content-card" style="margin-bottom:28px;">
                <div class="card-header">
                    <h3>Sales by Date</h3>
                    <div class="card-actions">
                        <button class="card-btn" onclick="exportSalesCsv()"><i class="fas fa-download"></i> CSV</button>
                    </div>
                </div>
                <div class="data-table-wrap" style="margin-top:16px;" id="salesTableWrap">
                    <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading…</p></div>
                </div>
            </div>
        </div>

        <!-- ══ TAB: Package Sales ══ -->
        <div class="tab-panel" id="panel-packages">
            <div class="chart-row">
                <div class="content-card">
                    <div class="card-header"><h3>Package Revenue Comparison</h3></div>
                    <div class="chart-container" style="height:300px;margin-top:16px;">
                        <canvas id="pkgRevenueChart"></canvas>
                    </div>
                </div>
                <div class="content-card">
                    <div class="card-header"><h3>Package Distribution</h3></div>
                    <div class="chart-container" style="height:300px;margin-top:16px;">
                        <canvas id="pkgDistChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="content-card" style="margin-bottom:28px;">
                <div class="card-header">
                    <h3>Package Sales Breakdown</h3>
                    <div class="card-actions">
                        <button class="card-btn" onclick="exportPackageCsv()"><i class="fas fa-download"></i> CSV</button>
                    </div>
                </div>
                <div class="data-table-wrap" style="margin-top:16px;" id="pkgTableWrap">
                    <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading…</p></div>
                </div>
            </div>
        </div>

        <!-- ══ TAB: Report Builder ══ -->
        <div class="tab-panel" id="panel-builder">
            <div class="builder-grid">
                <div class="builder-section">
                    <h4>Include Sections</h4>
                    <div class="check-list">
                        <label class="check-item"><input type="checkbox" id="inc-summary" checked> Summary & Key Metrics</label>
                        <label class="check-item"><input type="checkbox" id="inc-sales" checked> Sales by Date</label>
                        <label class="check-item"><input type="checkbox" id="inc-packages" checked> Package Sales Breakdown</label>
                        <label class="check-item"><input type="checkbox" id="inc-status"> Booking Status Breakdown</label>
                        <label class="check-item"><input type="checkbox" id="inc-walkin"> Walk-in vs Member Split</label>
                    </div>
                </div>
                <div class="builder-section">
                    <h4>Export Format</h4>
                    <div class="format-btns">
                        <button class="fmt-btn selected" id="fmt-pdf" onclick="selectFormat('pdf')">
                            <i class="fas fa-file-pdf" style="color:#ef4444;"></i> PDF
                        </button>
                        <button class="fmt-btn" id="fmt-excel" onclick="selectFormat('excel')">
                            <i class="fas fa-file-excel" style="color:#22c55e;"></i> Excel
                        </button>
                        <button class="fmt-btn" id="fmt-csv" onclick="selectFormat('csv')">
                            <i class="fas fa-file-csv" style="color:#f59e0b;"></i> CSV
                        </button>
                    </div>
                </div>
                <div class="builder-section" style="grid-column: 1 / -1;">
                    <h4>Report Title & Notes</h4>
                    <input type="text" id="reportTitle" class="settings-input" style="width:100%;margin-bottom:12px;" placeholder="e.g. Monthly Sales Report – March 2026" value="Gym Sales Report">
                    <textarea id="reportNotes" class="settings-input" style="width:100%;height:80px;resize:vertical;" placeholder="Optional notes to include in the report…"></textarea>
                </div>
            </div>
            <button class="generate-btn" onclick="generateReport()">
                <i class="fas fa-file-export"></i> Generate & Download Report
            </button>
        </div>

        <div class="footer">
            <p><i class="fas fa-heart" style="color:var(--primary);"></i> © <?php echo date('Y'); ?> Martinez Fitness Gym • FitPay Management System v2.0</p>
        </div>
    </main>

    <!-- Toast container -->
    <div class="rpt-toast" id="toastContainer"></div>

    <script src="../../assets/js/theme.js"></script>
    <script src="../../assets/js/reports.js?v=2.0"></script>
</body>
</html>
