// User Dashboard JavaScript

// Sample data - In a real app, this would come from a backend API
let userBookings = [];
let selectedFile = null;

// Helper to fix receipt URLs for display
function fixReceiptUrl(url) {
    if (!url) return '';
    if (url.startsWith('http') || url.startsWith('blob:')) return url;
    
    // Remove leading slash and 'Fit/' if present to standardize
    let cleanPath = url.replace(/^\/?Fit\//, '').replace(/^\//, '');
    
    // From views/user/ we need to go up two levels to root
    return '../../' + cleanPath;
}

// Load user bookings from database
async function loadUserBookings() {
    try {
        const response = await fetch('../../api/bookings/get-all.php');
        const data = await response.json();
        
        if (data.success) {
            // Data is already filtered by backend for the logged-in user
            userBookings = data.data;
        } else {
            console.error('Error loading bookings:', data.message);
            userBookings = [];
        }
    } catch (error) {
        console.error('Network error loading bookings:', error);
        userBookings = [];
    }
}

// Package data - load from database
let packagesData = [];

// Load packages data from database
async function loadPackagesData() {
    try {
        const response = await fetch('../../api/packages/get-all.php');
        const data = await response.json();
        
        if (data.success) {
            packagesData = data.data.map(pkg => ({
                id: pkg.id,
                name: pkg.name,
                duration: pkg.duration,
                // Ensure price is treated as a number
                price: '₱' + parseFloat(String(pkg.price).replace(/[^\d.-]/g, '')).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                tag: pkg.tag || 'Standard',
                description: pkg.description || 'Full gym access with all facilities'
            }));
        } else {
            console.error('Error loading packages:', data.message);
            // Fallback to default packages
            packagesData = getDefaultPackages();
        }
    } catch (error) {
        console.error('Network error loading packages:', error);
        // Fallback to default packages
        packagesData = getDefaultPackages();
    }
}

function getDefaultPackages() {
    return [
        { name: "Walk-in Pass", duration: "1 Day", price: "₱200.00", tag: "Basic", description: "Perfect for trying out our facilities" },
        { name: "Weekly Pass", duration: "7 Days", price: "₱500.00", tag: "Popular", description: "Great for short-term fitness goals" },
        { name: "Monthly Membership", duration: "30 Days", price: "₱1,500.00", tag: "Best Value", description: "Most popular choice for regular gym-goers" },
        { name: "3-Month Package", duration: "90 Days", price: "₱4,000.00", tag: "Premium", description: "Save more with our 3-month package" },
        { name: "Annual Membership", duration: "1 Year", price: "₱15,000.00", tag: "VIP", description: "Best value for long-term commitment" }
    ];
}

// Initialize Dashboard
async function initDashboard() {
    try {
        await loadPackagesData();
        await loadUserData(); // Await this to ensure bookings are loaded before populating UI
        await loadPaymentSettings(); // Load dynamic GCash settings
        populatePackages();
        updateStats();
        populateBookings();
        populatePayments();
        setupEventListeners();
        
        // Set today as minimum date for booking
        const today = new Date().toISOString().split('T')[0];
        const bookingDateInput = document.getElementById('bookingDate');
        if (bookingDateInput) {
            bookingDateInput.setAttribute('min', today);
        }
        
        // Update booking package select on initial load
        updateBookingPackageSelect();
        
        // Refresh packages every 3 seconds to catch updates
        setInterval(async () => {
            try {
                await loadPackagesData();
                populatePackages();
                updateBookingPackageSelect();
            } catch (err) {
                console.error('Interval package refresh error:', err);
            }
        }, 3000);

        // Check if survey needs to be shown
        checkSurveyStatus();
    } catch (error) {
        console.error('Dashboard initialization error:', error);
        // Still try to show survey if possible
        checkSurveyStatus();
    }
}

// Survey State
let surveyData = {
    goal: null,
    frequency: null,
    commitment: null
};
let currentSurveyStep = 1;

function checkSurveyStatus() {
    // We check if the survey has been completed for the current user
    let userId = 'guest';
    try {
        const userData = JSON.parse(localStorage.getItem('userData'));
        if (userData) {
            userId = userData.id || userData.email || 'guest';
        }
    } catch (e) {
        console.error('Error getting user identifier for survey:', e);
    }
    
    // NOTE: For testing purposes, we always show the survey even if completed
    const surveyCompleted = false; // localStorage.getItem('gym_survey_completed_' + userId);
    
    if (!surveyCompleted) {
        // Reset survey state just in case
        currentSurveyStep = 1;
        surveyData = { goal: null, frequency: null, commitment: null };
        
        setTimeout(() => {
            const modal = document.getElementById('surveyModal');
            if (modal) {
                // Ensure first step is active and others are hidden
                document.querySelectorAll('.survey-step').forEach(step => step.classList.remove('active'));
                const firstStep = document.querySelector('.survey-step[data-step="1"]');
                if (firstStep) firstStep.classList.add('active');
                
                // Reset progress bar
                const progressBar = document.getElementById('surveyProgress');
                if (progressBar) progressBar.style.width = '33%';
                
                // Reset next button
                const nextBtn = document.getElementById('surveyNextBtn');
                if (nextBtn) {
                    nextBtn.disabled = true;
                    nextBtn.innerHTML = '<span>Next Step</span> <i class="fas fa-arrow-right"></i>';
                }
                
                // Reset all selected options
                document.querySelectorAll('.option-card').forEach(card => card.classList.remove('selected'));
                
                modal.classList.add('active');
                console.log('Showing survey for user:', userId);
            }
        }, 1500);
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
            
            // Update dashboard GCash card
             const qrImage = document.querySelector('.qr-image-dash');
             const accountName = document.querySelector('.qr-info-dash p:nth-child(1)');
             const gcashNumber = document.querySelector('.qr-info-dash p:nth-child(2)');
             
             // Update profile GCash info
             const profileQR = document.querySelector('#profileSection .qr-container');
             const profileName = document.querySelector('#profileSection .qr-info p:nth-child(2)');
             const profileNumber = document.querySelector('#profileSection .qr-info p:nth-child(1)');
             
             // Update booking modal instructions
             const modalQR = document.querySelector('#bookingModal .qr-container-dash img');
             const modalDetails = document.querySelector('#bookingModal .payment-details');

             const updateQR = (imgEl, containerEl) => {
                 let qrPath = settings.gcash_qr_path;
                  // Reduce padding and adjust border radius to maximize QR size
                  const imgStyle = "width: 100%; height: 100%; object-fit: contain; border-radius: 8px;";
                  if (qrPath && qrPath !== '') {
                     if (!qrPath.startsWith('http')) {
                         qrPath = '../../' + qrPath;
                     }
                     if (imgEl) {
                         imgEl.src = qrPath;
                         imgEl.style.cssText = imgStyle;
                     }
                     if (containerEl && !imgEl) {
                         containerEl.innerHTML = `<img src="${qrPath}" style="${imgStyle}">`;
                     }
                 } else {
                     const fallbackQR = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=GCash:' + (settings.gcash_number || '09171234567');
                     if (imgEl) {
                         imgEl.src = fallbackQR;
                         imgEl.style.cssText = imgStyle;
                     }
                     if (containerEl && !imgEl) {
                         containerEl.innerHTML = `<img src="${fallbackQR}" style="${imgStyle}">`;
                     }
                 }
             };

             updateQR(qrImage);
             updateQR(modalQR);
             updateQR(null, profileQR);

             if (accountName && settings.gcash_name) {
                 accountName.innerHTML = `<strong>Account Name:</strong> ${settings.gcash_name}`;
             }
             if (gcashNumber && settings.gcash_number) {
                 gcashNumber.innerHTML = `<strong>GCash Number:</strong> ${settings.gcash_number}`;
             }

             if (profileName && settings.gcash_name) {
                 profileName.innerHTML = `<strong>Account Name:</strong> ${settings.gcash_name}`;
             }
             if (profileNumber && settings.gcash_number) {
                 profileNumber.innerHTML = `<strong>GCash Number:</strong> ${settings.gcash_number}`;
             }

             if (modalDetails) {
                 modalDetails.innerHTML = `
                     <p style="font-size: 0.85rem; margin-bottom: 5px;"><strong>GCash:</strong> ${settings.gcash_number || '0917-123-4567'}</p>
                     <p style="font-size: 0.85rem;"><strong>Name:</strong> ${settings.gcash_name || 'Martinez Fitness'}</p>
                 `;
             }
        }
    } catch (error) {
        console.error('Error loading payment settings:', error);
    }
}

// Initialize the dashboard
document.addEventListener('DOMContentLoaded', initDashboard);

// Load user data from localStorage or set defaults
async function loadUserData() {
    const savedUser = localStorage.getItem('userData');
    if (savedUser) {
        const user = JSON.parse(savedUser);
        document.getElementById('userName').textContent = user.name;
        document.getElementById('userEmail').textContent = user.email;
        document.getElementById('userAvatar').textContent = getInitials(user.name);
        document.getElementById('profileName').value = user.name;
        document.getElementById('profileEmail').value = user.email;
        document.getElementById('profileContact').value = user.contact || '';
        document.getElementById('profileAddress').value = user.address || '';
    }
    
    // Load bookings from database
    await loadUserBookings();
}

// Get user initials for avatar
function getInitials(name) {
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

// Show section
function showSection(section, event) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(sec => {
        sec.classList.remove('active');
    });
    
    // Show selected section
    const sectionMap = {
        'dashboard': 'dashboardSection',
        'packages': 'packagesSection',
        'bookings': 'bookingsSection',
        'payments': 'paymentsSection',
        'profile': 'profileSection'
    };
    
    const sectionId = sectionMap[section];
    if (sectionId) {
        document.getElementById(sectionId).classList.add('active');
        
        // Update page title
        const titles = {
            'dashboard': { title: 'Dashboard', subtitle: 'Welcome back! Manage your gym membership and bookings' },
            'packages': { title: 'Packages', subtitle: 'Choose a membership plan that fits your fitness goals' },
            'bookings': { title: 'My Bookings', subtitle: 'View and manage your booking requests' },
            'payments': { title: 'Payment History', subtitle: 'Track all your payment transactions' },
            'profile': { title: 'Profile', subtitle: 'Manage your account information and settings' }
        };
        
        const pageInfo = titles[section];
        if (pageInfo) {
            document.getElementById('pageTitle').textContent = pageInfo.title;
            document.getElementById('pageSubtitle').textContent = pageInfo.subtitle;
        }
        
        // Update active nav link
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.classList.remove('active');
        });
        
        if (event) {
            event.target.closest('a')?.classList.add('active');
        } else {
            // Find link by section
            document.querySelectorAll('.nav-links a').forEach(link => {
                if (link.getAttribute('onclick')?.includes(`'${section}'`)) {
                    link.classList.add('active');
                }
            });
        }
    }
}

// Populate packages grid
function populatePackages() {
    const grid = document.getElementById('packagesGrid');
    grid.innerHTML = '';
    
    packagesData.forEach(pkg => {
        const packageCard = document.createElement('div');
        packageCard.className = 'package-card-large';
        packageCard.innerHTML = `
            <div class="package-header">
                <h3>${pkg.name}</h3>
                <span class="package-tag">${pkg.tag}</span>
            </div>
            <p class="package-description">${pkg.description}</p>
            <div class="package-details">
                <div class="package-detail-item">
                    <i class="fas fa-clock"></i>
                    <span>${pkg.duration}</span>
                </div>
                ${pkg.description.split('\n').filter(line => line.trim() !== '').map(line => `
                <div class="package-detail-item">
                    <i class="fas fa-check-circle"></i>
                    <span>${line.trim()}</span>
                </div>
                `).join('')}
                ${pkg.description.trim() === '' ? `
                <div class="package-detail-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Full gym access</span>
                </div>
                <div class="package-detail-item">
                    <i class="fas fa-dumbbell"></i>
                    <span>All facilities</span>
                </div>
                ` : ''}
            </div>
            <div class="package-footer">
                <div class="package-price-large">${pkg.price}</div>
                <button class="btn btn-primary" onclick="selectPackageForBooking('${pkg.name}')">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Book Now</span>
                </button>
            </div>
        `;
        grid.appendChild(packageCard);
    });
}

// Update booking package select dropdown
async function updateBookingPackageSelect() {
    // Make sure packages are loaded
    await loadPackagesData();
    
    const select = document.getElementById('bookingPackage');
    if (!select) return;
    
    // Save current value
    const currentValue = select.value;
    
    // Clear and add default option
    select.innerHTML = '<option value="">Choose a package...</option>';
    
    // Add packages from packagesData
    packagesData.forEach(pkg => {
        const option = document.createElement('option');
        option.value = pkg.name;
        option.textContent = `${pkg.name} - ${pkg.price} (${pkg.duration})`;
        select.appendChild(option);
    });
    
    // Restore previous value if it still exists
    if (currentValue) {
        select.value = currentValue;
    }
}

// Select package for booking
function selectPackageForBooking(packageName) {
    showSection('bookings');
    updateBookingPackageSelect();
    
    // Wait for the UI to update before opening the modal
    setTimeout(() => {
        openBookingModal();
        document.getElementById('bookingPackage').value = packageName;
    }, 100);
}

// Populate bookings table
function populateBookings() {
    const tbody = document.getElementById('bookingsTable');
    const recentTbody = document.getElementById('recentBookingsTable');
    
    if (userBookings.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">
                    No bookings yet. <a href="#" onclick="showSection('packages')" style="color: var(--primary); text-decoration: underline;">Browse packages</a> to get started!
                </td>
            </tr>
        `;
        recentTbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">
                    No bookings yet. <a href="#" onclick="showSection('packages')" style="color: var(--primary); text-decoration: underline;">Browse packages</a> to get started!
                </td>
            </tr>
        `;
        return;
    }
    
    // Sort bookings by date (newest first)
    const sortedBookings = [...userBookings].sort((a, b) => new Date(b.booking_date || b.date || b.createdAt) - new Date(a.booking_date || a.date || a.createdAt));
    
    // Populate main bookings table
    tbody.innerHTML = '';
    sortedBookings.forEach(booking => {
        const row = createBookingRow(booking);
        tbody.appendChild(row);
    });
    
    // Populate recent bookings (last 5)
    recentTbody.innerHTML = '';
    sortedBookings.slice(0, 5).forEach(booking => {
        const row = createBookingRow(booking);
        recentTbody.appendChild(row);
    });
    
    // Update badge
    const pendingCount = userBookings.filter(b => b.status === 'pending').length;
    document.getElementById('bookingsBadge').textContent = pendingCount || '';
}

// Create booking row
function createBookingRow(booking) {
    const row = document.createElement('tr');
    const isActive = isBookingActive(booking);
    
    row.innerHTML = `
        <td data-label="Package">
            <div>${booking.package_name || booking.package}</div>
            ${booking.status === 'verified' ? `
                <div style="font-size: 0.75rem; margin-top: 4px;">
                    <span class="status-badge status-${isActive ? 'verified' : 'pending'}" style="padding: 2px 8px; font-size: 0.7rem;">
                        ${isActive ? 'Active' : 'Expired'}
                    </span>
                </div>
            ` : ''}
        </td>
        <td data-label="Date">${formatDate(booking.booking_date || booking.date || booking.createdAt)}</td>
        <td data-label="Amount" style="font-weight: 800;">₱${parseFloat(booking.amount).toFixed(2)}</td>
        <td data-label="Status"><span class="status-badge status-${booking.status}">${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}</span></td>
        <td data-label="Actions">
            <div class="table-actions">
                <button class="icon-btn" onclick="viewBookingDetails(${booking.id})" title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </td>
    `;
    return row;
}

// Populate payments table
function populatePayments() {
    const tbody = document.getElementById('paymentsTable');
    
    if (userBookings.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">
                    No payment history available.
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = '';
    const sortedBookings = [...userBookings].sort((a, b) => new Date(b.booking_date || b.date || b.createdAt) - new Date(a.booking_date || a.date || a.createdAt));
    
    sortedBookings.forEach((booking, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td data-label="Transaction ID">#${String(booking.id).padStart(6, '0')}</td>
            <td data-label="Package">${booking.package_name || booking.package}</td>
            <td data-label="Date">${formatDate(booking.booking_date || booking.date || booking.createdAt)}</td>
            <td data-label="Amount" style="font-weight: 800;">₱${parseFloat(booking.amount).toFixed(2)}</td>
            <td data-label="Status"><span class="status-badge status-${booking.status}">${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}</span></td>
        `;
        tbody.appendChild(row);
    });
}

// Update stats
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
    
    // Fallback to client-side calculation
    const bookingDate = new Date(booking.booking_date || booking.created_at);
    const days = parseDurationToDays(booking.duration);
    
    if (days === 0) return false;
    
    const expiryDate = new Date(bookingDate);
    expiryDate.setDate(expiryDate.getDate() + days);
    
    return now <= expiryDate;
}

function updateStats() {
    // Active membership = any verified booking that is NOT expired
    const activeVerifiedBookings = userBookings.filter(b => isBookingActive(b));
    const pending = userBookings.filter(b => b.status === 'pending').length;
    const totalVerified = userBookings.filter(b => b.status === 'verified').length;
    
    document.getElementById('activeBookingsCount').textContent = activeVerifiedBookings.length;
    document.getElementById('pendingBookingsCount').textContent = pending;
    document.getElementById('verifiedBookingsCount').textContent = totalVerified;
    
    // Update membership status display
    const membershipStatus = document.getElementById('membershipStatus');
    const profileMembershipBadge = document.getElementById('profileMembershipBadge');
    const profileMembershipValue = document.getElementById('profileMembershipValue');
    const profileMembershipStatus = document.getElementById('profileMembershipStatus');
    const profileMembershipPlan = document.getElementById('profileMembershipPlan');
    const profileMembershipExpiryDate = document.getElementById('profileMembershipExpiryDate');
    const profileMembershipExpiryRow = document.getElementById('profileMembershipExpiryRow');
    const sidebarMemberBadge = document.getElementById('sidebarMemberBadge');

    if (activeVerifiedBookings.length > 0) {
        // Find the booking with the latest expiry date
        const latestActive = [...activeVerifiedBookings].sort((a, b) => {
            const expA = a.expires_at ? new Date(a.expires_at).getTime() : (new Date(a.booking_date || a.created_at).getTime() + parseDurationToDays(a.duration) * 86400000);
            const expB = b.expires_at ? new Date(b.expires_at).getTime() : (new Date(b.booking_date || b.created_at).getTime() + parseDurationToDays(b.duration) * 86400000);
            return expB - expA;
        })[0];

        if (membershipStatus) {
            membershipStatus.textContent = 'Active';
            membershipStatus.className = 'stat-value status-verified';
            membershipStatus.style.color = '';
        }

        // Update Sidebar Badge
        if (sidebarMemberBadge) {
            sidebarMemberBadge.style.display = 'inline-flex';
        }

        // Update Profile Membership Badge
        if (profileMembershipBadge) {
            profileMembershipBadge.style.display = 'block';
            profileMembershipValue.textContent = 'Active Member';
            profileMembershipStatus.textContent = 'Active';
            profileMembershipStatus.className = 'status-badge status-verified';
            profileMembershipPlan.textContent = `${latestActive.package_name || latestActive.package} Plan`;
            
            // Show expiry date
            if (profileMembershipExpiryDate && profileMembershipExpiryRow) {
                profileMembershipExpiryRow.style.display = 'flex';
                const expiryDate = latestActive.expires_at ? new Date(latestActive.expires_at) : new Date(new Date(latestActive.booking_date || latestActive.created_at).getTime() + parseDurationToDays(latestActive.duration) * 86400000);
                profileMembershipExpiryDate.textContent = formatDate(expiryDate);
            }
        }
    } else {
        const hasVerified = totalVerified > 0;
        if (membershipStatus) {
            membershipStatus.textContent = hasVerified ? 'Expired' : 'None';
            membershipStatus.className = 'stat-value ' + (hasVerified ? 'status-rejected' : '');
            membershipStatus.style.color = '';
        }

        // Hide Sidebar Badge
        if (sidebarMemberBadge) {
            sidebarMemberBadge.style.display = 'none';
        }

        // Update Profile Membership Badge for expired/none
        if (profileMembershipBadge) {
            if (hasVerified) {
                profileMembershipBadge.style.display = 'block';
                profileMembershipValue.textContent = 'Expired Member';
                profileMembershipStatus.textContent = 'Expired';
                profileMembershipStatus.className = 'status-badge status-rejected';
                
                // Hide expiry row if expired
                if (profileMembershipExpiryRow) {
                    profileMembershipExpiryRow.style.display = 'none';
                }
                
                // Get the last verified booking to show what plan they had
                const lastVerified = [...userBookings]
                    .filter(b => b.status === 'verified')
                    .sort((a, b) => new Date(b.booking_date || b.created_at) - new Date(a.booking_date || a.created_at))[0];
                
                profileMembershipPlan.textContent = `Previous Plan: ${lastVerified.package_name || lastVerified.package}`;
            } else {
                profileMembershipBadge.style.display = 'none';
            }
        }
    }
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

// Open booking modal
function openBookingModal() {
    document.getElementById('bookingModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Close booking modal
function closeBookingModal() {
    document.getElementById('bookingModal').classList.remove('active');
    document.body.style.overflow = '';
    document.getElementById('bookingForm').reset();
    removeFile();
}

// Handle file select
function handleFileSelect(event) {
    const file = event.target.files[0];
    if (file) {
        selectedFile = file;
        const preview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const uploadArea = document.getElementById('fileUploadArea');
        
        fileName.textContent = file.name;
        preview.style.display = 'block';
        uploadArea.style.borderColor = 'var(--primary)';
        uploadArea.style.background = 'var(--glass)';
    }
}

// Remove file
function removeFile() {
    selectedFile = null;
    document.getElementById('filePreview').style.display = 'none';
    document.getElementById('receiptFile').value = '';
    document.getElementById('fileUploadArea').style.borderColor = '';
    document.getElementById('fileUploadArea').style.background = '';
}

// Submit booking
async function submitBooking(event) {
    event.preventDefault();
    
    if (!selectedFile) {
        showNotification('Please upload a payment receipt', 'warning');
        return;
    }
    
    const packageName = document.getElementById('bookingPackage').value;
    const date = document.getElementById('bookingDate').value;
    const contact = document.getElementById('bookingContact').value;
    const notes = document.getElementById('bookingNotes').value;
    
    // Validate required fields
    if (!packageName || !date || !contact) {
        showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    let originalText = '';
    const submitButton = document.querySelector('#bookingModal .btn-primary');
    
    try {
        // Show loading indicator
        originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        submitButton.disabled = true;
        
        // Upload receipt file
        const receiptUrl = await uploadReceipt(selectedFile);
        
        // Create booking data
        const bookingData = {
            package: packageName,
            date: date,
            contact: contact,
            notes: notes,
            receipt: receiptUrl
        };
        
        // Submit booking to database
        const response = await fetch('../../api/bookings/create.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(bookingData)
        });
        
        const result = await response.json();
        
        // Restore button
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
        
        if (result.success) {
            // Reload bookings from database
            await loadUserBookings();
            populateBookings();
            populatePayments();
            updateStats();
            closeBookingModal();
            
            showNotification('Booking submitted successfully! Waiting for admin verification.', 'success');
        } else {
            showNotification('Error submitting booking: ' + result.message, 'warning');
        }
    } catch (error) {
        console.error('Error submitting booking:', error);
        
        // Restore button
        if (submitButton && originalText) {
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
        
        showNotification('Error: ' + error.message, 'warning');
    }
}

// Upload receipt file
async function uploadReceipt(file) {
    const formData = new FormData();
    formData.append('receipt', file);
    
    const response = await fetch('../../api/upload/receipt.php', {
        method: 'POST',
        body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
        return result.data.url;
    } else {
        throw new Error(result.message);
    }
}

// View booking details
function viewBookingDetails(bookingId) {
    const booking = userBookings.find(b => String(b.id) === String(bookingId));
    if (!booking) return;
    
    document.getElementById('detailPackage').textContent = booking.package_name || booking.package;
    document.getElementById('detailDate').textContent = formatDate(booking.booking_date || booking.date || booking.createdAt);
    document.getElementById('detailAmount').textContent = '₱' + parseFloat(booking.amount).toFixed(2);
    
    const statusBadge = document.createElement('span');
    statusBadge.className = `status-badge status-${booking.status}`;
    statusBadge.textContent = booking.status.charAt(0).toUpperCase() + booking.status.slice(1);
    document.getElementById('detailStatus').innerHTML = '';
    document.getElementById('detailStatus').appendChild(statusBadge);
    
    const receiptUrl = booking.receipt_full_url || booking.receipt_url;
    if (receiptUrl) {
        const fixedUrl = fixReceiptUrl(receiptUrl);
        document.getElementById('detailReceipt').src = fixedUrl;
        document.getElementById('receiptSection').style.display = 'block';
        
        // Make image clickable
        const receiptImg = document.getElementById('detailReceipt');
        receiptImg.style.cursor = 'zoom-in';
        receiptImg.title = 'Click to view full image';
        receiptImg.onclick = () => window.open(fixedUrl, '_blank');
    } else {
        document.getElementById('receiptSection').style.display = 'none';
    }
    
    document.getElementById('bookingDetailsModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Close booking details modal
function closeBookingDetailsModal() {
    document.getElementById('bookingDetailsModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Update profile
function updateProfile() {
    const name = document.getElementById('profileName').value;
    const email = document.getElementById('profileEmail').value;
    const contact = document.getElementById('profileContact').value;
    const address = document.getElementById('profileAddress').value;
    
    const userData = {
        name: name,
        email: email,
        contact: contact,
        address: address
    };
    
    localStorage.setItem('userData', JSON.stringify(userData));
    
    // Update UI
    document.getElementById('userName').textContent = name;
    document.getElementById('userEmail').textContent = email;
    document.getElementById('userAvatar').textContent = getInitials(name);
    
    showNotification('Profile updated successfully!', 'success');
}

// Logout
async function logout() {
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

function selectSurveyOption(element, category, value) {
    // Remove selected class from all options in the same grid
    const grid = element.parentElement;
    grid.querySelectorAll('.option-card').forEach(card => card.classList.remove('selected'));
    
    // Add selected class to clicked card
    element.classList.add('selected');
    
    // Update survey data
    surveyData[category] = value;
    
    // Enable next button
    document.getElementById('surveyNextBtn').disabled = false;
}

function nextSurveyStep() {
    if (currentSurveyStep < 3) {
        // Move to next step
        document.querySelector(`.survey-step[data-step="${currentSurveyStep}"]`).classList.remove('active');
        currentSurveyStep++;
        document.querySelector(`.survey-step[data-step="${currentSurveyStep}"]`).classList.add('active');
        
        // Update progress bar
        const progress = (currentSurveyStep / 3) * 100;
        document.getElementById('surveyProgress').style.width = `${progress}%`;
        
        // Update button text for last step
        if (currentSurveyStep === 3) {
            document.getElementById('surveyNextBtn').innerHTML = '<span>Get My Plan</span> <i class="fas fa-check"></i>';
        }
        
        // Disable next button until option is selected for the new step
        document.getElementById('surveyNextBtn').disabled = true;
    } else {
        // Survey complete - calculate recommendation
        finishSurvey();
    }
}

function skipSurvey() {
    let userId = 'guest';
    try {
        const userData = JSON.parse(localStorage.getItem('userData'));
        if (userData) {
            userId = userData.id || userData.email || 'guest';
        }
    } catch (e) {
        console.error('Error getting user identifier for survey:', e);
    }
    
    // Set as completed even if skipped so it doesn't pop up again
    localStorage.setItem('gym_survey_completed_' + userId, 'true');
    document.getElementById('surveyModal').classList.remove('active');
    showNotification('Survey skipped. You can always view our packages in the sidebar!', 'info');
}

function finishSurvey() {
    let userId = 'guest';
    try {
        const userData = JSON.parse(localStorage.getItem('userData'));
        if (userData) {
            userId = userData.id || userData.email || 'guest';
        }
    } catch (e) {
        console.error('Error getting user identifier for survey:', e);
    }
    
    localStorage.setItem('gym_survey_completed_' + userId, 'true');
    document.getElementById('surveyModal').classList.remove('active');
    
    // Recommendation Logic based on Active Packages
    let recommendedPackage = null;
    
    // Helper to check if a package exists in our active packages list
    const getPackageByName = (name) => {
        return packagesData.find(pkg => pkg.name === name);
    };

    // Determine preferred package based on survey
    let preferredName = "";
    if (surveyData.commitment === 'long_term') {
        preferredName = "Annual Membership";
    } else if (surveyData.commitment === 'medium_term') {
        if (surveyData.goal === 'muscle_gain' || surveyData.frequency === 'daily') {
            preferredName = "3-Month Package";
        } else {
            preferredName = "Monthly Membership";
        }
    } else if (surveyData.commitment === 'short_term') {
        preferredName = "Weekly Pass";
    } else {
        preferredName = "Walk-in Pass";
    }

    // Fallback logic: if preferred isn't active, find the next best available
    recommendedPackage = getPackageByName(preferredName);
    
    if (!recommendedPackage) {
        // Fallback hierarchy if preferred is disabled
        const fallbackOrder = [
            "Monthly Membership", 
            "Weekly Pass", 
            "3-Month Package", 
            "Walk-in Pass", 
            "Annual Membership"
        ];
        
        for (const name of fallbackOrder) {
            recommendedPackage = getPackageByName(name);
            if (recommendedPackage) break;
        }
    }
    
    if (!recommendedPackage && packagesData.length > 0) {
        recommendedPackage = packagesData[0];
    }
    
    if (recommendedPackage) {
        showRecommendationModal(recommendedPackage);
    } else {
        showNotification("Thanks for completing the survey! Check out our available packages.", "info");
        showSection('packages');
    }
}

function showRecommendationModal(pkg) {
    document.getElementById('recPackageName').textContent = pkg.name;
    document.getElementById('recPackagePrice').textContent = pkg.price;
    document.getElementById('recPackageDuration').textContent = pkg.duration;
    document.getElementById('recPackageDesc').textContent = pkg.description || 'Full gym access with all facilities';
    
    const bookBtn = document.getElementById('bookRecommendedBtn');
    bookBtn.onclick = () => {
        closeRecommendationModal();
        openBookingModal(pkg.name, pkg.price);
    };
    
    setTimeout(() => {
        document.getElementById('recommendationModal').classList.add('active');
    }, 500);
}

function closeRecommendationModal() {
    document.getElementById('recommendationModal').classList.remove('active');
    // Still redirect to packages section so they can see everything
    showSection('packages');
    
    // Highlight the recommended package in the list too
    const recName = document.getElementById('recPackageName').textContent;
    setTimeout(() => {
        const packageCards = document.querySelectorAll('.package-card');
        packageCards.forEach(card => {
            const nameHeader = card.querySelector('h3');
            if (nameHeader && nameHeader.textContent === recName) {
                card.style.transform = 'scale(1.05)';
                card.style.borderColor = 'var(--primary)';
                card.style.boxShadow = '0 0 30px rgba(255, 255, 255, 0.2)';
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }, 500);
}

// Mobile menu toggle function
function toggleMobileMenu() {
    const sidebar = document.getElementById('sidebar');
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

// Show notifications
function showNotifications() {
    const pendingCount = userBookings.filter(b => b.status === 'pending').length;
    if (pendingCount > 0) {
        showNotification(`You have ${pendingCount} booking${pendingCount > 1 ? 's' : ''} pending verification`, 'info');
    } else {
        showNotification('No new notifications', 'info');
    }
}

// Show notification
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existing = document.querySelectorAll('.notification');
    existing.forEach(n => n.remove());
    
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
        max-width: 400px;
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
    // Mobile menu toggle
    const toggleBtn = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMobileMenu();
        });
        
        // Close sidebar when clicking outside
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('active') && 
                !sidebar.contains(e.target) && 
                !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('active');
                const icon = toggleBtn.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });
        
        // Close sidebar when clicking nav links on mobile
        sidebar.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('active');
                    const icon = toggleBtn.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                }
            });
        });
    }

    // File upload area click
    document.getElementById('fileUploadArea').addEventListener('click', () => {
        document.getElementById('receiptFile').click();
    });
    
    // Drag and drop
    const uploadArea = document.getElementById('fileUploadArea');
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--primary)';
        uploadArea.style.background = 'var(--glass)';
    });
    
    uploadArea.addEventListener('dragleave', () => {
        if (!selectedFile) {
            uploadArea.style.borderColor = '';
            uploadArea.style.background = '';
        }
    });
    
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        const file = e.dataTransfer.files[0];
        if (file && (file.type.startsWith('image/') || file.type === 'application/pdf')) {
            document.getElementById('receiptFile').files = e.dataTransfer.files;
            handleFileSelect({ target: { files: [file] } });
        }
    });
    
    // Close modals on outside click
    document.getElementById('bookingModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeBookingModal();
        }
    });
    
    document.getElementById('bookingDetailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeBookingDetailsModal();
        }
    });
    
    // Update data every few seconds
    setInterval(async () => {
        await loadUserBookings(); // Refresh bookings from database
        
        // Update UI components with new data
        updateStats();
        populateBookings();
        populatePayments();
        
        // Update notification count
        const pendingCount = userBookings.filter(b => b.status === 'pending').length;
        document.getElementById('notificationCount').textContent = pendingCount || '';
        document.getElementById('bookingsBadge').textContent = pendingCount || '0';
    }, 3000); // 3 seconds interval is better for performance than 1 second
}

// Add CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);
