// All members data
let allMembers = [];
let filteredMembers = [];
let currentViewingMember = null;

// Get user initials for avatar
function getInitials(name) {
    if (!name) return '??';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

// Load all members from database
async function loadAllMembers() {
    try {
        const [usersRes, bookingsRes, walkinsRes] = await Promise.all([
            fetch('../../api/users/get-all.php?role=user'),
            fetch('../../api/bookings/get-all.php'),
            fetch('../../api/walkin/get-all.php')
        ]);

        const usersData = await usersRes.json();
        const bookingsData = await bookingsRes.json();
        const walkinsData = await walkinsRes.json();

        if (usersData.success) {
            const allBookings = bookingsData.success ? bookingsData.data : [];
            const allWalkins = walkinsData.success ? walkinsData.data : [];
            
            // 1. Process regular registered users
            const registeredMembers = usersData.data
                .map(user => {
                    const userBookings = allBookings.filter(b => String(b.user_id) === String(user.id));
                    const verifiedBookings = userBookings.filter(b => b.status === 'verified');
                    
                    if (verifiedBookings.length === 0) return null;

                    const totalSpent = verifiedBookings.reduce((sum, b) => sum + (parseFloat(b.amount) || 0), 0);
                    
                    return {
                        id: 'user_' + user.id,
                        real_id: user.id,
                        type: 'registered',
                        name: user.name,
                        email: user.email,
                        contact: user.contact || 'N/A',
                        address: user.address || 'N/A',
                        role: user.role,
                        bookings: userBookings,
                        verifiedBookings: verifiedBookings,
                        totalSpent: totalSpent,
                        joinedDate: user.created_at
                    };
                })
                .filter(member => member !== null);

            // 2. Process walk-in customers as members if they have verified bookings
            // Group walk-ins by email to avoid duplicates
            const walkinMap = new Map();
            allWalkins.forEach(walkin => {
                if (walkin.status !== 'verified') return;

                const email = walkin.email.toLowerCase();
                if (!walkinMap.has(email)) {
                    walkinMap.set(email, {
                        id: 'walkin_' + walkin.id,
                        real_id: walkin.id,
                        type: 'walkin',
                        name: walkin.name,
                        email: walkin.email,
                        contact: walkin.contact || 'N/A',
                        address: 'Walk-in Customer',
                        role: 'user',
                        bookings: [],
                        verifiedBookings: [],
                        totalSpent: 0,
                        joinedDate: walkin.created_at
                    });
                }

                const member = walkinMap.get(email);
                member.bookings.push(walkin);
                member.verifiedBookings.push(walkin);
                member.totalSpent += parseFloat(walkin.amount) || 0;
                
                // Keep the earliest created_at as joinedDate
                if (new Date(walkin.created_at) < new Date(member.joinedDate)) {
                    member.joinedDate = walkin.created_at;
                }
            });

            const walkinMembers = Array.from(walkinMap.values());

            // Combine both
            allMembers = [...registeredMembers, ...walkinMembers];
        }
    } catch (error) {
        console.error('Error loading members:', error);
    }
    
    return allMembers;
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

// Check if member is active (has verified bookings that haven't expired)
function isMemberActive(member) {
    if (!member.verifiedBookings || member.verifiedBookings.length === 0) return false;
    
    const now = new Date();
    
    return member.verifiedBookings.some(booking => {
        const bookingDate = new Date(booking.booking_date || booking.created_at);
        const days = parseDurationToDays(booking.duration);
        
        // Calculate expiry date
        const expiryDate = new Date(bookingDate);
        expiryDate.setDate(expiryDate.getDate() + days);
        
        // User is active if current date is before or equal to expiry date
        return now <= expiryDate;
    });
}

// Apply filters
function applyFilters() {
    const statusFilter = document.getElementById('statusFilter').value;
    const sortBy = document.getElementById('sortBy').value;
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    
    filteredMembers = [...allMembers];
    
    // Status filter
    if (statusFilter !== 'all') {
        filteredMembers = filteredMembers.filter(member => {
            const isActive = isMemberActive(member);
            return statusFilter === 'active' ? isActive : !isActive;
        });
    }
    
    // Search filter
    if (searchTerm) {
        filteredMembers = filteredMembers.filter(member => {
            const name = (member.name || '').toLowerCase();
            const email = (member.email || '').toLowerCase();
            const contact = (member.contact || '').toLowerCase();
            const address = (member.address || '').toLowerCase();
            
            return name.includes(searchTerm) || 
                   email.includes(searchTerm) || 
                   contact.includes(searchTerm) ||
                   address.includes(searchTerm);
        });
    }
    
    // Sort
    filteredMembers.sort((a, b) => {
        switch (sortBy) {
            case 'name-asc':
                return (a.name || '').localeCompare(b.name || '');
            case 'name-desc':
                return (b.name || '').localeCompare(a.name || '');
            case 'bookings-desc':
                return b.bookings.length - a.bookings.length;
            case 'bookings-asc':
                return a.bookings.length - b.bookings.length;
            case 'revenue-desc':
                return b.totalSpent - a.totalSpent;
            case 'revenue-asc':
                return a.totalSpent - b.totalSpent;
            case 'recent':
                const dateA = new Date(a.joinedDate || 0);
                const dateB = new Date(b.joinedDate || 0);
                return dateB - dateA;
            default:
                return 0;
        }
    });
    
    populateMembersGrid();
    updateStats();
}

// Populate members grid
function populateMembersGrid() {
    const grid = document.getElementById('membersGrid');
    const noMembersMessage = document.getElementById('noMembersMessage');
    grid.innerHTML = '';
    
    if (filteredMembers.length === 0) {
        grid.style.display = 'none';
        noMembersMessage.style.display = 'block';
        document.getElementById('showingCount').textContent = '0';
        document.getElementById('totalCount').textContent = allMembers.length;
        return;
    }
    
    grid.style.display = 'grid';
    noMembersMessage.style.display = 'none';
    
    filteredMembers.forEach(member => {
        const isActive = isMemberActive(member);
        const memberCard = document.createElement('div');
        memberCard.className = 'package-card';
        memberCard.style.cursor = 'pointer';
        memberCard.onclick = () => viewMember(member.id);
        
        memberCard.innerHTML = `
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px; min-width: 0;">
                <div class="admin-avatar" style="width: 60px; height: 60px; font-size: 1.5rem; flex-shrink: 0;">
                    ${getInitials(member.name)}
                </div>
                <div style="flex: 1; min-width: 0; overflow: hidden;">
                    <h4 style="margin-bottom: 4px; color: var(--primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${member.name || 'Unknown User'}</h4>
                    <p style="font-size: 0.85rem; color: var(--dark-text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${member.email || 'No email'}</p>
                </div>
                <span class="status-badge status-${isActive ? 'verified' : 'pending'}" style="flex-shrink: 0;">
                    ${isActive ? 'Active' : 'Inactive'}
                </span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--dark-border);">
                <div>
                    <div style="font-size: 0.85rem; color: var(--dark-text-secondary); margin-bottom: 4px;">Bookings</div>
                    <div style="font-weight: 800; font-size: 1.2rem; color: var(--primary);">${member.bookings.length}</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: var(--dark-text-secondary); margin-bottom: 4px;">Total Spent</div>
                    <div style="font-weight: 800; font-size: 1.2rem; color: var(--success);">₱${Math.round(member.totalSpent).toLocaleString()}</div>
                </div>
            </div>
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--dark-border);">
                <div style="font-size: 0.85rem; color: var(--dark-text-secondary);">
                    <i class="fas fa-phone" style="margin-right: 8px;"></i>${member.contact || 'N/A'}
                </div>
            </div>
        `;
        
        grid.appendChild(memberCard);
    });
    
    document.getElementById('showingCount').textContent = filteredMembers.length;
    document.getElementById('totalCount').textContent = allMembers.length;
}

// Update stats
async function updateStats() {
    const totalMembers = allMembers.length;
    const activeMembers = allMembers.filter(m => isMemberActive(m)).length;
    
    let totalBookings = 0;
    let totalRevenue = 0;
    
    allMembers.forEach(member => {
        totalBookings += member.bookings.length;
        totalRevenue += member.totalSpent;
    });
    
    // Update stat cards
    document.getElementById('totalMembers').textContent = totalMembers;
    document.getElementById('activeMembers').textContent = activeMembers;
    document.getElementById('totalBookings').textContent = totalBookings;
    document.getElementById('totalRevenue').textContent = `₱${Math.round(totalRevenue).toLocaleString()}`;
    
    // Update pending bookings badge from API
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

// View member details
function viewMember(memberId) {
    const member = allMembers.find(m => m.id === memberId);
    if (!member) {
        showNotification('Member not found', 'warning');
        return;
    }
    
    currentViewingMember = member;
    const isActive = isMemberActive(member);
    
    // Populate modal
    document.getElementById('memberAvatar').textContent = getInitials(member.name);
    document.getElementById('memberName').textContent = member.name || 'Unknown User';
    document.getElementById('memberEmail').textContent = member.email || 'No email';
    document.getElementById('modalEmail').textContent = member.email || 'No email';
    document.getElementById('modalContact').textContent = member.contact || 'N/A';
    document.getElementById('modalAddress').textContent = member.address || 'N/A';
    document.getElementById('modalTotalBookings').textContent = member.bookings.length;
    document.getElementById('modalVerifiedPayments').textContent = member.verifiedBookings.length;
    document.getElementById('modalTotalSpent').textContent = `₱${Math.round(member.totalSpent).toLocaleString()}`;
    document.getElementById('modalStatus').textContent = isActive ? 'Active' : 'Inactive';
    document.getElementById('modalStatus').className = `status-badge status-${isActive ? 'verified' : 'pending'}`;
    document.getElementById('modalJoinedDate').textContent = formatDateForDisplay(member.joinedDate);
    
    // Populate bookings list
    const bookingsList = document.getElementById('memberBookingsList');
    bookingsList.innerHTML = '';
    
    if (member.bookings.length === 0) {
        bookingsList.innerHTML = '<p style="color: var(--dark-text-secondary); text-align: center; padding: 20px;">No bookings found</p>';
    } else {
        // Sort bookings by date (newest first)
        const sortedBookings = [...member.bookings].sort((a, b) => {
            const dateA = new Date(a.booking_date || a.created_at || 0);
            const dateB = new Date(b.booking_date || b.created_at || 0);
            return dateB - dateA;
        });
        
        sortedBookings.forEach(booking => {
            const bookingItem = document.createElement('div');
            bookingItem.style.cssText = `
                padding: 16px;
                margin-bottom: 12px;
                background: var(--dark-card);
                border: 1px solid var(--dark-border);
                border-radius: var(--radius-md);
                display: flex;
                justify-content: space-between;
                align-items: center;
            `;
            
            bookingItem.innerHTML = `
                <div>
                    <div style="font-weight: 700; color: var(--primary); margin-bottom: 4px;">
                        ${booking.package_name || 'N/A'}
                    </div>
                    <div style="font-size: 0.85rem; color: var(--dark-text-secondary);">
                        ${booking.date_formatted || formatDateForDisplay(booking.booking_date || booking.created_at)}
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-weight: 800; color: var(--success); margin-bottom: 4px;">
                        ${booking.amount_formatted || ('₱' + parseFloat(booking.amount).toFixed(2))}
                    </div>
                    <span class="status-badge status-${booking.status || 'pending'}">
                        ${(booking.status || 'pending').charAt(0).toUpperCase() + (booking.status || 'pending').slice(1)}
                    </span>
                </div>
            `;
            
            bookingsList.appendChild(bookingItem);
        });
    }
    
    // Show modal
    document.getElementById('memberModal').classList.add('active');
}

// Close modal
function closeModal() {
    document.getElementById('memberModal').classList.remove('active');
    currentViewingMember = null;
}

// Export members
function exportMembers() {
    if (filteredMembers.length === 0) {
        showNotification('No members to export', 'warning');
        return;
    }
    
    // Create CSV content
    let csv = 'Name,Email,Contact,Address,Total Bookings,Verified Payments,Total Spent,Status\n';
    
    filteredMembers.forEach(member => {
        const name = (member.name || 'Unknown').replace(/,/g, '');
        const email = (member.email || 'N/A').replace(/,/g, '');
        const contact = (member.contact || 'N/A').replace(/,/g, '');
        const address = (member.address || 'N/A').replace(/,/g, '');
        const bookings = member.bookings.length;
        const verified = member.verifiedBookings.length;
        const spent = Math.round(member.totalSpent);
        const status = isMemberActive(member) ? 'Active' : 'Inactive';
        
        csv += `${name},${email},${contact},${address},${bookings},${verified},₱${spent},${status}\n`;
    });
    
    // Create download link
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `members_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showNotification('Members exported successfully!', 'success');
}

// Refresh members
async function refreshMembers() {
    await loadAllMembers();
    applyFilters();
    showNotification('Members refreshed', 'success');
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
    await loadAllMembers();
    applyFilters();
    
    // Setup event listeners
    document.getElementById('statusFilter').addEventListener('change', applyFilters);
    document.getElementById('sortBy').addEventListener('change', applyFilters);
    document.getElementById('bookingsFilter').addEventListener('change', applyFilters);
    document.getElementById('searchInput').addEventListener('input', applyFilters);
    
    // Close modal on outside click
    document.getElementById('memberModal').addEventListener('click', function(e) {
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
            } catch (e) {}
        });
    }
    
    // Refresh members every 10 seconds
    setInterval(async () => {
        await loadAllMembers();
        applyFilters();
    }, 10000);
});
