// User Dashboard JavaScript

// Sample data - In a real app, this would come from a backend API
let userBookings = [];
let selectedFile = null;
let activeExercisesByPackage = {}; // Cache for package exercises

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

// Fetch exercises for active packages
async function loadActiveExercises() {
    const allBookedPackages = [...new Set(userBookings
        .filter(b => b.status === 'verified')
        .map(b => b.package_id)
        .filter(id => id !== null))];

    if (allBookedPackages.length === 0) return;

    for (const pkgId of allBookedPackages) {
        try {
            const response = await fetch(`../../api/packages/get-exercises.php?package_id=${pkgId}`);
            const data = await response.json();
            if (data.success) {
                activeExercisesByPackage[pkgId] = data.data;
            }
        } catch (error) {
            console.error(`Error loading exercises for package ${pkgId}:`, error);
        }
    }
}

// Randomize array with seed string
function shuffleWithSeed(array, seedString) {
    // Better hashing for seed (JS implementation of MurmurHash-style)
    let h1 = 0xdeadbeef, h2 = 0x41c6ce57;
    for (let i = 0, ch; i < seedString.length; i++) {
        ch = seedString.charCodeAt(i);
        h1 = Math.imul(h1 ^ ch, 2654435761);
        h2 = Math.imul(h2 ^ ch, 1597334677);
    }
    h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507);
    h1 ^= Math.imul(h2 ^ (h2 >>> 13), 3266489909);
    h2 = Math.imul(h2 ^ (h2 >>> 16), 2246822507);
    h2 ^= Math.imul(h1 ^ (h1 >>> 13), 3266489909);
    
    let seed = (h1 >>> 0) + (h2 >>> 0);
    const shuffled = [...array];
    
    // Mulberry32 seeded RNG
    const mulberry32 = (a) => {
        return function() {
          let t = a += 0x6D2B79F5;
          t = Math.imul(t ^ t >>> 15, t | 1);
          t ^= t + Math.imul(t ^ t >>> 7, t | 61);
          return ((t ^ t >>> 14) >>> 0) / 4294967296;
        }
    };

    const nextRand = mulberry32(seed);

    for (let i = shuffled.length - 1; i > 0; i--) {
        const j = Math.floor(nextRand() * (i + 1));
        [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
    }
    return shuffled;
}

// Get a random label and focus category from the exercises in the package based on a seed
function getSeededWorkout(dateStr, pkgId, exercises = []) {
    const categoryLabels = {
        'Chest': "🎯 Chest Focus",
        'Back': "🦅 Back Day",
        'Legs': "🦵 Leg Day",
        'Shoulders': "🛡️ Shoulder Press",
        'Arms': "🦾 Arms & Biceps",
        'Core': "💪 Core Strength",
        'Cardio': "🔥 Cardio Burn",
        'Full Body': "⚡ Full Body"
    };

    // Fallback if no specific exercises/categories found
    const defaultLabels = [
        { title: "🏋️ Upper Body", category: null },
        { title: "🦵 Leg Day", category: 'Legs' },
        { title: "🔥 Cardio Session", category: 'Cardio' },
        { title: "💪 Core Workout", category: 'Core' },
        { title: "⚡ Full Body", category: 'Full Body' },
        { title: "💥 Strength Training", category: null },
        { title: "🏆 Fitness Routine", category: null }
    ];

    let availableWorkouts = [];
    
    if (exercises && exercises.length > 0) {
        // Get unique categories present in the package
        const categories = [...new Set(exercises.map(ex => ex.category))];
        availableWorkouts = categories.map(cat => ({
            title: categoryLabels[cat] || `🏋️ ${cat} Routine`,
            category: cat
        }));
    }

    if (availableWorkouts.length === 0) {
        availableWorkouts = defaultLabels;
    }
    
    // Better hashing for seed
    let h1 = 0xdeadbeef, h2 = 0x41c6ce57;
    const combined = dateStr + pkgId;
    for (let i = 0, ch; i < combined.length; i++) {
        ch = combined.charCodeAt(i);
        h1 = Math.imul(h1 ^ ch, 2654435761);
        h2 = Math.imul(h2 ^ ch, 1597334677);
    }
    let seed = (h1 >>> 0) + (h2 >>> 0);
    
    const t = (seed += 0x6D2B79F5);
    const rand = ((Math.imul(t ^ t >>> 15, t | 1) ^ t >>> 14) >>> 0) / 4294967296;
    
    return availableWorkouts[Math.floor(rand * availableWorkouts.length)];
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
        await loadActiveExercises(); // Fetch exercises for active packages
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

// ===== User Calendar (Subscriptions) =====
let userCalendar = null;
let userViewMode = 'table';

// Helper to format date to YYYY-MM-DD local
function formatDateISO(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

async function getUserCalendarEvents() {
    const events = [];
    
    // 1. Add Bookings and Subscription Periods
    userBookings.forEach(b => {
        const startStr = b.booking_date || b.date || b.created_at;
        if (!startStr) return;
        
        const pkgName = b.package_name || b.package || 'Gym Session';
        
        // Main booking event
        events.push({
            id: `booking-${b.id}`,
            title: `${pkgName} (${b.status.toUpperCase()})`,
            start: startStr,
            allDay: true,
            classNames: [`event-status-${b.status || 'pending'}`],
            extendedProps: { ...b, type: 'booking' },
            color: b.status === 'verified' ? '#22c55e' : (b.status === 'pending' ? '#f59e0b' : '#ef4444')
        });
        
        // Background highlight for verified multi-day packages
        if (b.status === 'verified' && b.duration) {
            const days = parseDurationToDays(b.duration);
            if (days > 1) {
                const parts = startStr.split('-');
                const startDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                const endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + days);
                
                events.push({
                    id: `period-${b.id}`,
                    start: startStr,
                    end: formatDateISO(endDate),
                    display: 'background',
                    color: 'rgba(34, 197, 94, 0.08)',
                    allDay: true
                });
            }
        }
    });

    // 2. Fetch and add actual trainer-scheduled sessions for all verified bookings
    const verifiedBookings = userBookings.filter(b => b.status === 'verified');
    for (const b of verifiedBookings) {
        try {
            const resp = await fetch(`../../api/trainers/get-sessions.php?booking_id=${b.id}`);
            const sessions = await resp.json();
            if (Array.isArray(sessions)) {
                sessions.forEach(s => {
                    events.push({
                        ...s,
                        id: `session-${s.id}`,
                        title: `${s.title}${s.type === 'rest_day' ? '' : ' (Coach)'}`,
                        extendedProps: { ...s, type: 'session' }
                    });
                });
            }
        } catch (e) { console.error('Error fetching sessions for calendar:', e); }
    }
    
    return events;
}

async function initUserCalendar() {
    const el = document.getElementById('userCalendar');
    if (!el || userCalendar) return;
    
    const events = await getUserCalendarEvents();
    
    userCalendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        height: 'auto',
        events: events,
        eventClick: (info) => {
            const type = info.event.extendedProps.type;
            if (type === 'session') {
                showSessionDetails(info.event);
            } else if (type === 'booking' || type === 'expiry') {
                const bId = info.event.id.split('-')[1];
                viewBookingDetails(bId);
            }
        }
    });
    userCalendar.render();
}

async function updateUserCalendarEvents() {
    if (userCalendar) {
        const events = await getUserCalendarEvents();
        userCalendar.removeAllEvents();
        userCalendar.addEventSource(events);
    }
}

async function toggleUserView(mode) {
    userViewMode = mode;
    const tableContainer = document.querySelector('#bookingsSection .table-container');
    const calendarView = document.getElementById('userCalendarView');
    const tableBtn = document.getElementById('userTableViewBtn');
    const calBtn = document.getElementById('userCalendarViewBtn');
    if (!tableContainer || !calendarView || !tableBtn || !calBtn) return;
    
    if (mode === 'calendar') {
        tableContainer.style.display = 'none';
        calendarView.style.display = 'block';
        tableBtn.classList.remove('active');
        calBtn.classList.add('active');
        if (!userCalendar) {
            await initUserCalendar();
        } else {
            userCalendar.render();
            await updateUserCalendarEvents();
        }
    } else {
        tableContainer.style.display = 'block';
        calendarView.style.display = 'none';
        tableBtn.classList.add('active');
        calBtn.classList.remove('active');
    }
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
    if (!grid) return;
    grid.innerHTML = '';
    
    packagesData.forEach(pkg => {
        // Check if this package is currently active for the user
        const activeBooking = userBookings.find(b => 
            String(b.package_id) === String(pkg.id) && 
            b.status === 'verified' && 
            (!b.expires_at || new Date(b.expires_at) > new Date())
        );
        const isActive = !!activeBooking;

        const packageCard = document.createElement('div');
        packageCard.className = `package-card-large ${isActive ? 'active-plan' : ''}`;
        packageCard.innerHTML = `
            <div class="package-header">
                <div>
                    <h3 style="margin-bottom: 4px;">${pkg.name}</h3>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span class="package-tag">${pkg.tag || 'Standard'}</span>
                        <span style="font-size: 0.65rem; color: var(--primary); font-weight: 700;">
                            <i class="fas fa-bullseye"></i> ${pkg.goal || 'General Fitness'}
                        </span>
                        ${isActive ? `
                        <span style="font-size: 0.65rem; padding: 2px 8px; background: rgba(34, 197, 94, 0.1); color: #22c55e; border-radius: 4px; border: 1px solid rgba(34, 197, 94, 0.2); font-weight: 800; text-transform: uppercase;">
                            <i class="fas fa-check-circle"></i> Currently Active
                        </span>
                        ` : ''}
                    </div>
                </div>
            </div>
            <p class="package-description">${pkg.description || 'Full gym access with all facilities'}</p>
            <div class="package-details">
                <div class="package-detail-item">
                    <i class="fas fa-clock"></i>
                    <span>${pkg.duration}</span>
                </div>
                <div class="package-detail-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Full gym access</span>
                </div>
                ${pkg.is_trainer_assisted ? `
                <div class="package-detail-item" style="color: var(--primary); font-weight: 700;">
                    <i class="fas fa-user-tie"></i>
                    <span>Trainer Assisted</span>
                </div>
                ` : ''}
            </div>
            <div class="package-footer">
                <div class="package-price-large">${pkg.price}</div>
                <div class="package-btn-group">
                    <button class="btn btn-exercise" onclick="${isActive ? `viewBookingDetails(${activeBooking.id})` : `previewPackageHub(${pkg.id})`}" title="${isActive ? 'View My Hub' : 'View Package Details'}">
                        <i class="fas ${isActive ? 'fa-th-large' : 'fa-list-ul'}"></i>
                    </button>
                    ${isActive ? `
                    <button class="btn btn-book" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); cursor: default;" disabled>
                        <i class="fas fa-check"></i>
                        <span>Subscribed</span>
                    </button>
                    ` : `
                    <button class="btn btn-book" onclick="selectPackageForBooking('${pkg.name}')">
                        <i class="fas fa-calendar-plus"></i>
                        <span style="white-space: nowrap;">Book Now</span>
                    </button>
                    `}
                </div>
            </div>
        `;
        grid.appendChild(packageCard);
    });
}

// Preview a package Hub (before booking)
async function previewPackageHub(packageId) {
    const pkg = packagesData.find(p => p.id === packageId);
    if (!pkg) return;

    const modal = document.getElementById('bookingDetailsModal');
    if (!modal) return;

    // Set modal to "Preview" mode
    document.getElementById('detailRef').textContent = `PREVIEW: ${pkg.name}`;
    document.getElementById('detailPackage').querySelector('span').textContent = pkg.name;
    document.getElementById('detailDate').querySelector('span').textContent = 'Starts upon booking';
    document.getElementById('detailAmount').querySelector('span').textContent = pkg.price;
    document.getElementById('detailContact').querySelector('span').textContent = 'N/A (Preview)';
    
    // Status Badge
    document.getElementById('detailStatus').innerHTML = '<span class="status-badge status-pending">Preview</span>';
    
    // Trainer Container
    const trainerContainer = document.getElementById('detailTrainerContainer');
    trainerContainer.style.display = pkg.is_trainer_assisted ? 'block' : 'none';
    if (pkg.is_trainer_assisted) {
        document.getElementById('detailTrainer').querySelector('span').textContent = 'Assigned after booking';
    }

    // Notes
    document.getElementById('detailNotesSection').style.display = 'block';
    document.getElementById('detailNotes').textContent = pkg.description || 'No additional details.';
    
    // Receipt (Hide for preview)
    document.getElementById('receiptSection').style.display = 'none';

    // Load Tabs Content for Preview
    switchModalTab('info');
    
    // 1. Exercise Plan Preview
    const planContent = document.getElementById('modalPlanContent');
    planContent.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading default plan...</div>';
    
    try {
        const response = await fetch(`../../api/packages/get-exercises.php?package_id=${packageId}`);
        const data = await response.json();
        if (data.success && data.data.length > 0) {
            planContent.innerHTML = data.data.map(ex => `
                <div class="exercise-item" style="display: flex; gap: 20px; padding: 20px; border-bottom: 1px solid var(--dark-border); align-items: center;">
                    <img src="${ex.image_url || '../../assets/img/exercise-placeholder.jpg'}" style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover; background: #1a1a1a;">
                    <div style="flex: 1;">
                        <h4 style="margin-bottom: 4px; font-weight: 700; color: white;">${ex.name}</h4>
                        <div style="display: flex; gap: 15px; font-size: 0.85rem; color: var(--primary);">
                            <span><i class="fas fa-redo"></i> ${ex.sets} Sets</span>
                            <span><i class="fas fa-running"></i> ${ex.reps} Reps</span>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            planContent.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">This package has general gym access.</div>';
        }
    } catch (e) {
        planContent.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;">Failed to load plan.</div>';
    }

    // 2. Calendar Preview (Sample)
    const calendarEl = document.getElementById('modalCalendar');
    if (modalCalendar) modalCalendar.destroy();
    
    modalCalendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth' },
        themeSystem: 'standard',
        events: [
            { title: 'Workout A', start: new Date().toISOString().split('T')[0], color: '#3b82f6' },
            { title: 'Workout B', start: new Date(Date.now() + 86400000).toISOString().split('T')[0], color: '#3b82f6' },
            { title: 'Rest Day', start: new Date(Date.now() + 172800000).toISOString().split('T')[0], color: '#ef4444' }
        ]
    });
    modalCalendar.render();

    // 3. Diet & Tips (Sample for Preview)
    document.getElementById('modalDietContent').innerHTML = `
        <div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">
            <i class="fas fa-utensils" style="font-size: 2.5rem; opacity: 0.1; margin-bottom: 15px; display: block;"></i>
            <h4 style="color: white; margin-bottom: 8px;">Personalized Nutrition</h4>
            <p style="font-size: 0.9rem; line-height: 1.6;">Once you book this package, your coach will provide a daily meal plan tailored to your goal of <strong>${pkg.goal || 'General Fitness'}</strong>.</p>
        </div>
    `;
    
    document.getElementById('modalTipsContent').innerHTML = `
        <div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">
            <i class="fas fa-lightbulb" style="font-size: 2.5rem; opacity: 0.1; margin-bottom: 15px; display: block;"></i>
            <h4 style="color: white; margin-bottom: 8px;">Professional Guidance</h4>
            <p style="font-size: 0.9rem; line-height: 1.6;">Your assigned trainer will share daily tips on form, hydration, and recovery through this portal.</p>
        </div>
    `;

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Show exercise plan for a package
async function showExercisePlan(packageId, packageName, date = null, focusCategory = null, whoRationale = null) {
    const modal = document.getElementById('exercisePlanModal');
    const content = document.getElementById('exercisePlanContent');
    const title = document.getElementById('exercisePlanTitle');
    const subtitle = document.getElementById('exercisePlanSubtitle');
    
    if (!modal || !content) return;
    
    title.textContent = date ? `Workout for ${formatDate(date)}` : `${packageName} - Exercise Plan`;
    subtitle.textContent = date ? `Daily focus: ${focusCategory || 'General'} workout` : `A curated list of exercises and equipment for this membership`;
    
    // Show loading state
    content.innerHTML = '<div class="no-exercises"><i class="fas fa-spinner fa-spin"></i><p>Loading exercise plan...</p></div>';
    modal.classList.add('active');
    
    try {
        const response = await fetch(`../../api/packages/get-exercises.php?package_id=${packageId}`);
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            let exercises = data.data;
            
            // Filter by focus category if provided
            if (focusCategory) {
                const filtered = exercises.filter(ex => ex.category === focusCategory);
                // If we found exercises for this focus, use them
                if (filtered.length > 0) {
                    exercises = filtered;
                }
                // If no exercises for this specific focus, fallback to full list
            }
            
            // Randomize if date is provided
            if (date) {
                exercises = shuffleWithSeed(exercises, date + packageId);
                // Limit to 6-8 exercises if there are many
                if (exercises.length > 8) {
                    exercises = exercises.slice(0, 8);
                }
            }
            
            content.innerHTML = '';

            // Add WHO rationale if provided
            if (whoRationale) {
                const rationaleBox = document.createElement('div');
                rationaleBox.style.cssText = `
                    background: rgba(34, 197, 94, 0.1);
                    border: 1px solid rgba(34, 197, 94, 0.3);
                    padding: 20px;
                    border-radius: 12px;
                    margin-bottom: 25px;
                    display: flex;
                    align-items: center;
                    gap: 15px;
                `;
                rationaleBox.innerHTML = `
                    <div style="font-size: 1.5rem; color: #22c55e;"><i class="fas fa-info-circle"></i></div>
                    <div style="font-size: 0.95rem; color: #fff; line-height: 1.5;">
                        <strong style="color: #22c55e; display: block; margin-bottom: 4px; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px;">WHO (World Health Organization) Recommendation</strong>
                        ${whoRationale}
                    </div>
                `;
                content.appendChild(rationaleBox);
            }

            exercises.forEach(ex => {
                const item = document.createElement('div');
                item.className = 'exercise-item';
                
                // Determine icon based on category
                let icon = 'dumbbell';
                if (ex.category === 'Cardio') icon = 'running';
                if (ex.category === 'Core') icon = 'user-ninja';
                if (ex.category === 'Legs') icon = 'walking';
                
                item.innerHTML = `
                    <div class="exercise-icon">
                        <i class="fas fa-${icon}"></i>
                    </div>
                    <div class="exercise-info">
                        <span class="exercise-category">${ex.category}</span>
                        <h4>${ex.name}</h4>
                        
                        ${ex.image_url ? `
                            <img src="${ex.image_url}" alt="${ex.name}" class="exercise-image" 
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <div class="exercise-image-fallback" style="display: none;">
                                <i class="fas fa-image" style="font-size: 2rem;"></i>
                            </div>
                        ` : ''}
                        
                        <p class="exercise-description">${ex.description}</p>
                        <div class="exercise-details">
                            <div class="exercise-detail">
                                <i class="fas fa-redo"></i>
                                <span>${ex.sets} Sets × ${ex.reps}</span>
                            </div>
                            <div class="exercise-detail">
                                <i class="fas fa-tools"></i>
                                <span><strong>Equipment:</strong> ${ex.equipment_name || 'No equipment'}</span>
                            </div>
                        </div>
                        ${ex.notes ? `<p class="exercise-notes" style="margin-top: 10px; font-size: 0.85rem; font-style: italic; color: var(--dark-text-secondary);"><i class="fas fa-sticky-note"></i> Note: ${ex.notes}</p>` : ''}
                    </div>
                `;
                content.appendChild(item);
            });
        } else {
            content.innerHTML = `
                <div class="no-exercises">
                    <i class="fas fa-dumbbell"></i>
                    <p>No specific exercises assigned to this package yet.</p>
                    <p style="font-size: 0.9rem;">General gym access is included with all our packages.</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error fetching exercises:', error);
        content.innerHTML = '<div class="no-exercises"><i class="fas fa-exclamation-triangle"></i><p>Failed to load exercise plan.</p></div>';
    }
}

function closeExercisePlanModal() {
    const modal = document.getElementById('exercisePlanModal');
    if (modal) {
        modal.classList.remove('active');
    }
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
    const totalVerified = userBookings.filter(b => b.status === 'verified').length;
    
    document.getElementById('activeBookingsCount').textContent = activeVerifiedBookings.length;
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
    const statTrainer = document.getElementById('statTrainer');

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

        if (statTrainer) {
            statTrainer.textContent = latestActive.trainer_name || 'Not Assigned';
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
        
        if (statTrainer) {
            statTrainer.textContent = 'None';
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

// Format date for display
function formatDate(dateString) {
    if (!dateString) return '';
    
    // Handle YYYY-MM-DD specifically to avoid timezone shifts
    const parts = String(dateString).split('-');
    if (parts.length === 3 && parts[0].length === 4) {
        const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }
    
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
        // Validate file size (max 5MB)
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if (file.size > maxSize) {
            showNotification('Image size is too huge! Maximum allowed size is 5MB.', 'warning');
            event.target.value = ''; // Clear the input
            return;
        }

        selectedFile = file;
        const preview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const uploadArea = document.getElementById('fileUploadArea');
        
        fileName.textContent = file.name;
        if (preview) preview.style.display = 'block';
        if (uploadArea) {
            uploadArea.style.borderColor = 'var(--primary)';
            uploadArea.style.background = 'var(--glass)';
            uploadArea.style.display = 'none';
        }
    }
}

// Remove file
function removeFile() {
    selectedFile = null;
    const preview = document.getElementById('filePreview');
    const input = document.getElementById('receiptFile');
    const uploadArea = document.getElementById('fileUploadArea');
    if (preview) preview.style.display = 'none';
    if (input) input.value = '';
    if (uploadArea) {
        uploadArea.style.borderColor = '';
        uploadArea.style.background = '';
        uploadArea.style.display = 'block';
    }
}

// Submit booking
async function submitBooking(event) {
    event.preventDefault();
    
    if (!selectedFile) {
        showNotification('Please upload a payment receipt', 'warning');
        return;
    }

    // Double check file size before uploading
    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if (selectedFile.size > maxSize) {
        showNotification('Image size is too huge! Maximum allowed size is 5MB.', 'warning');
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

let trainingCalendar = null;

async function initTrainingCalendar(bookingId) {
    const calendarEl = document.getElementById('trainingCalendar');
    if (!calendarEl) return;
    
    document.getElementById('trainingCalendarSection').style.display = 'block';
    
    if (trainingCalendar) {
        trainingCalendar.destroy();
    }
    
    trainingCalendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek'
        },
        themeSystem: 'standard',
        events: '../../api/trainers/get-sessions.php?booking_id=' + bookingId,
        eventDidMount: function(info) {
            if (info.event.extendedProps.type === 'rest_day') {
                info.el.style.backgroundColor = '#ef4444';
                info.el.style.borderColor = '#ef4444';
            }
        },
        eventClick: function(info) {
            showSessionDetails(info.event);
        }
    });
    
    trainingCalendar.render();
}

function showSessionDetails(event) {
    const props = event.extendedProps;
    let content = `
        <div style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: var(--primary); margin: 0;">${event.title}</h3>
                <span class="status-badge" style="background: ${props.type === 'rest_day' ? 'rgba(239, 68, 68, 0.1)' : 'rgba(59, 130, 246, 0.1)'}; color: ${props.type === 'rest_day' ? '#ef4444' : '#3b82f6'}; border: 1px solid currentColor;">
                    ${props.type.replace('_', ' ').toUpperCase()}
                </span>
            </div>
            
            <div style="margin-bottom: 20px; color: var(--dark-text-secondary); font-size: 0.9rem;">
                <p><i class="far fa-calendar-alt"></i> ${event.start.toLocaleDateString(undefined, {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'})}</p>
                ${!event.allDay ? `<p><i class="far fa-clock"></i> ${event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>` : ''}
            </div>
            
            ${props.exercise_names && props.exercise_names.length > 0 ? `
                <div style="margin-bottom: 20px;">
                    <h4 style="color: white; margin-bottom: 12px; font-size: 0.95rem;"><i class="fas fa-dumbbell"></i> Exercises for today:</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        ${props.exercise_names.map(ex => `<span style="background: var(--glass); padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; border: 1px solid var(--dark-border);">${ex}</span>`).join('')}
                    </div>
                </div>
            ` : ''}
            
            ${props.notes ? `
                <div style="background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; border-left: 3px solid var(--primary);">
                    <h4 style="color: white; margin-bottom: 8px; font-size: 0.9rem;">Coach's Notes:</h4>
                    <p style="font-size: 0.9rem; color: var(--dark-text-secondary); line-height: 1.5; margin: 0;">${props.notes}</p>
                </div>
            ` : ''}
        </div>
    `;

    // Create a temporary modal or use an existing one
    const modal = document.createElement('div');
    modal.className = 'modal-overlay active';
    modal.style.zIndex = '10000';
    modal.innerHTML = `
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Session Details</h3>
                <button class="close-modal" onclick="this.closest('.modal-overlay').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">${content}</div>
            <div class="modal-footer" style="text-align: right; padding: 16px 24px; border-top: 1px solid var(--dark-border);">
                <button class="btn btn-primary" onclick="this.closest('.modal-overlay').remove()">Close</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

// Initialize Training Calendar
let modalCalendar = null;

async function initModalCalendar(bookingId) {
    const calendarEl = document.getElementById('modalCalendar');
    if (!calendarEl) return;
    
    if (modalCalendar) {
        modalCalendar.destroy();
    }
    
    modalCalendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek'
        },
        themeSystem: 'standard',
        events: '../../api/trainers/get-sessions.php?booking_id=' + bookingId,
        eventClick: function(info) {
            showSessionDetails(info.event);
        },
        eventDidMount: function(info) {
            if (info.event.extendedProps.type === 'rest_day') {
                info.el.style.backgroundColor = '#ef4444';
                info.el.style.borderColor = '#ef4444';
            }
        }
    });
    
    modalCalendar.render();
}

function switchModalTab(tabId) {
    // Update buttons
    document.querySelectorAll('.modal-tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('onclick').includes(`'${tabId}'`)) {
            btn.classList.add('active');
        }
    });
    
    // Update content
    document.querySelectorAll('.modal-tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    const targetContent = document.getElementById(`modalTab${tabId.charAt(0).toUpperCase() + tabId.slice(1)}`);
    if (targetContent) targetContent.classList.add('active');
    
    // Special handling for specific tabs
    if (tabId === 'calendar' && modalCalendar) {
        setTimeout(() => modalCalendar.updateSize(), 100);
    }
}

async function loadModalPlan(bookingId) {
    const content = document.getElementById('modalPlanContent');
    try {
        const response = await fetch(`../../api/trainers/get-member-plan.php?booking_id=${bookingId}`);
        const data = await response.json();
        
        if (data.success && data.data.exercises.length > 0) {
            content.innerHTML = data.data.exercises.map(ex => `
                <div class="exercise-item" style="display: flex; gap: 20px; padding: 20px; border-bottom: 1px solid var(--dark-border); align-items: center;">
                    <img src="${ex.image_url || '../../assets/img/exercise-placeholder.jpg'}" style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover; background: #1a1a1a;">
                    <div style="flex: 1;">
                        <h4 style="margin-bottom: 4px; font-weight: 700; color: white;">${ex.name}</h4>
                        <div style="display: flex; gap: 15px; font-size: 0.85rem; color: var(--primary);">
                            <span><i class="fas fa-redo"></i> ${ex.sets} Sets</span>
                            <span><i class="fas fa-running"></i> ${ex.reps} Reps</span>
                        </div>
                        ${ex.notes ? `<p style="margin-top: 8px; font-size: 0.85rem; color: var(--dark-text-secondary); font-style: italic;"><i class="fas fa-sticky-note"></i> ${ex.notes}</p>` : ''}
                    </div>
                </div>
            `).join('');
        } else {
            content.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">Your coach hasn\'t assigned specific exercises to this plan yet.</div>';
        }
    } catch (error) {
        content.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;">Failed to load plan.</div>';
    }
}

async function loadModalDiet(memberId) {
    const content = document.getElementById('modalDietContent');
    try {
        const response = await fetch(`../../api/trainers/get-food.php?member_id=${memberId}`);
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            content.innerHTML = data.data.map(f => `
                <div style="padding: 20px; border-bottom: 1px solid var(--dark-border); border-left: 4px solid var(--info); background: rgba(255,255,255,0.02); margin-bottom: 12px; border-radius: 0 8px 8px 0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                        <span class="status-badge" style="background: rgba(59, 130, 246, 0.1); color: var(--info); border: 1px solid rgba(59, 130, 246, 0.2); text-transform: uppercase; font-size: 0.7rem; font-weight: 800; padding: 4px 10px;">${f.meal_type}</span>
                        ${f.calories ? `<span style="font-size: 0.85rem; font-weight: 700; color: white;"><i class="fas fa-fire"></i> ${f.calories} kcal</span>` : ''}
                    </div>
                    <p style="font-size: 0.95rem; color: var(--dark-text-secondary); line-height: 1.6; margin: 0; background: rgba(0,0,0,0.2); padding: 12px; border-radius: 8px;">${f.food_items}</p>
                    <div style="font-size: 0.75rem; color: #555; text-align: right; margin-top: 12px;">
                        <i class="far fa-calendar-alt"></i> ${formatDate(f.created_at)}
                    </div>
                </div>
            `).join('');
        } else {
            content.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);"><i class="fas fa-utensils" style="font-size: 2rem; opacity: 0.2; margin-bottom: 12px; display: block;"></i><p>No nutrition plan assigned yet.</p></div>';
        }
    } catch (error) {
        content.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;">Failed to load nutrition plan.</div>';
    }
}

async function loadModalTips(memberId) {
    const content = document.getElementById('modalTipsContent');
    try {
        const response = await fetch(`../../api/trainers/get-tips.php?member_id=${memberId}`);
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            content.innerHTML = data.data.map(t => `
                <div style="padding: 20px; border-bottom: 1px solid var(--dark-border); border-left: 4px solid var(--warning); background: rgba(255,255,255,0.02); margin-bottom: 12px; border-radius: 0 8px 8px 0;">
                    <p style="font-size: 0.95rem; color: white; line-height: 1.6; margin-bottom: 12px;">${t.tip_text}</p>
                    <div style="font-size: 0.75rem; color: var(--dark-text-secondary); text-align: right;">
                        <i class="far fa-clock"></i> ${formatDate(t.created_at)}
                    </div>
                </div>
            `).join('');
        } else {
            content.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);"><i class="fas fa-lightbulb" style="font-size: 2rem; opacity: 0.2; margin-bottom: 12px; display: block;"></i><p>No tips shared by your coach yet.</p></div>';
        }
    } catch (error) {
        content.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;">Failed to load tips.</div>';
    }
}

// View booking details
function viewBookingDetails(bookingId) {
    const booking = userBookings.find(b => String(b.id) === String(bookingId));
    if (!booking) return;
    
    // Update basic info
    document.getElementById('detailRef').textContent = `REF: #${String(booking.id).padStart(6, '0')}`;
    document.getElementById('detailPackage').querySelector('span').textContent = booking.package_name || booking.package;
    document.getElementById('detailDate').querySelector('span').textContent = formatDate(booking.booking_date || booking.date || booking.createdAt);
    document.getElementById('detailAmount').querySelector('span').textContent = '₱' + parseFloat(booking.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('detailContact').querySelector('span').textContent = booking.contact || 'No contact provided';
    
    // Status Badge
    const statusBadge = document.createElement('span');
    statusBadge.className = `status-badge status-${booking.status}`;
    statusBadge.textContent = booking.status.charAt(0).toUpperCase() + booking.status.slice(1);
    document.getElementById('detailStatus').innerHTML = '';
    document.getElementById('detailStatus').appendChild(statusBadge);
    
    // Handle Expiry
    const expiryContainer = document.getElementById('detailExpiryContainer');
    const expiryValue = document.getElementById('detailExpiry').querySelector('span');
    if (booking.status === 'verified' && booking.duration) {
        expiryContainer.style.display = 'block';
        const expiryDate = booking.expires_at ? new Date(booking.expires_at) : new Date(new Date(booking.booking_date || booking.created_at).getTime() + parseDurationToDays(booking.duration) * 86400000);
        expiryValue.textContent = formatDate(expiryDate);
    } else {
        expiryContainer.style.display = 'none';
    }

    // Handle Trainer
    const trainerContainer = document.getElementById('detailTrainerContainer');
    const trainerValue = document.getElementById('detailTrainer').querySelector('span');
    const trainerActions = document.getElementById('trainerActions');
    
    if (booking.trainer_name) {
        trainerContainer.style.display = 'block';
        trainerValue.textContent = booking.trainer_name;
        
        // Store current booking ID for plan/progress viewing
        currentViewingBookingId = booking.id;
        
        // Load all data for the Hub
        initModalCalendar(booking.id);
        loadModalPlan(booking.id);
        loadModalDiet(booking.userId || booking.user_id || userData.id);
        loadModalTips(booking.userId || booking.user_id || userData.id);
        
        // Reset to Info tab
        switchModalTab('info');
    } else {
        trainerContainer.style.display = 'none';
    }

    // Handle Notes
    const notesSection = document.getElementById('detailNotesSection');
    if (booking.notes && booking.notes.trim() !== '') {
        notesSection.style.display = 'block';
        document.getElementById('detailNotes').textContent = booking.notes;
    } else {
        notesSection.style.display = 'none';
    }
    
    // Handle Receipt
    const receiptUrl = booking.receipt_full_url || booking.receipt_url;
    if (receiptUrl) {
        const fixedUrl = fixReceiptUrl(receiptUrl);
        document.getElementById('detailReceipt').src = fixedUrl;
        document.getElementById('receiptSection').style.display = 'block';
        
        // Make image clickable
        const receiptWrapper = document.getElementById('receiptImgWrapper');
        receiptWrapper.onclick = () => window.open(fixedUrl, '_blank');
    } else {
        document.getElementById('receiptSection').style.display = 'none';
    }
    
    document.getElementById('bookingDetailsModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

let currentViewingBookingId = null;

async function viewMyPlan() {
    if (!currentViewingBookingId) return;
    
    const modal = document.getElementById('exercisePlanModal');
    const title = document.getElementById('exercisePlanTitle');
    const subtitle = document.getElementById('exercisePlanSubtitle');
    const content = document.getElementById('exercisePlanContent');
    
    try {
        content.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading your customized plan...</div>';
        modal.classList.add('active');
        
        const response = await fetch(`../../api/trainers/get-member-plan.php?booking_id=${currentViewingBookingId}`);
        const data = await response.json();
        
        if (data.success) {
            const plan = data.data;
            title.textContent = `Exercise Plan: ${plan.package_name}`;
            subtitle.textContent = plan.is_customized ? 'Personalized for you by your trainer' : 'Standard package routine';
            
            if (plan.exercises.length === 0) {
                content.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">Your trainer hasn\'t assigned specific exercises to this plan yet.</div>';
                return;
            }
            
            content.innerHTML = plan.exercises.map(ex => `
                <div class="exercise-item" style="display: flex; gap: 20px; padding: 20px; border-bottom: 1px solid var(--dark-border); align-items: center;">
                    <img src="${ex.image_url || '../../assets/img/exercise-placeholder.jpg'}" style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover; background: #1a1a1a;">
                    <div style="flex: 1;">
                        <h4 style="margin-bottom: 4px; font-weight: 700;">${ex.name}</h4>
                        <div style="display: flex; gap: 15px; font-size: 0.85rem; color: var(--primary);">
                            <span><i class="fas fa-redo"></i> ${ex.sets} Sets</span>
                            <span><i class="fas fa-running"></i> ${ex.reps} Reps</span>
                        </div>
                        ${ex.notes ? `<p style="margin-top: 8px; font-size: 0.85rem; color: var(--dark-text-secondary); font-style: italic;"><i class="fas fa-sticky-note"></i> ${ex.notes}</p>` : ''}
                    </div>
                </div>
            `).join('');
        } else {
            content.innerHTML = `<div style="text-align: center; padding: 40px; color: #ef4444;">${data.message}</div>`;
        }
    } catch (error) {
        console.error('Error loading plan:', error);
        content.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;">Failed to load exercise plan.</div>';
    }
}

async function viewMyProgress() {
    if (!currentViewingBookingId) return;
    
    const modal = document.getElementById('myProgressModal');
    const content = document.getElementById('myProgressContent');
    
    try {
        content.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading progress history...</div>';
        modal.classList.add('active');
        
        const response = await fetch(`../../api/trainers/get-progress-history.php?booking_id=${currentViewingBookingId}`);
        const data = await response.json();
        
        if (data.success) {
            if (data.data.length === 0) {
                content.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">No progress has been logged for this subscription yet.</div>';
                return;
            }
            
            content.innerHTML = data.data.map(log => `
                <div style="padding: 20px; border-bottom: 1px solid var(--dark-border); border-left: 4px solid var(--primary); background: rgba(255,255,255,0.02); margin-bottom: 12px; border-radius: 0 8px 8px 0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <strong style="color: var(--primary);"><i class="fas fa-calendar-alt"></i> ${formatDate(log.logged_at)}</strong>
                        ${log.weight ? `<span style="font-weight: 800; color: #fff;"><i class="fas fa-weight"></i> ${log.weight} kg</span>` : ''}
                    </div>
                    <p style="font-size: 0.95rem; line-height: 1.6; color: var(--dark-text-secondary);">${log.remarks || 'No remarks provided.'}</p>
                    <div style="margin-top: 12px; font-size: 0.75rem; color: #555; text-align: right;">Logged by: Coach ${log.trainer_name}</div>
                </div>
            `).join('');
        } else {
            content.innerHTML = `<div style="text-align: center; padding: 40px; color: #ef4444;">${data.message}</div>`;
        }
    } catch (error) {
        console.error('Error loading progress:', error);
        content.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;">Failed to load progress history.</div>';
    }
}

function closeMyProgressModal() {
    document.getElementById('myProgressModal').classList.remove('active');
}

// Close booking details modal
function closeBookingDetailsModal() {
    document.getElementById('bookingDetailsModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Update profile
async function updateProfile() {
    const name = document.getElementById('profileName').value.trim();
    const email = document.getElementById('profileEmail').value.trim();
    const contact = document.getElementById('profileContact').value.trim();
    const address = document.getElementById('profileAddress').value.trim();
    const updateBtn = document.getElementById('updateProfileBtn');
    
    if (!name) {
        showNotification('Name is required', 'warning');
        return;
    }
    
    try {
        const originalBtnHtml = updateBtn.innerHTML;
        updateBtn.disabled = true;
        updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        const response = await fetch('../../api/users/update-profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, contact, address })
        });
        
        const result = await response.json();
        
        updateBtn.disabled = false;
        updateBtn.innerHTML = originalBtnHtml;
        
        if (result.success) {
            // Update localStorage
            localStorage.setItem('userData', JSON.stringify(result.data));
            
            // Update UI
            document.getElementById('userName').textContent = result.data.name;
            document.getElementById('userEmail').textContent = result.data.email;
            document.getElementById('userAvatar').textContent = getInitials(result.data.name);
            
            showNotification('Profile updated successfully!', 'success');
        } else {
            showNotification(result.message || 'Failed to update profile', 'warning');
        }
    } catch (error) {
        console.error('Update profile error:', error);
        updateBtn.disabled = false;
        showNotification('Server error occurred', 'warning');
    }
}

// Change password
async function changePassword(event) {
    event.preventDefault();
    
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmNewPassword = document.getElementById('confirmNewPassword').value;
    const changeBtn = document.getElementById('changePasswordBtn');
    
    if (newPassword !== confirmNewPassword) {
        showNotification('New passwords do not match', 'warning');
        return;
    }
    
    if (newPassword.length < 6) {
        showNotification('New password must be at least 6 characters', 'warning');
        return;
    }
    
    try {
        const originalBtnHtml = changeBtn.innerHTML;
        changeBtn.disabled = true;
        changeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing...';
        
        const response = await fetch('../../api/users/change-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ currentPassword, newPassword })
        });
        
        const result = await response.json();
        
        changeBtn.disabled = false;
        changeBtn.innerHTML = originalBtnHtml;
        
        if (result.success) {
            document.getElementById('changePasswordForm').reset();
            showNotification('Password changed successfully!', 'success');
        } else {
            showNotification(result.message || 'Failed to change password', 'warning');
        }
    } catch (error) {
        console.error('Change password error:', error);
        changeBtn.disabled = false;
        showNotification('Server error occurred', 'warning');
    }
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
        updateUserCalendarEvents();
        
        // Update notification count
        const notifBadge = document.getElementById('notifBadge');
        const pendingCount = userBookings.filter(b => b.status === 'pending').length;
        if (notifBadge) notifBadge.textContent = pendingCount || '0';
        
        const bookingsBadge = document.getElementById('bookingsBadge');
        if (bookingsBadge) bookingsBadge.textContent = pendingCount || '0';
    }, 3000); // 3 seconds interval is better for performance than 1 second

    // View toggle buttons
    const userTableBtn = document.getElementById('userTableViewBtn');
    const userCalendarBtn = document.getElementById('userCalendarViewBtn');
    if (userTableBtn) {
        userTableBtn.addEventListener('click', async () => await toggleUserView('table'));
    }
    if (userCalendarBtn) {
        userCalendarBtn.addEventListener('click', async () => await toggleUserView('calendar'));
    }
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
