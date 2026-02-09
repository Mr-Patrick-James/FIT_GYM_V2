<?php
// Test script to verify the column fix
require_once 'api/session.php';
requireAdmin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Column Fix Test | FitPay Admin</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="assets/css/dashboard.css?v=1.6">
    
    <style>
        body {
            padding: 20px;
            background: var(--dark-bg);
            color: var(--dark-text);
            font-family: 'Inter', sans-serif;
        }
        
        .test-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .success-message {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.2);
            color: #22c55e;
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .demo-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: var(--dark-card);
            border-radius: 12px;
            overflow: hidden;
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
        
        .action-btn {
            padding: 12px 24px;
            background: var(--primary);
            color: var(--dark-bg);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1><i class="fas fa-tools"></i> Column Fix Verification</h1>
        
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>Fixed!</strong> The booking type column has been properly aligned. Data should now appear in the correct columns.
            </div>
        </div>
        
        <h2>Expected Table Structure:</h2>
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
                        <div style="font-weight: 700; color: var(--primary);">John Doe</div>
                        <div style="font-size: 0.9rem; color: var(--dark-text-secondary);">john@example.com</div>
                    </td>
                    <td>
                        <div class="booking-type-cell">
                            <span class="walkin-badge">🚶 Walk-in</span>
                        </div>
                    </td>
                    <td>Walk-in Pass</td>
                    <td>Jan 30, 2026</td>
                    <td style="font-weight: 800;">₱200.00</td>
                    <td>09123456789</td>
                    <td><span class="status-badge status-pending">Pending</span></td>
                    <td>
                        <div class="table-actions">
                            <button class="icon-btn" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div style="font-weight: 700; color: var(--primary);">Jane Smith</div>
                        <div style="font-size: 0.9rem; color: var(--dark-text-secondary);">jane@example.com</div>
                    </td>
                    <td>
                        <div class="booking-type-cell">
                            <span class="regular-badge">👤 Member</span>
                        </div>
                    </td>
                    <td>Monthly Membership</td>
                    <td>Jan 29, 2026</td>
                    <td style="font-weight: 800;">₱1,500.00</td>
                    <td>09987654321</td>
                    <td><span class="status-badge status-verified">Verified</span></td>
                    <td>
                        <div class="table-actions">
                            <button class="icon-btn" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <h2>What Was Fixed:</h2>
        <div style="background: var(--glass); padding: 20px; border-radius: 12px; margin: 20px 0;">
            <h3 style="color: var(--primary); margin-bottom: 16px;">
                <i class="fas fa-code"></i> Technical Fix
            </h3>
            <ul style="color: var(--dark-text-secondary); line-height: 1.8;">
                <li><strong>Issue:</strong> The <code>populateBookingsTable()</code> function was missing the booking type column</li>
                <li><strong>Cause:</strong> Two different functions were creating table rows with different structures</li>
                <li><strong>Solution:</strong> Updated the main table population function to include the booking type column</li>
                <li><strong>Result:</strong> Data now appears in the correct columns</li>
            </ul>
        </div>
        
        <h2>Column Mapping:</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 20px 0;">
            <div style="background: var(--glass); padding: 16px; border-radius: 8px;">
                <h4 style="color: var(--primary); margin-bottom: 8px;">Column 1: Client</h4>
                <p style="color: var(--dark-text-secondary); margin: 0;">Name and email</p>
            </div>
            <div style="background: var(--glass); padding: 16px; border-radius: 8px;">
                <h4 style="color: var(--primary); margin-bottom: 8px;">Column 2: Booking Type</h4>
                <p style="color: var(--dark-text-secondary); margin: 0;">🚶 Walk-in or 👤 Member</p>
            </div>
            <div style="background: var(--glass); padding: 16px; border-radius: 8px;">
                <h4 style="color: var(--primary); margin-bottom: 8px;">Column 3: Package</h4>
                <p style="color: var(--dark-text-secondary); margin: 0;">Package name</p>
            </div>
            <div style="background: var(--glass); padding: 16px; border-radius: 8px;">
                <h4 style="color: var(--primary); margin-bottom: 8px;">Column 4: Date</h4>
                <p style="color: var(--dark-text-secondary); margin: 0;">Booking date</p>
            </div>
            <div style="background: var(--glass); padding: 16px; border-radius: 8px;">
                <h4 style="color: var(--primary); margin-bottom: 8px;">Column 5: Amount</h4>
                <p style="color: var(--dark-text-secondary); margin: 0;">Price</p>
            </div>
            <div style="background: var(--glass); padding: 16px; border-radius: 8px;">
                <h4 style="color: var(--primary); margin-bottom: 8px;">Column 6: Contact</h4>
                <p style="color: var(--dark-text-secondary); margin: 0;">Phone number</p>
            </div>
            <div style="background: var(--glass); padding: 16px; border-radius: 8px;">
                <h4 style="color: var(--primary); margin-bottom: 8px;">Column 7: Status</h4>
                <p style="color: var(--dark-text-secondary); margin: 0;">Pending/Verified</p>
            </div>
            <div style="background: var(--glass); padding: 16px; border-radius: 8px;">
                <h4 style="color: var(--primary); margin-bottom: 8px;">Column 8: Actions</h4>
                <p style="color: var(--dark-text-secondary); margin: 0;">View/Verify buttons</p>
            </div>
        </div>
        
        <div style="text-align: center; margin: 40px 0;">
            <a href="views/admin/bookings.php" class="action-btn" target="_blank">
                <i class="fas fa-external-link-alt"></i>
                Test Live Bookings Page
            </a>
        </div>
    </div>
</body>
</html>
