// All bookings data - loaded from localStorage
let allBookings = [];
let lastBookingsJSON = '';
let lastPackagesJSON = '';
let packagesData = [];

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
    initializeChart();
    updateStats();
    setupEventListeners();
    
    // Refresh bookings every 3 seconds
    setInterval(async () => {
        const changed = await loadAllBookings();
        if (changed) {
            console.log('Bookings data changed, re-rendering...');
            populateBookingsTable();
            updateStats();
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
                    <button class="icon-btn" onclick="viewBooking('${booking.id}')" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="icon-btn ${booking.status === 'pending' ? 'primary' : ''}" 
                            onclick="verifyBooking('${booking.id}')" 
                            title="Verify Payment"
                            ${booking.status !== 'pending' ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>
                        <i class="fas fa-check"></i>
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
    packagesList.innerHTML = '';
    
    packagesData.forEach(pkg => {
        const packageCard = document.createElement('div');
        packageCard.className = 'package-card';
        packageCard.innerHTML = `
            <div class="package-info">
                <h4>${pkg.name}</h4>
                <p>${pkg.duration} • Full gym access with all facilities</p>
                <span class="package-tag">${pkg.tag}</span>
            </div>
            <div class="package-price">${pkg.price}</div>
        `;
        packagesList.appendChild(packageCard);
    });
}

// Initialize revenue chart
function initializeChart() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(255, 255, 255, 0.3)');
    gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
    
    const months = ['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'];
    const revenue = [8500, 9200, 10100, 11300, 12450, 13500];
    
    window.revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Revenue',
                data: revenue,
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
                
                if (result.success) {
                    await loadAllBookings();
                    populateBookingsTable();
                    updateStats();
                    showNotification(`Payment for ${booking.user_name || booking.name || 'user'} has been verified!`, 'success');
                    if (currentViewingBooking && currentViewingBooking.id === id) closeModal();
                } else {
                    showNotification('Error: ' + result.message, 'warning');
                }
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

// Update stats
async function updateStats() {
    const totalBookings = allBookings.length;
    const pendingBookings = allBookings.filter(b => b.status === 'pending').length;
    const verifiedBookings = allBookings.filter(b => b.status === 'verified').length;
    
    // Calculate monthly revenue (verified bookings only)
    let monthlyRevenue = 0;
    const currentMonth = new Date().getMonth();
    const currentYear = new Date().getFullYear();
    
    allBookings
        .filter(b => b.status === 'verified')
        .forEach(b => {
            const bookingDate = new Date(b.booking_date || b.created_at);
            if (bookingDate.getMonth() === currentMonth && bookingDate.getFullYear() === currentYear) {
                const amount = parseFloat(b.amount) || 0;
                monthlyRevenue += amount;
            }
        });
    
    // Update active members count from API
    let activeMembersCount = 0;
    try {
        const [usersRes, bookingsRes] = await Promise.all([
            fetch('../../api/users/get-all.php?role=user'),
            fetch('../../api/bookings/get-all.php')
        ]);

        const usersData = await usersRes.json();
        const bookingsData = await bookingsRes.json();

        if (usersData.success && bookingsData.success) {
            const allUsers = usersData.data;
            const allBookingsData = bookingsData.data;
            const now = new Date();

            // Function to parse duration to days (matching members.js)
            const parseDurationToDays = (durationStr) => {
                if (!durationStr) return 0;
                const parts = durationStr.toLowerCase().split(' ');
                const value = parseInt(parts[0]);
                const unit = parts[1];
                if (isNaN(value)) return 0;
                if (unit.includes('day')) return value;
                if (unit.includes('week')) return value * 7;
                if (unit.includes('month')) return value * 30;
                if (unit.includes('year')) return value * 365;
                return value;
            };

            // Count users who have at least one verified booking that hasn't expired
            activeMembersCount = allUsers.filter(user => {
                const userVerifiedBookings = allBookingsData.filter(b => 
                    String(b.user_id) === String(user.id) && b.status === 'verified'
                );
                
                return userVerifiedBookings.some(booking => {
                    const bookingDate = new Date(booking.booking_date || booking.created_at);
                    const days = parseDurationToDays(booking.duration);
                    if (days === 0) return false;
                    const expiryDate = new Date(bookingDate);
                    expiryDate.setDate(expiryDate.getDate() + days);
                    return now <= expiryDate;
                });
            }).length;
        }
    } catch (e) {
        console.error('Error updating active members stat:', e);
    }
    
    // Update stat cards if they exist
    const statCards = document.querySelectorAll('.stat-value');
    if (statCards.length >= 4) {
        statCards[0].textContent = totalBookings; // Total Bookings This Month
        statCards[1].textContent = pendingBookings; // Pending Verifications
        statCards[2].textContent = `₱${monthlyRevenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`; // Monthly Revenue
        statCards[3].textContent = activeMembersCount; // Active Members
    }
    
    // Update notification badge
    const notificationBadge = document.querySelector('.notification-badge');
    if (notificationBadge) {
        notificationBadge.textContent = pendingBookings || '0';
    }

    // Update Quick Actions pending text
    const pendingText = document.getElementById('pendingVerificationsText');
    if (pendingText) {
        pendingText.textContent = `${pendingBookings} pending verification${pendingBookings !== 1 ? 's' : ''}`;
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

