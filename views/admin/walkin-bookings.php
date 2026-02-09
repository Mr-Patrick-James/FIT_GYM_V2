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
    <title>Walk-in Bookings | FitPay Admin</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1.6">
    
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
        
        <nav class="sidebar-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="members.php"><i class="fas fa-users"></i> Members</a></li>
                <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
                <li><a href="bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a></li>
                <li><a href="walkin-bookings.php" class="active"><i class="fas fa-walking"></i> Walk-ins</a></li>
                <li><a href="../user/dashboard.php"><i class="fas fa-home"></i> User View</a></li>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <div>
                    <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
            <a href="../../api/auth/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="page-header">
            <div class="header-content">
                <div>
                    <h1 class="page-title">Walk-in Bookings</h1>
                    <p class="page-subtitle">Manage walk-in customer transactions</p>
                </div>
                <button class="btn btn-primary" id="addWalkinBtn">
                    <i class="fas fa-plus"></i> New Walk-in
                </button>
            </div>
        </header>
        
        <!-- Filters Section -->
        <section class="filters-section">
            <div class="filters-container">
                <div class="filter-group">
                    <label for="statusFilter">Status:</label>
                    <select id="statusFilter">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="verified">Verified</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="searchFilter">Search:</label>
                    <input type="text" id="searchFilter" placeholder="Search by name, email, contact...">
                </div>
                
                <div class="filter-group">
                    <label for="dateFrom">From:</label>
                    <input type="date" id="dateFrom">
                </div>
                
                <div class="filter-group">
                    <label for="dateTo">To:</label>
                    <input type="date" id="dateTo">
                </div>
                
                <button class="btn btn-secondary" id="resetFilters">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
        </section>
        
        <!-- Bookings Table -->
        <section class="table-section">
            <div class="table-container">
                <div class="table-header">
                    <h2>Walk-in Transactions</h2>
                    <div class="table-actions">
                        <span class="record-count" id="recordCount">Loading...</span>
                    </div>
                </div>
                
                <div class="table-wrapper">
                    <table class="data-table" id="walkinTable">
                        <thead>
                            <tr>
                                <th data-sort="date-desc">
                                    Date <i class="fas fa-sort"></i>
                                </th>
                                <th data-sort="name-asc">
                                    Customer <i class="fas fa-sort"></i>
                                </th>
                                <th>Contact</th>
                                <th>Package</th>
                                <th data-sort="amount-desc">
                                    Amount <i class="fas fa-sort"></i>
                                </th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="walkinTableBody">
                            <tr>
                                <td colspan="8" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading walk-in bookings...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
    
    <!-- Walk-in Modal -->
    <div class="modal-overlay" id="walkinModal">
        <div class="modal">
            <div class="modal-header">
                <h3>New Walk-in Booking</h3>
                <button class="modal-close" id="closeWalkinModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="walkinForm">
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
                
                <!-- GCash Payment Details (Dynamic) -->
                <div id="gcashDetails" class="payment-method-details" style="display: none; background: rgba(0,0,0,0.05); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <h4 style="margin-bottom: 10px; font-size: 0.9rem;">GCash Payment Information</h4>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <div id="gcashQRContainer" style="width: 100px; height: 100px; background: #eee; border-radius: 4px; overflow: hidden;">
                            <!-- QR Code will be loaded here -->
                        </div>
                        <div>
                            <p style="margin: 0; font-size: 0.85rem;"><strong>Number:</strong> <span id="displayGcashNumber">0917-123-4567</span></p>
                            <p style="margin: 0; font-size: 0.85rem;"><strong>Name:</strong> <span id="displayGcashName">Martinez Fitness</span></p>
                            <p style="margin-top: 5px; font-size: 0.75rem; color: #666;">Scan QR or use number to pay via GCash.</p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="walkinNotes">Notes</label>
                    <textarea id="walkinNotes" name="notes" rows="3" placeholder="Additional notes..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="receiptUpload">Receipt (Optional)</label>
                    <input type="file" id="receiptUpload" accept="image/*">
                    <div class="file-preview" id="receiptPreview" style="display: none;">
                        <img id="receiptImage" src="" alt="Receipt">
                        <button type="button" class="btn btn-sm btn-danger" id="removeReceipt">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelWalkin">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveWalkin">
                        <i class="fas fa-save"></i> Save Walk-in
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Status Update Modal -->
    <div class="modal-overlay" id="statusModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Update Booking Status</h3>
                <button class="modal-close" id="closeStatusModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="statusForm">
                <input type="hidden" id="statusBookingId">
                
                <div class="form-group">
                    <label for="statusSelect">New Status:</label>
                    <select id="statusSelect" required>
                        <option value="pending">Pending</option>
                        <option value="verified">Verified</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="statusNotes">Notes:</label>
                    <textarea id="statusNotes" rows="3" placeholder="Reason for status change..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelStatus">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../../assets/js/walkin-bookings.js"></script>
</body>
</html>
