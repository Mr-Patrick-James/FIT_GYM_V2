<?php
require_once '../../api/session.php';
requireAdmin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings Management | FitPay Admin</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1.6">
    
    <!-- FullCalendar CDN -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    
    <style>
        /* Modern Calendar Styles */
        .view-toggle {
            display: flex;
            background: var(--dark-card);
            padding: 4px;
            border-radius: var(--radius-md);
            border: 1px solid var(--dark-border);
            margin-right: 12px;
        }
        
        .view-btn {
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            border: none;
            background: transparent;
            color: var(--dark-text-secondary);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .view-btn.active {
            background: var(--primary);
            color: var(--dark-bg);
        }
        
        #calendar-view {
            display: none;
            margin-top: 32px;
            background: var(--dark-card);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid var(--dark-border);
        }
        
        /* FullCalendar Customization */
        .fc {
            --fc-border-color: var(--dark-border);
            --fc-daygrid-event-dot-width: 8px;
            --fc-list-event-dot-width: 10px;
            --fc-neutral-bg-color: var(--dark-card);
            --fc-page-bg-color: var(--dark-bg);
            --fc-today-bg-color: rgba(255, 255, 255, 0.05);
            font-family: 'Inter', sans-serif;
        }
        
        .fc .fc-toolbar-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-text);
        }
        
        .fc .fc-button-primary {
            background-color: var(--dark-card);
            border-color: var(--dark-border);
            color: var(--dark-text);
            font-weight: 600;
            text-transform: capitalize;
            padding: 8px 16px;
        }
        
        .fc .fc-button-primary:hover {
            background-color: var(--dark-border);
            border-color: var(--dark-text-secondary);
        }
        
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background-color: var(--primary);
            border-color: var(--primary);
            color: var(--dark-bg);
        }
        
        .fc-theme-standard td, .fc-theme-standard th {
            border: 1px solid var(--dark-border);
        }
        
        .fc-daygrid-event {
            border-radius: 6px;
            padding: 2px 4px;
            font-size: 0.85rem;
            border: none;
        }
        
        .fc-v-event {
            background-color: var(--primary);
            border: none;
        }
        
        .fc-event-title {
            font-weight: 600;
        }
        
        .booking-event {
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .booking-event:hover {
            transform: scale(1.02);
        }
        
        .event-status-pending { background-color: var(--warning) !important; color: #000 !important; }
        .event-status-verified { background-color: var(--success) !important; color: #fff !important; }
        .event-status-rejected { background-color: var(--danger) !important; color: #fff !important; }
        
        .light-mode .fc {
            --fc-border-color: #e5e7eb;
            --fc-neutral-bg-color: #f8f9fa;
            --fc-page-bg-color: #ffffff;
            --fc-today-bg-color: rgba(0, 0, 0, 0.05);
        }
    </style>
    
    <!-- Apply theme immediately before page renders to prevent flash -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'light') {
                document.documentElement.classList.add('light-mode');
                if (document.body) {
                    document.body.classList.add('light-mode');
                }
            } else {
                document.documentElement.classList.remove('light-mode');
                if (document.body) {
                    document.body.classList.remove('light-mode');
                }
            }
        })();
    </script>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-btn" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo">
            <h1>FitPay</h1>
            <p>GYM MANAGEMENT</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a></li>
            <li><a href="bookings.php" class="active"><i class="fas fa-calendar-check"></i> <span>Bookings</span> <span class="badge" id="bookingsBadge">0</span></a></li>
            <li><a href="payments.php"><i class="fas fa-money-check"></i> <span>Payments</span></a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> <span>Members</span></a></li>
            <li><a href="packages.php"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="report.php"><i class="fas fa-file-invoice-dollar"></i> <span>Reports</span></a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
        </ul>
        
        <div class="admin-profile">
            <div class="admin-avatar"><?php 
                $adminName = $user['name'] ?? 'Admin';
                $initials = '';
                foreach(explode(' ', $adminName) as $word) {
                    if (!empty($word)) $initials .= strtoupper($word[0]);
                }
                echo htmlspecialchars(substr($initials, 0, 2));
            ?></div>
            <div class="admin-info">
                <h4><?php echo htmlspecialchars($adminName); ?></h4>
                <p>Gym Owner / Manager</p>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Bookings Management</h1>
                <p>View, filter, and manage all booking requests from members</p>
            </div>
            
            <div class="header-actions">
                <div class="view-toggle">
                    <button class="view-btn active" id="tableViewBtn" title="Table View">
                        <i class="fas fa-table"></i>
                        <span>Table</span>
                    </button>
                    <button class="view-btn" id="calendarViewBtn" title="Calendar View">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Calendar</span>
                    </button>
                </div>
                
                <button class="action-btn primary" onclick="openWalkinModal()">
                    <i class="fas fa-walking"></i>
                    <span>Walk-in Booking</span>
                </button>
                
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search bookings, members...">
                </div>
                
                <button class="action-btn notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge">0</span>
                </button>
                
                <button class="action-btn" title="Logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="totalTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="totalBookings">0</div>
                <div class="stat-label">Total Bookings</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-walking"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="walkinTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="walkinBookings">0</div>
                <div class="stat-label">Walk-in Customers</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="regularTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="regularBookings">0</div>
                <div class="stat-label">Regular Members</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="trend down">
                        <i class="fas fa-arrow-down"></i>
                        <span id="pendingTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="pendingBookings">0</div>
                <div class="stat-label">Pending Verification</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="verifiedTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="verifiedBookings">0</div>
                <div class="stat-label">Verified Bookings</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="trend">
                        <i class="fas fa-arrow-up"></i>
                        <span id="revenueTrend">0%</span>
                    </div>
                </div>
                <div class="stat-value" id="totalRevenue">₱0</div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <!-- Calendar View -->
        <div id="calendar-view">
            <div id="calendar"></div>
        </div>

        <!-- Filters and Actions -->
        <div class="content-card" id="table-filters" style="margin-top: 32px;">
            <div class="card-header">
                <h3>Filter & Sort</h3>
                <div class="card-actions">
                    <button class="card-btn" onclick="exportBookings()">
                        <i class="fas fa-download"></i>
                        <span>Export CSV</span>
                    </button>
                    <button class="card-btn primary" onclick="refreshBookings()">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>
            
            <div style="display: flex; gap: 16px; flex-wrap: wrap; padding: 20px;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--dark-text-secondary); font-size: 0.9rem; font-weight: 600;">Booking Type</label>
                    <select id="bookingTypeFilter" class="card-btn" style="width: 100%; padding: 12px 16px; cursor: pointer;">
                        <option value="all">All Bookings</option>
                        <option value="regular">Regular Members</option>
                        <option value="walkin">Walk-in Customers</option>
                    </select>
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--dark-text-secondary); font-size: 0.9rem; font-weight: 600;">Status Filter</label>
                    <select id="statusFilter" class="card-btn" style="width: 100%; padding: 12px 16px; cursor: pointer;">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="verified">Verified</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--dark-text-secondary); font-size: 0.9rem; font-weight: 600;">Sort By</label>
                    <select id="sortBy" class="card-btn" style="width: 100%; padding: 12px 16px; cursor: pointer;">
                        <option value="date-desc">Date (Newest First)</option>
                        <option value="date-asc">Date (Oldest First)</option>
                        <option value="amount-desc">Amount (High to Low)</option>
                        <option value="amount-asc">Amount (Low to High)</option>
                        <option value="name-asc">Name (A-Z)</option>
                        <option value="name-desc">Name (Z-A)</option>
                    </select>
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--dark-text-secondary); font-size: 0.9rem; font-weight: 600;">Date Range</label>
                    <select id="dateRange" class="card-btn" style="width: 100%; padding: 12px 16px; cursor: pointer;">
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Bookings Table -->
        <div class="content-card" id="bookings-table-container" style="margin-top: 32px;">
            <div class="card-header">
                <h3>All Bookings</h3>
                <div class="card-actions">
                    <span style="color: var(--dark-text-secondary); font-size: 0.9rem;">
                        Showing <strong id="showingCount">0</strong> of <strong id="totalCount">0</strong> bookings
                    </span>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Booking Type</th>
                            <th>Package</th>
                            <th>Date</th>
                            <th>Expiry</th>
                            <th>Amount</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bookingsTable">
                        <!-- Populated by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <div id="noBookingsMessage" style="display: none; text-align: center; padding: 60px 20px; color: var(--dark-text-secondary);">
                <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3 style="margin-bottom: 8px;">No bookings found</h3>
                <p>Bookings will appear here when members submit booking requests.</p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>
                <i class="fas fa-heart" style="color: var(--primary);"></i>
                © <?php echo date('Y'); ?> Martinez Fitness Gym • FitPay Management System v2.0
                <i class="fas fa-bolt" style="color: var(--primary);"></i>
            </p>
        </div>
    </main>

    <!-- Booking Details Modal -->
    <div class="modal-overlay" id="bookingModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Booking Details</h3>
                <button class="close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="detail-grid">
                    <div class="detail-group">
                        <label>Booking ID</label>
                        <div class="value" id="modalBookingId">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Booking Type</label>
                        <div class="value" id="modalBookingType">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Status</label>
                        <div class="value" id="modalStatus">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Client Name</label>
                        <div class="value" id="modalClientName">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Contact Number</label>
                        <div class="value" id="modalContact">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Email Address</label>
                        <div class="value" id="modalEmail">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Package Selected</label>
                        <div class="value" id="modalPackage">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Duration</label>
                        <div class="value" id="modalDuration">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Booking Date</label>
                        <div class="value" id="modalDate">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Expiry Date</label>
                        <div class="value" id="modalExpiry">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Payment Amount</label>
                        <div class="value" id="modalAmount">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Payment Method</label>
                        <div class="value" id="modalPaymentMethod">-</div>
                    </div>
                    <div class="detail-group">
                        <label>Created At</label>
                        <div class="value" id="modalCreatedAt">-</div>
                    </div>
                    <div class="detail-group" id="verifiedAtGroup" style="display: none;">
                        <label>Verified At</label>
                        <div class="value" id="modalVerifiedAt">-</div>
                    </div>
                    <div class="detail-group" id="notesGroup" style="display: none;">
                        <label>Notes</label>
                        <div class="value" id="modalNotes">-</div>
                    </div>
                </div>
                
                <div class="receipt-section" id="receiptSection" style="display: none;">
                    <h4><i class="fas fa-receipt"></i> Payment Receipt</h4>
                    <img src="" alt="Payment Receipt" class="receipt-image" id="modalReceipt">
                </div>
                
                <div class="modal-actions">
                    <button class="btn btn-secondary" id="rejectPaymentBtn" onclick="rejectPayment()">
                        <i class="fas fa-times"></i>
                        Reject Payment
                    </button>
                    <button class="btn btn-primary" id="verifyPaymentBtn" onclick="verifyPayment()">
                        <i class="fas fa-check"></i>
                        Verify Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Walk-in Booking Modal -->
    <div class="modal-overlay" id="walkinModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-walking"></i> New Walk-in Booking</h3>
                <button class="close-modal" onclick="closeWalkinModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="walkinForm">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customerName">Customer Name *</label>
                            <input type="text" id="customerName" name="customer_name" required>
                        </div>
                        <div class="form-group">
                            <label for="customerEmail">Email Address *</label>
                            <input type="email" id="customerEmail" name="customer_email" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customerContact">Contact Number *</label>
                            <input type="tel" id="customerContact" name="customer_contact" required>
                        </div>
                        <div class="form-group">
                            <label for="packageSelect">Package *</label>
                            <select id="packageSelect" name="package" required>
                                <option value="">Select Package</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bookingDate">Booking Date</label>
                            <input type="date" id="bookingDate" name="date">
                        </div>
                        <div class="form-group">
                            <label for="paymentMethod">Payment Method</label>
                            <select id="paymentMethod" name="payment_method">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="gcash">GCash</option>
                                <option value="paymaya">PayMaya</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="walkinNotes">Notes</label>
                        <textarea id="walkinNotes" name="notes" rows="3" placeholder="Additional notes..."></textarea>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeWalkinModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Walk-in
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Theme Script -->
    <script src="../../assets/js/theme.js"></script>
    <!-- Bookings Scripts -->
    <script src="../../assets/js/bookings.js"></script>
</body>
</html>
