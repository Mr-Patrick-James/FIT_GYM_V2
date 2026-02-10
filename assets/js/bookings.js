// All bookings data - loaded from database
let allBookings = [];
let filteredBookings = [];
let currentViewingBooking = null;

// Current filter values
let currentFilters = {
    bookingType: 'all',
    status: 'all',
    search: '',
    sort: 'date-desc'
};

// Walk-in booking data
let packages = [];

// Helper to fix receipt URLs for display in admin views
function fixReceiptUrl(url) {
    if (!url) return '';
    if (url.startsWith('http') || url.startsWith('blob:')) return url;
    
    // Remove leading slash and 'Fit/' if present to standardize
    let cleanPath = url.replace(/^\/?Fit\//, '').replace(/^\//, '');
    
    // From views/admin/ we need to go up two levels to root
    return '../../' + cleanPath;
}

// Load all bookings from database
async function loadAllBookings() {
    try {
        const params = new URLSearchParams({
            status: currentFilters.status,
            search: currentFilters.search,
            sort: currentFilters.sort
        });
        
        const response = await fetch(`../../api/bookings/get-all.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            allBookings = data.data.map(booking => ({
                ...booking,
                id: booking.id,
                name: booking.name,
                email: booking.email,
                contact: booking.contact || 'N/A',
                package: booking.package_name,
                date: booking.date_formatted,
                amount: booking.amount_formatted,
                status: booking.status,
                payment_method: booking.payment_method || 'GCash',
                duration: booking.duration || 'N/A',
                verified_at: booking.verified_at,
                createdAt: booking.created_at,
                notes: booking.notes,
                expires_at: booking.expires_at,
                is_walkin: booking.is_walkin || false
            }));
        } else {
            console.error('Error loading bookings:', data.message);
            allBookings = [];
        }
    } catch (error) {
        console.error('Network error loading bookings:', error);
        allBookings = [];
    }
    
    return allBookings;
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
            day: 'numeric' 
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

// Apply filters
async function applyFilters() {
    const bookingTypeFilter = document.getElementById('bookingTypeFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    const sortBy = document.getElementById('sortBy').value;
    const dateRange = document.getElementById('dateRange').value;
    const searchTerm = document.getElementById('searchInput').value;
    
    // Update current filters
    currentFilters = {
        bookingType: bookingTypeFilter,
        status: statusFilter,
        search: searchTerm,
        sort: sortBy
    };
    
    // Reload bookings with new filters
    await loadAllBookings();
    
    // Apply booking type and date range filters on client-side
    filteredBookings = allBookings.filter(booking => {
        // Booking type filter
        if (bookingTypeFilter !== 'all') {
            if (bookingTypeFilter === 'walkin' && !booking.is_walkin) return false;
            if (bookingTypeFilter === 'regular' && booking.is_walkin) return false;
        }
        
        // Date range filter
        if (dateRange !== 'all') {
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            
            const bookingDate = getDateForFilter(booking.createdAt);
            if (!bookingDate) return false;
            
            switch (dateRange) {
                case 'today':
                    return bookingDate >= today;
                case 'week':
                    const weekAgo = new Date(today);
                    weekAgo.setDate(weekAgo.getDate() - 7);
                    return bookingDate >= weekAgo;
                case 'month':
                    const monthAgo = new Date(today);
                    monthAgo.setMonth(monthAgo.getMonth() - 1);
                    return bookingDate >= monthAgo;
                case 'year':
                    const yearAgo = new Date(today);
                    yearAgo.setFullYear(yearAgo.getFullYear() - 1);
                    return bookingDate >= yearAgo;
                default:
                    return true;
            }
        }
        
        return true; // Include booking if it passes all filters
    });
    
    populateBookingsTable();
    updateStats();
}

// Parse duration string (e.g., "30 Days", "1 Year") to number of days
function parseDurationToDays(durationStr) {
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
}

// Check if a specific booking is currently active (not expired)
function isBookingActive(booking) {
    if (booking.status !== 'verified') return false;
    
    const now = new Date();
    
    // If backend already provided an expiry date, use it
    if (booking.expires_at) {
        return now <= new Date(booking.expires_at);
    }
    
    const bookingDate = new Date(booking.booking_date || booking.createdAt);
    const days = parseDurationToDays(booking.duration);
    
    if (days === 0) return false; // Or handle based on your business logic
    
    const expiryDate = new Date(bookingDate);
    expiryDate.setDate(expiryDate.getDate() + days);
    
    return now <= expiryDate;
}

// Populate bookings table
function populateBookingsTable() {
    const tbody = document.getElementById('bookingsTable');
    const noBookingsMessage = document.getElementById('noBookingsMessage');
    tbody.innerHTML = '';
    
    if (filteredBookings.length === 0) {
        tbody.style.display = 'none';
        noBookingsMessage.style.display = 'block';
        document.getElementById('showingCount').textContent = '0';
        document.getElementById('totalCount').textContent = allBookings.length;
        return;
    }
    
    tbody.style.display = 'table-row-group';
    noBookingsMessage.style.display = 'none';
    
    filteredBookings.forEach(booking => {
        const row = document.createElement('tr');
        const displayDate = formatDateForDisplay(booking.date || booking.createdAt);
        const isActive = isBookingActive(booking);
        
        row.innerHTML = `
            <td data-label="Client">
                <div style="font-weight: 700; color: var(--primary);">${booking.name || 'Unknown User'}</div>
                <div style="font-size: 0.9rem; color: var(--dark-text-secondary);">${booking.email || 'No email'}</div>
            </td>
            <td data-label="Booking Type">
                <div class="booking-type-cell">
                    ${booking.is_walkin 
                        ? '<span class="walkin-badge"><i class="fas fa-person-walking"></i> Walk-in</span>' 
                        : '<span class="regular-badge"><i class="fas fa-user-check"></i> Member</span>'}
                </div>
            </td>
            <td data-label="Package">
                <div>${booking.package || 'N/A'}</div>
                ${booking.status === 'verified' ? `
                    <div style="font-size: 0.8rem; margin-top: 4px;">
                        <span class="status-badge status-${isActive ? 'verified' : 'pending'}" style="padding: 2px 8px; font-size: 0.75rem;">
                            ${isActive ? 'Active' : 'Expired'}
                        </span>
                    </div>
                ` : ''}
            </td>
            <td data-label="Date">${displayDate}</td>
            <td data-label="Expiry">
                <div style="font-weight: 500;">${formatDateForDisplay(booking.expires_at)}</div>
            </td>
            <td data-label="Amount" style="font-weight: 800;">${booking.amount || '₱0'}</td>
            <td data-label="Contact">${booking.contact || 'N/A'}</td>
            <td data-label="Status"><span class="status-badge status-${booking.status || 'pending'}">${(booking.status || 'pending').charAt(0).toUpperCase() + (booking.status || 'pending').slice(1)}</span></td>
            <td data-label="Actions">
                <div class="table-actions">
                    <button class="icon-btn" onclick="viewBooking('${booking.id}')" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${booking.status === 'pending' ? `
                        <button class="icon-btn success" onclick="verifyBooking('${booking.id}')" title="Verify Payment">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="icon-btn danger" onclick="rejectBooking('${booking.id}')" title="Reject Payment">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    document.getElementById('showingCount').textContent = filteredBookings.length;
    document.getElementById('totalCount').textContent = allBookings.length;
}

// Update stats
function updateStats() {
    const totalBookings = allBookings.length;
    const pendingBookings = allBookings.filter(b => b.status === 'pending').length;
    const verifiedBookings = allBookings.filter(b => b.status === 'verified').length;
    
    // Walk-in vs Regular breakdown
    const walkinBookings = allBookings.filter(b => b.is_walkin).length;
    const regularBookings = allBookings.filter(b => !b.is_walkin).length;
    
    // Calculate total revenue
    let totalRevenue = 0;
    allBookings
        .filter(b => b.status === 'verified')
        .forEach(b => {
            const amount = parseFloat((b.amount || '0').replace(/[₱,]/g, '')) || 0;
            totalRevenue += amount;
        });
    
    // Update stat cards
    document.getElementById('totalBookings').textContent = totalBookings;
    document.getElementById('pendingBookings').textContent = pendingBookings;
    document.getElementById('verifiedBookings').textContent = verifiedBookings;
    document.getElementById('totalRevenue').textContent = `₱${totalRevenue.toLocaleString()}`;
    
    // Update booking type breakdown (if elements exist)
    const walkinStatElement = document.getElementById('walkinBookings');
    const regularStatElement = document.getElementById('regularBookings');
    
    if (walkinStatElement) {
        walkinStatElement.textContent = walkinBookings;
    }
    if (regularStatElement) {
        regularStatElement.textContent = regularBookings;
    }
    
    // Update badges
    const bookingsBadge = document.getElementById('bookingsBadge');
    if (bookingsBadge) {
        bookingsBadge.textContent = pendingBookings || '';
    }
    
    const notificationBadge = document.getElementById('notificationBadge');
    if (notificationBadge) {
        notificationBadge.textContent = pendingBookings || '';
    }
}

// View booking details
function viewBooking(id) {
    try {
        const booking = allBookings.find(b => String(b.id) === String(id));
        if (!booking) {
            showNotification('Booking not found', 'warning');
            return;
        }
        
        currentViewingBooking = booking;
        
        // Populate modal
        document.getElementById('modalBookingId').textContent = `#${booking.id}`;
        document.getElementById('modalBookingType').innerHTML = booking.is_walkin 
            ? '<span class="walkin-badge"><i class="fas fa-person-walking"></i> Walk-in</span>' 
            : '<span class="regular-badge"><i class="fas fa-user-check"></i> Member</span>';
        
        const statusBadge = `<span class="status-badge status-${booking.status || 'pending'}">${(booking.status || 'pending').charAt(0).toUpperCase() + (booking.status || 'pending').slice(1)}</span>`;
        document.getElementById('modalStatus').innerHTML = statusBadge;
        
        document.getElementById('modalClientName').textContent = booking.name || 'Unknown User';
        document.getElementById('modalContact').textContent = booking.contact || 'N/A';
        document.getElementById('modalEmail').textContent = booking.email || 'No email';
        document.getElementById('modalPackage').textContent = booking.package || 'N/A';
        document.getElementById('modalDuration').textContent = booking.duration || 'N/A';
        document.getElementById('modalDate').textContent = formatDateForDisplay(booking.date || booking.createdAt);
        document.getElementById('modalExpiry').textContent = formatDateForDisplay(booking.expires_at);
        document.getElementById('modalAmount').textContent = booking.amount || '₱0';
        document.getElementById('modalPaymentMethod').textContent = (booking.payment_method || 'N/A').toUpperCase();
        document.getElementById('modalCreatedAt').textContent = formatDateForDisplay(booking.createdAt);
        
        // Show verified at if available
        if (booking.verified_at) {
            document.getElementById('verifiedAtGroup').style.display = 'block';
            document.getElementById('modalVerifiedAt').textContent = formatDateForDisplay(booking.verified_at);
        } else {
            document.getElementById('verifiedAtGroup').style.display = 'none';
        }
        
        // Show notes if available
        if (booking.notes) {
            document.getElementById('notesGroup').style.display = 'block';
            document.getElementById('modalNotes').textContent = booking.notes;
        } else {
            document.getElementById('notesGroup').style.display = 'none';
        }
        
        // Show receipt if available
        const receiptImg = document.getElementById('modalReceipt');
        const receiptSection = document.getElementById('receiptSection');
        
        const receiptUrl = booking.receipt_full_url || booking.receipt_url || booking.receipt;
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
        
        // Update action buttons based on status
        const verifyBtn = document.querySelector('.modal-actions .btn-primary');
        const rejectBtn = document.querySelector('.modal-actions .btn-secondary');
        
        if (booking.status === 'pending') {
            verifyBtn.style.display = 'flex';
            verifyBtn.innerHTML = '<i class="fas fa-check"></i> Verify Payment';
            verifyBtn.onclick = verifyPayment;
            rejectBtn.style.display = 'flex';
            rejectBtn.innerHTML = '<i class="fas fa-times"></i> Reject Payment';
            rejectBtn.onclick = rejectPayment;
        } else if (booking.status === 'verified') {
            verifyBtn.style.display = 'none';
            rejectBtn.style.display = 'flex';
            rejectBtn.innerHTML = '<i class="fas fa-times"></i> Close';
            rejectBtn.onclick = closeModal;
        } else {
            verifyBtn.style.display = 'none';
            rejectBtn.style.display = 'flex';
            rejectBtn.innerHTML = '<i class="fas fa-times"></i> Close';
            rejectBtn.onclick = closeModal;
        }
        
        // Show modal
        const modal = document.getElementById('bookingModal');
        if (modal) {
            modal.classList.add('active');
        }
    } catch (error) {
        console.error('Error viewing booking:', error);
        showNotification('Error loading booking details. Please try again.', 'warning');
    }
}

// Verify booking
async function verifyBooking(id) {
    try {
        const booking = allBookings.find(b => String(b.id) === String(id));
        if (!booking) {
            showNotification('Booking not found', 'warning');
            return;
        }
        
        if (booking.status === 'pending') {
            if (confirm(`Verify payment for ${booking.name || 'this user'}?`)) {
                // Update booking status via API
                const response = await fetch(`../../api/bookings/update.php?id=${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        status: 'verified',
                        notes: booking.notes || ''
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(`Payment for ${booking.name || 'user'} has been verified!`, 'success');
                    
                    // Refresh bookings
                    await loadAllBookings();
                    await applyFilters();
                    
                    if (currentViewingBooking && String(currentViewingBooking.id) === String(id)) {
                        closeModal();
                    }
                } else {
                    showNotification('Error verifying booking: ' + data.message, 'warning');
                }
            }
        } else {
            showNotification('This booking has already been processed', 'info');
        }
    } catch (error) {
        console.error('Error verifying booking:', error);
        showNotification('Error verifying booking. Please try again.', 'warning');
    }
}

// Reject booking from table
async function rejectBooking(id) {
    try {
        const booking = allBookings.find(b => String(b.id) === String(id));
        if (!booking) {
            showNotification('Booking not found', 'warning');
            return;
        }
        
        if (booking.status === 'pending') {
            if (confirm(`Reject payment for ${booking.name || 'this user'}? The client will be notified.`)) {
                // Update booking status via API
                const response = await fetch(`../../api/bookings/update.php?id=${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        status: 'rejected',
                        notes: booking.notes || ''
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Payment rejected. Notification sent to client.', 'warning');
                    
                    // Refresh bookings
                    await loadAllBookings();
                    await applyFilters();
                    
                    if (currentViewingBooking && String(currentViewingBooking.id) === String(id)) {
                        closeModal();
                    }
                } else {
                    showNotification('Error rejecting booking: ' + data.message, 'warning');
                }
            }
        } else {
            showNotification('This booking has already been processed', 'info');
        }
    } catch (error) {
        console.error('Error rejecting booking:', error);
        showNotification('Error rejecting booking. Please try again.', 'warning');
    }
}

// Add verified booking to payments
function addToPayments(booking) {
    try {
        // Get existing payments
        let payments = [];
        const paymentsKey = 'verifiedPayments';
        const existingPayments = localStorage.getItem(paymentsKey);
        if (existingPayments) {
            try {
                payments = JSON.parse(existingPayments);
            } catch (e) {
                payments = [];
            }
        }
        
        // Check if payment already exists
        const existingPayment = payments.find(p => String(p.id) === String(booking.id));
        if (!existingPayment) {
            // Create payment entry from booking
            const payment = {
                id: booking.id,
                name: booking.name,
                email: booking.email,
                contact: booking.contact,
                package: booking.package,
                date: booking.date || booking.createdAt,
                amount: booking.amount,
                status: 'verified',
                verifiedAt: booking.verifiedAt || new Date().toISOString(),
                receipt: booking.receipt || null
            };
            
            payments.push(payment);
            localStorage.setItem(paymentsKey, JSON.stringify(payments));
        }
    } catch (error) {
        console.error('Error adding to payments:', error);
    }
}

// Verify payment from modal
async function verifyPayment() {
    if (!currentViewingBooking) {
        showNotification('No booking selected', 'warning');
        return;
    }
    
    try {
        const booking = currentViewingBooking;
        if (booking.status === 'pending') {
            if (confirm(`Verify payment for ${booking.name || 'this user'}?`)) {
                // Update booking status via API
                const response = await fetch(`../../api/bookings/update.php?id=${booking.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        status: 'verified',
                        notes: booking.notes || ''
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(`Payment for ${booking.name || 'user'} has been verified!`, 'success');
                    
                    // Refresh bookings
                    await loadAllBookings();
                    await applyFilters();
                    closeModal();
                } else {
                    showNotification('Error verifying payment: ' + data.message, 'warning');
                }
            }
        } else {
            showNotification('This booking has already been processed', 'info');
        }
    } catch (error) {
        console.error('Error verifying payment:', error);
        showNotification('Error verifying payment. Please try again.', 'warning');
    }
}

// Reject payment
async function rejectPayment() {
    if (!currentViewingBooking) {
        showNotification('No booking selected', 'warning');
        return;
    }
    
    try {
        const booking = currentViewingBooking;
        if (booking.status === 'pending') {
            if (confirm(`Reject payment for ${booking.name || 'this user'}? The client will be notified.`)) {
                // Update booking status via API
                const response = await fetch(`../../api/bookings/update.php?id=${booking.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        status: 'rejected',
                        notes: booking.notes || ''
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Payment rejected. Notification sent to client.', 'warning');
                    
                    // Refresh bookings
                    await loadAllBookings();
                    await applyFilters();
                    closeModal();
                } else {
                    showNotification('Error rejecting payment: ' + data.message, 'warning');
                }
            }
        } else {
            showNotification('This booking has already been processed', 'info');
        }
    } catch (error) {
        console.error('Error rejecting payment:', error);
        showNotification('Error rejecting payment. Please try again.', 'warning');
    }
}

// Close modal
function closeModal() {
    document.getElementById('bookingModal').classList.remove('active');
    currentViewingBooking = null;
}

// Export bookings
function exportBookings() {
    if (filteredBookings.length === 0) {
        showNotification('No bookings to export', 'warning');
        return;
    }
    
    // Create CSV content
    let csv = 'Client Name,Email,Contact,Package,Date,Amount,Status\n';
    
    filteredBookings.forEach(booking => {
        const name = (booking.name || 'Unknown').replace(/,/g, '');
        const email = (booking.email || 'N/A').replace(/,/g, '');
        const contact = (booking.contact || 'N/A').replace(/,/g, '');
        const packageName = (booking.package || 'N/A').replace(/,/g, '');
        const date = formatDateForDisplay(booking.date || booking.createdAt).replace(/,/g, '');
        const amount = (booking.amount || '₱0').replace(/,/g, '');
        const status = (booking.status || 'pending').replace(/,/g, '');
        
        csv += `${name},${email},${contact},${packageName},${date},${amount},${status}\n`;
    });
    
    // Create download link
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `bookings_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showNotification('Bookings exported successfully!', 'success');
}

// Refresh bookings
async function refreshBookings() {
    await loadAllBookings();
    await applyFilters();
    showNotification('Bookings refreshed', 'success');
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
async function initPage() {
    await loadAllBookings();
    await applyFilters();
    
    // Setup event listeners
    document.getElementById('bookingTypeFilter').addEventListener('change', async () => {
        await applyFilters();
    });
    document.getElementById('statusFilter').addEventListener('change', async () => {
        await applyFilters();
    });
    document.getElementById('sortBy').addEventListener('change', async () => {
        await applyFilters();
    });
    document.getElementById('dateRange').addEventListener('change', async () => {
        await applyFilters();
    });
    document.getElementById('searchInput').addEventListener('input', async () => {
        await applyFilters();
    });
    
    // Close modal on outside click
    document.getElementById('bookingModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Notification button
    const notificationBtn = document.querySelector('.notification-btn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function() {
            const pendingCount = allBookings.filter(b => b.status === 'pending').length;
            showNotification(`You have ${pendingCount} pending booking${pendingCount !== 1 ? 's' : ''} to verify`, 'info');
        });
    }
    
    // Refresh bookings every 3 seconds
    setInterval(async () => {
        await loadAllBookings();
        await applyFilters();
    }, 3000);
}

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    initPage();
    
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
});

// Walk-in Booking Functions
function openWalkinModal() {
    document.getElementById('walkinModal').classList.add('active');
    loadPackages();
    setDefaultDate();
    resetWalkinForm();
}

function closeWalkinModal() {
    document.getElementById('walkinModal').classList.remove('active');
}

function resetWalkinForm() {
    document.getElementById('walkinForm').reset();
    removeReceipt();
}

function setDefaultDate() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('bookingDate').value = today;
}

async function loadPackages() {
    try {
        const response = await fetch('../../api/packages/get-all.php');
        const result = await response.json();
        
        if (result.success) {
            packages = result.data;
            const packageSelect = document.getElementById('packageSelect');
            packageSelect.innerHTML = '<option value="">Select Package</option>';
            
            packages.forEach(pkg => {
                const option = document.createElement('option');
                option.value = pkg.name;
                option.textContent = `${pkg.name} - ₱${parseFloat(pkg.price).toFixed(2)}`;
                option.dataset.price = pkg.price;
                packageSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading packages:', error);
        showNotification('Error loading packages', 'error');
    }
}

// Handle walk-in form submission
document.addEventListener('DOMContentLoaded', function() {
    // Check for action=walkin in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'walkin') {
        // Small delay to ensure everything is loaded
        setTimeout(() => {
            openWalkinModal();
            // Clean up the URL without refreshing
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        }, 500);
    }

    const walkinForm = document.getElementById('walkinForm');
    if (walkinForm) {
        walkinForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(walkinForm);
            const data = Object.fromEntries(formData.entries());
            
            // Add receipt URL if uploaded
            const receiptInput = document.getElementById('receiptUpload');
            if (receiptInput.files.length > 0) {
                data.receipt = await uploadReceipt(receiptInput.files[0]);
            }
            
            try {
                // Show loading state
                const submitBtn = walkinForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
                submitBtn.disabled = true;
                walkinForm.classList.add('loading');
                
                const response = await fetch('../../api/walkin/create.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Walk-in booking created successfully!', 'success');
                    closeWalkinModal();
                    loadAllBookings(); // Refresh the bookings list
                    updateStats(); // Update statistics
                    
                    // Add success animation
                    document.getElementById('walkinModal').classList.add('success');
                    setTimeout(() => {
                        document.getElementById('walkinModal').classList.remove('success');
                    }, 600);
                    
                    // Generate receipt for walk-in booking
                    setTimeout(() => {
                        generateWalkinReceipt(result.data.id);
                    }, 1000);
                } else {
                    showNotification(result.message || 'Error creating booking', 'error');
                }
            } catch (error) {
                console.error('Error creating booking:', error);
                showNotification('Error creating booking. Please try again.', 'error');
            } finally {
                // Reset loading state
                const submitBtn = walkinForm.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Walk-in';
                submitBtn.disabled = false;
                walkinForm.classList.remove('loading');
            }
        });
    }
    
    // Receipt upload handling
    const receiptUpload = document.getElementById('receiptUpload');
    if (receiptUpload) {
        receiptUpload.addEventListener('change', handleReceiptUpload);
    }
});

function handleReceiptUpload(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById('receiptImage').src = e.target.result;
            document.getElementById('receiptPreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

function removeReceipt() {
    document.getElementById('receiptUpload').value = '';
    document.getElementById('receiptPreview').style.display = 'none';
    document.getElementById('receiptImage').src = '';
}

async function uploadReceipt(file) {
    const formData = new FormData();
    formData.append('receipt', file);
    
    try {
        const response = await fetch('../../api/upload/receipt.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            return result.data.file_path;
        } else {
            throw new Error(result.message || 'Upload failed');
        }
    } catch (error) {
        console.error('Error uploading receipt:', error);
        throw error;
    }
}

// Generate receipt for walk-in booking
async function generateWalkinReceipt(bookingId) {
    try {
        showNotification('Generating receipt...', 'info');
        
        const response = await fetch('../../api/receipt/generate-walkin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ booking_id: bookingId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Open receipt in new window for printing
            const printWindow = window.open('', '_blank', 'width=400,height=600,scrollbars=yes');
            printWindow.document.write(result.data.receipt_html);
            printWindow.document.close();
            
            showNotification('Receipt ready for printing!', 'success');
        } else {
            showNotification(result.message || 'Error generating receipt', 'error');
        }
    } catch (error) {
        console.error('Error generating receipt:', error);
        showNotification('Error generating receipt', 'error');
    }
}

// Update renderBookingRow to show walk-in indicator
function renderBookingRow(booking) {
    const bookingTypeBadge = booking.is_walkin 
        ? '<span class="walkin-badge"><i class="fas fa-person-walking"></i> Walk-in</span>' 
        : '<span class="regular-badge"><i class="fas fa-user-check"></i> Member</span>';
    
    return `
        <tr class="booking-row" data-id="${booking.id}">
            <td>
                <div class="booking-info">
                    <div class="booking-name">${booking.name}</div>
                    <div class="booking-email">${booking.email}</div>
                </div>
            </td>
            <td>
                <div class="booking-type-cell">
                    ${bookingTypeBadge}
                </div>
            </td>
            <td>
                <div class="package-info">
                    <div class="package-name">${booking.package}</div>
                    <div class="booking-date">${booking.date}</div>
                </div>
            </td>
            <td>
                <div class="expiry-date">${formatDateForDisplay(booking.expires_at)}</div>
            </td>
            <td class="amount">${booking.amount}</td>
            <td>${booking.contact}</td>
            <td>
                <span class="status-badge ${booking.status}">${booking.status}</span>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-primary" onclick="viewBooking(${booking.id})" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${booking.receipt_url ? `
                        <a href="${fixReceiptUrl(booking.receipt_url)}" target="_blank" class="btn btn-sm btn-info" title="View Receipt">
                            <i class="fas fa-receipt"></i>
                        </a>
                    ` : ''}
                </div>
            </td>
        </tr>
    `;
}
