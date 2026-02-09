<?php
// Test page for the improved walk-in modal design
require_once 'api/session.php';
requireAdmin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Modal Design Test | FitPay Admin</title>
    
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
            max-width: 1200px;
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
        
        .test-section p {
            color: var(--dark-text-secondary);
            margin-bottom: 24px;
            line-height: 1.6;
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
        
        .changelog {
            background: var(--glass);
            border-left: 4px solid var(--primary);
            padding: 20px;
            border-radius: var(--radius-sm);
            margin-top: 24px;
        }
        
        .changelog h3 {
            color: var(--primary);
            margin-bottom: 12px;
        }
        
        .changelog ul {
            list-style: none;
            padding: 0;
        }
        
        .changelog li {
            padding: 8px 0;
            color: var(--dark-text-secondary);
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        
        .changelog li::before {
            content: '✓';
            color: var(--primary);
            font-weight: bold;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-section">
            <h1><i class="fas fa-walking"></i> Walk-in Modal Design Test</h1>
            <p>This page demonstrates the improved walk-in booking modal with enhanced scrolling, design, and user experience.</p>
            
            <div class="demo-buttons">
                <button class="demo-btn primary" onclick="openWalkinModal()">
                    <i class="fas fa-walking"></i>
                    Open Walk-in Modal
                </button>
                <a href="views/admin/bookings.php" class="demo-btn secondary">
                    <i class="fas fa-calendar-check"></i>
                    Go to Bookings Page
                </a>
                <a href="test-integrated-walkin.php" class="demo-btn secondary">
                    <i class="fas fa-vial"></i>
                    Run Integration Test
                </a>
            </div>
        </div>
        
        <div class="test-section">
            <h2><i class="fas fa-sparkles"></i> Design Improvements</h2>
            
            <div class="feature-grid">
                <div class="feature-card">
                    <h3><i class="fas fa-arrows-alt-v"></i> Enhanced Scrolling</h3>
                    <p>Custom scrollbar with smooth scrolling, better visibility, and responsive height management</p>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-palette"></i> Modern Design</h3>
                    <p>Improved form layout with better spacing, enhanced inputs, and professional styling</p>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-mobile-alt"></i> Responsive Design</h3>
                    <p>Fully responsive layout that works perfectly on mobile and tablet devices</p>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-magic"></i> Smooth Animations</h3>
                    <p>Enhanced button animations, loading states, and success feedback</p>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-upload"></i> Better File Upload</h3>
                    <p>Improved receipt upload with preview, drag-and-drop styling, and better UX</p>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-check-circle"></i> Loading States</h3>
                    <p>Visual feedback during form submission with loading spinners and disabled states</p>
                </div>
            </div>
        </div>
        
        <div class="test-section">
            <h2><i class="fas fa-code"></i> Technical Features</h2>
            
            <div class="changelog">
                <h3>What's New:</h3>
                <ul>
                    <li>Custom scrollbar styling with hover effects and smooth scrolling</li>
                    <li>Modal height limited to 85vh with proper overflow handling</li>
                    <li>Enhanced form layout with two-column grid for better space utilization</li>
                    <li>Improved file upload with dashed border and hover effects</li>
                    <li>Button animations with shimmer effects and hover states</li>
                    <li>Loading states with spinning indicators and disabled buttons</li>
                    <li>Success animations with pulse effects</li>
                    <li>Responsive design for mobile devices</li>
                    <li>Better focus states with glowing borders</li>
                    <li>Sticky footer for action buttons</li>
                </ul>
            </div>
        </div>
        
        <div class="test-section">
            <h2><i class="fas fa-keyboard"></i> Keyboard & Accessibility</h2>
            <p>The modal includes full keyboard navigation and accessibility features:</p>
            
            <div class="feature-grid">
                <div class="feature-card">
                    <h3><i class="fas fa-keyboard"></i> Tab Navigation</h3>
                    <p>Full keyboard support with proper tab order and focus management</p>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-times-circle"></i> Escape to Close</h3>
                    <p>Press Escape key to close the modal at any time</p>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-universal-access"></i> Screen Reader</h3>
                    <p>Proper ARIA labels and semantic HTML for accessibility</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Walk-in Modal (Same as in bookings page) -->
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
                    
                    <div class="form-group">
                        <label for="receiptUpload">Receipt (Optional)</label>
                        <input type="file" id="receiptUpload" accept="image/*">
                        <div class="file-preview" id="receiptPreview" style="display: none;">
                            <img id="receiptImage" src="" alt="Receipt">
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeReceipt()">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </div>
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
    
    <script src="assets/js/bookings.js"></script>
    
    <script>
        // Add keyboard support for closing modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeWalkinModal();
            }
        });
        
        // Add click outside to close
        document.getElementById('walkinModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeWalkinModal();
            }
        });
    </script>
</body>
</html>
