// User Dashboard JavaScript

// Sample data - In a real app, this would come from a backend API
let userBookings = [];
let selectedFile = null;
let activeExercisesByPackage = {}; // Cache for package exercises
let userData = null;

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
        const response = await fetch('../../api/bookings/get-all.php?status=all');
        const data = await response.json();
        
        if (data.success) {
            // Backend now auto-expires bookings in DB before returning them
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
                price: '₱' + parseFloat(String(pkg.price).replace(/[^\d.-]/g, '')).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }),
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
        
        // Update Coach Hub Indicator
        updateCoachHubIndicator();
        
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
    age: null,
    sex: 'Male',
    weight: null,
    height: null,
    medical_conditions: 'None',
    exercise_history: 'Beginner',
    primary_goal: 'Stay fit / general health',
    goal_pace: 'Moderately',
    workout_days_per_week: '1-2 days',
    preferred_workout_time: 'Morning',
    injuries_limitations: 'None',
    focus_areas: [],
    workout_type: 'Mixed',
    trainer_guidance: 'Independent workout',
    equipment_confidence: 'Not confident'
};
let currentSurveyStep = 1;

function checkStepValid() {
    const nextBtn = document.getElementById('surveyNextBtn');
    if (!nextBtn) return;

    let isValid = false;
    
    if (currentSurveyStep === 1) {
        const age = document.getElementById('surveyAge').value;
        const height = document.getElementById('surveyHeight').value;
        const weight = document.getElementById('surveyWeight').value;
        isValid = age > 0 && height > 0 && weight > 0 && surveyData.exercise_history !== null;
    } else if (currentSurveyStep === 2) {
        isValid = surveyData.primary_goal !== null && surveyData.goal_pace !== null;
    } else if (currentSurveyStep === 3) {
        isValid = surveyData.workout_days_per_week !== null && surveyData.preferred_workout_time !== null;
    } else if (currentSurveyStep === 4) {
        isValid = surveyData.focus_areas.length > 0;
    } else if (currentSurveyStep === 5) {
        isValid = surveyData.workout_type !== null && surveyData.trainer_guidance !== null && surveyData.equipment_confidence !== null;
    }

    nextBtn.disabled = !isValid;
}

async function checkSurveyStatus() {
    try {
        const response = await fetch('../../api/users/get-questionnaire.php');
        const data = await response.json();
        
        if (data.success) {
            // Already completed
            console.log('Survey already completed');
            return;
        }
    } catch (e) {
        console.error('Error checking survey status:', e);
    }
    
    // Reset survey state
    currentSurveyStep = 1;
    
    setTimeout(() => {
        const modal = document.getElementById('surveyModal');
        if (modal) {
            document.querySelectorAll('.survey-step').forEach(step => step.classList.remove('active'));
            const firstStep = document.querySelector('.survey-step[data-step="1"]');
            if (firstStep) firstStep.classList.add('active');
            
            const progressBar = document.getElementById('surveyProgress');
            if (progressBar) progressBar.style.width = '20%';
            
            const nextBtn = document.getElementById('surveyNextBtn');
            const backBtn = document.getElementById('surveyBackBtn');
            if (nextBtn) {
                nextBtn.disabled = true;
                nextBtn.innerHTML = '<span>Next Step</span> <i class="fas fa-arrow-right"></i>';
            }
            if (backBtn) backBtn.style.display = 'none';
            
            document.querySelectorAll('.option-card').forEach(card => card.classList.remove('selected'));
            
            modal.classList.add('active');
        }
    }, 1500);
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
        userData = JSON.parse(savedUser);
        document.getElementById('userName').textContent = userData.name;
        document.getElementById('userEmail').textContent = userData.email;
        document.getElementById('userAvatar').textContent = getInitials(userData.name);
        document.getElementById('profileName').value = userData.name;
        document.getElementById('profileEmail').value = userData.email;
        document.getElementById('profileContact').value = userData.contact || '';
        document.getElementById('profileAddress').value = userData.address || '';
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
    
    // 1. Add Trainer Workout Plans/Sessions for each booking
    for (const b of userBookings) {
        if (b.status !== 'verified') continue;
        
        try {
            const resp = await fetch(`../../api/trainers/get-sessions.php?booking_id=${b.id}`);
            const sessions = await resp.json();
            if (Array.isArray(sessions)) {
                sessions.forEach(s => {
                    events.push({
                        ...s,
                        id: `session-${s.id}`,
                        title: `${s.title}${s.type === 'rest_day' ? '' : ' (Coach)'}`,
                        classNames: [s.type === 'rest_day' ? 'event-rest-day' : 'event-routine'],
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
        dayMaxEvents: 2,
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
        'trainer': 'trainerSection',
        'profile': 'profileSection'
    };
    
    const sectionId = sectionMap[section];
    if (sectionId) {
        document.getElementById(sectionId).classList.add('active');
        
        // Update page title
        const titles = {
            'dashboard': { title: 'Dashboard', subtitle: 'Welcome back! Manage your gym membership and bookings' },
            'packages': { title: 'Packages', subtitle: 'Choose a membership plan that fits your fitness goals' },
            'bookings': { title: 'Booking History', subtitle: 'View your complete booking history including expired packages' },
            'payments': { title: 'Payment History', subtitle: 'Track all your payment transactions' },
            'trainer': { title: 'My Trainer', subtitle: 'View your assigned trainer and session details' },
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

        // Load section-specific data
        if (section === 'trainer') {
            trainerSectionLoaded = false; // allow reload on nav
            loadTrainerSection();
        }
    }
}

// Populate packages grid
function populatePackages() {
    const grid = document.getElementById('packagesGrid');
    const activeGrid = document.getElementById('activePackagesGrid');
    const activeSection = document.getElementById('activePackagesSection');
    
    if (!grid) return;
    grid.innerHTML = '';
    if (activeGrid) activeGrid.innerHTML = '';
    
    let activeCount = 0;
    
    // Define package hierarchy (lower index = lower tier) - case-insensitive
    const packageHierarchy = ['basic', 'popular', 'best value', 'premium', 'vip'];
    
    // Check if user has any truly active subscription and get their highest current tier
    // Backend now marks expired bookings with status='expired', so only status='verified' are genuinely active
    let currentUserTier = -1;
    const activeVerifiedBookings = userBookings.filter(b => b.status === 'verified');
    
    if (activeVerifiedBookings.length > 0) {
        activeVerifiedBookings.forEach(b => {
            const pkg = packagesData.find(p => String(p.id) === String(b.package_id));
            if (pkg) {
                const tier = packageHierarchy.indexOf((pkg.tag || 'Basic').toLowerCase().trim());
                if (tier > currentUserTier) {
                    currentUserTier = tier;
                }
            }
        });
    }
    
    const hasActiveSubscription = currentUserTier >= 0;
    const hasPendingBooking = userBookings.some(b => b.status === 'pending');
    const pendingBooking = userBookings.find(b => b.status === 'pending');
    
    packagesData.forEach(pkg => {
        // Check if this package is currently active for the user
        // Backend sets status='expired' for expired bookings, so only status='verified' are active
        const activeBooking = userBookings.find(b => 
            String(b.package_id) === String(pkg.id) && 
            b.status === 'verified'
        );
        const isActive = !!activeBooking;
        const isPendingPackage = pendingBooking && String(pendingBooking.package_id) === String(pkg.id);
        
        // Get package tier level (case-insensitive)
        const packageTier = packageHierarchy.indexOf((pkg.tag || 'Basic').toLowerCase());
        const isUpgrade = hasActiveSubscription && packageTier > currentUserTier;
        
        // Check if this specific active package has already been superseded by a higher tier active package
        const isAlreadyUpgraded = isActive && currentUserTier > packageTier;
        
        let expiryDisplay = '';
        if (isActive) {
            const expiryDate = activeBooking.expires_at ? new Date(activeBooking.expires_at) : new Date(new Date(activeBooking.booking_date || activeBooking.created_at).getTime() + parseDurationToDays(activeBooking.duration) * 86400000);
            const diffTime = expiryDate.getTime() - new Date().getTime();
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            let timeRemaining = '';
            if (diffDays > 0) timeRemaining = `${diffDays}d left`;
            else if (diffDays === 0) timeRemaining = `Expires today`;
            else timeRemaining = 'Expired';

            expiryDisplay = `
                <div style="margin-top: 12px; padding: 10px; background: rgba(255,255,255,0.02); border-radius: 10px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; flex-direction: column; gap: 2px;">
                        <span style="font-size: 0.6rem; color: var(--dark-text-secondary); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Expires on</span>
                        <span style="font-size: 0.75rem; color: #fff; font-weight: 700;">${formatDate(expiryDate)}</span>
                    </div>
                    <span style="font-size: 0.65rem; padding: 4px 8px; background: ${diffDays <= 7 ? 'rgba(245, 158, 11, 0.1)' : 'rgba(34, 197, 94, 0.1)'}; color: ${diffDays <= 7 ? 'var(--warning)' : '#22c55e'}; border-radius: 6px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">
                        ${timeRemaining}
                    </span>
                </div>
            `;
        }

        const canRenew = isActive && !isAlreadyUpgraded && canRenewBooking(activeBooking);
        const canUpgrade = (isActive && canUpgradeBooking(activeBooking)) || isUpgrade;

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
                        ` : isPendingPackage ? `
                        <span style="font-size: 0.65rem; padding: 2px 8px; background: rgba(245, 158, 11, 0.1); color: var(--warning); border-radius: 4px; border: 1px solid rgba(245, 158, 11, 0.2); font-weight: 800; text-transform: uppercase;">
                            <i class="fas fa-clock"></i> Pending
                        </span>
                        ` : ''}
                        ${isAlreadyUpgraded ? `
                        <span style="font-size: 0.65rem; padding: 2px 8px; background: rgba(34, 197, 94, 0.05); color: #22c55e; border-radius: 4px; border: 1px solid rgba(34, 197, 94, 0.1); font-weight: 800; text-transform: uppercase;">
                            <i class="fas fa-arrow-up"></i> Upgraded
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
                ${expiryDisplay}
            </div>
            <div class="package-footer">
                <div class="package-price-large">${pkg.price}</div>
                <div class="package-btn-group">
                    <button class="btn btn-exercise" onclick="${isActive ? `viewBookingDetails(${activeBooking.id})` : `previewPackageHub(${pkg.id})`}" title="${isActive ? 'View My Hub' : 'View Package Details'}">
                        <i class="fas ${isActive ? 'fa-th-large' : 'fa-list-ul'}"></i>
                    </button>

                    ${canRenew ? `
                    <button class="btn btn-renew" onclick="renewBooking(${activeBooking.id})" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); flex: 1;">
                        <i class="fas fa-redo"></i>
                        <span>Renew</span>
                    </button>
                    ` : ''}
                    ${isAlreadyUpgraded ? `
                    <button class="btn btn-upgrade" style="background: rgba(34, 197, 94, 0.05); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.1); flex: 1; opacity: 0.8; cursor: default;" disabled>
                        <i class="fas fa-check"></i>
                        <span>Upgraded</span>
                    </button>
                    ` : hasPendingBooking ? `
                    <button class="btn btn-book" style="background: rgba(255, 255, 255, 0.05); color: var(--dark-text-secondary); border: 1px solid var(--dark-border); flex: 1; cursor: not-allowed;" title="You have a pending booking" disabled>
                        <i class="fas ${isPendingPackage ? 'fa-clock' : 'fa-lock'}"></i>
                        <span style="white-space: nowrap;">${isPendingPackage ? 'Pending' : 'Locked'}</span>
                    </button>
                    ` : canUpgrade ? `
                    <button class="btn btn-upgrade" onclick="openUpgradeModal(${pkg.id})" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); flex: 1;">
                        <i class="fas fa-arrow-up"></i>
                        <span>Upgrade</span>
                    </button>
                    ` : !isActive && hasActiveSubscription ? `
                    <button class="btn btn-book" style="background: rgba(255, 255, 255, 0.05); color: var(--dark-text-secondary); border: 1px solid var(--dark-border); flex: 1; cursor: not-allowed;" disabled>
                        <i class="fas fa-lock"></i>
                        <span style="white-space: nowrap;">Lower Tier</span>
                    </button>
                    ` : !isActive ? `
                    <button class="btn btn-book" onclick="selectPackageForBooking('${pkg.name}')" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); flex: 1;">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Buy Now</span>
                    </button>
                    ` : ''}
                </div>
            </div>
        `;
        
        if (isActive && activeGrid) {
            activeGrid.appendChild(packageCard);
            activeCount++;
        } else {
            grid.appendChild(packageCard);
        }
    });
    
    // Show/hide active section
    if (activeSection) {
        activeSection.style.display = activeCount > 0 ? 'block' : 'none';
    }
}

// Renew booking function
async function renewBooking(bookingId) {
    const booking = userBookings.find(b => b.id === bookingId);
    if (!booking) {
        showNotification('Booking not found', 'error');
        return;
    }
    
    // Pre-fill the booking form with the same package
    showSection('bookings');
    await updateBookingPackageSelect();
    
    // Select the same package
    const packageSelect = document.getElementById('bookingPackage');
    if (packageSelect) {
        packageSelect.value = booking.package_name || booking.package;
    }
    
    // Open the booking modal
    openBookingModal();
    
    showNotification(`Renewing ${booking.package_name || booking.package}`, 'success');
}

// ===== Edit Booking (for pending bookings) =====
let editBookingId = null;
let selectedEditFile = null;

function openChangePlanModal(bookingId) {
    // Keep this wrapper so old button references still work if cached, 
    // but the button in the HTML was also updated to openEditBookingModal? 
    // Wait, in HTML I kept onclick="openChangePlanModal(${booking.id})" but I'll change it here.
    openEditBookingModal(bookingId);
}

function openEditBookingModal(bookingId) {
    const booking = userBookings.find(b => b.id === bookingId || b.id === String(bookingId));
    if (!booking || booking.status !== 'pending') {
        showNotification('Only pending bookings can be edited', 'warning');
        return;
    }
    editBookingId = booking.id;

    // Populate packages
    const packageSelect = document.getElementById('editBookingPackage');
    if (packageSelect) {
        packageSelect.innerHTML = '<option value="">Choose a package...</option>';
        packagesData.forEach(pkg => {
            const opt = document.createElement('option');
            opt.value = pkg.name;
            opt.textContent = `${pkg.name} - ₱${pkg.price} (${pkg.duration})`;
            if (pkg.name === (booking.package_name || booking.package)) {
                opt.selected = true;
            }
            packageSelect.appendChild(opt);
        });
    }

    // Populate Date
    const dateInput = document.getElementById('editBookingDate');
    if (dateInput) {
        dateInput.value = booking.booking_date || booking.created_at ? 
            (booking.booking_date || booking.created_at).split(' ')[0] : '';
    }

    // Populate Contact
    const contactInput = document.getElementById('editBookingContact');
    if (contactInput) {
        contactInput.value = booking.contact || '';
    }

    // Populate Notes
    const notesInput = document.getElementById('editBookingNotes');
    if (notesInput) {
        notesInput.value = booking.notes || '';
    }

    // Reset Receipt
    removeEditFile();
    
    // Show modal
    const modal = document.getElementById('editBookingModal');
    if (modal) modal.classList.add('active');
}

function closeEditBookingModal() {
    const modal = document.getElementById('editBookingModal');
    if (modal) modal.classList.remove('active');
    editBookingId = null;
    removeEditFile();
}

// Handle file select for Edit Modal
function handleEditFileSelect(event) {
    const file = event.target.files[0];
    if (file) {
        // Validate file size (max 5MB)
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if (file.size > maxSize) {
            showNotification('Image size is too huge! Maximum allowed size is 5MB.', 'warning');
            event.target.value = ''; // Clear the input
            return;
        }

        selectedEditFile = file;
        const preview = document.getElementById('editFilePreview');
        const fileName = document.getElementById('editFileName');
        const uploadArea = document.getElementById('editFileUploadArea');
        const alert = document.getElementById('editCurrentReceiptAlert');
        
        fileName.textContent = file.name;
        if (preview) preview.style.display = 'block';
        if (uploadArea) {
            uploadArea.style.borderColor = 'var(--primary)';
            uploadArea.style.background = 'var(--glass)';
            uploadArea.style.display = 'none';
        }
        if (alert) alert.style.display = 'none';
    }
}

// Remove file for Edit Modal
function removeEditFile() {
    selectedEditFile = null;
    const preview = document.getElementById('editFilePreview');
    const input = document.getElementById('editReceiptFile');
    const uploadArea = document.getElementById('editFileUploadArea');
    const alert = document.getElementById('editCurrentReceiptAlert');
    
    if (preview) preview.style.display = 'none';
    if (input) input.value = '';
    if (uploadArea) {
        uploadArea.style.borderColor = '';
        uploadArea.style.background = '';
        uploadArea.style.display = 'block';
    }
    if (alert) alert.style.display = 'block';
}

async function submitEditBooking(event) {
    event.preventDefault();
    
    if (!editBookingId) {
        showNotification('No booking selected', 'error');
        return;
    }

    // Double check file size before uploading
    if (selectedEditFile) {
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if (selectedEditFile.size > maxSize) {
            showNotification('Image size is too huge! Maximum allowed size is 5MB.', 'warning');
            return;
        }
    }
    
    const packageName = document.getElementById('editBookingPackage').value;
    const date = document.getElementById('editBookingDate').value;
    const contact = document.getElementById('editBookingContact').value;
    const notes = document.getElementById('editBookingNotes').value;
    
    // Validate contact number
    if (!validateContactNumber(contact)) {
        showNotification('Contact number must be exactly 11 digits (numbers only)', 'error');
        return;
    }
    
    // Validate required fields
    if (!packageName || !date || !contact) {
        showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    let originalText = '';
    const submitButton = document.getElementById('editBookingSubmitBtn');
    
    try {
        // Show loading indicator
        originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitButton.disabled = true;
        
        // Upload receipt file IF a new one is selected
        let receiptUrl = '';
        if (selectedEditFile) {
            receiptUrl = await uploadReceipt(selectedEditFile);
        }
        
        // Create booking data
        const bookingData = {
            booking_id: editBookingId,
            package: packageName,
            date: date,
            contact: contact,
            notes: notes,
            receipt: receiptUrl
        };
        
        // Submit edited booking to database (reusing the change-plan endpoint which handles edits now)
        const response = await fetch('../../api/bookings/change-plan.php', {
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
            closeEditBookingModal();
            showNotification('Booking updated successfully!', 'success');
            
            // Reload bookings and refresh UI
            await loadUserBookings();
            populateBookings();
            populatePackages();
            updateStats();
        } else {
            showNotification(result.message || 'Failed to update booking', 'error');
        }
    } catch (error) {
        console.error('Error updating booking:', error);
        
        // Restore button
        if (submitButton && originalText) {
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
        
        showNotification('Error updating: ' + error.message, 'error');
    }
}

// Open upgrade modal
function openUpgradeModal(clickedPackageId = null) {
    const modal = document.getElementById('upgradeModal');
    if (!modal) {
        showNotification('Upgrade modal not found', 'error');
        return;
    }
    
    let upgradeFromId = clickedPackageId;
    
    // If no specific package ID provided, try to find the user's active one
    if (!upgradeFromId) {
        const activeUserBooking = userBookings.find(b => 
            b.status === 'verified' && 
            (!b.expires_at || new Date(b.expires_at) > new Date())
        );
        
        if (activeUserBooking) {
            upgradeFromId = activeUserBooking.package_id;
        }
    }
    
    if (!upgradeFromId) {
        showNotification('No active subscription found to upgrade', 'error');
        return;
    }
    
    const activePackage = packagesData.find(p => String(p.id) === String(upgradeFromId));
    if (!activePackage) {
        showNotification('Selected package not found', 'error');
        return;
    }
    
    // Use the specific package ID to show higher tiers for it
    populateUpgradePlans(activePackage.id);
    
    // Show modal
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Close upgrade modal
function closeUpgradeModal() {
    const modal = document.getElementById('upgradeModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Populate upgrade plans in modal
function populateUpgradePlans(currentPackageId) {
    const container = document.getElementById('upgradePlansContainer');
    if (!container) return;
    
    // Define package hierarchy (case-insensitive)
    const packageHierarchy = ['basic', 'popular', 'best value', 'premium', 'vip'];
    
    // Get current package tier
    const currentPackage = packagesData.find(p => p.id === currentPackageId);
    if (!currentPackage) {
        console.error('Current package not found:', currentPackageId);
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;">Error: Current package not found</div>';
        return;
    }
    
    const currentTag = (currentPackage.tag || 'Basic').toLowerCase().trim();
    const currentTier = packageHierarchy.indexOf(currentTag);
    
    // Filter packages that are higher tier
    const upgradablePackages = packagesData.filter(pkg => {
        // Skip the current package
        if (pkg.id === currentPackageId) return false;
        
        const pkgTag = (pkg.tag || 'Basic').toLowerCase().trim();
        const pkgTier = packageHierarchy.indexOf(pkgTag);
        
        // If tier is found and higher, include it
        if (pkgTier !== -1 && pkgTier > currentTier) {
            return true;
        }
        
        // If current tier is -1 (unknown), include all packages
        if (currentTier === -1) {
            return true;
        }
        
        return false;
    });
    
    console.log('Upgradable packages:', upgradablePackages);
    
    // Sort by tier
    upgradablePackages.sort((a, b) => {
        const aTier = packageHierarchy.indexOf((a.tag || 'Basic').toLowerCase().trim());
        const bTier = packageHierarchy.indexOf((b.tag || 'Basic').toLowerCase().trim());
        return aTier - bTier;
    });
    
    // Render upgrade options
    if (upgradablePackages.length > 0) {
        container.innerHTML = upgradablePackages.map(pkg => `
            <div class="upgrade-plan-card" onclick="selectUpgradePlan(${pkg.id})">
                <div class="upgrade-plan-header">
                    <h4>${pkg.name}</h4>
                    <span class="upgrade-plan-tag">${pkg.tag || 'Standard'}</span>
                </div>
                <div class="upgrade-plan-price">${pkg.price}</div>
                <p class="upgrade-plan-description">${pkg.description || 'Full gym access with all facilities'}</p>
                <div class="upgrade-plan-features">
                    <div><i class="fas fa-clock"></i> ${pkg.duration}</div>
                    <div><i class="fas fa-check-circle"></i> Full gym access</div>
                    ${pkg.is_trainer_assisted ? '<div><i class="fas fa-user-tie"></i> Trainer Assisted</div>' : ''}
                </div>
                <button class="btn btn-upgrade-select">
                    <i class="fas fa-arrow-up"></i> Upgrade to ${pkg.name}
                </button>
            </div>
        `).join('');
    } else {
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);"><i class="fas fa-crown" style="font-size: 3rem; opacity: 0.2; margin-bottom: 16px;"></i><p>You\'re already on the highest tier!</p><p style="font-size: 0.85rem; margin-top: 8px;">Current package: ' + currentPackage.name + ' (' + currentPackage.tag + ')</p></div>';
    }
}

// Select upgrade plan
function selectUpgradePlan(packageId) {
    const pkg = packagesData.find(p => p.id === packageId);
    if (!pkg) return;
    
    // Close upgrade modal
    closeUpgradeModal();
    
    // Pre-fill booking form
    showSection('bookings');
    updateBookingPackageSelect().then(() => {
        const packageSelect = document.getElementById('bookingPackage');
        if (packageSelect) {
            packageSelect.value = pkg.name;
        }
        openBookingModal();
    });
    
    showNotification(`Upgrading to ${pkg.name}`, 'success');
}

// Show notification helper
function showNotification(message, type = 'info') {
    // Check if notification container exists
    let container = document.getElementById('notificationContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificationContainer';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000;';
        document.body.appendChild(container);
    }
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        background: ${type === 'success' ? 'rgba(34, 197, 94, 0.9)' : type === 'error' ? 'rgba(239, 68, 68, 0.9)' : 'rgba(59, 130, 246, 0.9)'};
        color: white;
        padding: 16px 24px;
        border-radius: 8px;
        margin-bottom: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideInRight 0.3s ease;
        min-width: 300px;
    `;
    
    const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
    notification.innerHTML = `
        <i class="fas ${icon}"></i>
        <span>${message}</span>
    `;
    
    container.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
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
        dayMaxEvents: 2,
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
                <td colspan="6" style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">
                    No booking history yet. <a href="#" onclick="showSection('packages')" style="color: var(--primary); text-decoration: underline;">Browse packages</a> to get started!
                </td>
            </tr>
        `;
        recentTbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">
                    No booking history yet. <a href="#" onclick="showSection('packages')" style="color: var(--primary); text-decoration: underline;">Browse packages</a> to get started!
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
        const row = createBookingRow(booking, true); // true means include expiry
        tbody.appendChild(row);
    });
    
    // Populate recent bookings (last 5)
    recentTbody.innerHTML = '';
    sortedBookings.slice(0, 5).forEach(booking => {
        const row = createBookingRow(booking, false); // false means no expiry for recent table (compact)
        recentTbody.appendChild(row);
    });
    
    // Update badge
    const pendingCount = userBookings.filter(b => b.status === 'pending').length;
    document.getElementById('bookingsBadge').textContent = pendingCount || '';
}

// Create booking row
function createBookingRow(booking, includeExpiry = false) {
    const row = document.createElement('tr');
    const isActive = isBookingActive(booking);
    const isUpgradable = canUpgradeBooking(booking);
    
    // Check if this specific booking is already superseded by a higher tier active booking
    let isAlreadyUpgraded = false;
    const packageHierarchy = ['basic', 'popular', 'best value', 'premium', 'vip'];
    const currentPkg = packagesData.find(p => String(p.id) === String(booking.package_id));
    
    if (isActive && currentPkg) {
        const packageTier = packageHierarchy.indexOf((currentPkg.tag || 'Basic').toLowerCase().trim());
        let highestTier = -1;
        userBookings.forEach(b => {
            if (isBookingActive(b)) {
                const p = packagesData.find(pkg => String(pkg.id) === String(b.package_id));
                if (p) {
                    const tier = packageHierarchy.indexOf((p.tag || 'Basic').toLowerCase().trim());
                    if (tier > highestTier) highestTier = tier;
                }
            }
        });
        isAlreadyUpgraded = highestTier > packageTier;
    }
    
    const canRenew = (booking.status === 'verified') && !isAlreadyUpgraded && canRenewBooking(booking);
    
    // Calculate expiry date for display
    let expiryDateDisplay = '-';
    let remainingDaysDisplay = '';
    if (booking.status === 'verified' || booking.status === 'pending' || booking.status === 'expired') {
        const rawDate = booking.booking_date || booking.created_at || booking.date;
        const dateObj = booking.expires_at ? new Date(booking.expires_at) : new Date(new Date(rawDate).getTime() + parseDurationToDays(booking.duration) * 86400000);
        if (!isNaN(dateObj.getTime())) {
            expiryDateDisplay = formatDate(dateObj);
        }
        
        if (isActive) {
            const diffTime = dateObj.getTime() - new Date().getTime();
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            if (diffDays > 0) {
                remainingDaysDisplay = `<div style="font-size: 0.65rem; color: #22c55e; margin-top: 2px;">${diffDays} days left</div>`;
            } else if (diffDays === 0) {
                remainingDaysDisplay = `<div style="font-size: 0.65rem; color: var(--warning); margin-top: 2px;">Expires today</div>`;
            }
        }
    }

    // Build status badge — map 'expired' to a proper red badge
    let statusLabel = booking.status.charAt(0).toUpperCase() + booking.status.slice(1);
    let statusClass = booking.status;
    // 'expired' has its own CSS class; make sure it looks red
    let statusDisplay = `<span class="status-badge status-${statusClass}" style="${booking.status === 'expired' ? 'background:rgba(239,68,68,0.12);color:#ef4444;border:1px solid rgba(239,68,68,0.3);' : ''}">${statusLabel}</span>`;
    if (booking.status === 'rejected' && booking.notes) {
        statusDisplay += `
            <div class="rejection-hint" title="Reason: ${booking.notes}" style="margin-top: 4px; font-size: 0.7rem; color: #ef4444; cursor: help;">
                <i class="fas fa-comment-dots"></i> View Reason
            </div>
        `;
    }

    const commonActions = `
        <div class="table-actions">
            <button class="icon-btn" onclick="showBookingSummary(${booking.id})" title="View Summary & Receipt">
                <i class="fas fa-file-invoice"></i>
            </button>
            ${booking.status === 'pending' ? `
            <button class="icon-btn" onclick="openEditBookingModal(${booking.id})" title="Edit Booking" style="color:#3b82f6; border-color:rgba(59,130,246,0.3);">
                <i class="fas fa-edit"></i>
            </button>
            ` : ''}
        </div>
    `;

    if (includeExpiry) {
        row.innerHTML = `
            <td data-label="Package">
                <div style="font-weight: 700;">${booking.package_name || booking.package}</div>
              
            </td>
            <td data-label="Date">${formatDate(booking.booking_date || booking.date || booking.createdAt)}</td>
            <td data-label="Expiry Date">
                <div style="font-weight: 600; color: ${isActive ? '#22c55e' : 'var(--dark-text-secondary)'};">
                    ${expiryDateDisplay}
                </div>
                ${remainingDaysDisplay}
            </td>
            <td data-label="Amount" style="font-weight: 800; color: var(--primary);">₱${parseFloat(booking.amount).toFixed(2)}</td>
            <td data-label="Status">${statusDisplay}</td>
            <td data-label="Actions">${commonActions}</td>
        `;
    } else {
        row.innerHTML = `
            <td data-label="Package">
                <div style="font-weight: 700;">${booking.package_name || booking.package}</div>
                ${(booking.status === 'verified' || booking.status === 'pending' || booking.status === 'expired') ? `
                    <div style="font-size: 0.75rem; margin-top: 4px;">
                        <span class="status-badge" style="padding: 2px 8px; font-size: 0.7rem; ${isActive ? 'background:rgba(34,197,94,0.1);color:#22c55e;border:1px solid rgba(34,197,94,0.2);' : 'background:rgba(239,68,68,0.12);color:#ef4444;border:1px solid rgba(239,68,68,0.3);'}">
                            ${isActive ? 'Active' : 'Expired'}
                        </span>
                    </div>
                ` : ''}
            </td>
            <td data-label="Date">${formatDate(booking.booking_date || booking.date || booking.createdAt)}</td>
            <td data-label="Amount" style="font-weight: 800; color: var(--primary);">₱${parseFloat(booking.amount).toFixed(2)}</td>
            <td data-label="Status">${statusDisplay}</td>
            <td data-label="Actions">${commonActions}</td>
        `;
    }
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

    // Examples in this project: "30 Day", "30 Days", "7 Days", "6 months", "1  month", "1 Year"
    const str = String(durationStr).toLowerCase().trim();
    const match = str.match(/(\d+)\s*([a-z]+)/i);
    if (!match) return 0;

    const value = parseInt(match[1], 10);
    const unit = match[2] ? String(match[2]).toLowerCase() : '';

    if (isNaN(value)) return 0;

    if (unit.startsWith('day')) return value;
    if (unit.startsWith('week')) return value * 7;
    if (unit.startsWith('month')) return value * 30;
    if (unit.startsWith('year')) return value * 365;

    // Fallback (best-effort): treat unknown unit as "days"
    return value;
}

// Check if a specific booking is currently active (not expired)
function isBookingActive(booking) {
    // 'expired' is now set explicitly by the backend
    if (booking.status === 'expired') return false;
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

// Check if a booking can be upgraded
function canUpgradeBooking(booking) {
    if (!isBookingActive(booking)) return false;
    
    const packageHierarchy = ['basic', 'popular', 'best value', 'premium', 'vip'];
    const currentPkg = packagesData.find(p => String(p.id) === String(booking.package_id));
    if (!currentPkg) return false;
    
    const currentTier = packageHierarchy.indexOf((currentPkg.tag || 'Basic').toLowerCase().trim());
    
    // Check if there's any package with a higher tier
    return packagesData.some(pkg => {
        const pkgTier = packageHierarchy.indexOf((pkg.tag || 'Basic').toLowerCase().trim());
        return pkgTier > currentTier;
    });
}

// Helper to check if a booking is superseded by a higher tier active plan
function checkIsAlreadyUpgraded(booking) {
    if (!isBookingActive(booking)) return false;
    
    const packageHierarchy = ['basic', 'popular', 'best value', 'premium', 'vip'];
    const currentPkg = packagesData.find(p => String(p.id) === String(booking.package_id));
    if (!currentPkg) return false;
    
    const packageTier = packageHierarchy.indexOf((currentPkg.tag || 'Basic').toLowerCase().trim());
    
    let highestTier = -1;
    userBookings.forEach(b => {
        if (isBookingActive(b)) {
            const p = packagesData.find(pkg => String(pkg.id) === String(b.package_id));
            if (p) {
                const tier = packageHierarchy.indexOf((p.tag || 'Basic').toLowerCase().trim());
                if (tier > highestTier) highestTier = tier;
            }
        }
    });
    
    return highestTier > packageTier;
}

// Check if a booking can be renewed (only when close to expiry, 7 days or less, but NOT yet expired)
function canRenewBooking(booking) {
    // Only verified (active) bookings can be renewed — expired ones must buy new
    if (booking.status !== 'verified') return false;
    
    const now = new Date();
    const expiryDate = booking.expires_at ? new Date(booking.expires_at) : new Date(new Date(booking.booking_date || booking.created_at).getTime() + parseDurationToDays(booking.duration) * 86400000);
    
    // Already expired — must buy a new package
    if (now > expiryDate) return false;
    
    // Only allow renew if 7 days or less remain
    const diffTime = expiryDate.getTime() - now.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    return diffDays <= 7;
}

function updateStats() {
    // Total bookings = all bookings regardless of status
    const totalBookings = userBookings.length;
    // Verified = currently active (status='verified', not yet expired)
    const totalVerified = userBookings.filter(b => b.status === 'verified').length;
    // Expired = bookings where status was set to 'expired' by backend
    const hasExpired = userBookings.some(b => b.status === 'expired');
    
    // Active membership = any verified booking that is NOT expired (for other logic)
    const activeVerifiedBookings = userBookings.filter(b => isBookingActive(b));
    
    // Calculate current highest tier for upgrade logic
    const packageHierarchy = ['basic', 'popular', 'best value', 'premium', 'vip'];
    let currentUserTier = -1;
    if (activeVerifiedBookings.length > 0) {
        activeVerifiedBookings.forEach(b => {
            const pkg = packagesData.find(p => String(p.id) === String(b.package_id));
            if (pkg) {
                const tier = packageHierarchy.indexOf((pkg.tag || 'Basic').toLowerCase().trim());
                if (tier > currentUserTier) currentUserTier = tier;
            }
        });
    }

    document.getElementById('activeBookingsCount').textContent = totalBookings;
    document.getElementById('verifiedBookingsCount').textContent = totalVerified;
    
    // Update membership status display
    const membershipStatus = document.getElementById('membershipStatus');
    const profileMembershipBadge = document.getElementById('profileMembershipBadge');
    const profileMembershipValue = document.getElementById('profileMembershipValue');
    const profileMembershipStatus = document.getElementById('profileMembershipStatus');
    const profileMembershipPlan = document.getElementById('profileMembershipPlan');
    const profileMembershipExpiryDate = document.getElementById('profileMembershipExpiryDate');
    const profileMembershipExpiryRow = document.getElementById('profileMembershipExpiryRow');
    const membershipExpiry = document.getElementById('membershipExpiry');
    const membershipExpiryDate = document.getElementById('membershipExpiryDate');
    const sidebarMemberBadge = document.getElementById('sidebarMemberBadge');
    const statTrainer = document.getElementById('statTrainer');

    if (activeVerifiedBookings.length > 0) {
        // Find the booking with the latest expiry date
        const latestActive = [...activeVerifiedBookings].sort((a, b) => {
            const expA = a.expires_at ? new Date(a.expires_at).getTime() : (new Date(a.booking_date || a.created_at).getTime() + parseDurationToDays(a.duration) * 86400000);
            const expB = b.expires_at ? new Date(b.expires_at).getTime() : (new Date(b.booking_date || b.created_at).getTime() + parseDurationToDays(b.duration) * 86400000);
            return expB - expA;
        })[0];

        // Get all unique active package names
        const activePackageNames = [...new Set(activeVerifiedBookings.map(b => b.package_name || b.package))];

        if (membershipStatus) {
            membershipStatus.textContent = activePackageNames.join(', ');
            membershipStatus.className = 'stat-value status-verified';
            membershipStatus.style.color = '';
            // Adjust font size if multiple packages to avoid overflow
            if (activePackageNames.length > 1) {
                membershipStatus.style.fontSize = '1.1rem';
            } else {
                membershipStatus.style.fontSize = '';
            }
        }

        // Show expiry date on dashboard card
        const membershipStartDate = document.getElementById('membershipStartDate');
        if (membershipExpiry && membershipExpiryDate) {
            membershipExpiry.style.display = 'flex';
            if (membershipStartDate) {
                membershipStartDate.textContent = formatDate(latestActive.booking_date || latestActive.created_at);
            }
            const expiryDate = latestActive.expires_at ? new Date(latestActive.expires_at) : new Date(new Date(latestActive.booking_date || latestActive.created_at).getTime() + parseDurationToDays(latestActive.duration) * 86400000);
            membershipExpiryDate.textContent = formatDate(expiryDate);
            
            // Handle Dash Quick Actions
            const dashRenewBtn = document.getElementById('dashRenewBtn');
            const dashUpgradeBtn = document.getElementById('dashUpgradeBtn');
            if (dashRenewBtn) {
                // Determine if we should show renew for the latest active package
                // 1. Must NOT be already upgraded
                // 2. Must be verified
                // 3. Must be close to expiry (7 days or less)
                const currentPkg = packagesData.find(p => String(p.id) === String(latestActive.package_id));
                const packageTier = currentPkg ? packageHierarchy.indexOf((currentPkg.tag || 'Basic').toLowerCase().trim()) : -1;
                const isAlreadyUpgraded = currentUserTier > packageTier;
                const isCloseToExpiry = canRenewBooking(latestActive);
                
                dashRenewBtn.style.display = (!isAlreadyUpgraded && isCloseToExpiry) ? 'block' : 'none';
                dashRenewBtn.onclick = (e) => {
                    e.stopPropagation();
                    renewBooking(latestActive.id);
                };
            }
            if (dashUpgradeBtn) {
                const isUpgradable = canUpgradeBooking(latestActive);
                const currentPkg = packagesData.find(p => String(p.id) === String(latestActive.package_id));
                let isAlreadyUpgraded = false;
                
                if (currentPkg) {
                    const packageTier = packageHierarchy.indexOf((currentPkg.tag || 'Basic').toLowerCase().trim());
                    // currentUserTier is calculated at the beginning of updateStats
                    isAlreadyUpgraded = currentUserTier > packageTier;
                }

                if (isAlreadyUpgraded) {
                    dashUpgradeBtn.style.display = 'block';
                    dashUpgradeBtn.style.background = 'rgba(34, 197, 94, 0.05)';
                    dashUpgradeBtn.style.color = '#22c55e';
                    dashUpgradeBtn.style.border = '1px solid rgba(34, 197, 94, 0.1)';
                    dashUpgradeBtn.style.opacity = '0.8';
                    dashUpgradeBtn.style.cursor = 'default';
                    dashUpgradeBtn.disabled = true;
                    dashUpgradeBtn.innerHTML = '<i class="fas fa-check"></i> Upgraded';
                    dashUpgradeBtn.onclick = null;
                } else {
                    dashUpgradeBtn.style.display = isUpgradable ? 'block' : 'none';
                    dashUpgradeBtn.style.opacity = '';
                    dashUpgradeBtn.style.cursor = 'pointer';
                    dashUpgradeBtn.disabled = false;
                    dashUpgradeBtn.innerHTML = '<i class="fas fa-arrow-up"></i> Upgrade';
                    dashUpgradeBtn.onclick = (e) => {
                        e.stopPropagation();
                        openUpgradeModal(latestActive.package_id);
                    };
                }
            }
        }

        if (statTrainer) {
            statTrainer.textContent = latestActive.trainer_name || 'Not Assigned';
        }

        // Show/Hide Coach Hub Float
        const coachFloat = document.querySelector('.coach-corner-float');
        if (coachFloat) {
            // Show if they have a trainer assigned
            coachFloat.style.display = latestActive.trainer_id ? 'flex' : 'none';
        }

        // Update Sidebar Badge
        if (sidebarMemberBadge) {
            sidebarMemberBadge.style.display = 'inline-flex';
        }

        // Update Profile Membership Badge
        if (profileMembershipBadge) {
            profileMembershipBadge.style.display = 'block';
            profileMembershipValue.textContent = activePackageNames.join(', ');
            profileMembershipStatus.textContent = 'Active';
            profileMembershipStatus.className = 'status-badge status-verified';
            profileMembershipPlan.textContent = `${latestActive.package_name || latestActive.package} Plan`;
            
            // Show expiry date
            if (profileMembershipExpiryDate && profileMembershipExpiryRow) {
                profileMembershipExpiryRow.style.display = 'flex';
                const profileMembershipStartDate = document.getElementById('profileMembershipStartDate');
                if (profileMembershipStartDate) {
                    profileMembershipStartDate.textContent = formatDate(latestActive.booking_date || latestActive.created_at);
                }
                const expiryDate = latestActive.expires_at ? new Date(latestActive.expires_at) : new Date(new Date(latestActive.booking_date || latestActive.created_at).getTime() + parseDurationToDays(latestActive.duration) * 86400000);
                profileMembershipExpiryDate.textContent = formatDate(expiryDate);
            }
        }
    } else {
        // No active subscriptions — check if they had any expired ones
        if (membershipStatus) {
            membershipStatus.textContent = hasExpired ? 'Expired' : 'None';
            membershipStatus.className = 'stat-value ' + (hasExpired ? 'status-rejected' : '');
            membershipStatus.style.color = '';
        }
        
        // Hide expiry date on dashboard card
        if (membershipExpiry) {
            membershipExpiry.style.display = 'none';
        }
        
        if (statTrainer) {
            statTrainer.textContent = 'None';
        }

        // Hide Coach Hub Float
        const coachFloat = document.querySelector('.coach-corner-float');
        if (coachFloat) coachFloat.style.display = 'none';

        // Hide Sidebar Badge
        if (sidebarMemberBadge) {
            sidebarMemberBadge.style.display = 'none';
        }

        // Update Profile Membership Badge for expired/none
        if (profileMembershipBadge) {
            if (hasExpired) {
                profileMembershipBadge.style.display = 'block';
                profileMembershipValue.textContent = 'Expired Member';
                profileMembershipStatus.textContent = 'Expired';
                profileMembershipStatus.className = 'status-badge status-rejected';
                
                // Hide expiry row if expired
                if (profileMembershipExpiryRow) {
                    profileMembershipExpiryRow.style.display = 'none';
                }
                
                // Get the last expired booking to show what plan they had
                const lastExpired = [...userBookings]
                    .filter(b => b.status === 'expired' || b.status === 'verified')
                    .sort((a, b) => new Date(b.booking_date || b.created_at) - new Date(a.booking_date || a.created_at))[0];
                
                if (lastExpired) {
                    profileMembershipPlan.textContent = `Previous Plan: ${lastExpired.package_name || lastExpired.package}`;
                }
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

// Save initial measurements from survey
async function saveInitialMeasurements(weight, height) {
    try {
        const response = await fetch('../../api/users/update-profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: userData.name, // Required by API
                contact: userData.contact || '',
                address: userData.address || '',
                weight: weight,
                height: height
            })
        });
        
        const result = await response.json();
        if (result.success) {
            // Update local userData
            userData.weight = weight;
            userData.height = height;
            localStorage.setItem('userData', JSON.stringify(userData));
            console.log('Measurements saved successfully');
        }
    } catch (error) {
        console.error('Error saving measurements:', error);
    }
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
    
    // Validate contact number
    if (!validateContactNumber(contact)) {
        showNotification('Contact number must be exactly 11 digits (numbers only)', 'error');
        return;
    }
    
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
        selectable: false, // Users can't select, only view
        dayMaxEvents: 2,
        eventDidMount: function(info) {
            info.el.style.borderRadius = '8px';
            info.el.style.border = 'none';
            info.el.style.padding = '2px 6px';
            info.el.style.fontWeight = '700';
            info.el.style.fontSize = '0.8rem';
            
            if (info.event.backgroundColor === '#3b82f6') {
                info.el.style.backgroundColor = 'rgba(59, 130, 246, 0.2)';
                info.el.style.color = '#3b82f6';
                info.el.style.borderLeft = '3px solid #3b82f6';
            } else if (info.event.backgroundColor === '#22c55e') {
                info.el.style.backgroundColor = 'rgba(34, 197, 94, 0.2)';
                info.el.style.color = '#22c55e';
                info.el.style.borderLeft = '3px solid #22c55e';
            } else if (info.event.backgroundColor === '#f59e0b') {
                info.el.style.backgroundColor = 'rgba(245, 158, 11, 0.2)';
                info.el.style.color = '#f59e0b';
                info.el.style.borderLeft = '3px solid #f59e0b';
            }
            
            if (info.event.extendedProps.type === 'rest_day') {
                info.el.classList.add('event-rest-day');
            }
        },
        eventClick: function(info) {
            showSessionDetails(info.event);
        },
        eventSources: [
            {
                url: '../../api/trainers/get-sessions.php?booking_id=' + bookingId,
                color: '#3b82f6'
            },
            {
                url: '../../api/trainers/get-progress-history.php?booking_id=' + bookingId + '&calendar=1',
                color: '#22c55e'
            },
            {
                events: [
                    {
                        title: '⭐ Subscription Started',
                        start: (userBookings.find(b => b.id == bookingId)?.booking_date || userBookings.find(b => b.id == bookingId)?.date || userBookings.find(b => b.id == bookingId)?.created_at || '').split(' ')[0],
                        allDay: true,
                        color: '#f59e0b',
                        display: 'block',
                        extendedProps: { type: 'milestone' }
                    }
                ]
            }
        ]
    });
    
    trainingCalendar.render();
}

function showSessionDetails(event) {
    const props = event.extendedProps;
    const isRestDay = props.type === 'rest_day';
    
    let content = `
        <div style="padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <div>
                    <h3 style="color: #fff; margin: 0; font-size: 1.5rem; font-weight: 800;">${event.title}</h3>
                    <div style="display: flex; gap: 12px; margin-top: 8px; color: rgba(255,255,255,0.5); font-size: 0.85rem;">
                        <span><i class="far fa-calendar-alt"></i> ${event.start.toLocaleDateString(undefined, {weekday: 'short', month: 'short', day: 'numeric'})}</span>
                        ${!event.allDay ? `<span><i class="far fa-clock"></i> ${event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>` : ''}
                    </div>
                </div>
                <span class="status-badge" style="background: ${isRestDay ? 'rgba(239, 68, 68, 0.1)' : 'rgba(59, 130, 246, 0.1)'}; color: ${isRestDay ? '#ef4444' : '#3b82f6'}; border: 1px solid currentColor; padding: 6px 12px; border-radius: 8px; font-weight: 800; font-size: 0.75rem;">
                    ${props.type.replace('_', ' ').toUpperCase()}
                </span>
            </div>
            
            ${props.exercise_details && props.exercise_details.length > 0 ? `
                <div style="margin-bottom: 24px;">
                    <h4 style="color: rgba(255,255,255,0.4); margin-bottom: 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Workout Lineup</h4>
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        ${props.exercise_details.map(ex => `
                            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 16px; display: flex; gap: 16px; align-items: center;">
                                <div style="width: 64px; height: 64px; border-radius: 12px; background: #111; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); flex-shrink: 0;">
                                    <img src="${ex.image_url || '../../assets/img/exercise-placeholder.jpg'}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='../../assets/img/exercise-placeholder.jpg'">
                                </div>
                                <div style="flex: 1;">
                                    <h5 style="color: #fff; margin: 0 0 6px 0; font-size: 1rem; font-weight: 700;">${ex.name}</h5>
                                    <div style="display: flex; gap: 12px; font-size: 0.8rem; color: var(--primary); font-weight: 800;">
                                        <span>${ex.sets} SETS</span>
                                        <span style="color: rgba(255,255,255,0.2)">|</span>
                                        <span>${ex.reps} REPS</span>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : (isRestDay ? `
                <div style="text-align: center; padding: 40px 20px; background: rgba(239, 68, 68, 0.05); border-radius: 20px; border: 1px dashed rgba(239, 68, 68, 0.2);">
                    <i class="fas fa-bed" style="font-size: 2.5rem; color: #ef4444; margin-bottom: 16px; opacity: 0.5;"></i>
                    <p style="color: #ef4444; font-weight: 700; margin: 0;">Recovery is key! Enjoy your rest day.</p>
                </div>
            ` : '')}
            
            ${props.notes ? `
                <div style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: 16px; border-left: 4px solid var(--primary);">
                    <h4 style="color: rgba(255,255,255,0.4); margin-bottom: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;">Coach's Guidance</h4>
                    <p style="font-size: 0.9rem; color: rgba(255,255,255,0.7); line-height: 1.6; margin: 0;">${props.notes}</p>
                </div>
            ` : ''}
        </div>
    `;

    // Use a high-quality modal wrapper
    const modalId = 'sessionDetailsModal_' + Date.now();
    const modalOverlay = document.createElement('div');
    modalOverlay.className = 'modal-overlay active';
    modalOverlay.id = modalId;
    modalOverlay.style.zIndex = '10000';
    modalOverlay.innerHTML = `
        <div class="modal" style="max-width: 550px; background: #080808; border: 1px solid rgba(255,255,255,0.1); border-radius: 28px; overflow: hidden; animation: modalFadeIn 0.3s ease;">
            <div style="position: absolute; top: 20px; right: 20px; z-index: 1;">
                <button onclick="document.getElementById('${modalId}').remove()" style="background: rgba(255,255,255,0.05); border: none; width: 36px; height: 36px; border-radius: 10px; color: #fff; cursor: pointer; transition: 0.2s;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 0;">${content}</div>
            <div style="padding: 20px 24px; background: rgba(255,255,255,0.02); border-top: 1px solid rgba(255,255,255,0.05); text-align: right;">
                <button class="btn btn-primary" onclick="document.getElementById('${modalId}').remove()" style="padding: 10px 24px; border-radius: 12px; font-weight: 700;">Got it, Coach!</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modalOverlay);
    
    // Close on overlay click
    modalOverlay.onclick = (e) => {
        if (e.target === modalOverlay) modalOverlay.remove();
    };
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
        dayMaxEvents: 2,
        eventSources: [
            // Source 1: The actual trainer-scheduled sessions
            {
                url: '../../api/trainers/get-sessions.php?booking_id=' + bookingId,
                method: 'GET'
            },
            // Source 2: The static subscription milestone events (Expiry only)
            async function(fetchInfo, successCallback, failureCallback) {
                const b = userBookings.find(b => b.id == bookingId);
                if (!b) return successCallback([]);
                
                const milestones = [];
                const startStr = b.booking_date || b.date || b.created_at;
                
                // Expiry Milestone
                if (b.duration) {
                    const days = parseDurationToDays(b.duration);
                    if (days > 1) {
                        const parts = startStr.split('-');
                        const startDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                        const endDate = new Date(startDate);
                        endDate.setDate(endDate.getDate() + days);
                        
                        milestones.push({
                            title: `Expiry`,
                            start: formatDateISO(endDate),
                            allDay: true,
                            color: '#ef4444',
                            classNames: ['event-milestone-expiry'],
                            extendedProps: { type: 'expiry', ...b }
                        });

                        milestones.push({
                            start: startStr,
                            end: formatDateISO(new Date(endDate.getTime() + 86400000)),
                            display: 'background',
                            color: 'rgba(34, 197, 94, 0.08)',
                            allDay: true
                        });
                    }
                }
                successCallback(milestones);
            }
        ],
        eventClick: function(info) {
            const type = info.event.extendedProps.type;
            if (type !== 'booking' && type !== 'expiry') {
                showSessionDetails(info.event);
            }
        },
        eventDidMount: function(info) {
            if (info.event.extendedProps.type === 'rest_day') {
                info.el.classList.add('event-rest-day');
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

async function loadModalPlan(bookingId, packageId = null) {
    const content = document.getElementById('modalPlanContent');
    try {
        // 1. Try to fetch trainer-assigned plan first
        const response = await fetch(`../../api/trainers/get-member-plan.php?booking_id=${bookingId}`);
        const data = await response.json();
        
        if (data.success && data.data.exercises && data.data.exercises.length > 0) {
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
            return;
        }

        // 2. Fallback to package-default exercises if packageId is provided
        if (packageId) {
            const pkgResponse = await fetch(`../../api/packages/get-exercises.php?package_id=${packageId}`);
            const pkgData = await pkgResponse.json();
            
            if (pkgData.success && pkgData.data && pkgData.data.length > 0) {
                content.innerHTML = pkgData.data.map(ex => `
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
                return;
            }
        }

        content.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">This plan has general gym access. No specific exercises assigned yet.</div>';
    } catch (error) {
        console.error('Error loading plan:', error);
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

// ===== Coach's Corner Hub (Floating Action) =====
let coachCalendar = null;

async function updateCoachHubIndicator() {
    const activeWithTrainer = userBookings.find(b => isBookingActive(b) && b.trainer_id);
    const badge = document.getElementById('coachHubBadge');
    if (!activeWithTrainer || !badge) {
        if (badge) badge.style.display = 'none';
        return;
    }

    try {
        // Fetch upcoming sessions for this booking
        const response = await fetch(`../../api/trainers/get-sessions.php?booking_id=${activeWithTrainer.id}&upcoming=1`);
        const sessions = await response.json();
        
        if (Array.isArray(sessions) && sessions.length > 0) {
            badge.textContent = sessions.length;
            badge.style.display = 'flex';
            const floatBtn = document.getElementById('coachHubFloat');
            if (floatBtn) floatBtn.title = `${sessions.length} Upcoming Session${sessions.length > 1 ? 's' : ''}`;
            
            // Pulse animation for the badge if there are new sessions
            badge.style.animation = 'none';
            badge.offsetHeight; // trigger reflow
            badge.style.animation = 'bounceIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
        } else {
            badge.style.display = 'none';
        }
    } catch (error) {
        console.error('Error updating Coach Hub indicator:', error);
    }
}

async function openCoachHub() {
    // Find the primary active booking with a trainer
    const activeWithTrainer = userBookings.find(b => isBookingActive(b) && b.trainer_id);
    
    if (!activeWithTrainer) {
        showNotification('This feature is only available for active trainer-assisted packages.', 'info');
        return;
    }

    const modal = document.getElementById('coachHubModal');
    if (!modal) return;

    // Set global context if needed
    currentViewingBookingId = activeWithTrainer.id;

    // Populate Dates in Hub
    const hubDatesRow = document.getElementById('hubSubscriptionDates');
    const hubStart = document.getElementById('hubStartDate');
    const hubExpiry = document.getElementById('hubExpiryDate');
    
    if (hubDatesRow && hubStart && hubExpiry) {
        hubDatesRow.style.display = 'flex';
        hubStart.textContent = formatDate(activeWithTrainer.booking_date || activeWithTrainer.date || activeWithTrainer.created_at);
        
        const expDate = activeWithTrainer.expires_at ? new Date(activeWithTrainer.expires_at) : new Date(new Date(activeWithTrainer.booking_date || activeWithTrainer.created_at).getTime() + parseDurationToDays(activeWithTrainer.duration) * 86400000);
        hubExpiry.textContent = formatDate(expDate);
    }

    // Initial Load
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Small delay to ensure modal transition has started
    setTimeout(() => {
        switchHubTab('calendar');
    }, 50);
}

function closeCoachHub() {
    const modal = document.getElementById('coachHubModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

async function switchHubTab(tabId) {
    // Update button states
    document.querySelectorAll('.hub-tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('onclick').includes(`'${tabId}'`)) {
            btn.classList.add('active');
        }
    });

    // Update content visibility
    document.querySelectorAll('.hub-tab-content').forEach(content => {
        content.style.display = 'none';
    });

    const target = document.getElementById(`hub${tabId.charAt(0).toUpperCase() + tabId.slice(1)}`);
    if (target) target.style.display = 'block';

    // Fetch data based on tab
    const activeBooking = userBookings.find(b => isBookingActive(b) && b.trainer_id);
    if (!activeBooking) return;

    if (tabId === 'calendar') {
        initCoachCalendar(activeBooking.id);
    } else if (tabId === 'progress') {
        loadHubProgress(activeBooking.id);
    } else if (tabId === 'plans') {
        loadHubPlans(activeBooking.id);
    } else if (tabId === 'guidance') {
        loadHubGuidance(activeBooking.userId || activeBooking.user_id || (JSON.parse(localStorage.getItem('userData'))?.id));
    }
}

async function initCoachCalendar(bookingId) {
    const el = document.getElementById('userCoachCalendar');
    if (!el) return;

    if (coachCalendar) {
        coachCalendar.destroy();
    }

    coachCalendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek'
        },
        height: '100%',
        themeSystem: 'standard',
        dayMaxEvents: 2,
        eventSources: [
            // Source 1: The actual trainer-scheduled sessions
            {
                url: '../../api/trainers/get-sessions.php?booking_id=' + bookingId,
                method: 'GET'
            },
            // Source 2: The static subscription milestone events
            async function(fetchInfo, successCallback, failureCallback) {
                const b = userBookings.find(b => b.id == bookingId);
                if (!b) return successCallback([]);
                
                const milestones = [];
                const startStr = b.booking_date || b.date || b.created_at;
                const pkgName = b.package_name || b.package || 'Plan';
                
                 // Expiry Milestone
                 if (b.duration) {
                     const days = parseDurationToDays(b.duration);
                     if (days > 1) {
                         const parts = startStr.split('-');
                         const startDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                         const endDate = new Date(startDate);
                         endDate.setDate(endDate.getDate() + days);
                         
                         milestones.push({
                             title: `Expiry`,
                             start: formatDateISO(endDate),
                             allDay: true,
                             color: '#ef4444',
                             classNames: ['event-milestone-expiry'],
                             extendedProps: { type: 'expiry', ...b }
                         });

                        milestones.push({
                            start: startStr,
                            end: formatDateISO(new Date(endDate.getTime() + 86400000)),
                            display: 'background',
                            color: 'rgba(34, 197, 94, 0.08)',
                            allDay: true
                        });
                    }
                }
                successCallback(milestones);
            }
        ],
        eventClick: (info) => {
            const type = info.event.extendedProps.type;
            if (type === 'booking' || type === 'expiry') {
                viewBookingDetails(bookingId);
            } else {
                showSessionDetails(info.event);
            }
        },
        eventDidMount: (info) => {
            if (info.event.extendedProps.type === 'rest_day') {
                info.el.classList.add('event-rest-day');
            }
        }
    });

    coachCalendar.render();
    
    // Multiple update attempts to ensure rendering after modal animation completes
    setTimeout(() => coachCalendar.updateSize(), 100);
    setTimeout(() => coachCalendar.updateSize(), 300);
    setTimeout(() => coachCalendar.updateSize(), 500);
}

async function loadHubProgress(bookingId) {
    const list = document.getElementById('hubProgressList');
    if (!list) return;

    list.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading progress...</div>';

    try {
        const resp = await fetch(`../../api/trainers/get-progress-history.php?booking_id=${bookingId}`);
        const data = await resp.json();

        if (data.success && data.data.length > 0) {
            list.innerHTML = data.data.map(log => `
                <div class="coach-card">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                        <span style="color: var(--primary); font-weight: 700; font-size: 0.85rem;"><i class="fas fa-calendar-alt"></i> ${formatDate(log.logged_at)}</span>
                        ${log.weight ? `<span style="font-weight: 900; color: #fff; background: rgba(255,255,255,0.05); padding: 4px 12px; border-radius: 8px;"><i class="fas fa-weight"></i> ${log.weight} kg</span>` : ''}
                    </div>
                    <p style="color: rgba(255,255,255,0.7); font-size: 0.95rem; line-height: 1.6; margin-bottom: 12px;">${log.remarks || 'No remarks provided.'}</p>
                    <div style="font-size: 0.7rem; color: rgba(255,255,255,0.3); text-align: right;">Verified by Coach ${log.trainer_name}</div>
                </div>
            `).join('');
        } else {
            list.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 60px; color: rgba(255,255,255,0.2);"><i class="fas fa-chart-line" style="font-size: 3rem; margin-bottom: 20px; display: block;"></i><p>No progress logs yet. Your coach will log your milestones here.</p></div>';
        }
    } catch (e) {
        list.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #ef4444;">Failed to load progress.</div>';
    }
}

async function loadHubPlans(bookingId) {
    const list = document.getElementById('hubPlansList');
    if (!list) return;

    list.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading workout plan...</div>';

    try {
        const resp = await fetch(`../../api/trainers/get-member-plan.php?booking_id=${bookingId}`);
        const data = await resp.json();

        if (data.success && data.data.exercises.length > 0) {
            list.innerHTML = data.data.exercises.map(ex => `
                <div class="coach-card" style="display: flex; gap: 20px; align-items: center;">
                    <div style="width: 80px; height: 80px; border-radius: 16px; background: #111; overflow: hidden; border: 1px solid rgba(255,255,255,0.05);">
                        <img src="${ex.image_url || '../../assets/img/exercise-placeholder.jpg'}" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <div style="flex: 1;">
                        <h4 style="color: #fff; margin-bottom: 6px;">${ex.name}</h4>
                        <div style="display: flex; gap: 15px; font-size: 0.85rem; color: var(--primary); font-weight: 700;">
                            <span><i class="fas fa-redo"></i> ${ex.sets} Sets</span>
                            <span><i class="fas fa-running"></i> ${ex.reps} Reps</span>
                        </div>
                        ${ex.notes ? `<p style="margin-top: 8px; font-size: 0.8rem; color: rgba(255,255,255,0.4); font-style: italic;">Note: ${ex.notes}</p>` : ''}
                    </div>
                </div>
            `).join('');
        } else {
            list.innerHTML = '<div style="text-align: center; padding: 60px; color: rgba(255,255,255,0.2);"><i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 20px; display: block;"></i><p>Your coach hasn\'t assigned a customized plan yet.</p></div>';
        }
    } catch (e) {
        list.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;">Failed to load plan.</div>';
    }
}

async function loadHubGuidance(memberId) {
    const tipsList = document.getElementById('hubTipsList');
    const foodList = document.getElementById('hubFoodList');
    
    if (tipsList) tipsList.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    if (foodList) foodList.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
        // Fetch Tips
        const tipsResp = await fetch(`../../api/trainers/get-tips.php?member_id=${memberId}`);
        const tipsData = await tipsResp.json();
        if (tipsData.success && tipsData.data.length > 0) {
            tipsList.innerHTML = tipsData.data.map(t => `
                <div style="padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem; line-height: 1.5; margin-bottom: 4px;">${t.tip_text}</p>
                    <span style="font-size: 0.7rem; color: rgba(255,255,255,0.2);"><i class="far fa-clock"></i> ${formatDate(t.created_at)}</span>
                </div>
            `).join('');
        } else {
            tipsList.innerHTML = '<p style="color: rgba(255,255,255,0.2); font-size: 0.85rem;">No tips from your coach yet.</p>';
        }

        // Fetch Food
        const foodResp = await fetch(`../../api/trainers/get-food.php?member_id=${memberId}`);
        const foodData = await foodResp.json();
        if (foodData.success && foodData.data.length > 0) {
            foodList.innerHTML = foodData.data.map(f => `
                <div style="padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span style="color: var(--primary); font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">${f.meal_type}</span>
                        ${f.calories ? `<span style="color: #fff; font-size: 0.75rem; font-weight: 700;">${f.calories} kcal</span>` : ''}
                    </div>
                    <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem; line-height: 1.5;">${f.food_items}</p>
                </div>
            `).join('');
        } else {
            foodList.innerHTML = '<p style="color: rgba(255,255,255,0.2); font-size: 0.85rem;">No food recommendations yet.</p>';
        }
    } catch (e) {
        console.error('Error loading guidance:', e);
    }
}

// Show comprehensive booking summary with receipt, sessions, and progress
async function showBookingSummary(bookingId) {
    const booking = userBookings.find(b => String(b.id) === String(bookingId));
    if (!booking) return;
    
    // Show loading state
    showNotification('Loading booking summary...', 'info');
    
    try {
        // Fetch sessions for this booking
        let sessions = [];
        if (booking.status === 'verified') {
            try {
                const resp = await fetch(`../../api/trainers/get-sessions.php?booking_id=${booking.id}`);
                sessions = await resp.json();
                if (!Array.isArray(sessions)) sessions = [];
            } catch (e) {
                console.error('Error fetching sessions:', e);
                sessions = [];
            }
        }
        
        // Calculate statistics
        const totalSessions = sessions.filter(s => s.type !== 'rest_day').length;
        const restDays = sessions.filter(s => s.type === 'rest_day').length;
        const completedSessions = sessions.filter(s => s.type !== 'rest_day' && new Date(s.date) <= new Date()).length;
        
        // Create summary HTML
        const summaryContent = `
            <div style="padding: 32px; background: var(--dark-card);">
                <!-- Header -->
                <div style="text-align: center; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--dark-border);">
                    <h2 style="color: #fff; margin-bottom: 8px; font-size: 1.5rem;">Booking Summary & Receipt</h2>
                    <p style="color: var(--dark-text-secondary);">REF: #${String(booking.id).padStart(6, '0')}</p>
                </div>
                
                <!-- Booking Information -->
                <div style="margin-bottom: 32px;">
                    <h3 style="color: #fff; margin-bottom: 16px; font-size: 1.1rem;">
                        <i class="fas fa-receipt" style="margin-right: 8px; color: var(--primary);"></i>
                        Booking Information
                    </h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div style="padding: 16px; background: rgba(255,255,255,0.02); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                            <div style="font-size: 0.8rem; color: var(--dark-text-secondary); margin-bottom: 4px;">Package</div>
                            <div style="color: #fff; font-weight: 700;">${booking.package_name || booking.package}</div>
                        </div>
                        <div style="padding: 16px; background: rgba(255,255,255,0.02); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                            <div style="font-size: 0.8rem; color: var(--dark-text-secondary); margin-bottom: 4px;">Amount Paid</div>
                            <div style="color: var(--primary); font-weight: 700;">₱${parseFloat(booking.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                        </div>
                        <div style="padding: 16px; background: rgba(255,255,255,0.02); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                            <div style="font-size: 0.8rem; color: var(--dark-text-secondary); margin-bottom: 4px;">Booking Date</div>
                            <div style="color: #fff; font-weight: 700;">${formatDate(booking.booking_date || booking.date || booking.createdAt)}</div>
                        </div>
                        <div style="padding: 16px; background: rgba(255,255,255,0.02); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                            <div style="font-size: 0.8rem; color: var(--dark-text-secondary); margin-bottom: 4px;">Status</div>
                            <div style="font-weight: 700;">${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}</div>
                        </div>
                    </div>
                </div>
                
                <!-- Session Statistics -->
                ${sessions.length > 0 ? `
                <div style="margin-bottom: 32px;">
                    <h3 style="color: #fff; margin-bottom: 16px; font-size: 1.1rem;">
                        <i class="fas fa-chart-bar" style="margin-right: 8px; color: var(--primary);"></i>
                        Training Progress
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                        <div style="padding: 20px; background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05)); border-radius: 12px; border: 1px solid rgba(34, 197, 94, 0.2); text-align: center;">
                            <div style="font-size: 2rem; font-weight: 800; color: #22c55e; margin-bottom: 4px;">${totalSessions}</div>
                            <div style="font-size: 0.8rem; color: var(--dark-text-secondary);">Total Sessions</div>
                        </div>
                        <div style="padding: 20px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); border-radius: 12px; border: 1px solid rgba(59, 130, 246, 0.2); text-align: center;">
                            <div style="font-size: 2rem; font-weight: 800; color: #3b82f6; margin-bottom: 4px;">${completedSessions}</div>
                            <div style="font-size: 0.8rem; color: var(--dark-text-secondary);">Completed</div>
                        </div>
                        <div style="padding: 20px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); border-radius: 12px; border: 1px solid rgba(245, 158, 11, 0.2); text-align: center;">
                            <div style="font-size: 2rem; font-weight: 800; color: #f59e0b; margin-bottom: 4px;">${restDays}</div>
                            <div style="font-size: 0.8rem; color: var(--dark-text-secondary);">Rest Days</div>
                        </div>
                    </div>
                </div>
                ` : ''}
                
                <!-- Recent Sessions -->
                ${sessions.length > 0 ? `
                <div style="margin-bottom: 32px;">
                    <h3 style="color: #fff; margin-bottom: 16px; font-size: 1.1rem;">
                        <i class="fas fa-calendar-check" style="margin-right: 8px; color: var(--primary);"></i>
                        Recent Sessions
                    </h3>
                    <div style="max-height: 300px; overflow-y: auto;">
                        ${sessions.slice(0, 10).map(session => `
                            <div style="padding: 12px; margin-bottom: 8px; background: rgba(255,255,255,0.02); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="color: #fff; font-weight: 600; margin-bottom: 4px;">${session.title}</div>
                                    <div style="font-size: 0.8rem; color: var(--dark-text-secondary);">${formatDate(session.date)}</div>
                                </div>
                                <div style="padding: 4px 12px; background: ${session.type === 'rest_day' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(34, 197, 94, 0.1)'}; color: ${session.type === 'rest_day' ? '#f59e0b' : '#22c55e'}; border-radius: 12px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">
                                    ${session.type === 'rest_day' ? 'Rest Day' : 'Training'}
                                </div>
                            </div>
                        `).join('')}
                        ${sessions.length > 10 ? `
                            <div style="text-align: center; padding: 12px; color: var(--dark-text-secondary); font-size: 0.8rem;">
                                ... and ${sessions.length - 10} more sessions
                            </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}
                
                <!-- Notes -->
                ${booking.notes ? `
                <div style="margin-bottom: 32px;">
                    <h3 style="color: #fff; margin-bottom: 16px; font-size: 1.1rem;">
                        <i class="fas fa-sticky-note" style="margin-right: 8px; color: var(--primary);"></i>
                        Notes
                    </h3>
                    <div style="padding: 16px; background: rgba(255,255,255,0.02); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                        <div style="color: var(--dark-text-secondary);">${booking.notes}</div>
                    </div>
                </div>
                ` : ''}
                
                <!-- Footer -->
                <div style="text-align: center; padding-top: 24px; border-top: 1px solid var(--dark-border);">
                    <div style="font-size: 0.8rem; color: var(--dark-text-secondary); margin-bottom: 16px;">
                        Generated on ${formatDate(new Date())}
                    </div>
                    <button class="btn btn-primary" onclick="printBookingSummary(${booking.id})" style="background: var(--primary); color: var(--dark-bg); border: none; padding: 12px 24px; border-radius: 8px; font-weight: 700;">
                        <i class="fas fa-print" style="margin-right: 8px;"></i>
                        Print Summary
                    </button>
                </div>
            </div>
        `;
        
        // Show in modal
        const modal = document.createElement('div');
        modal.className = 'modal-overlay active';
        modal.innerHTML = `
            <div class="modal" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
                <div class="modal-header" style="padding: 24px 24px 16px; border: none; background: transparent; display: flex; justify-content: space-between; align-items: flex-start;">
                    <div></div>
                    <button class="close-modal" onclick="this.closest('.modal-overlay').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" style="padding: 0;">
                    ${summaryContent}
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
        
    } catch (error) {
        console.error('Error loading booking summary:', error);
        showNotification('Error loading booking summary', 'error');
    }
}

// Print booking summary
function printBookingSummary(bookingId) {
    const booking = userBookings.find(b => String(b.id) === String(bookingId));
    if (!booking) return;
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
        showNotification('Please allow pop-ups to print the receipt', 'error');
        return;
    }
    
    // Fetch sessions data for the receipt
    fetch(`../../api/trainers/get-sessions.php?booking_id=${booking.id}`)
        .then(response => response.json())
        .then(sessions => {
            if (!Array.isArray(sessions)) sessions = [];
            
            // Calculate statistics
            const totalSessions = sessions.filter(s => s.type !== 'rest_day').length;
            const restDays = sessions.filter(s => s.type === 'rest_day').length;
            const completedSessions = sessions.filter(s => s.type !== 'rest_day' && new Date(s.date) <= new Date()).length;
            
            // Create printable HTML document
            const printContent = `
<!DOCTYPE html>
<html>
<head>
    <title>Booking Receipt #${String(booking.id).padStart(6, '0')}</title>
    <style>
        @page {
            margin: 20mm;
            size: A4;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }
        
        .header h1 {
            margin: 0 0 5px 0;
            font-size: 24px;
            font-weight: bold;
        }
        
        .header p {
            margin: 0;
            font-size: 14px;
            color: #666;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .info-box {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .info-label {
            font-size: 11px;
            color: #666;
            margin-bottom: 3px;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        .info-value {
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }
        
        .stats-section {
            margin-bottom: 25px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            text-align: center;
            padding: 15px;
            border: 2px solid #333;
            border-radius: 8px;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ddd;
        }
        
        .session-list {
            margin-bottom: 20px;
        }
        
        .session-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            margin-bottom: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        
        .session-info {
            flex: 1;
        }
        
        .session-title {
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .session-date {
            font-size: 11px;
            color: #666;
        }
        
        .session-type {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .type-training {
            background: #e8f5e8;
            color: #2e7d2e;
        }
        
        .type-rest {
            background: #fff3cd;
            color: #856404;
        }
        
        .notes-section {
            margin-top: 25px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 5px;
            border-left: 4px solid #333;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 11px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>BOOKING RECEIPT & SUMMARY</h1>
        <p>Reference #: ${String(booking.id).padStart(6, '0')}</p>
        <p>Generated on ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
    </div>
    
    <div class="info-grid">
        <div class="info-box">
            <div class="info-label">Package</div>
            <div class="info-value">${booking.package_name || booking.package}</div>
        </div>
        <div class="info-box">
            <div class="info-label">Amount Paid</div>
            <div class="info-value">₱${parseFloat(booking.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
        </div>
        <div class="info-box">
            <div class="info-label">Booking Date</div>
            <div class="info-value">${formatDate(booking.booking_date || booking.date || booking.createdAt)}</div>
        </div>
        <div class="info-box">
            <div class="info-label">Status</div>
            <div class="info-value">${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}</div>
        </div>
    </div>
    
    <div class="stats-section">
        <div class="section-title">TRAINING PROGRESS STATISTICS</div>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-number">${totalSessions}</div>
                <div class="stat-label">Total Sessions</div>
            </div>
            <div class="stat-box">
                <div class="stat-number">${completedSessions}</div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-box">
                <div class="stat-number">${restDays}</div>
                <div class="stat-label">Rest Days</div>
            </div>
        </div>
    </div>
    
    ${sessions.length > 0 ? `
    <div class="session-section">
        <div class="section-title">SESSION ACTIVITIES</div>
        <div class="session-list">
            ${sessions.map(session => `
                <div class="session-item">
                    <div class="session-info">
                        <div class="session-title">${session.title}</div>
                        <div class="session-date">${formatDate(session.date)}</div>
                    </div>
                    <div class="session-type ${session.type === 'rest_day' ? 'type-rest' : 'type-training'}">
                        ${session.type === 'rest_day' ? 'Rest Day' : 'Training'}
                    </div>
                </div>
            `).join('')}
        </div>
    </div>
    ` : ''}
    
    ${booking.notes ? `
    <div class="notes-section">
        <div class="section-title">NOTES</div>
        <div>${booking.notes}</div>
    </div>
    ` : ''}
    
    <div class="footer">
        <p>This is an official receipt of your booking and training activities.</p>
        <p>Thank you for choosing our gym! Keep up the great work! 💪</p>
    </div>
</body>
</html>`;
            
            // Write content to the new window
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Wait for content to load, then print
            printWindow.onload = function() {
                setTimeout(() => {
                    printWindow.print();
                    printWindow.close();
                }, 500);
            };
        })
        .catch(error => {
            console.error('Error fetching sessions for receipt:', error);
            showNotification('Error generating receipt', 'error');
        });
}

// View booking details (keeping original function for other uses)
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
    if ((booking.status === 'verified' || booking.status === 'pending') && booking.duration) {
        expiryContainer.style.display = 'block';
        const expiryDate = booking.expires_at ? new Date(booking.expires_at) : new Date(new Date(booking.booking_date || booking.created_at).getTime() + parseDurationToDays(booking.duration) * 86400000);
        expiryValue.textContent = formatDate(expiryDate);
        
        // Add remaining days indicator if active
        const isActive = isBookingActive(booking);
        if (isActive) {
            const diffTime = expiryDate.getTime() - new Date().getTime();
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            let remainingText = '';
            if (diffDays > 0) remainingText = ` (${diffDays} days left)`;
            else if (diffDays === 0) remainingText = ` (Expires today)`;
            expiryValue.textContent += remainingText;
        }
    } else {
        expiryContainer.style.display = 'none';
    }

    // Handle Trainer
    const trainerContainer = document.getElementById('detailTrainerContainer');
    const trainerValue = document.getElementById('detailTrainer').querySelector('span');
    
    if (booking.trainer_name) {
        trainerContainer.style.display = 'block';
        trainerValue.textContent = booking.trainer_name;
    } else {
        trainerContainer.style.display = 'none';
    }

    // Always load the Hub data (it will fallback to package-defaults if no trainer plan exists)
    currentViewingBookingId = booking.id;
    initModalCalendar(booking.id);
    loadModalPlan(booking.id, booking.package_id);
    loadModalDiet(booking.userId || booking.user_id || userData.id);
    loadModalTips(booking.userId || booking.user_id || userData.id);
    
    // Reset to Info tab
    switchModalTab('info');

    // Handle Notes / Rejection Reason
    const notesSection = document.getElementById('detailNotesSection');
    const notesTitle = notesSection.querySelector('span');
    const notesContent = document.getElementById('detailNotes');
    
    if (booking.notes && booking.notes.trim() !== '') {
        notesSection.style.display = 'block';
        notesContent.textContent = booking.notes;
        
        if (booking.status === 'rejected') {
            notesSection.style.background = 'rgba(239, 68, 68, 0.05)';
            notesSection.style.borderLeft = '4px solid #ef4444';
            if (notesTitle) {
                notesTitle.textContent = 'REJECTION REASON';
                notesTitle.style.color = '#ef4444';
            }
        } else {
            notesSection.style.background = ''; // Use CSS default
            notesSection.style.borderLeft = ''; // Use CSS default
            if (notesTitle) {
                notesTitle.textContent = 'ADMIN NOTES';
                notesTitle.style.color = ''; // Use CSS default
            }
        }
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

    // Handle Quick Actions in Hub
    const detailRenewBtn = document.getElementById('detailRenewBtn');
    const detailUpgradeBtn = document.getElementById('detailUpgradeBtn');
    
    if (detailRenewBtn) {
        const isAlreadyUpgraded = checkIsAlreadyUpgraded(booking);
        const canRenew = (booking.status === 'verified') && !isAlreadyUpgraded && canRenewBooking(booking);
        detailRenewBtn.style.display = canRenew ? 'flex' : 'none';
        detailRenewBtn.onclick = () => {
            closeBookingDetailsModal();
            renewBooking(booking.id);
        };
    }
    
    if (detailUpgradeBtn) {
        const isUpgradable = canUpgradeBooking(booking);
        const isAlreadyUpgraded = checkIsAlreadyUpgraded(booking);

        if (isAlreadyUpgraded) {
            detailUpgradeBtn.style.display = 'flex';
            detailUpgradeBtn.style.background = 'rgba(34, 197, 94, 0.05)';
            detailUpgradeBtn.style.color = '#22c55e';
            detailUpgradeBtn.style.border = '1px solid rgba(34, 197, 94, 0.1)';
            detailUpgradeBtn.style.opacity = '0.8';
            detailUpgradeBtn.style.cursor = 'default';
            detailUpgradeBtn.disabled = true;
            detailUpgradeBtn.innerHTML = '<i class="fas fa-check"></i><span>Already Upgraded</span>';
            detailUpgradeBtn.onclick = null;
        } else {
            detailUpgradeBtn.style.display = isUpgradable ? 'flex' : 'none';
            detailUpgradeBtn.style.opacity = '';
            detailUpgradeBtn.style.cursor = '';
            detailUpgradeBtn.disabled = false;
            detailUpgradeBtn.innerHTML = '<i class="fas fa-arrow-up"></i><span>Upgrade Tier</span>';
            detailUpgradeBtn.onclick = () => {
                closeBookingDetailsModal();
                openUpgradeModal(booking.package_id);
            };
        }
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
    checkStepValid();
}

function toggleSurveyOption(element, category, value) {
    if (!surveyData[category]) surveyData[category] = [];
    
    const index = surveyData[category].indexOf(value);
    if (index > -1) {
        surveyData[category].splice(index, 1);
        element.classList.remove('selected');
    } else {
        surveyData[category].push(value);
        element.classList.add('selected');
    }
    
    checkStepValid();
}

function nextSurveyStep() {
    if (currentSurveyStep < 5) {
        // Collect inputs if on Step 1
        if (currentSurveyStep === 1) {
            surveyData.age = document.getElementById('surveyAge').value;
            surveyData.height = document.getElementById('surveyHeight').value;
            surveyData.weight = document.getElementById('surveyWeight').value;
            surveyData.sex = document.getElementById('surveySex').value;
            surveyData.medical_conditions = document.getElementById('surveyMedical').value || 'None';
        }
        
        // Collect inputs if on Step 4
        if (currentSurveyStep === 4) {
            surveyData.injuries_limitations = document.getElementById('surveyInjuries').value || 'None';
        }

        // Move to next step
        document.querySelector(`.survey-step[data-step="${currentSurveyStep}"]`).classList.remove('active');
        currentSurveyStep++;
        document.querySelector(`.survey-step[data-step="${currentSurveyStep}"]`).classList.add('active');
        
        // Update progress bar
        const progress = (currentSurveyStep / 5) * 100;
        document.getElementById('surveyProgress').style.width = `${progress}%`;
        
        // Show/Hide back button
        document.getElementById('surveyBackBtn').style.display = 'block';

        // Update button text for last step
        if (currentSurveyStep === 5) {
            document.getElementById('surveyNextBtn').innerHTML = '<span>Get My Plan</span> <i class="fas fa-check"></i>';
        } else {
            document.getElementById('surveyNextBtn').innerHTML = '<span>Next Step</span> <i class="fas fa-arrow-right"></i>';
        }
        
        // Check if next step is already valid (e.g., if user went back)
        checkStepValid();
    } else {
        // Survey complete
        finishSurvey();
    }
}

function prevSurveyStep() {
    if (currentSurveyStep > 1) {
        document.querySelector(`.survey-step[data-step="${currentSurveyStep}"]`).classList.remove('active');
        currentSurveyStep--;
        document.querySelector(`.survey-step[data-step="${currentSurveyStep}"]`).classList.add('active');
        
        const progress = (currentSurveyStep / 5) * 100;
        document.getElementById('surveyProgress').style.width = `${progress}%`;
        
        if (currentSurveyStep === 1) {
            document.getElementById('surveyBackBtn').style.display = 'none';
        }
        
        document.getElementById('surveyNextBtn').innerHTML = '<span>Next Step</span> <i class="fas fa-arrow-right"></i>';
        checkStepValid();
    }
}

function skipSurvey() {
    document.getElementById('surveyModal').classList.remove('active');
    showNotification('Survey skipped. You can always view our packages in the sidebar!', 'info');
}

async function finishSurvey() {
    try {
        // Prepare focus areas as string
        const focusAreasString = Array.isArray(surveyData.focus_areas) ? surveyData.focus_areas.join(', ') : surveyData.focus_areas;
        
        const response = await fetch('../../api/users/save-questionnaire.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ...surveyData,
                focus_areas: focusAreasString
            })
        });
        
        const result = await response.json();
        if (result.success) {
            document.getElementById('surveyModal').classList.remove('active');
            
            // Calculate recommendation
            calculateRecommendation();
        } else {
            showNotification('Error saving profile: ' + result.message, 'warning');
        }
    } catch (error) {
        console.error('Error finishing survey:', error);
        showNotification('Connection error while saving profile', 'warning');
    }
}

function calculateRecommendation() {
    // Recommendation Logic based on Active Packages
    let recommendedPackage = null;
    
    const getPackageByName = (name) => {
        return packagesData.find(pkg => pkg.name.toLowerCase().includes(name.toLowerCase()));
    };

    // Determine preferred package based on survey
    let preferredKeyword = "";
    
    // Strategy:
    // 1. If user wants a trainer and it's muscle gain/weight loss, recommend something intensive
    // 2. Based on workout days
    if (surveyData.primary_goal === 'Gain muscle' || surveyData.primary_goal === 'Lose weight') {
        if (surveyData.workout_days_per_week === '5+ days') {
            preferredKeyword = "Monthly"; // Assuming monthly is the premium/intensive one
        } else {
            preferredKeyword = "Weekly";
        }
    } else {
        preferredKeyword = "Pass"; // General fitness
    }

    // Fallback logic
    recommendedPackage = getPackageByName(preferredKeyword);
    
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
    
    const editUploadArea = document.getElementById('editFileUploadArea');
    if (editUploadArea) {
        editUploadArea.addEventListener('click', () => {
            const actInput = document.getElementById('editReceiptFile');
            if (actInput) actInput.click();
        });
    }
    
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

    // Drag and drop for Edit Modal
    if (editUploadArea) {
        editUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            editUploadArea.style.borderColor = 'var(--primary)';
            editUploadArea.style.background = 'var(--glass)';
        });
        
        editUploadArea.addEventListener('dragleave', () => {
            if (!selectedEditFile) {
                editUploadArea.style.borderColor = '';
                editUploadArea.style.background = '';
            }
        });
        
        editUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            const file = e.dataTransfer.files[0];
            if (file && (file.type.startsWith('image/') || file.type === 'application/pdf')) {
                document.getElementById('editReceiptFile').files = e.dataTransfer.files;
                handleEditFileSelect({ target: { files: [file] } });
            }
        });
    }
    
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
        updateCoachHubIndicator();
        
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

// Contact number validation function
function validateContactNumber(contact) {
    // Remove any non-digit characters for validation
    const cleanContact = contact.replace(/\D/g, '');
    
    // Check if it's exactly 11 digits
    return cleanContact.length === 11 && /^\d{11}$/.test(cleanContact);
}

// Add real-time input validation for contact number
document.addEventListener('DOMContentLoaded', function() {
    const contactInput = document.getElementById('bookingContact');
    
    if (contactInput) {
        // Restrict input to numbers only and limit to 11 digits
        contactInput.addEventListener('input', function(e) {
            // Remove any non-digit characters
            let value = e.target.value.replace(/\D/g, '');
            
            // Limit to 11 digits
            if (value.length > 11) {
                value = value.slice(0, 11);
            }
            
            // Update the input value
            e.target.value = value;
            
            // Visual feedback
            const isValid = value.length === 11;
            if (value.length > 0) {
                if (isValid) {
                    e.target.style.borderColor = '#22c55e';
                    e.target.style.boxShadow = '0 0 0 2px rgba(34, 197, 94, 0.1)';
                } else {
                    e.target.style.borderColor = '#ef4444';
                    e.target.style.boxShadow = '0 0 0 2px rgba(239, 68, 68, 0.1)';
                }
            } else {
                e.target.style.borderColor = '';
                e.target.style.boxShadow = '';
            }
        });
        
        // Prevent pasting non-numeric content
        contactInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            const numericOnly = paste.replace(/\D/g, '').slice(0, 11);
            e.target.value = numericOnly;
            
            // Trigger input event to apply validation styling
            e.target.dispatchEvent(new Event('input'));
        });
        
        // Prevent non-numeric key presses
        contactInput.addEventListener('keypress', function(e) {
            // Allow backspace, delete, tab, escape, enter
            if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }
            
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    }
});

// ===== My Trainer Section =====
let trainerSectionLoaded = false;

async function loadTrainerSection() {
    if (trainerSectionLoaded) return;
    trainerSectionLoaded = true;

    try {
        const res = await fetch('../../api/trainers/get-my-trainer.php');
        const data = await res.json();

        if (!data.success || !data.data) {
            document.getElementById('trainerNoData').style.display = 'block';
            document.getElementById('trainerData').style.display = 'none';
            return;
        }

        const t = data.data;
        window._trainerBookingId = t.booking_id;
        document.getElementById('trainerNoData').style.display = 'none';
        document.getElementById('trainerData').style.display = 'block';

        // Profile
        const photo = document.getElementById('trainerPhoto');
        photo.src = t.photo_url
            ? '../../' + t.photo_url.replace(/^\/?Fit\//, '').replace(/^\//, '')
            : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(t.trainer_name) + '&background=22c55e&color=fff&size=120';
        document.getElementById('trainerName').textContent = t.trainer_name || 'Your Trainer';
        document.getElementById('trainerSpec').textContent = '';
        document.getElementById('trainerBio').textContent = t.bio || 'No bio available.';
        document.getElementById('trainerContactVal').textContent = t.trainer_contact || 'N/A';
        document.getElementById('trainerEmailVal').textContent = t.trainer_email || 'N/A';
        document.getElementById('trainerClientsVal').textContent = t.total_clients || 0;
        document.getElementById('trainerPackageName').textContent = t.package_name || 'N/A';
        document.getElementById('trainerExpiry').textContent = t.expires_at
            ? new Date(t.expires_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
            : 'No expiry';

        // Availability
        const availEl = document.getElementById('trainerAvailability');
        if (availEl) {
            if (t.availability) {
                try {
                    const avail = JSON.parse(t.availability);
                    if (Array.isArray(avail)) {
                        availEl.innerHTML = avail.map(a => `<div style="margin-bottom:6px;"><i class="fas fa-check-circle" style="color:var(--primary);margin-right:6px;"></i>${a}</div>`).join('');
                    } else if (avail && typeof avail === 'object') {
                        const days = Array.isArray(avail.days) ? avail.days.join(', ') : (avail.days || '');
                        const hours = (avail.from && avail.until) ? `${avail.from} – ${avail.until}` : '';
                        availEl.innerHTML = `
                            ${days ? `<div style="margin-bottom:8px;"><i class="fas fa-calendar-week" style="color:var(--primary);margin-right:6px;"></i>${days}</div>` : ''}
                            ${hours ? `<div><i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i>${hours}</div>` : ''}
                        `;
                    } else {
                        availEl.textContent = t.availability;
                    }
                } catch {
                    availEl.textContent = t.availability;
                }
            } else {
                availEl.innerHTML = '<p style="color:var(--dark-text-secondary);">Not specified</p>';
            }
        }

        // Certifications
        const certEl = document.getElementById('trainerCertifications');
        if (certEl) {
            if (t.certifications) {
                try {
                    const certs = JSON.parse(t.certifications);
                    certEl.innerHTML = Array.isArray(certs)
                        ? certs.map(c => `<div style="margin-bottom:6px;"><i class="fas fa-medal" style="color:#f59e0b;margin-right:6px;"></i>${c}</div>`).join('')
                        : `<p>${t.certifications}</p>`;
                } catch {
                    certEl.textContent = t.certifications;
                }
            } else {
                certEl.innerHTML = '<p style="color:var(--dark-text-secondary);">No certifications listed</p>';
            }
        }

        // Sessions
        const sessEl = document.getElementById('trainerSessions');
        if (t.sessions && t.sessions.length > 0) {
            sessEl.innerHTML = t.sessions.map(s => `
                <div class="trainer-session-item">
                    <div class="trainer-session-date">
                        <span class="session-day">${new Date(s.session_date).toLocaleDateString('en-US', { weekday: 'short' })}</span>
                        <span class="session-num">${new Date(s.session_date).getDate()}</span>
                    </div>
                    <div class="trainer-session-details">
                        <strong>${s.title || 'Session'}</strong>
                        <span>${s.session_time ? s.session_time.slice(0,5) : ''} &bull; ${s.duration || ''} mins</span>
                        ${s.notes ? `<p style="font-size:0.78rem;color:var(--dark-text-secondary);margin-top:4px;">${s.notes}</p>` : ''}
                    </div>
                    <span class="status-badge status-${s.status || 'scheduled'}">${s.status || 'Scheduled'}</span>
                </div>
            `).join('');
        } else {
            sessEl.innerHTML = '<p style="color:var(--dark-text-secondary);text-align:center;padding:20px 0;">No upcoming sessions scheduled.</p>';
        }

        // Tips
        const tipsEl = document.getElementById('trainerTips');
        if (t.tips && t.tips.length > 0) {
            tipsEl.innerHTML = t.tips.map(tip => `
                <div class="trainer-tip-item">
                    <i class="fas fa-lightbulb" style="color:#f59e0b;margin-right:8px;"></i>
                    <div>
                        <p style="margin:0;font-size:0.88rem;">${tip.tip}</p>
                        <small style="color:var(--dark-text-secondary);">${new Date(tip.created_at).toLocaleDateString()}</small>
                    </div>
                </div>
            `).join('');
        } else {
            tipsEl.innerHTML = '<p style="color:var(--dark-text-secondary);text-align:center;padding:20px 0;">No tips from your trainer yet.</p>';
        }

        // Food
        const foodEl = document.getElementById('trainerFood');
        if (t.food && t.food.length > 0) {
            foodEl.innerHTML = t.food.map(f => `
                <div class="trainer-tip-item">
                    <i class="fas fa-utensils" style="color:var(--primary);margin-right:8px;"></i>
                    <div>
                        <p style="margin:0;font-size:0.88rem;">${f.recommendation}</p>
                        <small style="color:var(--dark-text-secondary);">${new Date(f.created_at).toLocaleDateString()}</small>
                    </div>
                </div>
            `).join('');
        } else {
            foodEl.innerHTML = '<p style="color:var(--dark-text-secondary);text-align:center;padding:20px 0;">No meal guidance yet.</p>';
        }

        // Progress
        const progEl = document.getElementById('trainerProgress');
        if (t.progress && t.progress.length > 0) {
            progEl.innerHTML = `
                <div class="trainer-progress-list">
                    ${t.progress.map(p => `
                        <div class="trainer-progress-item">
                            <div class="progress-weight">${p.weight_kg ? p.weight_kg + ' kg' : '—'}</div>
                            <div class="progress-info">
                                <span class="progress-date">${new Date(p.logged_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                                ${p.height ? `<span style="font-size:0.78rem;color:var(--dark-text-secondary);margin-left:8px;">${p.height} cm</span>` : ''}
                                ${p.logged_by === 'user' ? `<span style="font-size:0.72rem;background:rgba(34,197,94,0.1);color:#22c55e;padding:1px 7px;border-radius:99px;margin-left:6px;">Self-logged</span>` : ''}
                                ${p.remarks ? `<p style="margin:4px 0 0;font-size:0.8rem;color:var(--dark-text-secondary);">${p.remarks}</p>` : ''}
                            </div>
                            ${p.photo_url ? `<img src="../../${p.photo_url}" alt="Progress photo" style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid var(--dark-border);cursor:pointer;flex-shrink:0;" onclick="openProgressPhoto('../../${p.photo_url}')">` : ''}
                        </div>
                    `).join('')}
                </div>`;
        } else {
            progEl.innerHTML = '<p style="color:var(--dark-text-secondary);text-align:center;padding:20px 0;">No progress logs recorded yet.</p>';
        }

    } catch (err) {
        console.error('Error loading trainer section:', err);
        document.getElementById('trainerNoData').style.display = 'block';
        document.getElementById('trainerData').style.display = 'none';
    }
}

// ===== User Progress Self-Log =====
function toggleProgressForm() {
    const form = document.getElementById('progressLogForm');
    const btn = document.getElementById('logProgressBtn');
    const visible = form.style.display !== 'none';
    form.style.display = visible ? 'none' : 'block';
    if (!visible) {
        // Set today's date as default
        document.getElementById('userProgressDate').value = new Date().toISOString().split('T')[0];
    }
}

// Photo preview
document.addEventListener('change', function(e) {
    if (e.target.id === 'userProgressPhoto') {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                document.getElementById('progressPhotoImg').src = ev.target.result;
                document.getElementById('progressPhotoPreview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    }
});

async function submitUserProgress() {
    const weight   = document.getElementById('userProgressWeight').value;
    const height   = document.getElementById('userProgressHeight').value;
    const date     = document.getElementById('userProgressDate').value;
    const remarks  = document.getElementById('userProgressRemarks').value;
    const photoFile = document.getElementById('userProgressPhoto').files[0];
    const btn      = document.getElementById('submitProgressBtn');

    if (!date) { alert('Please select a date.'); return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    try {
        let photo_url = null;

        // Upload photo first if provided
        if (photoFile) {
            const formData = new FormData();
            formData.append('photo', photoFile);
            const upRes = await fetch('../../api/upload/progress-photo.php', { method: 'POST', body: formData });
            const upData = await upRes.json();
            if (upData.success) {
                photo_url = upData.data.url;
            }
        }

        // Get booking_id from loaded trainer data
        const bookingId = window._trainerBookingId;
        if (!bookingId) { alert('No active booking found.'); return; }

        const res = await fetch('../../api/trainers/log-progress-user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                booking_id: bookingId,
                weight: weight || null,
                height: height || null,
                remarks,
                photo_url,
                logged_at: date
            })
        });

        const data = await res.json();
        if (data.success) {
            toggleProgressForm();
            // Reset form
            document.getElementById('userProgressWeight').value = '';
            document.getElementById('userProgressHeight').value = '';
            document.getElementById('userProgressRemarks').value = '';
            document.getElementById('userProgressPhoto').value = '';
            document.getElementById('progressPhotoPreview').style.display = 'none';
            // Reload trainer section
            trainerSectionLoaded = false;
            loadTrainerSection();
        } else {
            alert(data.message || 'Failed to save progress.');
        }
    } catch (err) {
        console.error(err);
        alert('Error saving progress.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Progress';
    }
}

function openProgressPhoto(url) {
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer;';
    overlay.innerHTML = `<img src="${url}" style="max-width:90vw;max-height:90vh;border-radius:12px;object-fit:contain;">`;
    overlay.onclick = () => overlay.remove();
    document.body.appendChild(overlay);
}
