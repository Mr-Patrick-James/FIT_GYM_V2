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
    $customerType = $isWalkin ? 'Walk-in' : 'Member';
    
    $paymentMethod = $payment['payment_method'] ?? 'Cash';
    $paymentStatus = $payment['status'] ?? 'completed';
    $transactionId = $payment['transaction_id'] ?? 'TXN' . strtoupper(uniqid());
    
    $companyName = "MARTINEZ FITNESS";
    $companyAddress = "123 Fitness Street";
    $companyCity = "Gym City, 1234";
    $companyContact = "(123) 456-7890";
    
    // Safely get booking details with defaults
    $customerName = htmlspecialchars($booking['name'] ?? 'Guest');
    $customerEmail = htmlspecialchars($booking['email'] ?? 'N/A');
    $customerContact = htmlspecialchars($booking['contact'] ?? 'N/A');
    $packageName = htmlspecialchars($booking['package_name'] ?? 'Standard Package');
    $bookingAmount = isset($booking['amount']) ? (float)$booking['amount'] : 0.0;
    
    // POS 58mm thermal receipt format
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt #' . $bookingId . '</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @page {
            size: 58mm auto;
            margin: 0;
        }
        
        @media print {
            body { 
                margin: 0; 
                padding: 0;
                width: 58mm;
            }
            .no-print { 
                display: none !important; 
            }
            .thermal-receipt { 
                box-shadow: none !important;
                border-radius: 0 !important;
                width: 58mm !important;
                padding: 2mm !important;
            }
        }
        
        body {
            font-family: "Courier New", "Courier", monospace;
            font-size: 9pt;
            line-height: 1.3;
            color: #000;
            background: #f5f5f5;
            padding: 10px;
        }
        
        .thermal-receipt {
            width: 58mm;
            max-width: 100%;
            background: white;
            padding: 3mm;
            margin: 0 auto;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 3px;
        }
        
        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 3mm;
            margin-bottom: 3mm;
        }
        
        .company-name {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin-bottom: 1mm;
        }
        
        .company-info {
            font-size: 8pt;
            line-height: 1.2;
        }
        
        .receipt-title {
            font-size: 8pt;
            font-weight: bold;
            margin-top: 2mm;
        }
        
        .section {
            margin-bottom: 3mm;
            font-size: 8pt;
        }
        
        .row {
            display: flex;
            justify-content: space-between;
            margin: 1mm 0;
        }
        
        .label {
            font-weight: normal;
        }
        
        .value {
            font-weight: bold;
            text-align: right;
        }
        
        .item-line {
            margin: 1mm 0;
        }
        
        .separator {
            border-bottom: 1px dashed #000;
            margin: 3mm 0;
        }
        
        .total-section {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 2mm 0;
            margin: 3mm 0;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 1mm 0;
            font-size: 9pt;
        }
        
        .grand-total {
            font-weight: bold;
            font-size: 10pt;
            margin-top: 2mm;
        }
        
        .footer {
            text-align: center;
            border-top: 1px dashed #000;
            padding-top: 3mm;
            margin-top: 3mm;
            font-size: 8pt;
        }
        
        .thank-you {
            font-weight: bold;
            margin-bottom: 2mm;
        }
        
        .footer-text {
            font-size: 7pt;
            margin: 1mm 0;
            color: #333;
        }
        
        .print-button {
            background: #2196F3;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
            font-size: 12px;
            font-family: Arial, sans-serif;
        }
        
        .print-button:hover {
            background: #1976D2;
        }
        
        .print-button.secondary {
            background: #757575;
        }
        
        .print-button.secondary:hover {
            background: #616161;
        }
    </style>
</head>
<body>
    <div class="thermal-receipt">
        <div class="header">
            <div class="company-name">' . $companyName . '</div>
            <div class="company-info">' . $companyAddress . '</div>
            <div class="company-info">' . $companyCity . '</div>
            <div class="company-info">Tel: ' . $companyContact . '</div>
            <div class="receipt-title">*** RECEIPT ***</div>
        </div>
        
        <div class="section">
            <div class="row">
                <span class="label">Receipt:</span>
                <span class="value">#' . $bookingId . '</span>
            </div>
            <div class="row">
                <span class="label">Date:</span>
                <span class="value" style="font-size:7pt;">' . $receiptDate . '</span>
            </div>
            <div class="row">
                <span class="label">Type:</span>
                <span class="value">' . $customerType . '</span>
            </div>
        </div>
        
        <div class="separator"></div>
        
        <div class="section">
            <div style="font-weight:bold; margin-bottom:1mm;">CUSTOMER:</div>
            <div class="item-line">' . $customerName . '</div>
            <div class="item-line" style="font-size:7pt;">' . $customerContact . '</div>
        </div>
        
        <div class="separator"></div>
        
        <div class="section">
            <div style="font-weight:bold; margin-bottom:1mm;">PACKAGE:</div>
            <div class="row">
                <span>' . $packageName . '</span>
                <span style="font-weight:bold;">₱' . number_format($bookingAmount, 2) . '</span>
            </div>
            <div class="item-line" style="font-size:7pt;">Date: ' . $formattedBookingDate . '</div>';
    
    if (!empty($booking['notes'])) {
        $html .= '<div class="item-line" style="font-size:7pt;">Note: ' . htmlspecialchars($booking['notes']) . '</div>';
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
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span>₱' . number_format($bookingAmount, 2) . '</span>
            </div>
        </div>
        
        <div class="section">
            <div class="row">
                <span class="label">Payment:</span>
                <span class="value">' . ucfirst($paymentMethod) . '</span>
            </div>
            <div class="row">
                <span class="label">Status:</span>
                <span class="value">' . ucfirst($paymentStatus) . '</span>
            </div>
            <div class="row">
                <span class="label">Ref:</span>
                <span class="value" style="font-size:7pt;">' . $transactionId . '</span>
            </div>
        </div>
        
        <div class="footer">
            <div class="thank-you">THANK YOU!</div>
            <div class="footer-text">Keep this receipt</div>
            <div class="footer-text">Questions? Call us!</div>
            <div class="footer-text" style="margin-top:2mm;">Powered by FitPay GMS</div>
        </div>
    </div>
    
    <div class="no-print" style="text-align:center; margin-top:15px;">
        <button class="print-button" onclick="window.print()">
            🖨️ Print Receipt
        </button>
        <button class="print-button secondary" onclick="window.close()">
            ✕ Close
        </button>
    </div>
    
    <script>
        // Auto-print dialog on page load
        window.onload = function() {
            setTimeout(() => {
                // Uncomment to enable auto-print
                // window.print();
            }, 500);
        };
        
        // Close window after print (if opened as popup)
        window.onafterprint = function() {
            // Uncomment to auto-close after print
            // setTimeout(() => window.close(), 500);
        };
    </script>
</body>
</html>';
    
    return $html;
}
?>
