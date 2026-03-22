// ── FitPay Reports v2.0 ──────────────────────────────────────────────────────
'use strict';

// ── State ────────────────────────────────────────────────────────────────────
let salesData    = null;   // raw API response
let salesChart   = null;
let statusChart  = null;
let pkgRevChart  = null;
let pkgDistChart = null;
let selectedFmt  = 'pdf';
let activeTab    = 'sales';

// ── Helpers ──────────────────────────────────────────────────────────────────
function peso(v) { return '₱' + (parseFloat(v) || 0).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }); }
function num(v)  { return (parseInt(v) || 0).toLocaleString(); }

function toast(msg, type = 'info') {
    const c = document.getElementById('toastContainer');
    if (!c) return;
    const icons = { success: 'check-circle', error: 'times-circle', info: 'info-circle', warning: 'exclamation-triangle' };
    const el = document.createElement('div');
    el.className = `rpt-toast-item ${type}`;
    el.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i><span>${msg}</span>`;
    c.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

function getDateRange() {
    const preset = document.getElementById('periodPreset').value;
    if (preset === 'all') return { start: null, end: null };
    if (preset === 'custom') {
        return {
            start: document.getElementById('startDate').value || null,
            end:   document.getElementById('endDate').value   || null
        };
    }
    const days = parseInt(preset);
    const end  = new Date();
    const start = new Date(end.getTime() - days * 86400000);
    return {
        start: start.toISOString().split('T')[0],
        end:   end.toISOString().split('T')[0]
    };
}

function getPeriodLabel() {
    const preset = document.getElementById('periodPreset');
    const v = preset.value;
    if (v === 'custom') {
        const s = document.getElementById('startDate').value;
        const e = document.getElementById('endDate').value;
        return `${s || '?'} to ${e || '?'}`;
    }
    return preset.selectedOptions[0]?.text || 'Selected Period';
}

// ── Tab switching ─────────────────────────────────────────────────────────────
function switchTab(tab) {
    activeTab = tab;
    document.querySelectorAll('.report-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    document.getElementById('panel-' + tab).classList.add('active');
    if (salesData) renderTab(tab);
}

// ── Period preset change ──────────────────────────────────────────────────────
function onPresetChange() {
    const v = document.getElementById('periodPreset').value;
    const cd = document.getElementById('customDates');
    if (v === 'custom') {
        cd.classList.add('visible');
        if (!document.getElementById('startDate').value) {
            const d = new Date(); d.setDate(d.getDate() - 30);
            document.getElementById('startDate').value = d.toISOString().split('T')[0];
            document.getElementById('endDate').value   = new Date().toISOString().split('T')[0];
        }
    } else {
        cd.classList.remove('visible');
        loadAll();
    }
}

// ── Load data from API ────────────────────────────────────────────────────────
async function loadAll() {
    const { start, end } = getDateRange();
    let url = '../../api/reports/get-sales.php?';
    if (start) url += `start_date=${start}&`;
    if (end)   url += `end_date=${end}&`;

    try {
        const res  = await fetch(url);
        const json = await res.json();
        if (!json.success) { toast('Failed to load report data', 'error'); return; }
        salesData = json.data;
        renderTab(activeTab);
        renderAllCharts();
    } catch (e) {
        console.error(e);
        toast('Network error loading data', 'error');
    }
}

function renderTab(tab) {
    if (tab === 'sales')    { renderSummary(); renderSalesTable(); renderSalesChart(); renderStatusChart(); }
    if (tab === 'packages') { renderPackageTable(); renderPkgCharts(); }
}

function renderAllCharts() {
    renderSummary();
    renderSalesTable();
    renderSalesChart();
    renderStatusChart();
    renderPackageTable();
    renderPkgCharts();
}

// ── Summary cards ─────────────────────────────────────────────────────────────
function renderSummary() {
    if (!salesData) return;
    const s = salesData.summary;
    document.getElementById('s-total-sales').textContent  = num(s.total_bookings);
    document.getElementById('s-total-rev').textContent    = peso(s.total_revenue);
    document.getElementById('s-verified-rev').textContent = peso(s.verified_revenue);
    document.getElementById('s-clients').textContent      = num(s.unique_clients);
    document.getElementById('s-walkin').textContent       = num(s.walkin_bookings);
    document.getElementById('s-pending').textContent      = num(s.pending_bookings);
}

// ── Sales by date table ───────────────────────────────────────────────────────
function renderSalesTable() {
    const wrap = document.getElementById('salesTableWrap');
    if (!salesData || !salesData.sales_by_date.length) {
        wrap.innerHTML = `<div class="empty-state"><i class="fas fa-calendar-times"></i><p>No sales data for this period</p></div>`;
        return;
    }
    const rows = salesData.sales_by_date.map(r => `
        <tr>
            <td class="bold">${formatDate(r.sale_date)}</td>
            <td class="num">${num(r.total_sales)}</td>
            <td class="num">${peso(r.total_revenue)}</td>
            <td class="num">${peso(r.verified_revenue)}</td>
            <td class="num">${num(r.member_count)}</td>
            <td class="num">${num(r.walkin_count)}</td>
        </tr>`).join('');
    wrap.innerHTML = `
        <table class="data-table">
            <thead><tr>
                <th>Date</th>
                <th class="num">Sales</th>
                <th class="num">Revenue</th>
                <th class="num">Verified Rev.</th>
                <th class="num">Members</th>
                <th class="num">Walk-ins</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>`;
}

function formatDate(d) {
    if (!d) return '—';
    const dt = new Date(d + 'T00:00:00');
    return dt.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

// ── Package table ─────────────────────────────────────────────────────────────
function renderPackageTable() {
    const wrap = document.getElementById('pkgTableWrap');
    if (!salesData || !salesData.package_sales.length) {
        wrap.innerHTML = `<div class="empty-state"><i class="fas fa-dumbbell"></i><p>No package data for this period</p></div>`;
        return;
    }
    const total = salesData.package_sales.reduce((a, p) => a + parseInt(p.total_availed || 0), 0);
    const rows = salesData.package_sales.map((p, i) => {
        const pct = total > 0 ? Math.round((p.total_availed / total) * 100) : 0;
        const rankClass = i === 0 ? 'rank-1' : i === 1 ? 'rank-2' : i === 2 ? 'rank-3' : 'rank-n';
        return `
        <tr>
            <td><span class="rank-badge ${rankClass}">${i + 1}</span></td>
            <td class="bold">${p.package_name}</td>
            <td>${p.package_tag ? `<span class="pill pill-verified">${p.package_tag}</span>` : '—'}</td>
            <td class="num bold">${num(p.total_availed)}</td>
            <td class="num">${num(p.unique_users)}</td>
            <td class="num">${num(p.verified_count)}</td>
            <td class="num">${num(p.pending_count)}</td>
            <td class="num">${peso(p.total_revenue)}</td>
            <td>
                <div style="display:flex;align-items:center;gap:8px;">
                    <div class="prog-wrap" style="flex:1;">
                        <div class="prog-fill" style="width:${pct}%;"></div>
                    </div>
                    <span style="font-size:.72rem;color:var(--dark-text-secondary);min-width:32px;">${pct}%</span>
                </div>
            </td>
        </tr>`;
    }).join('');
    wrap.innerHTML = `
        <table class="data-table">
            <thead><tr>
                <th>#</th>
                <th>Package</th>
                <th>Tag</th>
                <th class="num">Total Availed</th>
                <th class="num">Unique Users</th>
                <th class="num">Verified</th>
                <th class="num">Pending</th>
                <th class="num">Revenue</th>
                <th>Share</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>`;
}

// ── Charts ────────────────────────────────────────────────────────────────────
const CHART_DEFAULTS = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(15,15,15,.95)',
            titleColor: '#fff',
            bodyColor: '#ccc',
            borderColor: 'rgba(255,255,255,.1)',
            borderWidth: 1
        }
    }
};

function renderSalesChart() {
    const canvas = document.getElementById('salesChart');
    if (!canvas || !salesData) return;
    if (salesChart) salesChart.destroy();

    const mode   = document.getElementById('salesChartMode').value;
    const rows   = salesData.sales_by_date;
    const labels = rows.map(r => formatDate(r.sale_date));
    const data   = rows.map(r => mode === 'revenue' ? parseFloat(r.total_revenue) || 0 : parseInt(r.total_sales) || 0);

    const ctx = canvas.getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 300);
    grad.addColorStop(0, 'rgba(255,255,255,.25)');
    grad.addColorStop(1, 'rgba(255,255,255,0)');

    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: mode === 'revenue' ? 'Revenue' : 'Sales',
                data,
                borderColor: '#fff',
                backgroundColor: grad,
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#0a0a0a',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 7
            }]
        },
        options: {
            ...CHART_DEFAULTS,
            plugins: {
                ...CHART_DEFAULTS.plugins,
                tooltip: {
                    ...CHART_DEFAULTS.plugins.tooltip,
                    callbacks: {
                        label: ctx => mode === 'revenue' ? peso(ctx.parsed.y) : ctx.parsed.y + ' sales'
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: '#888', callback: v => mode === 'revenue' ? '₱' + v.toLocaleString() : v } },
                x: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: '#888', maxTicksLimit: 10 } }
            }
        }
    });
}

function renderStatusChart() {
    const canvas = document.getElementById('statusChart');
    if (!canvas || !salesData) return;
    if (statusChart) statusChart.destroy();

    const s = salesData.summary;
    statusChart = new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Verified', 'Pending', 'Rejected'],
            datasets: [{
                data: [s.verified_bookings, s.pending_bookings, s.rejected_bookings],
                backgroundColor: ['rgba(34,197,94,.8)', 'rgba(245,158,11,.8)', 'rgba(239,68,68,.8)'],
                borderColor: '#0a0a0a',
                borderWidth: 3
            }]
        },
        options: {
            ...CHART_DEFAULTS,
            plugins: {
                ...CHART_DEFAULTS.plugins,
                legend: { display: true, position: 'bottom', labels: { color: '#aaa', padding: 14, font: { size: 11 } } }
            },
            cutout: '68%'
        }
    });
}

function renderPkgCharts() {
    const pkgs = salesData?.package_sales;
    if (!pkgs || !pkgs.length) return;

    const labels   = pkgs.map(p => p.package_name);
    const revenues = pkgs.map(p => parseFloat(p.total_revenue) || 0);
    const counts   = pkgs.map(p => parseInt(p.total_availed) || 0);
    const colors   = ['rgba(255,255,255,.85)', 'rgba(255,255,255,.65)', 'rgba(255,255,255,.5)', 'rgba(255,255,255,.35)', 'rgba(255,255,255,.2)', 'rgba(255,255,255,.12)'];

    // Revenue bar chart
    const rc = document.getElementById('pkgRevenueChart');
    if (rc) {
        if (pkgRevChart) pkgRevChart.destroy();
        pkgRevChart = new Chart(rc.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Revenue',
                    data: revenues,
                    backgroundColor: colors,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                ...CHART_DEFAULTS,
                plugins: {
                    ...CHART_DEFAULTS.plugins,
                    tooltip: { ...CHART_DEFAULTS.plugins.tooltip, callbacks: { label: ctx => peso(ctx.parsed.y) } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: '#888', callback: v => '₱' + v.toLocaleString() } },
                    x: { grid: { display: false }, ticks: { color: '#888' } }
                }
            }
        });
    }

    // Distribution doughnut
    const dc = document.getElementById('pkgDistChart');
    if (dc) {
        if (pkgDistChart) pkgDistChart.destroy();
        pkgDistChart = new Chart(dc.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: counts,
                    backgroundColor: colors,
                    borderColor: '#0a0a0a',
                    borderWidth: 3
                }]
            },
            options: {
                ...CHART_DEFAULTS,
                plugins: {
                    ...CHART_DEFAULTS.plugins,
                    legend: { display: true, position: 'bottom', labels: { color: '#aaa', padding: 12, font: { size: 11 } } }
                },
                cutout: '65%'
            }
        });
    }
}

// ── CSV Exports ───────────────────────────────────────────────────────────────
function exportSalesCsv() {
    if (!salesData?.sales_by_date.length) { toast('No data to export', 'warning'); return; }
    let csv = 'Date,Total Sales,Revenue,Verified Revenue,Members,Walk-ins\n';
    salesData.sales_by_date.forEach(r => {
        csv += `${r.sale_date},${r.total_sales},${r.total_revenue},${r.verified_revenue},${r.member_count},${r.walkin_count}\n`;
    });
    downloadBlob(csv, 'text/csv', `sales_by_date_${today()}.csv`);
    toast('CSV exported', 'success');
}

function exportPackageCsv() {
    if (!salesData?.package_sales.length) { toast('No data to export', 'warning'); return; }
    let csv = 'Package,Tag,Total Availed,Unique Users,Verified,Pending,Rejected,Revenue\n';
    salesData.package_sales.forEach(p => {
        csv += `"${p.package_name}","${p.package_tag || ''}",${p.total_availed},${p.unique_users},${p.verified_count},${p.pending_count},${p.rejected_count},${p.total_revenue}\n`;
    });
    downloadBlob(csv, 'text/csv', `package_sales_${today()}.csv`);
    toast('CSV exported', 'success');
}

function downloadBlob(content, type, filename) {
    const blob = new Blob([content], { type });
    const url  = URL.createObjectURL(blob);
    const a    = Object.assign(document.createElement('a'), { href: url, download: filename });
    document.body.appendChild(a); a.click();
    setTimeout(() => { document.body.removeChild(a); URL.revokeObjectURL(url); }, 200);
}

function today() { return new Date().toISOString().split('T')[0]; }

// ── Report Builder ────────────────────────────────────────────────────────────
function selectFormat(fmt) {
    selectedFmt = fmt;
    document.querySelectorAll('.fmt-btn').forEach(b => b.classList.remove('selected'));
    document.getElementById('fmt-' + fmt).classList.add('selected');
}

// ── Fetch gym settings once ───────────────────────────────────────────────────
let _gymSettings = null;
async function getGymSettings() {
    if (_gymSettings) return _gymSettings;
    try {
        const res  = await fetch('../../api/settings/get.php');
        const json = await res.json();
        if (json.success) {
            _gymSettings = {};
            json.data.forEach(item => { _gymSettings[item.setting_key] = item.setting_value; });
        }
    } catch (_) {}
    return _gymSettings || {};
}

async function generateReport() {
    if (!salesData) { toast('Load data first by clicking Apply', 'warning'); return; }

    const title      = document.getElementById('reportTitle').value.trim() || 'Gym Sales Report';
    const notes      = document.getElementById('reportNotes').value.trim();
    const period     = getPeriodLabel();
    const incSummary  = document.getElementById('inc-summary').checked;
    const incSales    = document.getElementById('inc-sales').checked;
    const incPackages = document.getElementById('inc-packages').checked;
    const incStatus   = document.getElementById('inc-status').checked;
    const incWalkin   = document.getElementById('inc-walkin').checked;

    if (selectedFmt === 'excel') { await exportExcel(title, period, incSummary, incSales, incPackages, incStatus, incWalkin); return; }
    if (selectedFmt === 'csv')   { exportSalesCsv(); exportPackageCsv(); return; }

    toast('Generating PDF…', 'info');
    const gym = await getGymSettings();
    const html = buildReportHTML(gym, title, period, notes, incSummary, incSales, incPackages, incStatus, incWalkin);

    const container = document.createElement('div');
    container.style.cssText = 'position:fixed;left:-9999px;top:0;width:210mm;';
    container.innerHTML = html;
    document.body.appendChild(container);

    html2pdf().from(container).set({
        margin:      [15, 15, 20, 15],
        filename:    `${title.replace(/\s+/g,'_')}_${today()}.pdf`,
        image:       { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false, letterRendering: true },
        jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak:   { mode: ['avoid-all', 'css', 'legacy'] }
    }).save().then(() => {
        document.body.removeChild(container);
        toast('PDF downloaded', 'success');
    });
}

async function exportExcel(title, period, incSummary, incSales, incPackages, incStatus, incWalkin) {
    const gym = await getGymSettings();
    const gymName    = gym.gym_name    || 'Martinez Fitness Gym';
    const gymAddress = gym.gym_address || '';
    const gymContact = gym.gym_contact || '';
    const gymEmail   = gym.gym_email   || '';
    const wb = XLSX.utils.book_new();
    const s  = salesData.summary;
    const now = new Date().toLocaleString('en-PH');

    // ── Cover / Summary sheet ──────────────────────────────────────────────
    if (incSummary) {
        const totalSales   = parseInt(s.total_bookings)   || 0;
        const totalRev     = parseFloat(s.total_revenue)  || 0;
        const verifiedRev  = parseFloat(s.verified_revenue) || 0;
        const pendingRev   = totalRev - verifiedRev;
        const convRate     = totalSales > 0 ? ((parseInt(s.verified_bookings) / totalSales) * 100).toFixed(1) : '0.0';

        const aoa = [
            [gymName],
            [gymAddress],
            [gymContact + (gymEmail ? '  |  ' + gymEmail : '')],
            [''],
            [title.toUpperCase()],
            ['Report Period:', period],
            ['Generated On:', now],
            ['Generated By:', 'FitPay Management System'],
            [''],
            ['─────────────────────────────────────────'],
            ['KEY PERFORMANCE SUMMARY'],
            ['─────────────────────────────────────────'],
            ['Metric', 'Value'],
            ['Total Bookings',       totalSales],
            ['Total Revenue (PHP)',  totalRev],
            ['Verified Revenue (PHP)', verifiedRev],
            ['Pending Revenue (PHP)',  pendingRev],
            ['Unique Clients',       parseInt(s.unique_clients) || 0],
            ['Walk-in Bookings',     parseInt(s.walkin_bookings) || 0],
            ['Member Bookings',      totalSales - (parseInt(s.walkin_bookings) || 0)],
            ['Verified Bookings',    parseInt(s.verified_bookings) || 0],
            ['Pending Bookings',     parseInt(s.pending_bookings) || 0],
            ['Rejected Bookings',    parseInt(s.rejected_bookings) || 0],
            ['Conversion Rate (%)',  convRate],
            [''],
            ['─────────────────────────────────────────'],
            ['This report was generated automatically by FitPay Gym Management System.'],
            ['All figures are based on booking records within the selected date range.'],
        ];

        if (incStatus) {
            aoa.push(['']);
            aoa.push(['BOOKING STATUS BREAKDOWN']);
            aoa.push(['Status', 'Count', '% of Total']);
            const tot = totalSales || 1;
            aoa.push(['Verified', parseInt(s.verified_bookings), ((parseInt(s.verified_bookings)/tot)*100).toFixed(1)+'%']);
            aoa.push(['Pending',  parseInt(s.pending_bookings),  ((parseInt(s.pending_bookings)/tot)*100).toFixed(1)+'%']);
            aoa.push(['Rejected', parseInt(s.rejected_bookings), ((parseInt(s.rejected_bookings)/tot)*100).toFixed(1)+'%']);
        }

        if (incWalkin) {
            const tot = totalSales || 1;
            const wc  = parseInt(s.walkin_bookings) || 0;
            const mc  = totalSales - wc;
            aoa.push(['']);
            aoa.push(['WALK-IN VS MEMBER SPLIT']);
            aoa.push(['Type', 'Count', '% of Total']);
            aoa.push(['Walk-in', wc, ((wc/tot)*100).toFixed(1)+'%']);
            aoa.push(['Member',  mc, ((mc/tot)*100).toFixed(1)+'%']);
        }

        const ws = XLSX.utils.aoa_to_sheet(aoa);
        ws['!cols'] = [{ wch: 35 }, { wch: 20 }, { wch: 20 }];
        XLSX.utils.book_append_sheet(wb, ws, 'Summary');
    }

    // ── Sales by Date sheet ────────────────────────────────────────────────
    if (incSales && salesData.sales_by_date.length) {
        const header = [
            [gymName + ' — ' + title],
            ['Period: ' + period + '   |   Generated: ' + now],
            [''],
            ['SALES BY DATE'],
            ['Date', 'Total Sales', 'Revenue (PHP)', 'Verified Revenue (PHP)', 'Pending Revenue (PHP)', 'Member Bookings', 'Walk-in Bookings']
        ];
        const dataRows = salesData.sales_by_date.map(r => [
            r.sale_date,
            parseInt(r.total_sales),
            parseFloat(r.total_revenue),
            parseFloat(r.verified_revenue),
            parseFloat(r.total_revenue) - parseFloat(r.verified_revenue),
            parseInt(r.member_count),
            parseInt(r.walkin_count)
        ]);
        // Totals row
        const totals = ['TOTAL',
            dataRows.reduce((a,r) => a + r[1], 0),
            dataRows.reduce((a,r) => a + r[2], 0),
            dataRows.reduce((a,r) => a + r[3], 0),
            dataRows.reduce((a,r) => a + r[4], 0),
            dataRows.reduce((a,r) => a + r[5], 0),
            dataRows.reduce((a,r) => a + r[6], 0)
        ];
        const ws2 = XLSX.utils.aoa_to_sheet([...header, ...dataRows, [''], totals]);
        ws2['!cols'] = [{ wch: 14 }, { wch: 13 }, { wch: 18 }, { wch: 22 }, { wch: 22 }, { wch: 18 }, { wch: 18 }];
        XLSX.utils.book_append_sheet(wb, ws2, 'Sales by Date');
    }

    // ── Package Sales sheet ────────────────────────────────────────────────
    if (incPackages && salesData.package_sales.length) {
        const totalAvailed = salesData.package_sales.reduce((a, p) => a + parseInt(p.total_availed || 0), 0);
        const header = [
            [gymName + ' — ' + title],
            ['Period: ' + period + '   |   Generated: ' + now],
            [''],
            ['PACKAGE SALES BREAKDOWN'],
            ['Rank', 'Package Name', 'Tag / Category', 'Total Availed', 'Unique Users', 'Verified', 'Pending', 'Rejected', 'Revenue (PHP)', 'Share (%)']
        ];
        const dataRows = salesData.package_sales.map((p, i) => {
            const pct = totalAvailed > 0 ? ((parseInt(p.total_availed) / totalAvailed) * 100).toFixed(1) : '0.0';
            return [
                i + 1,
                p.package_name,
                p.package_tag || '—',
                parseInt(p.total_availed),
                parseInt(p.unique_users),
                parseInt(p.verified_count),
                parseInt(p.pending_count),
                parseInt(p.rejected_count),
                parseFloat(p.total_revenue),
                pct + '%'
            ];
        });
        const totals = ['TOTAL', '', '',
            dataRows.reduce((a,r) => a + r[3], 0),
            '',
            dataRows.reduce((a,r) => a + r[5], 0),
            dataRows.reduce((a,r) => a + r[6], 0),
            dataRows.reduce((a,r) => a + r[7], 0),
            dataRows.reduce((a,r) => a + r[8], 0),
            '100%'
        ];
        const ws3 = XLSX.utils.aoa_to_sheet([...header, ...dataRows, [''], totals]);
        ws3['!cols'] = [{ wch: 6 }, { wch: 28 }, { wch: 16 }, { wch: 14 }, { wch: 13 }, { wch: 11 }, { wch: 11 }, { wch: 11 }, { wch: 16 }, { wch: 11 }];
        XLSX.utils.book_append_sheet(wb, ws3, 'Package Sales');
    }

    XLSX.writeFile(wb, `${title.replace(/\s+/g,'_')}_${today()}.xlsx`);
    toast('Excel downloaded', 'success');
}

// ── PDF HTML builder ──────────────────────────────────────────────────────────
const PDF_STYLES = `
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Inter,Arial,sans-serif;color:#111;background:#fff;font-size:12px;line-height:1.5;}
.doc{width:100%;max-width:780px;margin:0 auto;}
.doc-header{background:#111;color:#fff;padding:28px 32px 22px;display:flex;justify-content:space-between;align-items:flex-start;}
.doc-header .gym-name{font-size:20px;font-weight:900;letter-spacing:-0.5px;margin-bottom:4px;}
.doc-header .gym-meta{font-size:10px;color:rgba(255,255,255,.65);line-height:1.7;}
.doc-header .report-info{text-align:right;}
.doc-header .report-title{font-size:13px;font-weight:700;color:#fff;margin-bottom:6px;}
.doc-header .report-meta{font-size:10px;color:rgba(255,255,255,.6);line-height:1.7;}
.doc-divider{height:4px;background:linear-gradient(90deg,#333,#888,#333);}
.doc-body{padding:24px 32px;}
.sec-heading{display:flex;align-items:center;gap:10px;margin:24px 0 12px;}
.sec-heading .sec-num{width:22px;height:22px;background:#111;color:#fff;border-radius:50%;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.sec-heading h3{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:#111;}
.sec-heading .sec-line{flex:1;height:1px;background:#ddd;}
.metric-grid{display:grid;gap:10px;margin-bottom:4px;}
.metric-grid.cols-3{grid-template-columns:repeat(3,1fr);}
.metric-grid.cols-2{grid-template-columns:repeat(2,1fr);}
.metric-box{background:#f8f9fa;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;}
.metric-box .m-label{font-size:9px;font-weight:700;text-transform:uppercase;color:#888;margin-bottom:5px;letter-spacing:.4px;}
.metric-box .m-value{font-size:18px;font-weight:800;color:#111;line-height:1;}
.metric-box .m-sub{font-size:9px;color:#aaa;margin-top:4px;}
.metric-box.accent{border-color:#111;background:#111;}
.metric-box.accent .m-label{color:rgba(255,255,255,.6);}
.metric-box.accent .m-value{color:#fff;}
.metric-box.accent .m-sub{color:rgba(255,255,255,.4);}
.doc-table{width:100%;border-collapse:collapse;font-size:11px;margin-bottom:4px;}
.doc-table thead tr{background:#111;}
.doc-table thead th{padding:9px 11px;text-align:left;color:#fff;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;}
.doc-table thead th.r{text-align:right;}
.doc-table tbody td{padding:8px 11px;border-bottom:1px solid #f0f0f0;color:#333;vertical-align:middle;}
.doc-table tbody td.r{text-align:right;font-variant-numeric:tabular-nums;}
.doc-table tbody td.bold{font-weight:700;}
.doc-table tbody tr:nth-child(even) td{background:#fafafa;}
.doc-table tfoot tr{background:#f0f0f0;}
.doc-table tfoot td{padding:9px 11px;font-weight:800;font-size:11px;border-top:2px solid #111;}
.doc-table tfoot td.r{text-align:right;}
.pill{display:inline-block;padding:2px 8px;border-radius:20px;font-size:9px;font-weight:700;text-transform:uppercase;}
.pill-v{background:#dcfce7;color:#166534;}
.pill-p{background:#fef9c3;color:#854d0e;}
.pill-r{background:#fee2e2;color:#991b1b;}
.notes-box{background:#f9f9f9;border-left:3px solid #111;padding:12px 16px;font-size:11px;color:#555;line-height:1.6;margin-top:8px;}
.notes-box strong{color:#111;}
.doc-footer{background:#f4f4f4;border-top:2px solid #111;padding:14px 32px;display:flex;justify-content:space-between;align-items:center;margin-top:32px;}
.doc-footer .footer-left{font-size:9.5px;color:#555;line-height:1.6;}
.doc-footer .footer-right{font-size:9px;color:#999;text-align:right;}
.doc-footer .footer-brand{font-weight:800;color:#111;font-size:10px;}
.page-break{page-break-before:always;}
`;

function buildReportHTML(gym, title, period, notes, incSummary, incSales, incPackages, incStatus, incWalkin) {
    const gymName    = gym.gym_name    || 'Martinez Fitness Gym';
    const gymAddress = gym.gym_address || '';
    const gymContact = gym.gym_contact || '';
    const gymEmail   = gym.gym_email   || '';
    const now        = new Date().toLocaleString('en-PH', { dateStyle: 'long', timeStyle: 'short' });

    const s          = salesData.summary;
    const totalSales = parseInt(s.total_bookings) || 0;
    const totalRev   = parseFloat(s.total_revenue) || 0;
    const verRev     = parseFloat(s.verified_revenue) || 0;
    const pendRev    = totalRev - verRev;
    const convRate   = totalSales > 0 ? ((parseInt(s.verified_bookings) / totalSales) * 100).toFixed(1) : '0.0';
    let sectionNum   = 0;

    function secHead(label) {
        sectionNum++;
        return `<div class="sec-heading"><div class="sec-num">${sectionNum}</div><h3>${label}</h3><div class="sec-line"></div></div>`;
    }

    // ── DOCUMENT HEADER ───────────────────────────────────────────────────
    let html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>${PDF_STYLES}</style></head><body><div class="doc">
    <div class="doc-header">
        <div>
            <div class="gym-name">${gymName.toUpperCase()}</div>
            <div class="gym-meta">
                ${gymAddress ? gymAddress + '<br>' : ''}
                ${gymContact ? 'Tel: ' + gymContact : ''}${gymContact && gymEmail ? '&nbsp;&nbsp;|&nbsp;&nbsp;' : ''}${gymEmail || ''}
            </div>
        </div>
        <div class="report-info">
            <div class="report-title">${title}</div>
            <div class="report-meta">
                Period: ${period}<br>
                Generated: ${now}<br>
                System: FitPay Management v2.0
            </div>
        </div>
    </div>
    <div class="doc-divider"></div>
    <div class="doc-body">`;

    // ── SECTION 1: KEY METRICS ─────────────────────────────────────────────
    if (incSummary) {
        html += secHead('Key Performance Summary');
        html += `<div class="metric-grid cols-3">
            <div class="metric-box accent"><div class="m-label">Total Bookings</div><div class="m-value">${num(totalSales)}</div><div class="m-sub">in selected period</div></div>
            <div class="metric-box accent"><div class="m-label">Total Revenue</div><div class="m-value">${peso(totalRev)}</div><div class="m-sub">all bookings</div></div>
            <div class="metric-box accent"><div class="m-label">Verified Revenue</div><div class="m-value">${peso(verRev)}</div><div class="m-sub">confirmed payments</div></div>
        </div>
        <div class="metric-grid cols-3" style="margin-top:10px;">
            <div class="metric-box"><div class="m-label">Unique Clients</div><div class="m-value">${num(s.unique_clients)}</div><div class="m-sub">members + walk-ins</div></div>
            <div class="metric-box"><div class="m-label">Pending Revenue</div><div class="m-value">${peso(pendRev)}</div><div class="m-sub">awaiting verification</div></div>
            <div class="metric-box"><div class="m-label">Conversion Rate</div><div class="m-value">${convRate}%</div><div class="m-sub">verified / total bookings</div></div>
        </div>`;
    }

    // ── SECTION 2: BOOKING STATUS ──────────────────────────────────────────
    if (incStatus) {
        const tot = totalSales || 1;
        const vb  = parseInt(s.verified_bookings) || 0;
        const pb  = parseInt(s.pending_bookings)  || 0;
        const rb  = parseInt(s.rejected_bookings) || 0;
        html += secHead('Booking Status Breakdown');
        html += `<table class="doc-table">
            <thead><tr><th>Status</th><th class="r">Count</th><th class="r">% of Total</th><th class="r">Revenue (PHP)</th></tr></thead>
            <tbody>
                <tr><td><span class="pill pill-v">Verified</span></td><td class="r bold">${num(vb)}</td><td class="r">${((vb/tot)*100).toFixed(1)}%</td><td class="r">${peso(verRev)}</td></tr>
                <tr><td><span class="pill pill-p">Pending</span></td><td class="r bold">${num(pb)}</td><td class="r">${((pb/tot)*100).toFixed(1)}%</td><td class="r">${peso(pendRev)}</td></tr>
                <tr><td><span class="pill pill-r">Rejected</span></td><td class="r bold">${num(rb)}</td><td class="r">${((rb/tot)*100).toFixed(1)}%</td><td class="r">—</td></tr>
            </tbody>
            <tfoot><tr><td class="bold">TOTAL</td><td class="r bold">${num(totalSales)}</td><td class="r">100%</td><td class="r bold">${peso(totalRev)}</td></tr></tfoot>
        </table>`;
    }

    // ── SECTION 3: WALK-IN VS MEMBER ──────────────────────────────────────
    if (incWalkin) {
        const tot = totalSales || 1;
        const wc  = parseInt(s.walkin_bookings) || 0;
        const mc  = totalSales - wc;
        html += secHead('Walk-in vs Member Split');
        html += `<div class="metric-grid cols-2">
            <div class="metric-box"><div class="m-label">Walk-in Bookings</div><div class="m-value">${num(wc)}</div><div class="m-sub">${((wc/tot)*100).toFixed(1)}% of total bookings</div></div>
            <div class="metric-box"><div class="m-label">Member Bookings</div><div class="m-value">${num(mc)}</div><div class="m-sub">${((mc/tot)*100).toFixed(1)}% of total bookings</div></div>
        </div>`;
    }

    // ── SECTION 4: PACKAGE SALES ───────────────────────────────────────────
    if (incPackages && salesData.package_sales.length) {
        const pkgTotal = salesData.package_sales.reduce((a,p) => a + parseInt(p.total_availed||0), 0);
        const pkgRev   = salesData.package_sales.reduce((a,p) => a + (parseFloat(p.total_revenue)||0), 0);
        const pkgVer   = salesData.package_sales.reduce((a,p) => a + parseInt(p.verified_count||0), 0);
        const pkgPend  = salesData.package_sales.reduce((a,p) => a + parseInt(p.pending_count||0), 0);
        html += secHead('Package Sales Breakdown');
        html += `<table class="doc-table">
            <thead><tr>
                <th style="width:28px;">#</th><th>Package Name</th><th>Category</th>
                <th class="r">Availed</th><th class="r">Unique Users</th>
                <th class="r">Verified</th><th class="r">Pending</th>
                <th class="r">Revenue</th><th class="r">Share</th>
            </tr></thead><tbody>`;
        salesData.package_sales.forEach((p, i) => {
            const pct = pkgTotal > 0 ? ((parseInt(p.total_availed)/pkgTotal)*100).toFixed(1) : '0.0';
            html += `<tr>
                <td class="bold" style="color:#888;">${i+1}</td>
                <td class="bold">${p.package_name}</td>
                <td>${p.package_tag ? `<span class="pill pill-v">${p.package_tag}</span>` : '—'}</td>
                <td class="r bold">${num(p.total_availed)}</td>
                <td class="r">${num(p.unique_users)}</td>
                <td class="r">${num(p.verified_count)}</td>
                <td class="r">${num(p.pending_count)}</td>
                <td class="r bold">${peso(p.total_revenue)}</td>
                <td class="r">${pct}%</td>
            </tr>`;
        });
        html += `</tbody><tfoot><tr>
            <td colspan="3" class="bold">TOTAL</td>
            <td class="r bold">${num(pkgTotal)}</td><td class="r">—</td>
            <td class="r bold">${num(pkgVer)}</td><td class="r bold">${num(pkgPend)}</td>
            <td class="r bold">${peso(pkgRev)}</td><td class="r">100%</td>
        </tr></tfoot></table>`;
    }

    // ── SECTION 5: SALES BY DATE ───────────────────────────────────────────
    if (incSales && salesData.sales_by_date.length) {
        const dTotal   = salesData.sales_by_date.reduce((a,r) => a + parseInt(r.total_sales||0), 0);
        const dRev     = salesData.sales_by_date.reduce((a,r) => a + (parseFloat(r.total_revenue)||0), 0);
        const dVer     = salesData.sales_by_date.reduce((a,r) => a + (parseFloat(r.verified_revenue)||0), 0);
        const dMem     = salesData.sales_by_date.reduce((a,r) => a + parseInt(r.member_count||0), 0);
        const dWalk    = salesData.sales_by_date.reduce((a,r) => a + parseInt(r.walkin_count||0), 0);
        html += `<div class="page-break"></div>`;
        html += secHead('Daily Sales Detail');
        html += `<table class="doc-table">
            <thead><tr>
                <th>Date</th><th class="r">Sales</th><th class="r">Revenue</th>
                <th class="r">Verified Rev.</th><th class="r">Pending Rev.</th>
                <th class="r">Members</th><th class="r">Walk-ins</th>
            </tr></thead><tbody>`;
        salesData.sales_by_date.forEach(r => {
            const pRev = (parseFloat(r.total_revenue)||0) - (parseFloat(r.verified_revenue)||0);
            html += `<tr>
                <td class="bold">${formatDate(r.sale_date)}</td>
                <td class="r">${num(r.total_sales)}</td>
                <td class="r">${peso(r.total_revenue)}</td>
                <td class="r">${peso(r.verified_revenue)}</td>
                <td class="r">${peso(pRev)}</td>
                <td class="r">${num(r.member_count)}</td>
                <td class="r">${num(r.walkin_count)}</td>
            </tr>`;
        });
        html += `</tbody><tfoot><tr>
            <td class="bold">TOTAL (${salesData.sales_by_date.length} days)</td>
            <td class="r bold">${num(dTotal)}</td>
            <td class="r bold">${peso(dRev)}</td>
            <td class="r bold">${peso(dVer)}</td>
            <td class="r bold">${peso(dRev - dVer)}</td>
            <td class="r bold">${num(dMem)}</td>
            <td class="r bold">${num(dWalk)}</td>
        </tr></tfoot></table>`;
    }

    // ── ADMIN NOTES ────────────────────────────────────────────────────────
    if (notes) {
        html += `<div style="margin-top:20px;"><div class="notes-box"><strong>Admin Notes:</strong><br>${notes.replace(/\n/g,'<br>')}</div></div>`;
    }

    html += `</div>`; // end doc-body

    // ── DOCUMENT FOOTER ────────────────────────────────────────────────────
    html += `<div class="doc-footer">
        <div class="footer-left">
            <div class="footer-brand">${gymName}</div>
            ${gymAddress ? '<div>' + gymAddress + '</div>' : ''}
            ${gymContact ? '<div>Tel: ' + gymContact + (gymEmail ? '&nbsp;&nbsp;|&nbsp;&nbsp;' + gymEmail : '') + '</div>' : ''}
        </div>
        <div class="footer-right">
            <div>Report: ${title}</div>
            <div>Period: ${period}</div>
            <div style="margin-top:4px;color:#bbb;">© ${new Date().getFullYear()} ${gymName} &bull; FitPay Management System</div>
        </div>
    </div>`;

    html += `</div></body></html>`;
    return html;
}

// ── Misc ──────────────────────────────────────────────────────────────────────
async function handleLogout() {
    if (!confirm('Are you sure you want to logout?')) return;
    try { await fetch('../../api/auth/logout.php', { method: 'POST' }); } catch (_) {}
    localStorage.removeItem('isLoggedIn');
    localStorage.removeItem('userRole');
    localStorage.removeItem('userData');
    window.location.href = '../../index.php';
}

const mobileBtn = document.getElementById('mobileMenuToggle');
const sidebar   = document.querySelector('.sidebar');
if (mobileBtn && sidebar) {
    mobileBtn.addEventListener('click', e => {
        e.stopPropagation();
        sidebar.classList.toggle('active');
        const ic = mobileBtn.querySelector('i');
        ic.classList.toggle('fa-bars');
        ic.classList.toggle('fa-times');
    });
    document.addEventListener('click', e => {
        if (!sidebar.contains(e.target) && !mobileBtn.contains(e.target) && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            const ic = mobileBtn.querySelector('i');
            ic.classList.add('fa-bars');
            ic.classList.remove('fa-times');
        }
    });
}

async function updatePendingBadge() {
    try {
        const res  = await fetch('../../api/bookings/get-all.php?status=pending');
        const data = await res.json();
        const cnt  = data.success ? data.data.length : 0;
        const b1   = document.getElementById('bookingsBadge');
        const b2   = document.getElementById('notificationBadge');
        if (b1) b1.textContent = cnt || '';
        if (b2) b2.textContent = cnt || '';
    } catch (_) {}
}

document.addEventListener('DOMContentLoaded', async () => {
    await loadAll();
    await updatePendingBadge();
    setInterval(updatePendingBadge, 30000);
});
