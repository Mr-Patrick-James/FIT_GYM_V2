// Reports Charts
let revenueChart = null;
let packageChart = null;
let statusChart = null;
let monthlyChart = null;
let currentPeriod = '30days';

// Load all data from database
async function loadReportsData() {
    try {
        const [bookingsRes, paymentsRes, membersRes] = await Promise.all([
            fetch('../../api/bookings/get-all.php'),
            fetch('../../api/payments/get-all.php'),
            fetch('../../api/users/get-all.php?role=user')
        ]);

        const bookingsData = await bookingsRes.json();
        const paymentsData = await paymentsRes.json();
        const membersData = await membersRes.json();

        return {
            bookings: bookingsData.success ? bookingsData.data : [],
            payments: paymentsData.success ? paymentsData.data : [],
            members: membersData.success ? membersData.data : []
        };
    } catch (error) {
        console.error('Error loading reports data:', error);
        return { bookings: [], payments: [], members: [] };
    }
}

// Get date range based on period
function getDateRange(period) {
    const now = new Date();
    const ranges = {
        '7days': new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000),
        '30days': new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000),
        '3months': new Date(now.getTime() - 90 * 24 * 60 * 60 * 1000),
        '6months': new Date(now.getTime() - 180 * 24 * 60 * 60 * 1000),
        'year': new Date(now.getTime() - 365 * 24 * 60 * 60 * 1000),
        'all': new Date(0)
    };
    
    return ranges[period] || ranges['30days'];
}

// Filter data by period
function filterByPeriod(data, period) {
    if (period === 'all') return data;
    const startDate = getDateRange(period);
    return data.filter(item => {
        const rawDate = item.created_at || item.booking_date || item.date || item.createdAt || null;
        const itemDate = rawDate ? new Date(rawDate) : new Date(0);
        return itemDate >= startDate;
    });
}

// Update key metrics
async function updateKeyMetrics() {
    const { bookings, payments, members } = await loadReportsData();
    const filteredPayments = filterByPeriod(payments, currentPeriod);
    const filteredBookings = filterByPeriod(bookings, currentPeriod);
    
    // Calculate current period metrics
    let totalRevenue = 0;
    filteredPayments.forEach(payment => {
        totalRevenue += parseFloat(payment.amount) || 0;
    });
    
    const days = getDaysInPeriod(currentPeriod);
    const avgRevenue = days > 0 ? totalRevenue / days : 0;
    
    // Calculate previous period metrics for growth calculation
    const prevPeriodData = await getPreviousPeriodMetrics(payments, bookings, members, currentPeriod);
    
    // Update UI
    document.getElementById('totalRevenue').textContent = `₱${Math.round(totalRevenue).toLocaleString()}`;
    document.getElementById('totalBookings').textContent = filteredBookings.length;
    
    // Active members = unique users (registered + walk-ins) with at least one verified booking that is NOT expired
    const activeMemberIdentifiers = new Set();
    bookings.forEach(booking => {
        if (isBookingActive(booking)) {
            activeMemberIdentifiers.add(booking.user_id || booking.email || booking.name);
        }
    });
    
    const activeMembersCount = activeMemberIdentifiers.size;
    document.getElementById('totalMembers').textContent = activeMembersCount;
    document.getElementById('avgRevenue').textContent = `₱${Math.round(avgRevenue).toLocaleString()}`;

    // Update Growth Percentages
    updateGrowthUI('revenueGrowth', totalRevenue, prevPeriodData.revenue);
    updateGrowthUI('bookingsGrowth', filteredBookings.length, prevPeriodData.bookings);
    updateGrowthUI('membersGrowth', activeMembersCount, prevPeriodData.members);
    updateGrowthUI('avgRevenueGrowth', avgRevenue, prevPeriodData.avgRevenue);
}

// Calculate metrics for the previous period to show growth
async function getPreviousPeriodMetrics(payments, bookings, members, period) {
    if (period === 'all') return { revenue: 0, bookings: 0, members: 0, avgRevenue: 0 };

    const now = new Date();
    const periodDays = getDaysInPeriod(period);
    const endPrevDate = getDateRange(period);
    const startPrevDate = new Date(endPrevDate.getTime() - periodDays * 24 * 60 * 60 * 1000);

    const prevPayments = payments.filter(p => {
        const d = new Date(p.created_at || p.date || 0);
        return d >= startPrevDate && d < endPrevDate;
    });

    const prevBookings = bookings.filter(b => {
        const d = new Date(b.created_at || b.booking_date || 0);
        return d >= startPrevDate && d < endPrevDate;
    });

    let prevRevenue = 0;
    prevPayments.forEach(p => prevRevenue += parseFloat(p.amount) || 0);
    
    const prevAvgRevenue = periodDays > 0 ? prevRevenue / periodDays : 0;

    // For active members, we'd need historical data which is complex. 
    // Let's use a simplified approach or just compare to total members if historical is unavailable.
    const prevActiveMembers = members.filter(m => {
        const d = new Date(m.created_at || 0);
        return d < endPrevDate;
    }).length;

    return {
        revenue: prevRevenue,
        bookings: prevBookings.length,
        members: prevActiveMembers,
        avgRevenue: prevAvgRevenue
    };
}

// Update growth percentage in UI
function updateGrowthUI(elementId, current, previous) {
    const el = document.getElementById(elementId);
    if (!el) return;

    let growth = 0;
    if (previous > 0) {
        growth = ((current - previous) / previous) * 100;
    } else if (current > 0) {
        growth = 100;
    }

    const isPositive = growth >= 0;
    el.innerHTML = `<i class="fas fa-arrow-${isPositive ? 'up' : 'down'}"></i> ${Math.abs(Math.round(growth))}%`;
    
    const trendContainer = el.parentElement;
    if (trendContainer) {
        trendContainer.className = `trend ${isPositive ? '' : 'down'}`;
    }
}

// Parse duration string to days
function parseDurationToDays(durationStr) {
    if (!durationStr) return 0;
    const parts = String(durationStr).toLowerCase().split(' ');
    const value = parseInt(parts[0], 10);
    const unit = parts[1] || '';
    if (isNaN(value)) return 0;
    if (unit.includes('day')) return value;
    if (unit.includes('week')) return value * 7;
    if (unit.includes('month')) return value * 30;
    if (unit.includes('year')) return value * 365;
    return value;
}

// Determine if a booking is currently active (verified and unexpired)
function isBookingActive(booking) {
    if (!booking || !booking.status) return false;
    
    const status = String(booking.status).toLowerCase();
    if (status !== 'verified') return false;
    
    const now = new Date();
    
    // If backend provided expires_at use it
    if (booking.expires_at) {
        const exp = new Date(booking.expires_at);
        if (!isNaN(exp.getTime())) {
            return now <= exp;
        }
    }
    
    // Fallback compute from booking_date/created_at and duration
    const startRaw = booking.booking_date || booking.created_at || booking.createdAt || booking.date;
    const days = parseDurationToDays(booking.duration);
    
    if (!startRaw || !days) return false;
    
    const start = new Date(startRaw);
    if (isNaN(start.getTime())) return false;
    
    const expiry = new Date(start);
    expiry.setDate(expiry.getDate() + days);
    
    // Be generous with end-of-day
    const endOfExpiryDay = new Date(expiry);
    endOfExpiryDay.setHours(23, 59, 59, 999);
    
    return now <= endOfExpiryDay;
}

// Get days in period
function getDaysInPeriod(period) {
    const days = {
        '7days': 7,
        '30days': 30,
        '3months': 90,
        '6months': 180,
        'year': 365,
        'all': 365
    };
    return days[period] || 30;
}

// Initialize Revenue Chart
async function initializeRevenueChart() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    const { payments } = await loadReportsData();
    const filteredPayments = filterByPeriod(payments, currentPeriod);
    
    // Group by date
    const revenueByDate = {};
    filteredPayments.forEach(payment => {
        const date = new Date(payment.created_at);
        const dateKey = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        
        if (!revenueByDate[dateKey]) {
            revenueByDate[dateKey] = 0;
        }
        
        const amount = parseFloat(payment.amount) || 0;
        revenueByDate[dateKey] += amount;
    });
    
    const labels = Object.keys(revenueByDate).sort((a, b) => {
        return new Date(a) - new Date(b);
    });
    const data = labels.map(label => revenueByDate[label]);
    
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(255, 255, 255, 0.3)');
    gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
    
    revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue',
                data: data,
                borderColor: '#ffffff',
                backgroundColor: gradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#0a0a0a',
                pointBorderWidth: 3,
                pointRadius: 6,
                pointHoverRadius: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 17, 17, 0.95)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    callbacks: {
                        label: function(context) {
                            return `₱${context.parsed.y.toLocaleString()}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)'
                    },
                    ticks: {
                        color: '#888',
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)'
                    },
                    ticks: {
                        color: '#888'
                    }
                }
            }
        }
    });
}

// Initialize Package Chart
async function initializePackageChart() {
    const ctx = document.getElementById('packageChart').getContext('2d');
    const { payments } = await loadReportsData();
    const filteredPayments = filterByPeriod(payments, currentPeriod);
    
    // Count by package
    const packageCount = {};
    filteredPayments.forEach(payment => {
        const pkg = payment.package_name || 'Unknown';
        packageCount[pkg] = (packageCount[pkg] || 0) + 1;
    });
    
    const labels = Object.keys(packageCount);
    const data = Object.values(packageCount);
    
    packageChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    'rgba(255, 255, 255, 0.8)',
                    'rgba(255, 255, 255, 0.6)',
                    'rgba(255, 255, 255, 0.4)',
                    'rgba(255, 255, 255, 0.3)',
                    'rgba(255, 255, 255, 0.2)'
                ],
                borderColor: '#0a0a0a',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#ffffff',
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 17, 17, 0.95)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#ffffff',
                    borderWidth: 2
                }
            }
        }
    });
}

// Initialize Status Chart
async function initializeStatusChart() {
    const ctx = document.getElementById('statusChart').getContext('2d');
    const { bookings } = await loadReportsData();
    const filteredBookings = filterByPeriod(bookings, currentPeriod);
    
    // Count by status
    const statusCount = {
        'pending': 0,
        'verified': 0,
        'rejected': 0
    };
    
    filteredBookings.forEach(booking => {
        const status = booking.status || 'pending';
        statusCount[status] = (statusCount[status] || 0) + 1;
    });
    
    statusChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Pending', 'Verified', 'Rejected'],
            datasets: [{
                label: 'Bookings',
                data: [statusCount.pending, statusCount.verified, statusCount.rejected],
                backgroundColor: [
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(34, 197, 94, 0.8)',
                    'rgba(239, 68, 68, 0.8)'
                ],
                borderColor: [
                    '#f59e0b',
                    '#22c55e',
                    '#ef4444'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 17, 17, 0.95)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#ffffff',
                    borderWidth: 2
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)'
                    },
                    ticks: {
                        color: '#888',
                        stepSize: 1
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)'
                    },
                    ticks: {
                        color: '#888'
                    }
                }
            }
        }
    });
}

// Initialize Monthly Chart
async function initializeMonthlyChart() {
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    const { payments } = await loadReportsData();
    const filteredPayments = filterByPeriod(payments, currentPeriod);
    
    // Group by month
    const monthlyRevenue = {};
    filteredPayments.forEach(payment => {
        const date = new Date(payment.created_at);
        const monthKey = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
        
        if (!monthlyRevenue[monthKey]) {
            monthlyRevenue[monthKey] = 0;
        }
        
        const amount = parseFloat(payment.amount) || 0;
        monthlyRevenue[monthKey] += amount;
    });
    
    const labels = Object.keys(monthlyRevenue).sort((a, b) => {
        return new Date(a) - new Date(b);
    });
    const data = labels.map(label => monthlyRevenue[label]);
    
    monthlyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue',
                data: data,
                backgroundColor: 'rgba(255, 255, 255, 0.6)',
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 17, 17, 0.95)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    callbacks: {
                        label: function(context) {
                            return `₱${context.parsed.y.toLocaleString()}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)'
                    },
                    ticks: {
                        color: '#888',
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)'
                    },
                    ticks: {
                        color: '#888'
                    }
                }
            }
        }
    });
}

// Update all charts
async function updateAllCharts() {
    const periodSelect = document.getElementById('periodSelect');
    if (!periodSelect) return;
    
    currentPeriod = periodSelect.value;
    
    // Destroy existing charts
    if (revenueChart) revenueChart.destroy();
    if (packageChart) packageChart.destroy();
    if (statusChart) statusChart.destroy();
    if (monthlyChart) monthlyChart.destroy();
    
    // Update metrics
    await updateKeyMetrics();
    
    // Reinitialize charts
    await initializeRevenueChart();
    await initializePackageChart();
    await initializeStatusChart();
    await initializeMonthlyChart();
}

// Export chart
function exportChart(chartId) {
    const canvas = document.getElementById(chartId);
    const url = canvas.toDataURL('image/png');
    const a = document.createElement('a');
    a.href = url;
    a.download = `${chartId}_${new Date().toISOString().split('T')[0]}.png`;
    a.click();
    showNotification('Chart exported successfully!', 'success');
}

// Export data
async function exportData(type) {
    const { bookings, payments } = await loadReportsData();
    const filteredData = type === 'revenue' ? filterByPeriod(payments, currentPeriod) : filterByPeriod(bookings, currentPeriod);
    
    if (filteredData.length === 0) {
        showNotification('No data to export', 'warning');
        return;
    }
    
    let csv = '';
    if (type === 'revenue') {
        csv = 'Date,Client,Package,Amount\n';
        filteredData.forEach(payment => {
            const date = new Date(payment.created_at).toLocaleDateString();
            const name = (payment.user_name || 'Unknown').replace(/,/g, '');
            const pkg = (payment.package_name || 'N/A').replace(/,/g, '');
            const amount = payment.amount;
            csv += `${date},${name},${pkg},${amount}\n`;
        });
    } else {
        csv = 'Date,Client,Package,Amount,Status\n';
        filteredData.forEach(booking => {
            const date = new Date(booking.created_at).toLocaleDateString();
            const name = (booking.user_name || 'Unknown').replace(/,/g, '');
            const pkg = (booking.package_name || 'N/A').replace(/,/g, '');
            const amount = booking.amount;
            const status = (booking.status || 'pending').replace(/,/g, '');
            csv += `${date},${name},${pkg},${amount},${status}\n`;
        });
    }
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${type}_report_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
    
    showNotification(`${type.charAt(0).toUpperCase() + type.slice(1)} data exported successfully!`, 'success');
}


// Generate comprehensive report
async function generateReport() {
    try {
        showNotification('Generating comprehensive report...', 'info');
        
        const { bookings, payments } = await loadReportsData();
        const filteredPayments = filterByPeriod(payments, currentPeriod);
        const filteredBookings = filterByPeriod(bookings, currentPeriod);
        
        let totalRevenue = 0;
        filteredPayments.forEach(payment => {
            const amount = parseFloat(payment.amount) || 0;
            totalRevenue += amount;
        });
        
        const periodSelect = document.getElementById('periodSelect');
        const periodText = periodSelect && periodSelect.selectedOptions && periodSelect.selectedOptions.length > 0 
            ? periodSelect.selectedOptions[0].text 
            : 'All Time';
        
        const report = `
COMPREHENSIVE GYM REPORT
Generated: ${new Date().toLocaleString()}
Period: ${periodText}

KEY METRICS:
- Total Revenue: ₱${Math.round(totalRevenue).toLocaleString()}
- Total Bookings: ${filteredBookings.length}
- Verified Payments: ${filteredPayments.length}
- Pending Bookings: ${filteredBookings.filter(b => b.status === 'pending').length}

PACKAGE PERFORMANCE:
${getPackagePerformance(filteredPayments)}

BOOKING STATUS:
- Verified: ${filteredBookings.filter(b => b.status === 'verified').length}
- Pending: ${filteredBookings.filter(b => b.status === 'pending').length}
- Rejected: ${filteredBookings.filter(b => b.status === 'rejected').length}
    `;
        
        const blob = new Blob([report], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `gym_report_${new Date().toISOString().split('T')[0]}.txt`;
        a.style.display = 'none';
        
        // Append to body, click, then remove
        document.body.appendChild(a);
        a.click();
        
        // Clean up after a short delay
        setTimeout(() => {
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }, 100);
        
        showNotification('Report generated successfully!', 'success');
    } catch (error) {
        console.error('Error generating report:', error);
        showNotification('Error generating report. Please try again.', 'warning');
    }
}

// Get package performance
function getPackagePerformance(payments) {
    const packageStats = {};
    
    payments.forEach(payment => {
        const pkg = payment.package_name || 'Unknown';
        if (!packageStats[pkg]) {
            packageStats[pkg] = { count: 0, revenue: 0 };
        }
        packageStats[pkg].count++;
        const amount = parseFloat(payment.amount) || 0;
        packageStats[pkg].revenue += amount;
    });
    
    let report = '';
    Object.keys(packageStats).forEach(pkg => {
        report += `- ${pkg}: ${packageStats[pkg].count} bookings, ₱${Math.round(packageStats[pkg].revenue).toLocaleString()} revenue\n`;
    });
    
    return report;
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 32px;
        background: ${type === 'success' ? '#22c55e' : type === 'warning' ? '#f59e0b' : '#3b82f6'};
        color: white;
        padding: 16px 24px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
        font-weight: 600;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Export Full Report in various formats
async function exportFullReport(format) {
    try {
        const { bookings, payments } = await loadReportsData();
        const filteredPayments = filterByPeriod(payments, currentPeriod);
        const filteredBookings = filterByPeriod(bookings, currentPeriod);
        
        const filename = `Gym_Report_${new Date().toISOString().split('T')[0]}`;
        const periodText = document.getElementById('periodSelect').selectedOptions[0].text;

        if (format === 'pdf') {
            showNotification('Generating PDF report...', 'info');
            const element = document.querySelector('.main-content');
            const opt = {
                margin: 10,
                filename: `${filename}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, backgroundColor: '#0a0a0a' },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            // Temporary hide sidebar and header for PDF
            const sidebar = document.querySelector('.sidebar');
            const topBar = document.querySelector('.top-bar');
            const dropdown = document.querySelector('.dropdown');
            
            if (sidebar) sidebar.style.display = 'none';
            if (topBar) topBar.style.display = 'none';
            if (dropdown) dropdown.style.display = 'none';
            document.querySelector('.main-content').style.margin = '0';
            document.querySelector('.main-content').style.width = '100%';

            html2pdf().from(element).set(opt).save().then(() => {
                if (sidebar) sidebar.style.display = 'block';
                if (topBar) topBar.style.display = 'flex';
                if (dropdown) dropdown.style.display = 'inline-block';
                document.querySelector('.main-content').style.margin = '';
                document.querySelector('.main-content').style.width = '';
                showNotification('PDF exported successfully!', 'success');
            });

        } else if (format === 'excel') {
            showNotification('Generating Excel report...', 'info');
            const data = filteredPayments.map(p => ({
                'Date': new Date(p.created_at).toLocaleDateString(),
                'Client': p.user_name || 'Walk-in',
                'Package': p.package_name || 'N/A',
                'Amount': parseFloat(p.amount) || 0,
                'Payment Method': p.payment_method || 'N/A'
            }));
            
            const ws = XLSX.utils.json_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Revenue");
            
            // Add bookings sheet
            const bookingData = filteredBookings.map(b => ({
                'Date': new Date(b.created_at).toLocaleDateString(),
                'Client': b.user_name || 'Walk-in',
                'Package': b.package_name || 'N/A',
                'Amount': parseFloat(b.amount) || 0,
                'Status': b.status
            }));
            const ws2 = XLSX.utils.json_to_sheet(bookingData);
            XLSX.utils.book_append_sheet(wb, ws2, "Bookings");
            
            XLSX.writeFile(wb, `${filename}.xlsx`);
            showNotification('Excel exported successfully!', 'success');

        } else if (format === 'docx' || format === 'csv') {
            // For DOCX we'll use a simplified approach generating a Blob of HTML
            // For CSV we already have exportData, but let's unify it here
            if (format === 'csv') {
                exportData('revenue');
            } else {
                showNotification('Generating Word report...', 'info');
                const content = document.querySelector('.main-content').innerHTML;
                const header = "<html xmlns:o='urn:schemas-microsoft-com:office:office' "+
                    "xmlns:w='urn:schemas-microsoft-com:office:word' "+
                    "xmlns='http://www.w3.org/TR/REC-html40'>"+
                    "<head><meta charset='utf-8'><title>Export HTML to Word</title></head><body>";
                const footer = "</body></html>";
                const sourceHTML = header + content + footer;
                
                const source = 'data:application/vnd.ms-word;charset=utf-8,' + encodeURIComponent(sourceHTML);
                const fileDownload = document.createElement("a");
                document.body.appendChild(fileDownload);
                fileDownload.href = source;
                fileDownload.download = `${filename}.doc`;
                fileDownload.click();
                document.body.removeChild(fileDownload);
                showNotification('Word report exported successfully!', 'success');
            }
        }
    } catch (error) {
        console.error('Export error:', error);
        showNotification('Error exporting report', 'warning');
    }
}

// Handle logout
async function handleLogout() {
    if (!confirm('Are you sure you want to logout?')) {
        return;
    }
    
    try {
        // Call logout API to clear PHP session
        const response = await fetch('../../api/auth/logout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        
        // Clear localStorage
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('userRole');
        localStorage.removeItem('userData');
        
        // Redirect to login page
        window.location.href = '../../index.php';
    } catch (error) {
        console.error('Logout error:', error);
        // Still clear localStorage and redirect even if API fails
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('userRole');
        localStorage.removeItem('userData');
        window.location.href = '../../index.php';
    }
}

// Mobile menu toggle functionality
const mobileMenuToggle = document.getElementById('mobileMenuToggle');
const sidebar = document.querySelector('.sidebar');

if (mobileMenuToggle && sidebar) {
    mobileMenuToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('active');
        
        // Change icon based on state
        const icon = this.querySelector('i');
        if (sidebar.classList.contains('active')) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-times');
        } else {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    });
    
    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
        if (!sidebar.contains(e.target) && 
            e.target !== mobileMenuToggle && 
            !mobileMenuToggle.contains(e.target) &&
            sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            const icon = mobileMenuToggle.querySelector('i');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    });
}

// Initialize page
document.addEventListener('DOMContentLoaded', async function() {
    await updateAllCharts();
    
    // Dropdown toggle
    const generateReportBtn = document.getElementById('generateReportBtn');
    const reportDropdown = document.getElementById('reportDropdown');
    
    if (generateReportBtn && reportDropdown) {
        generateReportBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const parent = this.parentElement;
            const isVisible = reportDropdown.style.display === 'block';
            reportDropdown.style.display = isVisible ? 'none' : 'block';
            if (isVisible) parent.classList.remove('active');
            else parent.classList.add('active');
        });
        
        document.addEventListener('click', function() {
            reportDropdown.style.display = 'none';
            if (generateReportBtn.parentElement) {
                generateReportBtn.parentElement.classList.remove('active');
            }
        });
    }

    // Update pending bookings badge
    async function updatePendingBadge() {
        try {
            const response = await fetch('../../api/bookings/get-all.php?status=pending');
            const data = await response.json();
            const pendingCount = data.success ? data.data.length : 0;
            
            const bookingsBadge = document.getElementById('bookingsBadge');
            if (bookingsBadge) {
                bookingsBadge.textContent = pendingCount || '';
            }
            
            const notificationBadge = document.getElementById('notificationBadge');
            if (notificationBadge) {
                notificationBadge.textContent = pendingCount || '';
            }
        } catch (e) {}
    }
    
    await updatePendingBadge();
    
    // Refresh charts every 10 seconds
    setInterval(async () => {
        await updateAllCharts();
        await updatePendingBadge();
    }, 10000);
});
