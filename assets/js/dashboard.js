// All bookings data - loaded from localStorage
let allBookings = [];
let lastBookingsJSON = '';
let lastPackagesJSON = '';
let packagesData = [];
let currentPackageMetric = 'bookings';

// Load all bookings from database
async function loadAllBookings() {
    try {
        const response = await fetch('../../api/bookings/get-all.php');
        const data = await response.json();
        
        if (data.success) {
            const newBookingsJSON = JSON.stringify(data.data);
            const hasChanged = newBookingsJSON !== lastBookingsJSON;
            
            if (hasChanged) {
                allBookings = data.data;
                lastBookingsJSON = newBookingsJSON;
                return true; // Data changed
            }
            return false; // Data unchanged
        } else {
            console.error('Error loading bookings:', data.message);
            allBookings = [];
            lastBookingsJSON = '';
            return true;
        }
    } catch (error) {
        console.error('Network error loading bookings:', error);
        allBookings = [];
        lastBookingsJSON = '';
        return true;
    }
}

// Load packages data from database
async function loadPackagesData() {
    try {
        const response = await fetch('../../api/packages/get-all.php');
        const data = await response.json();
        
        if (data.success) {
            const newPackagesJSON = JSON.stringify(data.data);
            const hasChanged = newPackagesJSON !== lastPackagesJSON;
            
            if (hasChanged) {
                packagesData = data.data.map(pkg => ({
                    id: pkg.id,
                    name: pkg.name,
                    duration: pkg.duration,
                    price: '₱' + parseFloat(pkg.price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                    tag: pkg.tag || 'Standard'
                }));
                lastPackagesJSON = newPackagesJSON;
                return true; // Data changed
            }
            return false; // Data unchanged
        } else {
            console.error('Error loading packages:', data.message);
            packagesData = getDefaultPackages();
            lastPackagesJSON = '';
            return true;
        }
    } catch (error) {
        console.error('Network error loading packages:', error);
        packagesData = getDefaultPackages();
        lastPackagesJSON = '';
        return true;
    }
}

// Initialize Dashboard
document.addEventListener('DOMContentLoaded', async function() {
    await loadPackagesData();
    await loadAllBookings();
    await loadPaymentSettings(); // Load dynamic GCash settings
    populateBookingsTable();
    populatePackages();
    await initializeChart();
    await initializePackageStatsChart();
    updateStats();
    setupEventListeners();
    
    // Set default dates for statistics filter
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const statsStartDate = document.getElementById('statsStartDate');
    const statsEndDate = document.getElementById('statsEndDate');
    if (statsStartDate) statsStartDate.value = firstDay.toISOString().split('T')[0];
    if (statsEndDate) statsEndDate.value = now.toISOString().split('T')[0];
    
    // Refresh bookings every 3 seconds
    setInterval(async () => {
        const changed = await loadAllBookings();
        if (changed) {
            console.log('Bookings data changed, re-rendering...');
            populateBookingsTable();
            updateStats();
            await initializeChart();
            await initializePackageStatsChart();
        }
    }, 3000);
    
    // Refresh packages every 10 seconds
    setInterval(async () => {
        const changed = await loadPackagesData();
        if (changed) {
            console.log('Packages data changed, re-rendering...');
            populatePackages();
        }
    }, 10000);
    
    // Refresh payment settings every 30 seconds
    setInterval(async () => {
        await loadPaymentSettings();
    }, 30000);
    
    // Mobile menu toggle functionality
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (mobileMenuToggle && sidebar) {
        mobileMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMobileMenu();
        });
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('active') && !sidebar.contains(e.target) && e.target !== mobileMenuToggle && !mobileMenuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                const icon = mobileMenuToggle.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });
    }
});

// Logout functionality
async function handleLogout() {
    if (!confirm('Are you sure you want to logout?')) {
        return;
    }
    
    try {
        const response = await fetch('../../api/auth/logout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('userRole');
        localStorage.removeItem('userData');
        
        window.location.href = '../../index.php';
    } catch (error) {
        console.error('Logout error:', error);
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('userRole');
        localStorage.removeItem('userData');
        window.location.href = '../../index.php';
    }
}

// Load dynamic payment settings from database
async function loadPaymentSettings() {
    try {
        const response = await fetch('../../api/settings/get.php');
        const result = await response.json();
        
        if (result.success) {
            const settings = {};
            result.data.forEach(item => {
                settings[item.setting_key] = item.setting_value;
            });
            
            // Update GCash card in dashboard
            const qrContainer = document.querySelector('.qr-section .qr-container');
            const gcashNumber = document.querySelector('.qr-section .qr-info p:nth-child(1)');
            const gcashName = document.querySelector('.qr-section .qr-info p:nth-child(2)');
            
            if (qrContainer) {
                        let qrPath = settings.gcash_qr_path;
                        // Increase size by allowing it to fill more of the container and reducing padding
                        const imgStyle = "width: 100%; height: 100%; object-fit: contain; border-radius: 4px; background: white; padding: 4px;";
                        if (qrPath && qrPath !== '') {
                            if (!qrPath.startsWith('http')) {
                                qrPath = '../../' + qrPath;
                            }
                            qrContainer.innerHTML = `<img src="${qrPath}" style="${imgStyle}">`;
                        } else {
                            const fallbackQR = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=GCash:' + (settings.gcash_number || '09171234567');
                            qrContainer.innerHTML = `<img src="${fallbackQR}" style="${imgStyle}">`;
                        }
                    }
            
            if (gcashNumber && settings.gcash_number) {
                gcashNumber.innerHTML = `<strong>GCash Number:</strong> ${settings.gcash_number}`;
            }
            
            if (gcashName && settings.gcash_name) {
                gcashName.innerHTML = `<strong>Account Name:</strong> ${settings.gcash_name}`;
            }
        }
    } catch (error) {
        console.error('Error loading payment settings:', error);
    }
}

// Mobile menu toggle function
function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.getElementById('mobileMenuToggle');
    if (sidebar && toggleBtn) {
        sidebar.classList.toggle('active');
        const icon = toggleBtn.querySelector('i');
        if (sidebar.classList.contains('active')) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-times');
        } else {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    }
}

// Format date for display
function formatDateForDisplay(dateString) {
    if (!dateString) return 'N/A';
    
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            // Try parsing as string date
            return dateString;
        }
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    } catch (e) {
        return dateString;
    }
}

// Populate bookings table
function populateBookingsTable() {
    const tbody = document.getElementById('bookingsTable');
    tbody.innerHTML = '';
    
    if (allBookings.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">
                    No bookings found. Bookings will appear here when users submit them.
                </td>
            </tr>
        `;
        return;
    }
    
    allBookings.forEach(booking => {
        const row = document.createElement('tr');
        const displayDate = booking.date_formatted || formatDateForDisplay(booking.booking_date || booking.created_at);
        const amount = booking.amount_formatted || ('₱' + parseFloat(booking.amount).toFixed(2));
        
        row.innerHTML = `
            <td data-label="Client">
                <div style="font-weight: 700; color: var(--primary);">${booking.user_name || booking.name || 'Unknown User'}</div>
                <div style="font-size: 0.9rem; color: var(--dark-text-secondary);">${booking.email || 'No email'}</div>
            </td>
            <td data-label="Package">${booking.package_name || booking.package || 'N/A'}</td>
            <td data-label="Date">${displayDate}</td>
            <td data-label="Amount" style="font-weight: 800;">${amount}</td>
            <td data-label="Status"><span class="status-badge status-${booking.status || 'pending'}">${(booking.status || 'pending').charAt(0).toUpperCase() + (booking.status || 'pending').slice(1)}</span></td>
            <td data-label="Actions">
                <div class="table-actions">
                    <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem; display: flex; align-items: center; gap: 6px;" onclick="viewBooking('${booking.id}')">
                        <i class="fas fa-tasks"></i>
                        <span>Manage</span>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    // Update badge count
    const pendingCount = allBookings.filter(b => b.status === 'pending').length;
    const badge = document.querySelector('.nav-links .badge');
    if (badge) {
        badge.textContent = pendingCount || '';
    }
}

// Populate packages
function populatePackages() {
    const packagesList = document.getElementById('packagesList');
    const packageFilter = document.getElementById('packageFilter');
    const currentFilterValue = packageFilter ? packageFilter.value : 'all';
    
    if (packagesList) packagesList.innerHTML = '';
    if (packageFilter) {
        packageFilter.innerHTML = '<option value="all">All Packages</option>';
    }
    
    packagesData.forEach(pkg => {
        if (packagesList) {
            const packageCard = document.createElement('div');
            packageCard.className = 'package-card';
            packageCard.innerHTML = `
                <div class="package-info">
                    <h4>${pkg.name}</h4>
                    <p>${pkg.duration} • ${pkg.description ? pkg.description.split('\n')[0] : 'Full gym access with all facilities'}</p>
                    <span class="package-tag">${pkg.tag}</span>
                </div>
                <div class="package-price">${pkg.price}</div>
            `;
            packagesList.appendChild(packageCard);
        }
        
        if (packageFilter) {
            const option = document.createElement('option');
            option.value = pkg.name;
            option.textContent = pkg.name;
            packageFilter.appendChild(option);
        }
    });

    // Restore filter value if it still exists
    if (packageFilter && Array.from(packageFilter.options).some(opt => opt.value === currentFilterValue)) {
        packageFilter.value = currentFilterValue;
    }
}

// Initialize revenue chart
async function initializeChart(months = 6) {
    const canvas = document.getElementById('revenueChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    
    // Group revenue by month for the specified number of months
    const monthlyRevenue = {};
    const now = new Date();
    for (let i = months - 1; i >= 0; i--) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const key = d.toLocaleDateString('en-US', { month: 'short' });
        monthlyRevenue[key] = 0;
    }

    allBookings
        .filter(b => b.status === 'verified')
        .forEach(b => {
            const date = new Date(b.booking_date || b.created_at);
            const key = date.toLocaleDateString('en-US', { month: 'short' });
            if (monthlyRevenue.hasOwnProperty(key)) {
                monthlyRevenue[key] += parseFloat(b.amount) || 0;
            }
        });

    const labels = Object.keys(monthlyRevenue);
    const data = Object.values(monthlyRevenue);
    
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(255, 255, 255, 0.3)');
    gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
    
    if (window.revenueChart instanceof Chart) {
        window.revenueChart.destroy();
    }
    
    window.revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue',
                data: data,
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
}

// Initialize package statistics chart
async function initializePackageStatsChart(startDate = null, endDate = null) {
    const canvas = document.getElementById('packageStatsChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    
    // Default to current month if no dates provided
    if (!startDate || !endDate) {
        const statsStartDateEl = document.getElementById('statsStartDate');
        const statsEndDateEl = document.getElementById('statsEndDate');
        startDate = statsStartDateEl ? statsStartDateEl.value : null;
        endDate = statsEndDateEl ? statsEndDateEl.value : null;
        
        if (!startDate || !endDate) {
            const now = new Date();
            const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
            startDate = firstDay.toISOString().split('T')[0];
            endDate = now.toISOString().split('T')[0];
        }
    }
    
    const start = new Date(startDate);
    const end = new Date(endDate);
    end.setHours(23, 59, 59, 999);
    
    const packageFilter = document.getElementById('packageFilter');
    const selectedPkgName = packageFilter ? packageFilter.value : 'all';
    
    // Count packages within the date range
    const packageStats = {};
    
    // Initialize stats for all packages if we're showing all
    if (selectedPkgName === 'all') {
        packagesData.forEach(pkg => {
            packageStats[pkg.name] = { bookings: 0, revenue: 0 };
        });
    } else {
        packageStats[selectedPkgName] = { bookings: 0, revenue: 0 };
    }
    
    allBookings
        .filter(b => b.status === 'verified')
        .forEach(b => {
            const date = new Date(b.booking_date || b.created_at);
            if (date >= start && date <= end) {
                const pkgName = b.package_name || b.package || 'Other';
                
                if (selectedPkgName === 'all' || pkgName === selectedPkgName) {
                    if (!packageStats[pkgName]) {
                        packageStats[pkgName] = { bookings: 0, revenue: 0 };
                    }
                    packageStats[pkgName].bookings += 1;
                    packageStats[pkgName].revenue += parseFloat(b.amount) || 0;
                }
            }
        });

    const labels = Object.keys(packageStats);
    const bookingsData = labels.map(label => packageStats[label].bookings);
    const revenueData = labels.map(label => packageStats[label].revenue);
    
    // Update summary cards
    updatePackageSummaryCards(packageStats, selectedPkgName);
    
    if (window.packageStatsChart instanceof Chart) {
        window.packageStatsChart.destroy();
    }
    
    // If no data and we're not showing all packages (which are initialized to 0), show a message
    if (labels.length === 0) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#888';
        ctx.textAlign = 'center';
        ctx.font = '14px Inter';
        ctx.fillText('No verified bookings found for this period', canvas.width / 2, canvas.height / 2);
        return;
    }

    const currentData = currentPackageMetric === 'bookings' ? bookingsData : revenueData;
    const isRevenue = currentPackageMetric === 'revenue';
    
    // Generate colors based on the data
    const backgroundColors = labels.map((_, i) => {
        const hue = (i * 137.5) % 360; // Use golden angle for distributed colors
        return `hsla(${hue}, 70%, 60%, 0.2)`;
    });
    const borderColors = labels.map((_, i) => {
        const hue = (i * 137.5) % 360;
        return `hsla(${hue}, 70%, 60%, 1)`;
    });

    window.packageStatsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: isRevenue ? 'Revenue' : 'Bookings',
                data: currentData,
                backgroundColor: isRevenue ? 'rgba(34, 197, 94, 0.2)' : 'rgba(255, 255, 255, 0.15)',
                borderColor: isRevenue ? '#22c55e' : '#ffffff',
                borderWidth: 2,
                borderRadius: 12,
                hoverBackgroundColor: isRevenue ? 'rgba(34, 197, 94, 0.4)' : 'rgba(255, 255, 255, 0.3)',
                barThickness: selectedPkgName === 'all' ? 'flex' : 60
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
                    borderColor: isRevenue ? '#22c55e' : '#ffffff',
                    borderWidth: 2,
                    cornerRadius: 12,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            let val = context.parsed.y;
                            if (isRevenue) {
                                return `Revenue: ₱${val.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
                            }
                            return `Bookings: ${val}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#888',
                        font: {
                            weight: '600',
                            family: 'Inter'
                        },
                        callback: function(value) {
                            if (isRevenue) {
                                return '₱' + (value >= 1000 ? (value / 1000) + 'k' : value);
                            }
                            return value;
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#888',
                        font: {
                            weight: '600',
                            family: 'Inter'
                        }
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            }
        }
    });
}

// Update package summary cards
function updatePackageSummaryCards(packageStats, selectedPkgName) {
    const selectedPkgNameEl = document.getElementById('selectedPackageName');
    const selectedPkgBookingsEl = document.getElementById('selectedPkgBookings');
    const selectedPkgRevenueEl = document.getElementById('selectedPkgRevenue');
    const topPackageNameEl = document.getElementById('topPackageName');
    const topPackageStatEl = document.getElementById('topPackageStat');
    
    if (!selectedPkgNameEl) return;
    
    let totalBookings = 0;
    let totalRevenue = 0;
    let topPkg = { name: 'None', bookings: -1 };
    
    const statsArray = Object.entries(packageStats);
    
    if (selectedPkgName === 'all') {
        selectedPkgNameEl.textContent = 'All Packages';
        statsArray.forEach(([name, data]) => {
            totalBookings += data.bookings;
            totalRevenue += data.revenue;
            if (data.bookings > topPkg.bookings) {
                topPkg = { name: name, bookings: data.bookings };
            }
        });
    } else {
        selectedPkgNameEl.textContent = selectedPkgName;
        const data = packageStats[selectedPkgName] || { bookings: 0, revenue: 0 };
        totalBookings = data.bookings;
        totalRevenue = data.revenue;
        
        // For top package, we still want to look at all packages even if one is filtered
        // So we need to re-calculate top package from allBookings within date range
        const allPackageStats = {};
        const startDate = document.getElementById('statsStartDate').value;
        const endDate = document.getElementById('statsEndDate').value;
        const start = new Date(startDate);
        const end = new Date(endDate);
        end.setHours(23, 59, 59, 999);
        
        allBookings
            .filter(b => b.status === 'verified')
            .forEach(b => {
                const date = new Date(b.booking_date || b.created_at);
                if (date >= start && date <= end) {
                    const pkgName = b.package_name || b.package || 'Other';
                    allPackageStats[pkgName] = (allPackageStats[pkgName] || 0) + 1;
                }
            });
            
        Object.entries(allPackageStats).forEach(([name, count]) => {
            if (count > topPkg.bookings) {
                topPkg = { name: name, bookings: count };
            }
        });
    }
    
    selectedPkgBookingsEl.textContent = totalBookings;
    selectedPkgRevenueEl.textContent = `₱${totalRevenue.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
    
    if (topPkg.bookings >= 0) {
        topPackageNameEl.textContent = topPkg.name;
        topPackageStatEl.textContent = `${topPkg.bookings} booking${topPkg.bookings !== 1 ? 's' : ''}`;
    } else {
        topPackageNameEl.textContent = 'No data';
        topPackageStatEl.textContent = '0 bookings';
    }
}

// Switch between bookings and revenue metric
function switchPackageMetric(metric) {
    currentPackageMetric = metric;
    
    // Update UI buttons
    const buttons = document.querySelectorAll('.metric-toggle .toggle-btn');
    buttons.forEach(btn => {
        if (btn.getAttribute('data-metric') === metric) {
            btn.classList.add('active');
            btn.style.background = 'var(--primary)';
            btn.style.color = 'white';
        } else {
            btn.classList.remove('active');
            btn.style.background = 'transparent';
            btn.style.color = 'var(--dark-text-secondary)';
        }
    });
    
    initializePackageStatsChart();
}

// Filter package stats by date range or package
function filterPackageStats() {
    const startDate = document.getElementById('statsStartDate').value;
    const endDate = document.getElementById('statsEndDate').value;
    
    if (!startDate || !endDate) {
        showNotification('Please select both start and end dates', 'warning');
        return;
    }
    
    if (new Date(startDate) > new Date(endDate)) {
        showNotification('Start date cannot be after end date', 'warning');
        return;
    }
    
    initializePackageStatsChart(startDate, endDate);
}

// Current viewing booking
let currentViewingBooking = null;

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

// View booking details
function viewBooking(id) {
    const booking = allBookings.find(b => String(b.id) === String(id));
    if (!booking) {
        showNotification('Booking not found', 'warning');
        return;
    }
    
    currentViewingBooking = booking;
    
    // Populate modal
    document.getElementById('modalClientName').textContent = booking.user_name || booking.name || 'Unknown User';
    document.getElementById('modalContact').textContent = booking.contact || 'N/A';
    document.getElementById('modalEmail').textContent = booking.email || 'No email';
    document.getElementById('modalPackage').textContent = booking.package_name || booking.package || 'N/A';
    document.getElementById('modalDate').textContent = booking.date_formatted || formatDateForDisplay(booking.booking_date || booking.created_at);
    document.getElementById('modalAmount').textContent = booking.amount_formatted || ('₱' + parseFloat(booking.amount).toFixed(2));
    
    // Show receipt if available
    const receiptImg = document.getElementById('modalReceipt');
    const receiptSection = document.querySelector('.receipt-section');
    
    const receiptUrl = booking.receipt_full_url || booking.receipt_url || booking.receipt;
    if (receiptUrl) {
        const fixedUrl = fixReceiptUrl(receiptUrl);
        receiptImg.src = fixedUrl;
        receiptImg.style.display = 'block';
        
        // Make image clickable
        receiptImg.style.cursor = 'zoom-in';
        receiptImg.title = 'Click to view full image';
        receiptImg.onclick = () => window.open(fixedUrl, '_blank');
        
        if (receiptSection) {
            receiptSection.style.display = 'block';
        }
    } else {
        receiptImg.style.display = 'none';
        if (receiptSection) {
            receiptSection.style.display = 'none';
        }
    }
    
    // Show modal
    document.getElementById('bookingModal').classList.add('active');
}

// Verify booking
async function verifyBooking(id) {
    const booking = allBookings.find(b => String(b.id) === String(id));
    if (!booking) {
        showNotification('Booking not found', 'warning');
        return;
    }
    
    if (booking.status === 'pending') {
        if (confirm(`Verify payment for ${booking.user_name || booking.name || 'this user'}?`)) {
            try {
                const response = await fetch('../../api/bookings/update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, status: 'verified' })
                });
                const result = await response.json();
                
                await loadAllBookings();
                populateBookingsTable();
                updateStats();
                showNotification(`Payment for ${booking.user_name || booking.name || 'user'} has been verified!`, 'success');
                if (currentViewingBooking && currentViewingBooking.id === id) closeModal();
            } catch (error) {
                console.error('Error verifying booking:', error);
                showNotification('Network error verifying booking', 'warning');
            }
        }
    } else {
        showNotification('This booking has already been processed', 'info');
    }
}

// Verify payment from modal
async function verifyPayment() {
    if (!currentViewingBooking) {
        showNotification('No booking selected', 'warning');
        return;
    }
    
    await verifyBooking(currentViewingBooking.id);
}

// Reject payment
async function rejectPayment() {
    if (!currentViewingBooking) {
        showNotification('No booking selected', 'warning');
        return;
    }
    
    const booking = currentViewingBooking;
    if (confirm(`Reject payment for ${booking.user_name || booking.name || 'this user'}? The client will be notified.`)) {
        try {
            const response = await fetch('../../api/bookings/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: booking.id, status: 'rejected' })
            });
            const result = await response.json();
            
            if (result.success) {
                await loadAllBookings();
                populateBookingsTable();
                updateStats();
                showNotification('Payment rejected. Notification sent to client.', 'warning');
                closeModal();
            } else {
                showNotification('Error: ' + result.message, 'warning');
            }
        } catch (error) {
            console.error('Error rejecting booking:', error);
            showNotification('Network error rejecting booking', 'warning');
        }
    }
}

let currentControlTab = 'overview';
let dashboardStats = {
    overview: [],
    bookings: [],
    payments: [],
    members: [],
    trainers: [],
    inventory: []
};

// Switch Control Tab
function switchControlTab(tab) {
    currentControlTab = tab;
    
    // Update active tab UI
    document.querySelectorAll('.control-tab').forEach(t => {
        t.classList.remove('active');
        if (t.dataset.tab === tab) t.classList.add('active');
    });
    
    // Re-render stats grid
    renderStatsGrid();
}

// Render Stats Grid based on active tab
function renderStatsGrid() {
    const grid = document.getElementById('dashboardStatsGrid');
    if (!grid) return;
    
    grid.innerHTML = '';
    const stats = dashboardStats[currentControlTab] || [];
    
    stats.forEach(stat => {
        const card = document.createElement('div');
        card.className = `stat-card ${stat.trendDir === 'down' ? 'warning' : stat.trendDir === 'up' ? 'success' : ''}`;
        
        let trendHtml = '';
        if (stat.trend) {
            trendHtml = `
                <div class="trend ${stat.trendDir || ''}">
                    <i class="fas fa-arrow-${stat.trendDir === 'down' ? 'down' : 'up'}"></i>
                    <span>${stat.trend}</span>
                </div>
            `;
        }

        card.innerHTML = `
            <div class="stat-header">
                <div class="stat-icon" style="${stat.iconStyle || ''}">
                    <i class="fas fa-${stat.icon}"></i>
                </div>
                ${trendHtml}
            </div>
            <div class="stat-value" id="${stat.id}">${stat.value}</div>
            <div class="stat-label">${stat.label}</div>
        `;
        grid.appendChild(card);
    });
}

// Update stats
async function updateStats() {
    // 1. Fetch all required data
    try {
        const [usersRes, bookingsRes, trainersRes, equipRes, exercisesRes] = await Promise.all([
            fetch('../../api/users/get-all.php?role=user'),
            fetch('../../api/bookings/get-all.php'),
            fetch('../../api/trainers/get-all.php'),
            fetch('../../api/equipment/get-all.php'),
            fetch('../../api/exercises/get-all.php')
        ]);

        const usersData = await usersRes.json();
        const bookingsData = await bookingsRes.json();
        const trainersData = await trainersRes.json();
        const equipData = await equipRes.json();
        const exercisesData = await exercisesRes.json();

        if (!bookingsData.success) return;
        const allBookingsData = bookingsData.data;
        const now = new Date();
        const currentMonth = now.getMonth();
        const currentYear = now.getFullYear();

        // --- CALCULATION HELPERS ---
        const isVerified = (b) => b.status === 'verified';
        const isPending = (b) => b.status === 'pending';
        const isToday = (dateStr) => {
            const d = new Date(dateStr);
            return d.getDate() === now.getDate() && d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
        };
        const isThisMonth = (dateStr) => {
            const d = new Date(dateStr);
            return d.getMonth() === currentMonth && d.getFullYear() === currentYear;
        };

        // --- CATEGORY: OVERVIEW ---
        let totalRevenue = 0;
        const activeMemberIdentifiers = new Set();
        
        allBookingsData.forEach(booking => {
            if (isVerified(booking)) {
                totalRevenue += parseFloat(booking.amount) || 0;
                
                let isActive = false;
                if (booking.expires_at) {
                    const exp = new Date(booking.expires_at);
                    if (!isNaN(exp.getTime())) isActive = now <= exp;
                }
                if (isActive) activeMemberIdentifiers.add(booking.user_id || booking.email || booking.name);
            }
        });

        dashboardStats.overview = [
            { id: 'totalRevenueStat', label: 'Total Revenue', value: `₱${totalRevenue.toLocaleString()}`, icon: 'money-bill-wave', trend: '15%', trendDir: 'up' },
            { id: 'activeMembersStat', label: 'Active Members', value: activeMemberIdentifiers.size, icon: 'users', trend: '5%', trendDir: 'up' },
            { id: 'totalTrainersStat', label: 'Active Trainers', value: trainersData.success ? trainersData.data.length : 0, icon: 'user-tie', trend: '2%', trendDir: 'up' },
            { id: 'gymCapacityStat', label: 'Gym Capacity', value: `${Math.min(100, Math.round(activeMemberIdentifiers.size / 2))}%`, icon: 'bolt', trend: 'Steady', trendDir: '' }
        ];

        // --- CATEGORY: BOOKINGS ---
        const totalBookings = allBookingsData.length;
        const pendingCount = allBookingsData.filter(isPending).length;
        const verifiedToday = allBookingsData.filter(b => isVerified(b) && isToday(b.verified_at || b.updated_at)).length;
        const rejectedCount = allBookingsData.filter(b => b.status === 'rejected').length;
        const rejectionRate = totalBookings > 0 ? Math.round((rejectedCount / totalBookings) * 100) : 0;

        dashboardStats.bookings = [
            { id: 'totalBookingsStat', label: 'Total Bookings', value: totalBookings, icon: 'calendar-check' },
            { id: 'pendingBookingsStat', label: 'Pending Verification', value: pendingCount, icon: 'clock', trendDir: pendingCount > 5 ? 'down' : 'up' },
            { id: 'verifiedTodayStat', label: 'Verified Today', value: verifiedToday, icon: 'check-circle', trend: 'Daily', trendDir: 'up' },
            { id: 'rejectionRateStat', label: 'Rejection Rate', value: `${rejectionRate}%`, icon: 'times-circle', trendDir: rejectionRate > 10 ? 'down' : 'up' }
        ];

        // --- CATEGORY: PAYMENTS ---
        let monthlyRev = 0;
        let todayRev = 0;
        let pendingRev = 0;
        allBookingsData.forEach(b => {
            const amt = parseFloat(b.amount) || 0;
            if (isVerified(b)) {
                if (isThisMonth(b.verified_at || b.updated_at)) monthlyRev += amt;
                if (isToday(b.verified_at || b.updated_at)) todayRev += amt;
            } else if (isPending(b)) {
                pendingRev += amt;
            }
        });
        const verifiedCount = allBookingsData.filter(isVerified).length;
        const avgPayment = verifiedCount > 0 ? totalRevenue / verifiedCount : 0;

        dashboardStats.payments = [
            { id: 'monthlyRevenueStat', label: 'Monthly Revenue', value: `₱${monthlyRev.toLocaleString()}`, icon: 'calendar-day', trend: 'Target: 80%', trendDir: 'up' },
            { id: 'todayRevenueStat', label: 'Today\'s Revenue', value: `₱${todayRev.toLocaleString()}`, icon: 'coins' },
            { id: 'avgPaymentStat', label: 'Average Payment', value: `₱${Math.round(avgPayment).toLocaleString()}`, icon: 'chart-line' },
            { id: 'outstandingStat', label: 'Outstanding (Pending)', value: `₱${pendingRev.toLocaleString()}`, icon: 'hand-holding-dollar', trendDir: 'down' }
        ];

        // --- CATEGORY: MEMBERS ---
        const registeredCount = usersData.success ? usersData.data.length : 0;
        const newThisMonth = usersData.success ? usersData.data.filter(u => isThisMonth(u.created_at)).length : 0;
        const inactiveCount = Math.max(0, registeredCount - activeMemberIdentifiers.size);

        dashboardStats.members = [
            { id: 'registeredMembersStat', label: 'Registered Members', value: registeredCount, icon: 'id-card' },
            { id: 'newMembersStat', label: 'New This Month', value: newThisMonth, icon: 'user-plus', trend: '+12', trendDir: 'up' },
            { id: 'activeMembersStat', label: 'Active Members', value: activeMemberIdentifiers.size, icon: 'user-check' },
            { id: 'inactiveMembersStat', label: 'Inactive Members', value: inactiveCount, icon: 'user-slash', trendDir: 'down' }
        ];

        // --- CATEGORY: TRAINERS ---
        const trainersList = trainersData.success ? trainersData.data : [];
        const activeTrainers = trainersList.filter(t => t.is_active).length;
        const avgLoad = activeTrainers > 0 ? (activeMemberIdentifiers.size / activeTrainers).toFixed(1) : 0;
        
        // Find most popular trainer
        const trainerCounts = {};
        allBookingsData.filter(isVerified).forEach(b => {
            if (b.trainer_id) trainerCounts[b.trainer_id] = (trainerCounts[b.trainer_id] || 0) + 1;
        });
        let topTrainerId = Object.keys(trainerCounts).reduce((a, b) => trainerCounts[a] > trainerCounts[b] ? a : b, null);
        let topTrainerName = 'None';
        if (topTrainerId) {
            const tt = trainersList.find(t => String(t.id) === String(topTrainerId));
            if (tt) topTrainerName = tt.name.split(' ')[0];
        }

        dashboardStats.trainers = [
            { id: 'totalTrainersStat', label: 'Total Trainers', value: trainersList.length, icon: 'user-tie' },
            { id: 'activeTrainersStat', label: 'Active Status', value: activeTrainers, icon: 'check-double' },
            { id: 'avgLoadStat', label: 'Avg. Client Load', value: avgLoad, icon: 'users-gear' },
            { id: 'topTrainerStat', label: 'Top Performer', value: topTrainerName, icon: 'award' }
        ];

        // --- CATEGORY: INVENTORY ---
        const equipmentList = equipData.success ? equipData.data : [];
        const exercisesList = exercisesData.success ? exercisesData.data : [];
        const maintenanceCount = equipmentList.filter(e => e.status === 'maintenance').length;

        dashboardStats.inventory = [
            { id: 'totalEquipmentStat', label: 'Gym Equipment', value: equipmentList.length, icon: 'dumbbell' },
            { id: 'maintenanceStat', label: 'Under Maintenance', value: maintenanceCount, icon: 'wrench', trendDir: maintenanceCount > 0 ? 'down' : 'up' },
            { id: 'totalExercisesStat', label: 'Exercise Library', value: exercisesList.length, icon: 'running' },
            { id: 'inventoryValueStat', label: 'Inventory Status', value: 'Healthy', icon: 'check-circle' }
        ];

        // 2. Initial Render or update if same tab
        renderStatsGrid();

        // 3. Update Other UI elements (Badges, etc.)
        const notificationBadge = document.querySelector('.notification-badge');
        if (notificationBadge) notificationBadge.textContent = pendingCount || '0';
        
        const bookingsBadge = document.getElementById('bookingsBadge');
        if (bookingsBadge) bookingsBadge.textContent = pendingCount || '';

        const pendingText = document.getElementById('pendingVerificationsText');
        if (pendingText) pendingText.textContent = `${pendingCount} pending verification${pendingCount !== 1 ? 's' : ''}`;

    } catch (e) {
        console.error('Error updating dashboard stats:', e);
    }
}

// Modal functions
function openModal() {
    // Open modal with first pending booking if available
    const firstPending = allBookings.find(b => b.status === 'pending');
    if (firstPending) {
        viewBooking(firstPending.id);
    } else if (allBookings.length > 0) {
        viewBooking(allBookings[0].id);
    } else {
        showNotification('No bookings available', 'info');
    }
}

function closeModal() {
    document.getElementById('bookingModal').classList.remove('active');
}

// Show notification
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;
    
    // Add styles
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
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Setup event listeners
function setupEventListeners() {
    // Logout button
    document.querySelector('.action-btn[title="Logout"]').addEventListener('click', async function() {
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
    });
    
    // Notification button
    document.querySelector('.notification-btn').addEventListener('click', function() {
        showNotification('You have 3 new notifications: 2 pending payments, 1 new booking', 'info');
    });
    
    // Search functionality
    const searchInput = document.querySelector('.search-box input');
    searchInput.addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#bookingsTable tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });
    
    // Revenue Period change
    const revenuePeriod = document.getElementById('revenuePeriod');
    if (revenuePeriod) {
        revenuePeriod.addEventListener('change', function() {
            initializeChart(parseInt(this.value));
        });
    }
    
    // Close modal on outside click
    document.getElementById('bookingModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Enter key in search
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            showNotification(`Searching for "${this.value}"...`, 'info');
        }
    });
}

