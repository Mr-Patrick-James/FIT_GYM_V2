<?php
require_once '../config.php';

// Start output buffering to catch any accidental output
ob_start();

// Allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $conn = getDBConnection();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ob_end_clean();
        sendResponse(false, 'Invalid request data', null, 400);
    }
    
    $bookingId = $input['booking_id'] ?? null;
    
    if (!$bookingId) {
        ob_end_clean();
        sendResponse(false, 'Booking ID is required', null, 400);
    }
    
    // Get booking details - use COALESCE to ensure we get a package name even if the join fails
    $sql = "SELECT b.*, COALESCE(p.name, b.package_name) as package_display_name, 
            COALESCE(b.amount, p.price, 0) as booking_amount
            FROM bookings b 
            LEFT JOIN packages p ON b.package_id = p.id 
            WHERE b.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        ob_end_clean();
        sendResponse(false, 'Booking not found', null, 404);
    }
    
    $booking = $result->fetch_assoc();
    
    // Use the display name and amount we selected
    $booking['package_name'] = $booking['package_display_name'] ?? $booking['package_name'] ?? 'Unknown Package';
    $booking['amount'] = $booking['booking_amount'] ?? $booking['amount'] ?? 0;
    
    // Get payment details
    $paymentSql = "SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1";
    $paymentStmt = $conn->prepare($paymentSql);
    $paymentStmt->bind_param("i", $bookingId);
    $paymentStmt->execute();
    $paymentResult = $paymentStmt->get_result();
    $payment = $paymentResult->fetch_assoc();
    
    // Generate receipt HTML
    $receiptHtml = generateWalkinReceiptHTML($booking, $payment);
    
    // Save receipt as HTML file
    $receiptFilename = 'receipt_' . $bookingId . '_' . date('Y-m-d_H-i-s') . '.html';
    $receiptPath = __DIR__ . '/../../uploads/receipts/' . $receiptFilename;
    
    // Create receipts directory if it doesn't exist
    $receiptsDir = __DIR__ . '/../../uploads/receipts';
    if (!is_dir($receiptsDir)) {
        @mkdir($receiptsDir, 0755, true);
    }
    
    // Save the receipt HTML file
    if (@file_put_contents($receiptPath, $receiptHtml)) {
        // Update booking record with receipt URL
        $updateSql = "UPDATE bookings SET receipt_url = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $dbReceiptPath = 'uploads/receipts/' . $receiptFilename;
        $updateStmt->bind_param("si", $dbReceiptPath, $bookingId);
        $updateStmt->execute();
        
        // Clear buffer before sending JSON
        if (ob_get_length()) ob_end_clean();
        
        sendResponse(true, 'Walk-in receipt generated successfully', [
            'receipt_html' => $receiptHtml,
            'receipt_url' => $dbReceiptPath,
            'receipt_filename' => $receiptFilename,
            'booking_id' => $bookingId
        ]);
    } else {
        if (ob_get_length()) ob_end_clean();
        sendResponse(false, 'Failed to save receipt file. Check folder permissions.', null, 500);
    }
    
} catch (Exception $e) {
    if (ob_get_length()) ob_end_clean();
    sendResponse(false, 'Error generating receipt: ' . $e->getMessage(), null, 500);
}

function generateWalkinReceiptHTML($booking, $payment) {
    $bookingId = $booking['id'] ?? 'N/A';
    
    // Handle dates safely
    try {
        $createdAt = !empty($booking['created_at']) ? $booking['created_at'] : 'now';
        $date = new DateTime($createdAt);
        $receiptDate = $date->format('M d, Y h:i A');
    } catch (Exception $e) {
        $receiptDate = date('M d, Y h:i A');
    }

    try {
        $bookingDateStr = !empty($booking['booking_date']) ? $booking['booking_date'] : 'now';
        $bookingDate = new DateTime($bookingDateStr);
        $formattedBookingDate = $bookingDate->format('M d, Y');
    } catch (Exception $e) {
        $formattedBookingDate = date('M d, Y');
    }
    
    $isWalkin = !isset($booking['user_id']) || is_null($booking['user_id']);
    $customerType = $isWalkin ? 'Walk-in Customer' : 'Member';
    
    $paymentMethod = $payment['payment_method'] ?? 'Cash';
    $paymentStatus = $payment['status'] ?? 'completed';
    $transactionId = $payment['transaction_id'] ?? 'TXN' . strtoupper(uniqid());
    
    $companyName = "MARTINEZ FITNESS";
    $companyAddress = "123 Fitness Street\nGym City, 1234\nTel: (123) 456-7890";
    
    // Safely get booking details with defaults
    $customerName = htmlspecialchars($booking['name'] ?? 'Guest');
    $customerEmail = htmlspecialchars($booking['email'] ?? 'N/A');
    $customerContact = htmlspecialchars($booking['contact'] ?? 'N/A');
    $packageName = htmlspecialchars($booking['package_name'] ?? 'Standard Package');
    $bookingAmount = isset($booking['amount']) ? (float)$booking['amount'] : 0.0;
    
    // Generate complete HTML for printing
    $html = '<!DOCTYPE html>
<html>
<head>
    <title>Receipt #' . $bookingId . '</title>
    <style>
        @media print {
            body { 
                margin: 0; 
                padding: 10px; 
                font-family: "Courier New", monospace;
            }
            .no-print { 
                display: none !important; 
            }
            .thermal-receipt { 
                box-shadow: none !important; 
                margin: 0 !important;
                width: 300px !important;
            }
        }
        @media screen {
            body { 
                background: #f0f0f0; 
                padding: 20px; 
                font-family: Arial, sans-serif;
            }
            .thermal-receipt { 
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                margin: 20px auto;
                background: white;
            }
        }
        
        .thermal-receipt {
            font-family: "Courier New", monospace;
            width: 300px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .company-name {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }
        
        .company-info {
            font-size: 11px;
            margin: 5px 0;
            white-space: pre-line;
        }
        
        .receipt-title {
            font-size: 10px;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .section {
            margin-bottom: 15px;
        }
        
        .section-title {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 5px;
        }
        
        .detail {
            font-size: 9px;
            margin: 2px 0;
        }
        
        .total-section {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 10px 0;
            margin: 15px 0;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 10px;
        }
        
        .total-row.bold {
            font-weight: bold;
            font-size: 11px;
        }
        
        .footer {
            text-align: center;
            border-top: 2px dashed #000;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .thank-you {
            font-weight: bold;
            font-size: 10px;
            margin: 5px 0;
        }
        
        .footer-text {
            font-size: 9px;
            margin: 3px 0;
            color: #666;
        }
        
        .print-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 10px;
            font-size: 14px;
        }
        
        .print-button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="thermal-receipt">
        <div class="header">
            <div class="company-name">' . $companyName . '</div>
            <div class="company-info">' . $companyAddress . '</div>
            <div class="receipt-title">*** GYM RECEIPT ***</div>
        </div>
        
        <div class="section">
            <div class="detail"><strong>Receipt #' . $transactionId . '</strong></div>
            <div class="detail">Date: ' . $receiptDate . '</div>
            <div class="detail">Customer Type: ' . $customerType . '</div>
        </div>
        
        <div class="section">
            <div class="section-title">Customer Details:</div>
            <div class="detail">Name: ' . $customerName . '</div>
            <div class="detail">Email: ' . $customerEmail . '</div>
            <div class="detail">Contact: ' . $customerContact . '</div>
        </div>
        
        <div class="section">
            <div class="section-title">Booking Details:</div>
            <div class="detail">Package: ' . $packageName . '</div>
            <div class="detail">Booking Date: ' . $formattedBookingDate . '</div>';
    
    if (!empty($booking['notes'])) {
        $html .= '<div class="detail">Notes: ' . htmlspecialchars($booking['notes']) . '</div>';
    }
    
    $html .= '
        </div>
        
        <div class="total-section">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>₱' . number_format($bookingAmount, 2) . '</span>
            </div>
            <div class="total-row">
                <span>Tax (0%):</span>
                <span>₱0.00</span>
            </div>
            <div class="total-row bold">
                <span>TOTAL:</span>
                <span>₱' . number_format($bookingAmount, 2) . '</span>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Payment:</div>
            <div class="detail">Method: ' . ucfirst($paymentMethod) . '</div>
            <div class="detail">Status: ' . ucfirst($paymentStatus) . '</div>
        </div>
        
        <div class="footer">
            <div class="thank-you">THANK YOU FOR YOUR BUSINESS!</div>
            <div class="footer-text">Please keep this receipt for your records</div>
            <div class="footer-text">Questions? Call us at (123) 456-7890</div>
            <div class="footer-text">Powered by FitPay Gym Management System</div>
        </div>
    </div>
    
    <div class="no-print" style="text-align: center; margin: 20px 0;">
        <button class="print-button" onclick="window.print()">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <button class="print-button" onclick="window.close()" style="background: #6c757d;">
            <i class="fas fa-times"></i> Close
        </button>
    </div>
    
    <script>
        // Auto-focus on print button when page loads
        window.onload = function() {
            setTimeout(() => {
                const printBtn = document.querySelector(".print-button");
                if (printBtn) {
                    printBtn.focus();
                }
            }, 100);
        };
    </script>
</body>
</html>';
    
    return $html;
}
?>
