<?php
// Test page for the new booking type column feature
require_once 'api/session.php';
requireAdmin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Type Column Test | FitPay Admin</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="assets/css/dashboard.css?v=1.6">
    
    <style>
        body {
            padding: 40px;
            background: var(--dark-bg);
            color: var(--dark-text);
            font-family: 'Inter', sans-serif;
        }
        
        .test-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .test-section {
            background: var(--dark-card);
            border-radius: var(--radius-lg);
            padding: 32px;
            margin-bottom: 32px;
            border: 1px solid var(--dark-border);
        }
        
        .test-section h2 {
            color: var(--primary);
            margin-bottom: 16px;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .demo-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .demo-table th {
            background: var(--glass);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--primary);
            border-bottom: 2px solid var(--dark-border);
        }
        
        .demo-table td {
            padding: 16px;
            border-bottom: 1px solid var(--dark-border);
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        
        .feature-card {
            background: var(--glass);
            border: 1px solid var(--dark-border);
            border-radius: var(--radius-md);
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
        }
        
        .feature-card h3 {
            color: var(--primary);
            margin-bottom: 12px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .feature-card p {
            color: var(--dark-text-secondary);
            font-size: 0.9rem;
            margin: 0;
        }
        
        .demo-buttons {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 24px;
        }
        
        .demo-btn {
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .demo-btn.primary {
            background: var(--primary);
            color: var(--dark-bg);
        }
        
        .demo-btn.secondary {
            background: var(--glass);
            color: var(--dark-text);
            border: 1px solid var(--dark-border);
        }
        
        .demo-btn:hover {
            transform: translateY(-2px);
        }
        
        .comparison-table {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 24px;
        }
        
        .before-after {
            background: var(--glass);
            border: 1px solid var(--dark-border);
            border-radius: var(--radius-md);
            padding: 20px;
        }
        
        .before-after h4 {
            color: var(--primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .before h4 {
            color: var(--warning);
        }
        
        .after h4 {
            color: var(--success);
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-section">
            <h1><i class="fas fa-table-columns"></i> Booking Type Column Test</h1>
            <p>This page demonstrates the new booking type column that clearly distinguishes between walk-in customers and regular members.</p>
            
            <div class="demo-buttons">
                <a href="views/admin/bookings.php" class="demo-btn primary">
                    <i class="fas fa-eye"></i>
                    View Live Table
                </a>
                <a href="test-walkin-filtering.php" class="demo-btn secondary">
                    <i class="fas fa-filter"></i>
                    Test Filtering
                </a>
                <a href="test-walkin-functionality.php" class="demo-btn secondary">
                    <i class="fas fa-vial"></i>
                    Test Functionality
                </a>
            </div>
        </div>
        
        <div class="test-section">
            <h2><i class="fas fa-columns"></i> New Table Structure</h2>
            
            <div class="comparison-table">
                <div class="before-after before">
                    <h4><i class="fas fa-times-circle"></i> Before (Mixed Client Info)</h4>
                    <table class="demo-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Package</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="booking-info">
                                        <div class="booking-name">John Doe</div>
                                        <div class="booking-email">john@example.com</div>
                                        <span class="walkin-badge">🚶 Walk-in</span>
                                    </div>
                                </td>
                                <td>Walk-in Pass</td>
                                <td>Jan 30, 2026</td>
                                <td>₱200.00</td>
                                <td>09123456789</td>
                                <td><span class="status-badge pending">Pending</span></td>
                                <td>
                                    <button class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p style="margin-top: 12px; color: var(--warning);">
                        <i class="fas fa-exclamation-triangle"></i> Booking type mixed with client info - hard to scan
                    </p>
                </div>
                
                <div class="before-after after">
                    <h4><i class="fas fa-check-circle"></i> After (Dedicated Column)</h4>
                    <table class="demo-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Booking Type</th>
                                <th>Package</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="booking-info">
                                        <div class="booking-name">John Doe</div>
                                        <div class="booking-email">john@example.com</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="booking-type-cell">
                                        <span class="walkin-badge">🚶 Walk-in</span>
                                    </div>
                                </td>
                                <td>Walk-in Pass</td>
                                <td>Jan 30, 2026</td>
                                <td>₱200.00</td>
                                <td>09123456789</td>
                                <td><span class="status-badge pending">Pending</span></td>
                                <td>
                                    <button class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="booking-info">
                                        <div class="booking-name">Jane Smith</div>
                                        <div class="booking-email">jane@example.com</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="booking-type-cell">
                                        <span class="regular-badge">👤 Member</span>
                                    </div>
                                </td>
                                <td>Monthly Membership</td>
                                <td>Jan 29, 2026</td>
                                <td>₱1,500.00</td>
                                <td>09987654321</td>
                                <td><span class="status-badge verified">Verified</span></td>
                                <td>
                                    <button class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p style="margin-top: 12px; color: var(--success);">
                        <i class="fas fa-check-circle"></i> Clear, dedicated column with visual badges
                    </p>
                </div>
            </div>
        </div>
        
        <div class="test-section">
            <h2><i class="fas fa-sparkles"></i> Visual Improvements</h2>
            
            <div class="feature-grid">
                <div class="feature-card">
                    <h3><i class="fas fa-eye"></i> Clear Visual Distinction</h3>
                    <p>Dedicated booking type column with color-coded badges for instant recognition</p>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-walking"></i> Walk-in Badge</h3>
                    <p>Blue badge with walking emoji for walk-in customers</p>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-user"></i> Member Badge</h3>
                    <p>Green badge with user emoji for regular members</p>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-mobile-alt"></i> Responsive Design</h3>
                    <p>Column adapts properly on mobile devices</p>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-filter"></i> Filter Integration</h3>
                    <p>Works seamlessly with existing booking type filters</p>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-chart-bar"></i> Statistics Support</h3>
                    <p>Counts reflected in dedicated stat cards</p>
                </div>
            </div>
        </div>
        
        <div class="test-section">
            <h2><i class="fas fa-code"></i> Implementation Details</h2>
            
            <div style="background: var(--glass); padding: 20px; border-radius: var(--radius-md); margin-top: 24px;">
                <h3 style="color: var(--primary); margin-bottom: 16px;">
                    <i class="fas fa-database"></i> Backend Changes
                </h3>
                <ul style="color: var(--dark-text-secondary); line-height: 1.8;">
                    <li><strong>API Response:</strong> Added <code>is_walkin</code> flag to bookings API</li>
                    <li><strong>Database Logic:</strong> Walk-ins identified by <code>user_id = NULL</code></li>
                    <li><strong>Unified Endpoint:</strong> Single API returns both booking types</li>
                </ul>
                
                <h3 style="color: var(--primary); margin-bottom: 16px; margin-top: 24px;">
                    <i class="fas fa-palette"></i> Frontend Changes
                </h3>
                <ul style="color: var(--dark-text-secondary); line-height: 1.8;">
                    <li><strong>Table Header:</strong> Added "Booking Type" column</li>
                    <li><strong>JavaScript:</strong> Updated <code>renderBookingRow()</code> function</li>
                    <li><strong>CSS Styling:</strong> Added badge styles and responsive design</li>
                    <li><strong>Visual Badges:</strong> 🚶 Walk-in and 👤 Member badges</li>
                </ul>
                
                <h3 style="color: var(--primary); margin-bottom: 16px; margin-top: 24px;">
                    <i class="fas fa-cogs"></i> Features
                </h3>
                <ul style="color: var(--dark-text-secondary); line-height: 1.8;">
                    <li><strong>Instant Recognition:</strong> Visual badges for quick scanning</li>
                    <li><strong>Color Coding:</strong> Blue for walk-ins, green for members</li>
                    <li><strong>Centered Alignment:</strong> Badges centered in dedicated column</li>
                    <li><strong>Mobile Responsive:</strong> Adapts to smaller screens</li>
                </ul>
            </div>
        </div>
        
        <div class="test-section">
            <h2><i class="fas fa-keyboard"></i> User Experience Benefits</h2>
            
            <div style="background: rgba(34, 197, 94, 0.1); padding: 20px; border-radius: var(--radius-md); border-left: 4px solid #22c55e; margin-top: 24px;">
                <h3 style="color: #22c55e; margin-bottom: 16px;">
                    <i class="fas fa-trophy"></i> Key Improvements
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                    <div>
                        <h4 style="color: var(--dark-text); margin-bottom: 8px;">
                            <i class="fas fa-search"></i> Faster Scanning
                        </h4>
                        <p style="color: var(--dark-text-secondary); margin: 0;">
                            Staff can quickly identify booking types without reading client details
                        </p>
                    </div>
                    <div>
                        <h4 style="color: var(--dark-text); margin-bottom: 8px;">
                            <i class="fas fa-sort"></i> Better Organization
                        </h4>
                        <p style="color: var(--dark-text-secondary); margin: 0;">
                            Clear separation makes data more organized and professional
                        </p>
                    </div>
                    <div>
                        <h4 style="color: var(--dark-text); margin-bottom: 8px;">
                            <i class="fas fa-chart-line"></i> Improved Analytics
                        </h4>
                        <p style="color: var(--dark-text-secondary); margin: 0;">
                            Easy to visually assess walk-in vs member booking ratios
                        </p>
                    </div>
                    <div>
                        <h4 style="color: var(--dark-text); margin-bottom: 8px;">
                            <i class="fas fa-users"></i> Staff Efficiency
                        </h4>
                        <p style="color: var(--dark-text-secondary); margin: 0;">
                            Reduces cognitive load when managing mixed booking types
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="test-section">
            <h2><i class="fas fa-rocket"></i> Ready to Use!</h2>
            
            <p style="margin-bottom: 20px;">The booking type column is now fully implemented and ready for production use:</p>
            
            <div class="demo-buttons">
                <a href="views/admin/bookings.php" class="demo-btn primary" target="_blank">
                    <i class="fas fa-external-link-alt"></i>
                    Open Live Bookings Page
                </a>
                <button class="demo-btn secondary" onclick="createTestData()">
                    <i class="fas fa-plus"></i>
                    Create Test Data
                </button>
            </div>
            
            <div id="testResult" style="margin-top: 20px; display: none;">
                <!-- Test results will appear here -->
            </div>
        </div>
    </div>
    
    <script>
        function createTestData() {
            const resultDiv = document.getElementById('testResult');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = `
                <div style="background: rgba(59, 130, 246, 0.1); padding: 16px; border-radius: 8px; border-left: 4px solid #3b82f6;">
                    <h4 style="color: #3b82f6; margin-bottom: 8px;">
                        <i class="fas fa-info-circle"></i> Test Data Creation
                    </h4>
                    <p style="color: var(--dark-text-secondary); margin: 0;">
                        To create test data, go to the <a href="views/admin/bookings.php" target="_blank">bookings page</a> and:
                    </p>
                    <ol style="color: var(--dark-text-secondary); margin: 8px 0 0 20px;">
                        <li>Click "Walk-in Booking" to create walk-in customers</li>
                        <li>Regular member bookings are created through the user dashboard</li>
                        <li>Use the filters to see the booking type column in action</li>
                    </ol>
                </div>
            `;
        }
    </script>
</body>
</html>
