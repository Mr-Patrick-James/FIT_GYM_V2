// All payments data - loaded from verified bookings
let allPayments = [];
let filteredPayments = [];
let currentViewingPayment = null;
let revenueChart = null;


// Load all payments from database
async function loadAllPayments() {
    try {
        const response = await fetch('../../api/payments/get-all.php');
        const data = await response.json();
        
        if (data.success) {
            allPayments = data.data;
        } else {
            console.error('Error loading payments:', data.message);
            allPayments = [];
        }
    } catch (error) {
        console.error('Network error loading payments:', error);
        allPayments = [];
    }
    
    return allPayments;
}

// Format date for display
function formatDateForDisplay(dateString) {
    if (!dateString) return 'N/A';
    
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            return dateString;
        }
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return dateString;
    }
}

// Format date for filtering
function getDateForFilter(dateString) {
    if (!dateString) return null;
    try {
        return new Date(dateString);
    } catch (e) {
        return null;
    }
}

// Get unique packages from payments
function getUniquePackages() {
    const packages = new Set();
    allPayments.forEach(payment => {
        const packageName = payment.package_name || payment.package;
        if (packageName) {
            packages.add(packageName);
        }
    });
    return Array.from(packages).sort();
}

// Populate package filter
function populatePackageFilter() {
    const packageFilter = document.getElementById('packageFilter');
    const packages = getUniquePackages();
    
    // Clear existing options except "All Packages"
    packageFilter.innerHTML = '<option value="all">All Packages</option>';
    
    packages.forEach(pkg => {
        const option = document.createElement('option');
        option.value = pkg;
        option.textContent = pkg;
        packageFilter.appendChild(option);
    });
}

// Apply filters
function applyFilters() {
    const packageFilter = document.getElementById('packageFilter').value;
    const sortBy = document.getElementById('sortBy').value;
    const dateRange = document.getElementById('dateRange').value;
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    
    filteredPayments = [...allPayments];
    
    // Package filter
    if (packageFilter !== 'all') {
        filteredPayments = filteredPayments.filter(p => (p.package_name || p.package) === packageFilter);
    }
    
    // Date range filter
    if (dateRange !== 'all') {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        
        filteredPayments = filteredPayments.filter(payment => {
            const paymentDate = new Date(payment.created_at);
            if (!paymentDate || isNaN(paymentDate.getTime())) return false;
            
            switch (dateRange) {
                case 'today':
                    return paymentDate >= today;
                case 'week':
                    const weekAgo = new Date(today);
                    weekAgo.setDate(weekAgo.getDate() - 7);
                    return paymentDate >= weekAgo;
                case 'month':
                    const monthAgo = new Date(today);
                    monthAgo.setMonth(monthAgo.getMonth() - 1);
                    return paymentDate >= monthAgo;
                case 'year':
                    const yearAgo = new Date(today);
                    yearAgo.setFullYear(yearAgo.getFullYear() - 1);
                    return paymentDate >= yearAgo;
                default:
                    return true;
            }
        });
    }
    
    // Search filter
    if (searchTerm) {
        filteredPayments = filteredPayments.filter(payment => {
            const name = (payment.user_name || payment.name || '').toLowerCase();
            const email = (payment.email || '').toLowerCase();
            const packageName = (payment.package_name || payment.package || '').toLowerCase();
            const contact = (payment.contact || '').toLowerCase();
            
            return name.includes(searchTerm) || 
                   email.includes(searchTerm) || 
                   packageName.includes(searchTerm) ||
                   contact.includes(searchTerm);
        });
    }
    
    // Sort
    filteredPayments.sort((a, b) => {
        switch (sortBy) {
            case 'date-desc':
                return new Date(b.created_at) - new Date(a.created_at);
            case 'date-asc':
                return new Date(a.created_at) - new Date(b.created_at);
            case 'amount-desc':
                return parseFloat(b.amount) - parseFloat(a.amount);
            case 'amount-asc':
                return parseFloat(a.amount) - parseFloat(b.amount);
            case 'name-asc':
                return (a.user_name || a.name || '').localeCompare(b.user_name || b.name || '');
            case 'name-desc':
                return (b.user_name || b.name || '').localeCompare(a.user_name || a.name || '');
            default:
                return 0;
        }
    });
    
    populatePaymentsTable();
    updateStats();
    updateChart();
}

// Populate payments table
function populatePaymentsTable() {
    const tbody = document.getElementById('paymentsTable');
    const noPaymentsMessage = document.getElementById('noPaymentsMessage');
    tbody.innerHTML = '';
    
    if (filteredPayments.length === 0) {
        tbody.style.display = 'none';
        noPaymentsMessage.style.display = 'block';
        document.getElementById('showingCount').textContent = '0';
        document.getElementById('totalCount').textContent = allPayments.length;
        return;
    }
    
    tbody.style.display = 'table-row-group';
    noPaymentsMessage.style.display = 'none';
    
    filteredPayments.forEach(payment => {
        const row = document.createElement('tr');
        const displayDate = payment.date_formatted || formatDateForDisplay(payment.created_at);
        const amount = payment.amount_formatted || ('₱' + parseFloat(payment.amount).toFixed(2));
        
        row.innerHTML = `
            <td data-label="Client">
                <div style="font-weight: 700; color: var(--primary);">${payment.user_name || payment.name || 'Unknown User'}</div>
                <div style="font-size: 0.9rem; color: var(--dark-text-secondary);">${payment.email || 'No email'}</div>
            </td>
            <td data-label="Package">${payment.package_name || payment.package || 'N/A'}</td>
            <td data-label="Date">${displayDate}</td>
            <td data-label="Amount" style="font-weight: 800; color: var(--success);">${amount}</td>
            <td data-label="Contact">${payment.contact || 'N/A'}</td>
            <td data-label="Status"><span class="status-badge status-verified">Verified</span></td>
            <td data-label="Actions">
                <div class="table-actions">
                    <button class="icon-btn" onclick="viewPayment('${payment.id}')" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    document.getElementById('showingCount').textContent = filteredPayments.length;
    document.getElementById('totalCount').textContent = allPayments.length;
}

// Update stats
function updateStats() {
    // Calculate total revenue
    let totalRevenue = 0;
    allPayments.forEach(p => {
        const amount = parseFloat(p.amount) || 0;
        totalRevenue += amount;
    });
    
    // Calculate monthly revenue
    const now = new Date();
    const currentMonth = now.getMonth();
    const currentYear = now.getFullYear();
    let monthlyRevenue = 0;
    
    allPayments.forEach(p => {
        const paymentDate = new Date(p.created_at);
        if (paymentDate && paymentDate.getMonth() === currentMonth && paymentDate.getFullYear() === currentYear) {
            const amount = parseFloat(p.amount) || 0;
            monthlyRevenue += amount;
        }
    });
    
    // Calculate average payment
    const averagePayment = allPayments.length > 0 ? totalRevenue / allPayments.length : 0;
    
    // Update stat cards
    document.getElementById('totalRevenue').textContent = `₱${totalRevenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    document.getElementById('monthlyRevenue').textContent = `₱${monthlyRevenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    document.getElementById('totalPayments').textContent = allPayments.length;
    document.getElementById('averagePayment').textContent = `₱${Math.round(averagePayment).toLocaleString()}`;
    
    // Update pending bookings badge from API
    updatePendingBadge();
}

async function updatePendingBadge() {
    try {
        const response = await fetch('../../api/bookings/get-all.php?status=pending');
        const data = await response.json();
        const pendingCount = data.success ? data.data.length : 0;
        
        const bookingsBadge = document.getElementById('bookingsBadge');
        if (bookingsBadge) {
            bookingsBadge.textContent = pendingCount || '0';
        }
        
        const notificationBadge = document.getElementById('notificationBadge');
        if (notificationBadge) {
            notificationBadge.textContent = pendingCount || '0';
        }
    } catch (e) {
        console.error('Error updating pending badge:', e);
    }
}

// Initialize revenue chart
function initializeChart() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(255, 255, 255, 0.3)');
    gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
    
    revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Revenue',
                data: [],
                borderColor: '#ffffff',
                backgroundColor: gradient,
                borderWidth: 4,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#0a0a0a',
                pointBorderWidth: 3,
                pointRadius: 8,
                pointHoverRadius: 12,
                pointHoverBackgroundColor: '#ffffff',
                pointHoverBorderColor: '#0a0a0a',
                pointHoverBorderWidth: 4
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
                    cornerRadius: 12,
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
                        },
                        font: {
                            weight: '600'
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)'
                    },
                    ticks: {
                        color: '#888',
                        font: {
                            weight: '600'
                        }
                    }
                }
            }
        }
    });
    
    updateChart();
}

// Update chart based on period
function updateChart() {
    if (!revenueChart) return;
    
    const period = document.getElementById('chartPeriod').value;
    const now = new Date();
    
    let labels = [];
    let data = [];
    
    if (period === 'all' || period === 'year') {
        // Group by month for all time or year
        const monthlyData = {};
        allPayments.forEach(payment => {
            const paymentDate = new Date(payment.created_at);
            if (!paymentDate || isNaN(paymentDate.getTime())) return;
            
            const monthKey = paymentDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
            if (!monthlyData[monthKey]) {
                monthlyData[monthKey] = 0;
            }
            const amount = parseFloat(payment.amount) || 0;
            monthlyData[monthKey] += amount;
        });
        
        labels = Object.keys(monthlyData).sort((a, b) => {
            return new Date(a) - new Date(b);
        });
        data = labels.map(label => monthlyData[label]);
        
        // Limit to last 12 months if too many
        if (labels.length > 12) {
            labels = labels.slice(-12);
            data = data.slice(-12);
        }
    } else if (period === '6months' || period === '3months') {
        const months = period === '6months' ? 6 : 3;
        const monthlyData = {};
        
        for (let i = months - 1; i >= 0; i--) {
            const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const monthKey = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
            monthlyData[monthKey] = 0;
        }
        
        allPayments.forEach(payment => {
            const paymentDate = new Date(payment.created_at);
            if (!paymentDate || isNaN(paymentDate.getTime())) return;
            
            const monthKey = paymentDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
            if (monthlyData.hasOwnProperty(monthKey)) {
                const amount = parseFloat(payment.amount) || 0;
                monthlyData[monthKey] += amount;
            }
        });
        
        labels = Object.keys(monthlyData);
        data = labels.map(label => monthlyData[label]);
    }
    
    revenueChart.data.labels = labels;
    revenueChart.data.datasets[0].data = data;
    revenueChart.update();
}

// Fix receipt URL to be accessible from admin views
function fixReceiptUrl(url) {
    if (!url) return '';
    
    // If it's already a full URL or blob, return as is
    if (url.startsWith('http') || url.startsWith('blob:')) return url;
    
    // Remove leading /Fit/ or / if present to standardize
    let cleanPath = url.replace(/^\/?Fit\//, '').replace(/^\//, '');
    
    // Return relative path from views/admin/ to root
    return '../../' + cleanPath;
}

// View payment details
function viewPayment(id) {
    const payment = allPayments.find(p => String(p.id) === String(id));
    if (!payment) {
        showNotification('Payment not found', 'warning');
        return;
    }
    
    currentViewingPayment = payment;
    
    // Populate modal
    document.getElementById('modalClientName').textContent = payment.user_name || payment.name || 'Unknown User';
    document.getElementById('modalContact').textContent = payment.contact || 'N/A';
    document.getElementById('modalEmail').textContent = payment.email || 'No email';
    document.getElementById('modalPackage').textContent = payment.package_name || payment.package || 'N/A';
    document.getElementById('modalDate').textContent = payment.date_formatted || formatDateForDisplay(payment.created_at);
    document.getElementById('modalAmount').textContent = payment.amount_formatted || ('₱' + parseFloat(payment.amount).toFixed(2));
    
    // Show notes if available
    if (payment.notes) {
        document.getElementById('notesGroup').style.display = 'block';
        document.getElementById('modalNotes').textContent = payment.notes;
    } else {
        document.getElementById('notesGroup').style.display = 'none';
    }
    
    // Show receipt if available
    const receiptImg = document.getElementById('modalReceipt');
    const receiptSection = document.getElementById('receiptSection');
    
    const receiptUrl = payment.receipt_full_url || payment.receipt_url || payment.receipt;
    if (receiptUrl) {
        const fixedUrl = fixReceiptUrl(receiptUrl);
        receiptImg.src = fixedUrl;
        receiptSection.style.display = 'block';
        
        // Make image clickable
        receiptImg.style.cursor = 'zoom-in';
        receiptImg.title = 'Click to view full image';
        receiptImg.onclick = () => window.open(fixedUrl, '_blank');
    } else {
        receiptSection.style.display = 'none';
    }
    
    // Show modal
    document.getElementById('paymentModal').classList.add('active');
}

// Close modal
function closeModal() {
    document.getElementById('paymentModal').classList.remove('active');
    currentViewingPayment = null;
}

// Print receipt
function printReceipt() {
    if (!currentViewingPayment) {
        showNotification('No payment selected', 'warning');
        return;
    }
    
    // Create printable receipt content
    const receiptContent = `
        <html>
            <head>
                <title>Payment Receipt</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 40px; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .details { margin: 20px 0; }
                    .detail-row { display: flex; justify-content: space-between; margin: 10px 0; }
                    .footer { margin-top: 30px; text-align: center; color: #666; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Martinez Fitness Gym</h1>
                    <p>Payment Receipt</p>
                </div>
                <div class="details">
                    <div class="detail-row">
                        <strong>Client:</strong> ${currentViewingPayment.name || 'N/A'}
                    </div>
                    <div class="detail-row">
                        <strong>Package:</strong> ${currentViewingPayment.package || 'N/A'}
                    </div>
                    <div class="detail-row">
                        <strong>Amount:</strong> ${currentViewingPayment.amount || '₱0'}
                    </div>
                    <div class="detail-row">
                        <strong>Date:</strong> ${formatDateForDisplay(currentViewingPayment.date || currentViewingPayment.createdAt)}
                    </div>
                    <div class="detail-row">
                        <strong>Status:</strong> Verified
                    </div>
                </div>
                <div class="footer">
                    <p>Thank you for your payment!</p>
                </div>
            </body>
        </html>
    `;
    
    const printWindow = window.open('', '_blank');
    if (printWindow) {
        printWindow.document.write(receiptContent);
        printWindow.document.close();
        printWindow.print();
    } else {
        showNotification('Popup blocked! Please allow popups to print receipts.', 'warning');
    }
}

// Export payments
function exportPayments() {
    if (filteredPayments.length === 0) {
        showNotification('No payments to export', 'warning');
        return;
    }
    
    // Create CSV content
    let csv = 'Client Name,Email,Contact,Package,Payment Date,Amount,Status\n';
    
    filteredPayments.forEach(payment => {
        const name = (payment.user_name || payment.name || 'Unknown').replace(/,/g, '');
        const email = (payment.email || 'N/A').replace(/,/g, '');
        const contact = (payment.contact || 'N/A').replace(/,/g, '');
        const packageName = (payment.package_name || payment.package || 'N/A').replace(/,/g, '');
        const date = (payment.date_formatted || formatDateForDisplay(payment.created_at)).replace(/,/g, '');
        const amount = (payment.amount_formatted || ('₱' + parseFloat(payment.amount).toFixed(2))).replace(/,/g, '');
        
        csv += `${name},${email},${contact},${packageName},${date},${amount},Verified\n`;
    });
    
    // Create download link
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `payments_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showNotification('Payments exported successfully!', 'success');
}

// Refresh payments
async function refreshPayments() {
    await loadAllPayments();
    populatePackageFilter();
    applyFilters();
    showNotification('Payments refreshed', 'success');
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

// Initialize page
document.addEventListener('DOMContentLoaded', async function() {
    await loadAllPayments();
    populatePackageFilter();
    initializeChart();
    applyFilters();
    
    // Setup event listeners
    document.getElementById('packageFilter').addEventListener('change', applyFilters);
    document.getElementById('sortBy').addEventListener('change', applyFilters);
    document.getElementById('dateRange').addEventListener('change', applyFilters);
    document.getElementById('searchInput').addEventListener('input', applyFilters);
    document.getElementById('chartPeriod').addEventListener('change', updateChart);
    
    // Close modal on outside click
    document.getElementById('paymentModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Notification button
    const notificationBtn = document.querySelector('.notification-btn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', async function() {
            try {
                const response = await fetch('../../api/bookings/get-all.php?status=pending');
                const data = await response.json();
                const pendingCount = data.success ? data.data.length : 0;
                showNotification(`You have ${pendingCount} pending booking${pendingCount !== 1 ? 's' : ''} to verify`, 'info');
            } catch (e) {
                console.error('Error fetching pending count:', e);
            }
        });
    }
    
    // Refresh payments every 3 seconds
    setInterval(async () => {
        await loadAllPayments();
        populatePackageFilter();
        applyFilters();
        await updatePendingBadge();
    }, 3000);

    // Initial badge update
    await updatePendingBadge();
});
