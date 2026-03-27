<?php
require_once '../../api/session.php';
require_once '../../api/config.php';
requireLogin();
$user = getCurrentUser();

// Fetch gym settings
$settings = [];
try {
    $conn = getDBConnection();
    $result = $conn->query("SELECT setting_key, setting_value FROM gym_settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

// Helper to get setting with fallback
function getSetting($key, $default = '', $settings = []) {
    return $settings[$key] ?? $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | <?php echo htmlspecialchars(getSetting('gym_name', 'Martinez Fitness', $settings)); ?></title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="../../assets/css/user-dashboard/base.css?v=1.6">
    <link rel="stylesheet" href="../../assets/css/user-dashboard/dashboard.css?v=1.6">
    <link rel="stylesheet" href="../../assets/css/user-dashboard/packages.css?v=1.6">
    <link rel="stylesheet" href="../../assets/css/user-dashboard/bookings.css?v=1.6">
    <link rel="stylesheet" href="../../assets/css/user-dashboard/payments.css?v=1.6">
    <link rel="stylesheet" href="../../assets/css/user-dashboard/profile.css?v=1.6">
    <link rel="stylesheet" href="../../assets/css/user-dashboard/trainer.css?v=1.1">

    <!-- FullCalendar CDN for user calendar -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

    <style>
        /* Survey & Recommendation Modal Styles */
        .survey-modal .modal, .recommendation-modal .modal {
            max-width: 600px;
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: var(--radius-xl);
            overflow: hidden;
            animation: modalFadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .survey-header, .recommendation-header {
            padding: 40px 40px 20px;
            text-align: center;
        }

        .survey-header i, .recommendation-header i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 20px;
            filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.3));
        }

        .survey-header h2, .recommendation-header h2 {
            font-size: 1.2rem;
            font-weight: 800;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #fff 0%, #888 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .survey-header p, .recommendation-header p {
            color: var(--dark-text-secondary);
            font-size: 0.75rem;
        }

        .survey-body, .recommendation-body {
            padding: 0 40px 40px;
            max-height: 60vh;
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* Custom scrollbar for survey body */
        .survey-body::-webkit-scrollbar {
            width: 6px;
        }
        .survey-body::-webkit-scrollbar-track {
            background: transparent;
        }
        .survey-body::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        .survey-body::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .survey-step {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .survey-step.active {
            display: block;
        }

        .question-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 24px;
            color: #fff;
            text-align: center;
        }

        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .option-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--dark-border);
            padding: 16px 12px;
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .option-card i {
            font-size: 1.2rem;
            color: var(--dark-text-secondary);
            transition: var(--transition);
        }

        .option-card span {
            font-weight: 600;
            font-size: 0.75rem;
        }

        .option-card:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: #666;
            transform: translateY(-4px);
        }

        .option-card.selected {
            background: var(--primary);
            border-color: var(--primary);
            color: var(--dark-bg);
        }

        .option-card.selected i {
            color: var(--dark-bg);
        }

        .survey-footer {
            margin-top: 0;
            padding: 20px 40px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--dark-border);
            background: var(--dark-card);
        }

        .progress-bar {
            flex: 1;
            height: 6px;
            background: var(--dark-border);
            border-radius: 3px;
            margin-right: 24px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            width: 33%;
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .survey-nav-btn {
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .btn-next {
            background: var(--primary);
            color: var(--dark-bg);
        }

        .btn-next:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
        }

        .btn-next:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Recommendation Specific */
        .recommended-package-preview {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid var(--primary);
            border-radius: var(--radius-lg);
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }

        .recommended-package-preview h3 {
            font-size: 1.2rem;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .recommended-package-preview .price {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .recommended-package-preview .duration {
            color: var(--dark-text-secondary);
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .recommended-actions {
            display: flex;
            gap: 16px;
        }

        .recommended-actions button {
            flex: 1;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* User Calendar View */
        .user-view-toggle {
            display: flex;
            background: var(--dark-card);
            padding: 4px;
            border-radius: var(--radius-md);
            border: 1px solid var(--dark-border);
            margin-left: auto;
            gap: 6px;
        }
        .user-view-btn {
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            border: none;
            background: transparent;
            color: var(--dark-text-secondary);
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .user-view-btn.active {
            background: var(--primary);
            color: var(--dark-bg);
        }
        #userCalendarView {
            display: none;
            margin: 16px 24px 24px;
            background: var(--dark-card);
            border-radius: var(--radius-lg);
            padding: 12px;
            border: 1px solid var(--dark-border);
        }
        /* FullCalendar Dark Theme Tweaks */
        .fc {
            --fc-border-color: rgba(255, 255, 255, 0.05);
            --fc-daygrid-event-dot-width: 8px;
            --fc-neutral-bg-color: transparent;
            --fc-page-bg-color: transparent;
            --fc-today-bg-color: rgba(255, 255, 255, 0.05);
            font-family: 'Inter', sans-serif;
            border: none;
        }
        .fc .fc-view-harness {
            background: var(--dark-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--dark-border);
            overflow: hidden;
        }
        .fc .fc-scrollgrid {
            border: none !important;
        }
        .fc .fc-col-header-cell {
            padding: 12px 0;
            background: rgba(255, 255, 255, 0.02);
            border-bottom: 1px solid var(--dark-border) !important;
        }
        .fc .fc-col-header-cell-cushion {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--dark-text-secondary);
            font-weight: 700;
        }
        .fc .fc-daygrid-day-number {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 8px 12px;
            color: var(--dark-text-secondary);
        }
        .fc .fc-day-today .fc-daygrid-day-number {
            color: var(--primary);
            font-weight: 800;
        }
        .fc .fc-toolbar-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark-text);
        }
        .fc .fc-button-primary {
            background-color: var(--dark-card);
            border: 1px solid var(--dark-border);
            color: var(--dark-text);
            font-weight: 600;
            padding: 6px 12px;
            border-radius: var(--radius-md);
            transition: all 0.2s;
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
            border: 1px solid rgba(255, 255, 255, 0.05) !important;
        }
        
        /* Event Styles */
        .fc-event {
            border: none !important;
            border-radius: 6px !important;
            padding: 2px 6px !important;
            font-size: 0.7rem !important;
            font-weight: 700 !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin: 1px 2px !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }

        .fc-h-event .fc-event-main {
            display: block !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }

        .fc-daygrid-event-dot {
            display: none !important;
        }

        /* Milestone Specific Styles - Minimal & Professional */
        .event-milestone-paid {
            background: rgba(34, 197, 94, 0.15) !important;
            border-left: 3px solid #22c55e !important;
            color: #22c55e !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .event-milestone-paid::before {
            content: '\f09d'; /* Credit Card icon */
            font-family: 'Font Awesome 6 Free';
            margin-right: 4px;
        }

        .event-milestone-expiry {
            background: rgba(239, 68, 68, 0.15) !important;
            border-left: 3px solid #ef4444 !important;
            color: #ef4444 !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .event-milestone-expiry::before {
            content: '\f273'; /* Calendar Times icon */
            font-family: 'Font Awesome 6 Free';
            margin-right: 4px;
        }

        .fc-event:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            filter: brightness(1.2);
            z-index: 5;
        }
        .event-status-pending { 
            background: rgba(245, 158, 11, 0.1) !important; 
            border-left: 3px solid var(--warning) !important;
            color: var(--warning) !important; 
        }
        .event-status-verified { 
            background: rgba(34, 197, 94, 0.1) !important; 
            border-left: 3px solid var(--success) !important;
            color: var(--success) !important; 
        }
        .event-status-rejected { 
            background: rgba(239, 68, 68, 0.1) !important; 
            border-left: 3px solid #ef4444 !important;
            color: #ef4444 !important; 
        }
        .event-routine {
            background: rgba(255, 255, 255, 0.05) !important;
            border-left: 3px solid var(--primary) !important;
            color: var(--primary) !important;
            backdrop-filter: blur(4px);
        }
        .event-rest-day {
            background: rgba(239, 68, 68, 0.1) !important;
            border-left: 3px solid #ef4444 !important;
            color: #ef4444 !important;
            backdrop-filter: blur(4px);
        }
/* Floating Coach Corner Icon */
        .coach-corner-float {
            position: fixed;
            bottom: 32px;
            right: 32px;
            width: 64px;
            height: 64px;
            background: #fff;
            color: #000;
            border-radius: 20px;
            display: none; /* Hidden by default, shown by JS if trainer assigned */
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(255, 255, 255, 0.2);
            z-index: 999;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
        }

        .coach-corner-float:hover {
            transform: scale(1.1) translateY(-5px);
            box-shadow: 0 15px 40px rgba(255, 255, 255, 0.3);
        }

        .coach-corner-float i {
            animation: pulse 2s infinite;
        }

        .hub-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 800;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            animation: bounceIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes bounceIn {
            from { opacity: 0; transform: scale(0.3); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Coach Hub Modal Premium */
        #coachHubModal .modal {
            max-width: 850px !important;
            height: 85vh;
            display: flex;
            flex-direction: column;
            background: #050505 !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: 32px !important;
        }

        .coach-hub-header {
            padding: 32px 40px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .coach-hub-nav {
            display: flex;
            gap: 8px;
            padding: 12px 40px;
            background: rgba(255, 255, 255, 0.02);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .hub-tab-btn {
            padding: 10px 20px;
            border-radius: 12px;
            border: none;
            background: transparent;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 700;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hub-tab-btn:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
        }

        .hub-tab-btn.active {
            color: #000;
            background: #fff;
        }

        .hub-content {
            flex: 1;
            overflow-y: auto;
            padding: 32px 40px;
            display: flex;
            flex-direction: column;
        }

        .hub-tab-content {
            display: none;
            flex: 1;
            height: 100%;
        }

        .hub-tab-content.active {
            display: block;
        }

        .coach-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .coach-card:hover {
            border-color: rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.05);
        }

        .hub-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        @media (max-width: 768px) {
            .hub-grid { grid-template-columns: 1fr; }
            .coach-corner-float { bottom: 20px; right: 20px; width: 56px; height: 56px; }
        }
    </style>
    <style>
        /* Modern Booking Details Modal */
        #bookingDetailsModal .modal {
            max-width: 550px;
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: var(--radius-xl);
        }
        .booking-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .booking-detail-item {
            background: rgba(255, 255, 255, 0.02);
            padding: 16px;
            border-radius: var(--radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: var(--transition);
        }
        .booking-detail-item:hover {
            background: rgba(255, 255, 255, 0.04);
            border-color: var(--primary);
        }
        .detail-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--dark-text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .detail-value {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--dark-text);
        }
        .detail-value i {
            color: var(--primary);
            width: 16px;
            text-align: center;
        }
        .receipt-preview-container {
            margin-top: 24px;
            border-top: 1px solid var(--dark-border);
            padding-top: 24px;
        }
        .receipt-preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .receipt-preview-header h4 {
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .receipt-preview-header h4 i {
            color: var(--success);
        }
        .receipt-img-wrapper {
            position: relative;
            width: 100%;
            height: 200px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--dark-border);
            cursor: zoom-in;
            background: rgba(0,0,0,0.2);
        }
        .receipt-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.3s;
        }
        .receipt-img-wrapper:hover img {
            transform: scale(1.05);
        }
        .receipt-img-wrapper::after {
            content: '\f00e';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2rem;
            color: #fff;
            opacity: 0;
            transition: var(--transition);
            pointer-events: none;
            text-shadow: 0 0 20px rgba(0,0,0,0.5);
        }
        .receipt-img-wrapper:hover::after {
            opacity: 1;
        }
        .notes-section {
            margin-top: 20px;
            padding: 16px;
            background: rgba(245, 158, 11, 0.05);
            border-left: 4px solid var(--warning);
            border-radius: var(--radius-md);
        }
        .notes-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--warning);
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .notes-text {
            font-size: 0.75rem;
            color: var(--dark-text);
            line-height: 1.5;
            font-style: italic;
        }
    </style>
    <style>
        /* Compact & Sleek Package Card Styles */
        #packagesGrid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 20px;
            padding: 10px;
        }

        .package-card-large {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--dark-border);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: all 0.3s ease;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .package-card-large:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .package-card-large.active-plan {
            border-color: #22c55e;
            background: rgba(34, 197, 94, 0.03);
        }
        
        .package-card-large.active-plan:hover {
            border-color: #22c55e;
            box-shadow: 0 10px 30px rgba(34, 197, 94, 0.1);
        }

        .package-header {
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .package-header h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark-text);
            margin: 0;
        }

        .package-tag {
            background: rgba(255,255,255,0.05);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .package-description {
            color: var(--dark-text-secondary);
            font-size: 0.7rem;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .package-details {
            margin-bottom: 24px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .package-detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--dark-text-secondary);
            font-size: 0.75rem;
            padding: 8px 12px;
            background: rgba(255,255,255,0.02);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.03);
        }

        .package-detail-item i {
            color: var(--primary);
            font-size: 0.9rem;
            width: 16px;
        }

        .package-footer {
            margin-top: auto;
            padding-top: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .package-price-large {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--dark-text);
        }

        .package-btn-group {
            display: flex;
            gap: 8px;
        }

        .package-btn-group .btn {
            height: 40px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0 16px;
        }

        .btn-exercise {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--dark-border);
            color: var(--dark-text);
            width: 40px;
            padding: 0 !important;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-book {
            background: var(--primary);
            border: none;
            color: var(--dark-bg);
        }

        .btn-book:hover {
            background: #fff;
            transform: translateY(-2px);
        }

        /* Exercise Plan Styles */
        .exercise-item {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--dark-border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 20px;
            transition: transform 0.2s;
        }
        
        .exercise-item:hover {
            transform: translateY(-2px);
            background: rgba(255,255,255,0.05);
        }
        
        .exercise-icon {
            width: 50px;
            height: 50px;
            background: var(--primary);
            color: var(--dark-bg);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .exercise-info h4 {
            color: var(--primary);
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .exercise-category {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--dark-text-secondary);
            font-weight: 700;
        }
        
        .exercise-details {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed var(--dark-border);
        }
        
        .exercise-detail {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            color: var(--dark-text);
        }
        
        .exercise-detail i {
            color: var(--primary);
        }
        
        .no-exercises {
            text-align: center;
            padding: 40px 20px;
            color: var(--dark-text-secondary);
        }
        
        .no-exercises i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .exercise-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 12px;
            border: 1px solid var(--dark-border);
        }
        
        .exercise-image-fallback {
            width: 100%;
            height: 180px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            margin-bottom: 12px;
            border: 1px dashed var(--dark-border);
            text-align: center;
            line-height: 180px;
            color: var(--dark-text-secondary);
        }
        /* Modal Tabs */
        .modal-tabs {
            display: flex;
            gap: 24px;
            border-bottom: 1px solid var(--dark-border);
            margin: 0 24px 20px;
            padding-bottom: 12px;
        }
        .modal-tab-btn {
            background: transparent;
            border: none;
            color: var(--dark-text-secondary);
            font-weight: 600;
            font-size: 0.75rem;
            padding: 8px 4px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        .modal-tab-btn.active {
            color: var(--primary);
        }
        .modal-tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -13px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
            border-radius: 3px 3px 0 0;
        }
        .modal-tab-content {
            display: none;
        }
        .modal-tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="dark-mode">
    <!-- Coach Hub Floating Action Button -->
    <button class="coach-corner-float" id="coachHubFloat" onclick="openCoachHub()" title="Coach's Corner">
        <i class="fas fa-dumbbell"></i>
        <span id="coachHubBadge" class="hub-badge" style="display: none;">0</span>
    </button>

    <!-- Coach Hub Modal -->
    <div class="modal-overlay" id="coachHubModal">
        <div class="modal">
            <div class="coach-hub-header">
                <div style="flex: 1;">
                    <h2 style="font-size: 1.75rem; font-weight: 900; color: #fff; letter-spacing: -0.8px; margin-bottom: 4px;">Coach's Corner</h2>
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <p style="color: rgba(255,255,255,0.5); font-size: 0.9rem; font-weight: 500;">Your personalized training & guidance hub</p>
                        <div id="hubSubscriptionDates" style="display: none; align-items: center; gap: 12px; background: rgba(255,255,255,0.05); padding: 4px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                            <span style="font-size: 0.75rem; color: rgba(255,255,255,0.4); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Plan Period:</span>
                            <span id="hubStartDate" style="font-size: 0.85rem; color: var(--primary); font-weight: 700;">--</span>
                            <i class="fas fa-long-arrow-alt-right" style="color: rgba(255,255,255,0.2); font-size: 0.8rem;"></i>
                            <span id="hubExpiryDate" style="font-size: 0.85rem; color: #ef4444; font-weight: 700;">--</span>
                        </div>
                    </div>
                </div>
                <button class="close-modal" onclick="closeCoachHub()" style="background: rgba(255,255,255,0.05); border: none; width: 40px; height: 40px; border-radius: 12px; color: #fff; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="coach-hub-nav">
                <button class="hub-tab-btn active" onclick="switchHubTab('calendar')"><i class="fas fa-calendar-alt"></i> Calendar</button>
                <button class="hub-tab-btn" onclick="switchHubTab('progress')"><i class="fas fa-chart-line"></i> Progress</button>
                <button class="hub-tab-btn" onclick="switchHubTab('plans')"><i class="fas fa-clipboard-list"></i> Workout Plans</button>
                <button class="hub-tab-btn" onclick="switchHubTab('guidance')"><i class="fas fa-lightbulb"></i> Guidance & Tips</button>
            </div>

            <div class="hub-content">
                <!-- Calendar Tab -->
                <div id="hubCalendar" class="hub-tab-content active" style="display: flex; flex-direction: column;">
                    <div id="userCoachCalendar" style="flex: 1; min-height: 500px;"></div>
                </div>

                <!-- Progress Tab -->
                <div id="hubProgress" class="hub-tab-content">
                    <div class="hub-grid" id="hubProgressList">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <!-- Plans Tab -->
                <div id="hubPlans" class="hub-tab-content">
                    <div id="hubPlansList">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <!-- Guidance Tab -->
                <div id="hubGuidance" class="hub-tab-content">
                    <div class="hub-grid">
                        <div class="coach-card">
                            <h4 style="color: #fff; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-lightbulb" style="color: var(--primary);"></i> Daily Tips
                            </h4>
                            <div id="hubTipsList"></div>
                        </div>
                        <div class="coach-card">
                            <h4 style="color: #fff; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-utensils" style="color: var(--primary);"></i> Nutrition
                            </h4>
                            <div id="hubFoodList"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-btn" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <?php 
                $gymName = getSetting('gym_name', 'MARTINEZ FITNESS GYM', $settings);
                $nameParts = explode(' ', $gymName);
            ?>
            <h1><?php echo htmlspecialchars($nameParts[0]); ?></h1>
            <p><?php echo htmlspecialchars(implode(' ', array_slice($nameParts, 1)) ?: 'FITNESS GYM'); ?></p>
        </div>
        
        <ul class="nav-links">
            <li><a href="#" class="active" onclick="showSection('dashboard', event)"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li><a href="#" onclick="showSection('packages', event)"><i class="fas fa-dumbbell"></i> <span>Packages</span></a></li>
            <li><a href="#" onclick="showSection('bookings', event)"><i class="fas fa-calendar-check"></i> <span>My Bookings</span> <span class="badge" id="bookingsBadge">0</span></a></li>
            <li><a href="#" onclick="showSection('payments', event)"><i class="fas fa-money-check"></i> <span>Payments</span></a></li>
            <li><a href="#" onclick="showSection('trainer', event)"><i class="fas fa-user-tie"></i> <span>My Trainer</span></a></li>
            <li><a href="#" onclick="showSection('profile', event)"><i class="fas fa-user"></i> <span>Profile</span></a></li>
        </ul>
        
        <div class="user-profile">
            <div class="user-avatar" id="userAvatar">JD</div>
            <div class="user-info">
                <div class="user-name-wrapper">
                    <h4 id="userName">Juan Dela Cruz</h4>
                    <span id="sidebarMemberBadge" class="member-badge" style="display: none;" title="Active Member">
                        <i class="fas fa-crown"></i>
                    </span>
                </div>
                <p id="userEmail">juan.delacruz@email.com</p>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1 id="pageTitle">Dashboard</h1>
                <p id="pageSubtitle">Welcome back! Manage your gym membership and bookings</p>
            </div>
            
            <div class="header-actions">
                <button class="action-btn notification-btn" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notifBadge">0</span>
                </button>
                
                <button class="action-btn" onclick="logout()" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <!-- Dashboard Section -->
        <div id="dashboardSection" class="content-section active">
            <div class="dashboard-grid-layout">
                <div class="dashboard-main-col">
                    <!-- Stats Grid -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                            </div>
                            <div class="stat-value" id="activeBookingsCount">0</div>
                            <div class="stat-label">Active Bookings</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                            </div>
                            <div class="stat-value" id="statTrainer">None</div>
                            <div class="stat-label">Assigned Trainer</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="stat-value" id="verifiedBookingsCount">0</div>
                            <div class="stat-label">Verified Bookings</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-icon">
                                    <i class="fas fa-id-card"></i>
                                </div>
                            </div>
                            <div class="stat-value" id="membershipStatus">None</div>
                            <div class="stat-label">Membership Status</div>
                            <div id="membershipExpiry" style="font-size: 0.75rem; color: var(--dark-text-secondary); margin-top: 8px; display: none; flex-direction: column; align-items: center; gap: 4px;">
                                <div>
                                    <i class="far fa-calendar-check" style="color: var(--primary);"></i> 
                                    <span>Starts: <strong id="membershipStartDate" style="color: #fff;">--</strong></span>
                                </div>
                                <div>
                                    <i class="far fa-calendar-times" style="color: #ef4444;"></i> 
                                    <span>Expires: <strong id="membershipExpiryDate" style="color: #fff;">--</strong></span>
                                </div>
                                
                                <!-- Membership Quick Actions (Renew/Upgrade) -->
                                <div id="membershipQuickActions" style="margin-top: 12px; display: flex; gap: 8px; width: 100%;">
                                    <button id="dashRenewBtn" class="btn btn-sm" style="flex: 1; padding: 6px; font-size: 0.65rem; background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); border-radius: 6px; cursor: pointer; transition: all 0.2s;">
                                        <i class="fas fa-redo"></i> Renew
                                    </button>
                                    <button id="dashUpgradeBtn" class="btn btn-sm" style="flex: 1; padding: 6px; font-size: 0.65rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 6px; cursor: pointer; transition: all 0.2s; display: none;">
                                        <i class="fas fa-arrow-up"></i> Upgrade
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Coach Recommendations Section (Dynamic) -->
                    <div id="coachSection" style="display: none; margin-bottom: 32px; grid-template-columns: 1fr 1fr; gap: 24px;">
                        <div class="content-card" style="margin-top: 0;">
                            <div class="card-header">
                                <h3><i class="fas fa-lightbulb" style="color: var(--primary);"></i> Coach's Tips</h3>
                            </div>
                            <div id="myTipsList" style="padding: 20px; max-height: 250px; overflow-y: auto;">
                                <p style="text-align: center; color: var(--dark-text-secondary); font-size: 0.9rem;">No tips from your coach yet.</p>
                            </div>
                        </div>
                        <div class="content-card" style="margin-top: 0;">
                            <div class="card-header">
                                <h3><i class="fas fa-utensils" style="color: var(--primary);"></i> Meal Guidance</h3>
                            </div>
                            <div id="myFoodList" style="padding: 20px; max-height: 250px; overflow-y: auto;">
                                <p style="text-align: center; color: var(--dark-text-secondary); font-size: 0.9rem;">No food recommendations yet.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3>Quick Actions</h3>
                        </div>
                        <div class="quick-actions-grid">
                            <button class="quick-action-card" onclick="showSection('packages', event)">
                                <i class="fas fa-dumbbell"></i>
                                <h4>Browse Packages</h4>
                                <p>View available membership plans</p>
                            </button>
                            <button class="quick-action-card" onclick="openBookingModal()">
                                <i class="fas fa-plus-circle"></i>
                                <h4>New Booking</h4>
                                <p>Create a new booking request</p>
                            </button>
                            <button class="quick-action-card" onclick="showSection('bookings', event)">
                                <i class="fas fa-list"></i>
                                <h4>View Bookings</h4>
                                <p>Check your booking status</p>
                            </button>
                            <button class="quick-action-card" onclick="showSection('payments', event)">
                                <i class="fas fa-receipt"></i>
                                <h4>Payment History</h4>
                                <p>View past transactions</p>
                            </button>
                        </div>
                    </div>
                </div>


            </div>

            <!-- Recent Bookings -->
            <div class="content-card" style="margin-top: 32px;">
                <div class="card-header">
                    <h3>Recent Bookings</h3>
                    <button class="card-btn" onclick="showSection('bookings', event)">
                        <span>View All</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Package</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recentBookingsTable">
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: var(--dark-text-secondary);" data-label="Info">
                                    No bookings yet. <a href="#" onclick="showSection('packages')" style="color: var(--primary); text-decoration: underline;">Browse packages</a> to get started!
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Packages Section -->
        <div id="packagesSection" class="content-section">
            <!-- Active Packages Section -->
            <div id="activePackagesSection" style="display: none; margin-bottom: 32px;">
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-check-circle" style="color: #22c55e; margin-right: 12px;"></i>Your Active Packages</h3>
                        <p style="color: var(--dark-text-secondary);">Manage your currently active gym memberships</p>
                    </div>
                    <div class="packages-grid" id="activePackagesGrid">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Available Packages Section -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Available Packages</h3>
                    <p style="color: var(--dark-text-secondary);">Choose a membership plan that fits your fitness goals</p>
                </div>
                <div class="packages-grid" id="packagesGrid">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Training Calendar Section -->
                    <div id="trainingCalendarSection" style="display: none; margin-bottom: 32px;">
                        <div class="content-card" style="margin-top: 0;">
                            <div class="card-header">
                                <h3><i class="fas fa-calendar-alt" style="color: var(--primary);"></i> My Training Schedule</h3>
                                <div class="card-actions">
                                    <span style="font-size: 0.75rem; color: var(--dark-text-secondary);">
                                        <i class="fas fa-info-circle"></i> Scheduled by your trainer
                                    </span>
                                </div>
                            </div>
                            <div style="padding: 24px;">
                                <div id="trainingCalendar" style="min-height: 500px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Bookings Section -->
        <div id="bookingsSection" class="content-section">
            <div class="content-card">
                <div class="card-header">
                    <h3>My Bookings</h3>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div class="user-view-toggle">
                            <button class="user-view-btn active" id="userTableViewBtn" title="Table View">
                                <i class="fas fa-table"></i>
                                <span>Table</span>
                            </button>
                            <button class="user-view-btn" id="userCalendarViewBtn" title="Calendar View">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Calendar</span>
                            </button>
                        </div>
                        <button class="card-btn primary" onclick="openBookingModal()">
                            <i class="fas fa-plus"></i>
                            <span>New Booking</span>
                        </button>
                    </div>
                </div>
                <div id="userCalendarView">
                    <div id="userCalendar"></div>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Package</th>
                                <th>Booking Date</th>
                                 <th>Expiry Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bookingsTable">
                            <!-- Populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payments Section -->
        <div id="paymentsSection" class="content-section">
            <div class="content-card">
                <div class="card-header">
                    <h3>Payment History</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Package</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="paymentsTable">
                            <!-- Populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- My Trainer Section -->
        <div id="trainerSection" class="content-section">

            <!-- No Trainer State -->
            <div id="trainerNoData" class="content-card" style="display:none; text-align:center; padding: 60px 24px;">
                <i class="fas fa-user-slash" style="font-size: 3rem; color: var(--dark-text-secondary); margin-bottom: 16px; display:block;"></i>
                <h3 style="margin-bottom: 8px;">No Trainer Assigned Yet</h3>
                <p style="color: var(--dark-text-secondary); font-size: 0.9rem;">Book a trainer-assisted package to get assigned a personal trainer.</p>
                <button class="btn btn-primary" style="margin-top: 20px;" onclick="showSection('packages', event)">Browse Packages</button>
            </div>

            <!-- Trainer Profile Card -->
            <div id="trainerData" style="display:none;">
                <div class="trainer-profile-card content-card">
                    <div class="trainer-profile-header">
                        <div class="trainer-avatar-wrap">
                            <img id="trainerPhoto" src="" alt="Trainer" class="trainer-avatar-img">
                            <span class="trainer-active-dot"></span>
                        </div>
                        <div class="trainer-profile-info">
                            <h2 id="trainerName"></h2>
                            <p id="trainerSpec" class="trainer-spec-badge"></p>
                            <p id="trainerBio" class="trainer-bio"></p>
                            <div class="trainer-meta-row">
                                <span id="trainerContact"><i class="fas fa-phone"></i> <span id="trainerContactVal"></span></span>
                                <span id="trainerEmail"><i class="fas fa-envelope"></i> <span id="trainerEmailVal"></span></span>
                                <span><i class="fas fa-users"></i> <span id="trainerClientsVal"></span> clients trained</span>
                            </div>
                        </div>
                    </div>
                    <div class="trainer-package-row">
                        <i class="fas fa-box"></i> Your Package: <strong id="trainerPackageName"></strong>
                        &nbsp;&nbsp;<i class="fas fa-calendar-times" style="color:#ef4444;"></i> Expires: <strong id="trainerExpiry"></strong>
                    </div>
                </div>

                <!-- Info Grid: Availability + Certifications -->
                <div class="trainer-info-grid">
                    <div class="content-card">
                        <div class="card-header"><h3><i class="fas fa-clock"></i> Availability</h3></div>
                        <div id="trainerAvailability" style="padding: 20px; color: var(--dark-text-secondary); font-size: 0.9rem; line-height: 1.7;"></div>
                    </div>
                    <div class="content-card">
                        <div class="card-header"><h3><i class="fas fa-certificate"></i> Certifications</h3></div>
                        <div id="trainerCertifications" style="padding: 20px; color: var(--dark-text-secondary); font-size: 0.9rem; line-height: 1.7;"></div>
                    </div>
                </div>

                <!-- Upcoming Sessions -->
                <div class="content-card" style="margin-top: 24px;">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Upcoming Sessions</h3>
                    </div>
                    <div id="trainerSessions" style="padding: 20px;"></div>
                </div>

                <!-- Tips + Meal in a grid -->
                <div class="trainer-info-grid" style="margin-top: 24px;">
                    <div class="content-card">
                        <div class="card-header"><h3><i class="fas fa-lightbulb"></i> Coach Tips</h3></div>
                        <div id="trainerTips" style="padding: 20px;"></div>
                    </div>
                    <div class="content-card">
                        <div class="card-header"><h3><i class="fas fa-utensils"></i> Meal Guidance</h3></div>
                        <div id="trainerFood" style="padding: 20px;"></div>
                    </div>
                </div>

                <!-- Progress History -->
                <div class="content-card" style="margin-top: 24px; overflow: visible;">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Progress History</h3>
                        <button class="btn btn-primary btn-sm" onclick="toggleProgressForm()" id="logProgressBtn">
                            <i class="fas fa-plus"></i> Log My Progress
                        </button>
                    </div>

                    <!-- User self-log form -->
                    <div id="progressLogForm" style="display:none; padding: 20px; border-bottom: 1px solid var(--dark-border); background: rgba(255,255,255,0.03);">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                            <div>
                                <label style="font-size:0.8rem;color:var(--dark-text-secondary);display:block;margin-bottom:4px;">Weight (kg)</label>
                                <input type="number" id="userProgressWeight" step="0.1" min="0" placeholder="e.g. 72.5"
                                    style="width:100%;padding:8px 12px;background:#1a1a1a;border:1px solid #333;border-radius:8px;color:#fff;font-size:0.9rem;box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="font-size:0.8rem;color:var(--dark-text-secondary);display:block;margin-bottom:4px;">Height (cm)</label>
                                <input type="number" id="userProgressHeight" step="0.1" min="0" placeholder="e.g. 170"
                                    style="width:100%;padding:8px 12px;background:#1a1a1a;border:1px solid #333;border-radius:8px;color:#fff;font-size:0.9rem;box-sizing:border-box;">
                            </div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label style="font-size:0.8rem;color:var(--dark-text-secondary);display:block;margin-bottom:4px;">Date</label>
                            <input type="date" id="userProgressDate"
                                style="width:100%;padding:8px 12px;background:#1a1a1a;border:1px solid #333;border-radius:8px;color:#fff;font-size:0.9rem;box-sizing:border-box;">
                        </div>
                        <div style="margin-bottom:12px;">
                            <label style="font-size:0.8rem;color:var(--dark-text-secondary);display:block;margin-bottom:4px;">Notes / Remarks</label>
                            <textarea id="userProgressRemarks" rows="2" placeholder="How are you feeling? Any notes..."
                                style="width:100%;padding:8px 12px;background:#1a1a1a;border:1px solid #333;border-radius:8px;color:#fff;font-size:0.9rem;resize:vertical;box-sizing:border-box;"></textarea>
                        </div>
                        <div style="margin-bottom:16px;">
                            <label style="font-size:0.8rem;color:var(--dark-text-secondary);display:block;margin-bottom:4px;">Progress Photo (optional)</label>
                            <input type="file" id="userProgressPhoto" accept="image/*"
                                style="width:100%;padding:8px 12px;background:#1a1a1a;border:1px solid #333;border-radius:8px;color:#fff;font-size:0.85rem;box-sizing:border-box;">
                            <div id="progressPhotoPreview" style="margin-top:8px;display:none;">
                                <img id="progressPhotoImg" src="" alt="Preview" style="max-width:120px;max-height:120px;border-radius:8px;object-fit:cover;border:1px solid #333;">
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button class="btn btn-primary btn-sm" onclick="submitUserProgress()" id="submitProgressBtn">
                                <i class="fas fa-save"></i> Save Progress
                            </button>
                            <button class="btn btn-sm" onclick="toggleProgressForm()" style="background:#1a1a1a;border:1px solid #333;color:#fff;">
                                Cancel
                            </button>
                        </div>
                    </div>

                    <div id="trainerProgress" style="padding: 20px;"></div>
                </div>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profileSection" class="content-section">
            <div class="profile-layout-grid">
                <!-- Left Column: User Profile Info -->
                <div class="profile-main-col">
                    <div class="content-card">
                        <div class="card-header">
                            <h3><i class="fas fa-user-circle" style="margin-right: 12px; color: var(--primary);"></i>Profile Information</h3>
                        </div>
                        <div class="profile-form">
                            <!-- Membership Status Badge -->
                            <div id="profileMembershipBadge" class="membership-badge-container" style="display: none;">
                                <div class="membership-status-card">
                                    <div class="membership-icon">
                                        <i class="fas fa-crown"></i>
                                    </div>
                                    <div class="membership-details">
                                        <span class="membership-label">MEMBERSHIP STATUS</span>
                                        <div class="membership-value-row">
                                            <h4 id="profileMembershipValue">Active Member</h4>
                                            <span class="status-badge status-verified" id="profileMembershipStatus">Active</span>
                                        </div>
                                        <p id="profileMembershipPlan">Monthly Membership Plan</p>
                                        <div id="profileMembershipExpiryRow" style="margin-top: 8px; font-size: 0.85rem; color: var(--dark-text-secondary); display: flex; flex-direction: column; gap: 4px;">
                                            <div style="display: flex; align-items: center;">
                                                <i class="far fa-calendar-check" style="margin-right: 8px; color: var(--primary); width: 14px;"></i>
                                                <span>Started on: <strong id="profileMembershipStartDate" style="color: #fff;">--</strong></span>
                                            </div>
                                            <div style="display: flex; align-items: center;">
                                                <i class="far fa-calendar-times" style="margin-right: 8px; color: #ef4444; width: 14px;"></i>
                                                <span>Expires on: <strong id="profileMembershipExpiryDate" style="color: #fff;">--</strong></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" id="profileName" value="Juan Dela Cruz" placeholder="Enter your full name">
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" id="profileEmail" value="juan.delacruz@email.com" placeholder="Enter your email">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Contact Number</label>
                                    <input type="tel" id="profileContact" value="0917-123-4567" placeholder="09XX-XXX-XXXX">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Address</label>
                                <textarea id="profileAddress" rows="3" placeholder="Enter your complete address">Manila, Philippines</textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button class="btn btn-primary" id="updateProfileBtn" onclick="updateProfile()">
                                    <i class="fas fa-save"></i>
                                    <span>Save Profile Changes</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Security & Payment Info -->
                <div class="profile-side-col">
                    <div class="content-card">
                        <div class="card-header">
                            <h3><i class="fas fa-shield-alt" style="margin-right: 12px; color: var(--primary);"></i>Security</h3>
                        </div>
                        <div class="security-form" style="padding: 28px;">
                            <p style="color: var(--dark-text-secondary); margin-bottom: 24px; font-size: 0.9rem; line-height: 1.5;">
                                Ensure your account is secure by using a strong password.
                            </p>
                            <form id="changePasswordForm" onsubmit="changePassword(event)">
                                <div class="form-group">
                                    <label>Current Password</label>
                                    <div class="password-input-wrapper">
                                        <input type="password" id="currentPassword" required placeholder="••••••••">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>New Password</label>
                                    <div class="password-input-wrapper">
                                        <input type="password" id="newPassword" required minlength="6" placeholder="At least 6 characters">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <div class="password-input-wrapper">
                                        <input type="password" id="confirmNewPassword" required minlength="6" placeholder="Repeat new password">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-secondary" id="changePasswordBtn" style="width: 100%; justify-content: center;">
                                    <i class="fas fa-key"></i>
                                    <span>Update Password</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="content-card">
                        <div class="card-header">
                            <h3><i class="fas fa-qrcode" style="margin-right: 12px; color: var(--primary);"></i>GCash Payment</h3>
                        </div>
                        <div class="gcash-info">
                            <div class="qr-container">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=GCash:09171234567" alt="GCash QR Code" style="width: 100%; height: 100%; object-fit: contain;">
                            </div>
                            <div class="qr-info">
                                <div class="payment-detail-item">
                                    <span class="detail-label">ACCOUNT NAME</span>
                                    <span class="detail-value">Martinez Fitness</span>
                                </div>
                                <div class="payment-detail-item">
                                    <span class="detail-label">GCASH NUMBER</span>
                                    <span class="detail-value">0917-123-4567</span>
                                </div>
                                <p style="margin-top: 20px; font-size: 0.85rem; color: var(--dark-text-secondary); line-height: 1.4;">
                                    Scan to pay for bookings. Don't forget to save your receipt for verification!
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Booking Modal -->
    <div class="modal-overlay" id="bookingModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Create New Booking</h3>
                <button class="close-modal" onclick="closeBookingModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="modal-layout">
                    <div class="modal-form-col">
                        <form id="bookingForm" onsubmit="submitBooking(event)">
                            <div class="form-group">
                                <label>Select Package <span style="color: var(--warning);">*</span></label>
                                <select id="bookingPackage" required>
                                    <option value="">Choose a package...</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Booking Date <span style="color: var(--warning);">*</span></label>
                                <input type="date" id="bookingDate" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Contact Number <span style="color: var(--warning);">*</span></label>
                                <input type="tel" id="bookingContact" placeholder="09171234567" maxlength="11" pattern="[0-9]{11}" title="Please enter exactly 11 digits" required>
                                <small style="color: var(--dark-text-secondary); font-size: 0.75rem; margin-top: 4px; display: block;">Enter 11 digits only (e.g., 09171234567)</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Payment Receipt (GCash) <span style="color: var(--warning);">*</span></label>
                                <div class="file-upload-area" id="fileUploadArea">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Click to upload or drag and drop</p>
                                    <span>PNG, JPG, PDF up to 5MB</span>
                                    <input type="file" id="receiptFile" accept="image/*,.pdf" required style="display: none;" onchange="handleFileSelect(event)">
                                </div>
                                <div id="filePreview" style="display: none; margin-top: 16px;">
                                    <div class="file-preview-item">
                                        <i class="fas fa-file-image"></i>
                                        <span id="fileName"></span>
                                        <button type="button" onclick="removeFile()" class="remove-file-btn">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Additional Notes (Optional)</label>
                                <textarea id="bookingNotes" rows="3" placeholder="Any special requests or notes..."></textarea>
                            </div>
                            
                            <div class="modal-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeBookingModal()">
                                    <i class="fas fa-times"></i>
                                    <span>Cancel</span>
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i>
                                    <span>Submit Booking</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="modal-side-col">
                        <div class="payment-instruction-card">
                            <h4><i class="fas fa-info-circle"></i> Payment Instructions</h4>
                            <p>1. Scan the QR code below using your GCash app.</p>
                            <p>2. Enter the amount for your package.</p>
                            <p>3. Take a screenshot of the receipt.</p>
                            <p>4. Upload the receipt below.</p>
                            
                            <div class="qr-container-dash" style="margin: 20px auto; width: 180px; height: 180px; border-radius: 15px;">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=GCash:09171234567" alt="GCash QR Code" class="qr-image-dash">
                            </div>
                            
                            <div class="payment-details" style="background: rgba(0,0,0,0.2); padding: 12px; border-radius: 12px; margin-top: 10px;">
                                <p style="font-size: 0.85rem; margin-bottom: 5px;"><strong>GCash:</strong> 0917-123-4567</p>
                                <p style="font-size: 0.85rem;"><strong>Name:</strong> Martinez Fitness</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upgrade Modal -->
    <div class="modal-overlay" id="upgradeModal">
        <div class="modal" style="max-width: 900px;">
            <div class="modal-header">
                <h3><i class="fas fa-arrow-up"></i> Upgrade Your Membership</h3>
                <button class="close-modal" onclick="closeUpgradeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body" style="padding: 24px;">
                <p style="color: var(--dark-text-secondary); margin-bottom: 24px; text-align: center;">
                    Choose a higher-tier package to unlock more benefits and features
                </p>
                
                <div id="upgradePlansContainer" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; max-height: 500px; overflow-y: auto; padding: 10px;">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
            
            <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--dark-border); text-align: right;">
                <button class="btn btn-secondary" onclick="closeUpgradeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Booking Details Modal -->
    <div class="modal-overlay" id="bookingDetailsModal">
        <div class="modal" style="max-width: 800px;">
            <div class="modal-header" style="padding: 24px 32px; border-bottom: 1px solid var(--dark-border);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; background: var(--glass); border-radius: 10px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--glass-border);">
                        <i class="fas fa-file-invoice" style="color: var(--primary);"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0;">Package Hub</h3>
                        <span id="detailRef" style="font-size: 0.75rem; color: var(--dark-text-secondary); font-weight: 700;">REF: #000000</span>
                    </div>
                </div>
                <button class="close-modal" onclick="closeBookingDetailsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-tabs">
                <button class="modal-tab-btn active" onclick="switchModalTab('info')">Overview</button>
                <button class="modal-tab-btn" onclick="switchModalTab('plan')">Exercise Plan</button>
                <button class="modal-tab-btn" onclick="switchModalTab('calendar')">Training Calendar</button>
                <button class="modal-tab-btn" onclick="switchModalTab('diet')">Nutrition & Diet</button>
                <button class="modal-tab-btn" onclick="switchModalTab('tips')">Tips & Guidance</button>
            </div>
            
            <div class="modal-body" style="padding: 0 32px 32px; max-height: 60vh; overflow-y: auto;">
                <!-- Info Tab -->
                <div id="modalTabInfo" class="modal-tab-content active">
                    <div class="booking-details-grid" style="margin-top: 20px;">
                        <div class="booking-detail-item">
                            <span class="detail-label">Package Name</span>
                            <div class="detail-value" id="detailPackage">
                                <i class="fas fa-dumbbell"></i>
                                <span>-</span>
                            </div>
                        </div>
                        <div class="booking-detail-item">
                            <span class="detail-label">Status</span>
                            <div id="detailStatus">
                                <!-- Badge here -->
                            </div>
                        </div>
                        <div class="booking-detail-item">
                            <span class="detail-label">Start Date</span>
                            <div class="detail-value" id="detailDate">
                                <i class="fas fa-calendar-alt"></i>
                                <span>-</span>
                            </div>
                        </div>
                        <div class="booking-detail-item" id="detailExpiryContainer">
                            <span class="detail-label">Expiry Date</span>
                            <div class="detail-value" id="detailExpiry">
                                <i class="fas fa-calendar-times"></i>
                                <span>-</span>
                            </div>
                        </div>
                        <div class="booking-detail-item">
                            <span class="detail-label">Amount Paid</span>
                            <div class="detail-value" id="detailAmount" style="color: var(--primary); font-size: 1.25rem; font-weight: 800;">
                                <i class="fas fa-tag"></i>
                                <span>₱0.00</span>
                            </div>
                        </div>
                        <div class="booking-detail-item">
                            <span class="detail-label">Contact Info</span>
                            <div class="detail-value" id="detailContact">
                                <i class="fas fa-phone-alt"></i>
                                <span>-</span>
                            </div>
                        </div>
                        <div class="booking-detail-item" id="detailTrainerContainer" style="display: none;">
                            <span class="detail-label">Assigned Trainer</span>
                            <div class="detail-value" id="detailTrainer" style="color: var(--primary); font-weight: 700;">
                                <i class="fas fa-user-tie"></i>
                                <span>-</span>
                            </div>
                        </div>
                    </div>

                    <div id="detailNotesSection" class="notes-section" style="display: none; margin-top: 24px;">
                        <span class="notes-label">Admin/User Notes</span>
                        <p class="notes-text" id="detailNotes">-</p>
                    </div>

                    <!-- Booking Hub Quick Actions -->
                    <div id="detailActions" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--dark-border); display: flex; gap: 12px;">
                        <button id="detailRenewBtn" class="btn btn-renew" style="flex: 1; background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); justify-content: center;">
                            <i class="fas fa-redo"></i>
                            <span>Renew Plan</span>
                        </button>
                        <button id="detailUpgradeBtn" class="btn btn-upgrade" style="flex: 1; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); justify-content: center; display: none;">
                            <i class="fas fa-arrow-up"></i>
                            <span>Upgrade Tier</span>
                        </button>
                    </div>
                    
                    <div class="receipt-preview-container" id="receiptSection" style="display: none; margin-top: 24px;">
                        <div class="receipt-preview-header">
                            <h4><i class="fas fa-receipt"></i> Payment Receipt</h4>
                            <span style="font-size: 0.75rem; color: var(--dark-text-secondary);"><i class="fas fa-info-circle"></i> Click to enlarge</span>
                        </div>
                        <div class="receipt-img-wrapper" id="receiptImgWrapper">
                            <img id="detailReceipt" src="" alt="Payment Receipt">
                        </div>
                    </div>
                </div>

                <!-- Plan Tab -->
                <div id="modalTabPlan" class="modal-tab-content">
                    <div id="modalPlanContent" style="padding-top: 20px;">
                        <div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">
                            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 12px;"></i>
                            <p>Loading your exercise plan...</p>
                        </div>
                    </div>
                </div>

                <!-- Calendar Tab -->
                <div id="modalTabCalendar" class="modal-tab-content">
                    <div style="padding-top: 20px;">
                        <div id="modalCalendar" style="min-height: 400px; background: var(--dark-card); border-radius: 12px; padding: 10px; border: 1px solid var(--dark-border);"></div>
                    </div>
                </div>

                <!-- Diet Tab -->
                <div id="modalTabDiet" class="modal-tab-content">
                    <div id="modalDietContent" style="padding-top: 20px;">
                        <div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">
                            <i class="fas fa-utensils" style="font-size: 2rem; opacity: 0.2; margin-bottom: 12px; display: block;"></i>
                            <p>No nutrition plan assigned yet.</p>
                        </div>
                    </div>
                </div>

                <!-- Tips Tab -->
                <div id="modalTabTips" class="modal-tab-content">
                    <div id="modalTipsContent" style="padding-top: 20px;">
                        <div style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">
                            <i class="fas fa-lightbulb" style="font-size: 2rem; opacity: 0.2; margin-bottom: 12px; display: block;"></i>
                            <p>No tips shared by your coach yet.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="padding: 24px 32px; border-top: 1px solid var(--dark-border); display: flex; justify-content: flex-end;">
                <button class="btn btn-secondary" onclick="closeBookingDetailsModal()" style="padding: 10px 24px;">
                    <i class="fas fa-times" style="margin-right: 8px;"></i>
                    <span>Close Hub</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Exercise Plan Modal -->
    <div class="modal-overlay" id="exercisePlanModal">
        <div class="modal" style="max-width: 700px;">
            <div class="modal-header">
                <div>
                    <h3 id="exercisePlanTitle">Exercise Plan</h3>
                    <p id="exercisePlanSubtitle" style="font-size: 0.9rem; color: var(--dark-text-secondary); margin-top: 4px;"></p>
                </div>
                <button class="close-modal" onclick="closeExercisePlanModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body" id="exercisePlanContent">
                <!-- Content populated by JS -->
            </div>
            
            <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--dark-border); text-align: right;">
                <button class="btn btn-secondary" onclick="closeExercisePlanModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Survey Modal -->
    <div class="modal-overlay survey-modal" id="surveyModal">
        <div class="modal" style="max-width: 700px;">
            <button class="close-modal" onclick="skipSurvey()" style="top: 20px; right: 20px;">
                <i class="fas fa-times"></i>
            </button>
            <div class="survey-header">
                <i class="fas fa-dumbbell"></i>
                <h2>Personalize Your Journey</h2>
                <p>Help us find the perfect package for your fitness goals!</p>
            </div>
            
            <div class="survey-body">
                <!-- Step 1: Basic Profile -->
                <div class="survey-step active" data-step="1">
                    <label class="question-label">A. Basic Profile</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                        <div class="form-group">
                            <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px;">Age</label>
                            <input type="number" id="surveyAge" class="form-control" placeholder="Years" style="background: rgba(255,255,255,0.05); border: 1px solid var(--dark-border); color: #fff; padding: 12px; border-radius: 8px; width: 100%;">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px;">Sex</label>
                            <select id="surveySex" class="form-control" style="background: rgba(255,255,255,0.05); border: 1px solid var(--dark-border); color: #fff; padding: 12px; border-radius: 8px; width: 100%;">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                        <div class="form-group">
                            <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px;">Height (cm)</label>
                            <input type="number" id="surveyHeight" class="form-control" placeholder="CM" oninput="checkStepValid()" style="background: rgba(255,255,255,0.05); border: 1px solid var(--dark-border); color: #fff; padding: 12px; border-radius: 8px; width: 100%;">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px;">Weight (kg)</label>
                            <input type="number" id="surveyWeight" class="form-control" placeholder="KG" oninput="checkStepValid()" style="background: rgba(255,255,255,0.05); border: 1px solid var(--dark-border); color: #fff; padding: 12px; border-radius: 8px; width: 100%;">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 24px;">
                        <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px;">Medical Conditions (if any)</label>
                        <textarea id="surveyMedical" class="form-control" placeholder="Specify any conditions or type 'None'" style="background: rgba(255,255,255,0.05); border: 1px solid var(--dark-border); color: #fff; padding: 12px; border-radius: 8px; width: 100%; height: 80px;"></textarea>
                    </div>
                    <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px; text-align: center;">Exercise Experience</label>
                    <div class="options-grid">
                        <div class="option-card" onclick="selectSurveyOption(this, 'exercise_history', 'Beginner')">
                            <i class="fas fa-seedling"></i>
                            <span>Beginner</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'exercise_history', 'Intermediate')">
                            <i class="fas fa-running"></i>
                            <span>Intermediate</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'exercise_history', 'Advanced')">
                            <i class="fas fa-fire"></i>
                            <span>Advanced</span>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Fitness Goals -->
                <div class="survey-step" data-step="2">
                    <label class="question-label">B. Fitness Goals</label>
                    <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px; text-align: center;">Primary Goal</label>
                    <div class="options-grid" style="margin-bottom: 24px;">
                        <div class="option-card" onclick="selectSurveyOption(this, 'primary_goal', 'Lose weight')">
                            <i class="fas fa-weight"></i>
                            <span>Lose Weight</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'primary_goal', 'Gain muscle')">
                            <i class="fas fa-dumbbell"></i>
                            <span>Gain Muscle</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'primary_goal', 'Improve endurance')">
                            <i class="fas fa-heartbeat"></i>
                            <span>Improve Endurance</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'primary_goal', 'Stay fit / general health')">
                            <i class="fas fa-user-check"></i>
                            <span>Stay Fit</span>
                        </div>
                    </div>
                    <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px; text-align: center;">Desired Pace</label>
                    <div class="options-grid">
                        <div class="option-card" onclick="selectSurveyOption(this, 'goal_pace', 'Slowly')">
                            <i class="fas fa-clock"></i>
                            <span>Slowly</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'goal_pace', 'Moderately')">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Moderately</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'goal_pace', 'Intensively')">
                            <i class="fas fa-bolt"></i>
                            <span>Intensively</span>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Availability & Commitment -->
                <div class="survey-step" data-step="3">
                    <label class="question-label">C. Availability & Commitment</label>
                    <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px; text-align: center;">Workout Days Per Week</label>
                    <div class="options-grid" style="margin-bottom: 24px;">
                        <div class="option-card" onclick="selectSurveyOption(this, 'workout_days_per_week', '1-2 days')">
                            <i class="fas fa-calendar-day"></i>
                            <span>1-2 Days</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'workout_days_per_week', '3-4 days')">
                            <i class="fas fa-calendar-week"></i>
                            <span>3-4 Days</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'workout_days_per_week', '5+ days')">
                            <i class="fas fa-calendar-check"></i>
                            <span>5+ Days</span>
                        </div>
                    </div>
                    <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px; text-align: center;">Preferred Workout Time</label>
                    <div class="options-grid">
                        <div class="option-card" onclick="selectSurveyOption(this, 'preferred_workout_time', 'Morning')">
                            <i class="fas fa-sun"></i>
                            <span>Morning</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'preferred_workout_time', 'Afternoon')">
                            <i class="fas fa-cloud-sun"></i>
                            <span>Afternoon</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'preferred_workout_time', 'Evening')">
                            <i class="fas fa-moon"></i>
                            <span>Evening</span>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Condition & Focus -->
                <div class="survey-step" data-step="4">
                    <label class="question-label">D. Physical Condition & Focus</label>
                    <div class="form-group" style="margin-bottom: 24px;">
                        <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px;">Injuries or Physical Limitations (if any)</label>
                        <textarea id="surveyInjuries" class="form-control" placeholder="Describe any injuries or type 'None'" style="background: rgba(255,255,255,0.05); border: 1px solid var(--dark-border); color: #fff; padding: 12px; border-radius: 8px; width: 100%; height: 80px;"></textarea>
                    </div>
                    <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px; text-align: center;">Focus Areas (Which areas do you want to focus on?)</label>
                    <div class="options-grid">
                        <div class="option-card" onclick="toggleSurveyOption(this, 'focus_areas', 'Arms')">
                            <i class="fas fa-hand-fist"></i>
                            <span>Arms</span>
                        </div>
                        <div class="option-card" onclick="toggleSurveyOption(this, 'focus_areas', 'Legs')">
                            <i class="fas fa-shoe-prints"></i>
                            <span>Legs</span>
                        </div>
                        <div class="option-card" onclick="toggleSurveyOption(this, 'focus_areas', 'Core')">
                            <i class="fas fa-shield-alt"></i>
                            <span>Core</span>
                        </div>
                        <div class="option-card" onclick="toggleSurveyOption(this, 'focus_areas', 'Full body')">
                            <i class="fas fa-male"></i>
                            <span>Full Body</span>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Preferences & Confidence -->
                <div class="survey-step" data-step="5">
                    <label class="question-label">E & F. Preferences & Confidence</label>
                    <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px; text-align: center;">Workout Type Preference</label>
                    <div class="options-grid" style="margin-bottom: 24px;">
                        <div class="option-card" onclick="selectSurveyOption(this, 'workout_type', 'Cardio')">
                            <i class="fas fa-heartbeat"></i>
                            <span>Cardio</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'workout_type', 'Strength training')">
                            <i class="fas fa-dumbbell"></i>
                            <span>Strength</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'workout_type', 'Mixed')">
                            <i class="fas fa-sync-alt"></i>
                            <span>Mixed</span>
                        </div>
                    </div>
                    <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px; text-align: center;">Guidance Preference</label>
                    <div class="options-grid" style="margin-bottom: 24px;">
                        <div class="option-card" onclick="selectSurveyOption(this, 'trainer_guidance', 'With trainer guidance')">
                            <i class="fas fa-user-friends"></i>
                            <span>With Trainer</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'trainer_guidance', 'Independent workout')">
                            <i class="fas fa-user"></i>
                            <span>Independent</span>
                        </div>
                    </div>
                    <label style="font-size: 0.75rem; color: var(--dark-text-secondary); display: block; margin-bottom: 8px; text-align: center;">Gym Equipment Confidence</label>
                    <div class="options-grid">
                        <div class="option-card" onclick="selectSurveyOption(this, 'equipment_confidence', 'Not confident')">
                            <i class="fas fa-frown"></i>
                            <span>Not Confident</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'equipment_confidence', 'Somewhat confident')">
                            <i class="fas fa-meh"></i>
                            <span>Somewhat</span>
                        </div>
                        <div class="option-card" onclick="selectSurveyOption(this, 'equipment_confidence', 'Very')">
                            <i class="fas fa-smile"></i>
                            <span>Very Confident</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="survey-footer">
                <div class="progress-bar">
                    <div class="progress-fill" id="surveyProgress" style="width: 20%;"></div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="survey-nav-btn" id="surveyBackBtn" style="display: none; background: rgba(255,255,255,0.05); color: #fff;" onclick="prevSurveyStep()">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back</span>
                    </button>
                    <button class="survey-nav-btn btn-next" id="surveyNextBtn" disabled onclick="nextSurveyStep()">
                        <span>Next Step</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Recommendation Modal -->
    <div class="modal-overlay recommendation-modal" id="recommendationModal">
        <div class="modal">
            <div class="recommendation-header">
                <i class="fas fa-star"></i>
                <h2>Your Perfect Match!</h2>
                <p>Based on your survey, we recommend this package for you:</p>
            </div>
            
            <div class="recommendation-body">
                <div class="recommended-package-preview" id="recommendedPackagePreview">
                    <h3 id="recPackageName">-</h3>
                    <div class="price" id="recPackagePrice">-</div>
                    <div class="duration" id="recPackageDuration">-</div>
                    <p id="recPackageDesc" style="color: var(--dark-text-secondary); margin-bottom: 0;">-</p>
                </div>

                <div class="recommended-actions">
                    <button class="btn btn-secondary" onclick="closeRecommendationModal()">
                        <i class="fas fa-times"></i>
                        <span>Maybe Later</span>
                    </button>
                    <button class="btn btn-primary" id="bookRecommendedBtn">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Book This Now</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress History Modal -->
    <div class="modal-overlay" id="myProgressModal">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-chart-line"></i> My Progress History</h3>
                <button class="close-modal" onclick="closeMyProgressModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="myProgressContent" style="max-height: 450px; overflow-y: auto;">
                    <!-- Populated by JS -->
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--dark-border); text-align: right;">
                <button class="btn btn-secondary" onclick="closeMyProgressModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Notifications Modal -->
    <div class="modal-overlay" id="notificationsModal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-bell"></i> My Notifications</h3>
                <button class="close-modal" onclick="toggleNotifications()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="notificationsList" style="max-height: 400px; overflow-y: auto; padding: 10px;">
                    <!-- Populated by JS -->
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--dark-border); text-align: right;">
                <button class="btn btn-secondary btn-sm" onclick="markAllAsRead()">Mark all as read</button>
            </div>
        </div>
    </div>

    <!-- Dashboard Scripts -->
    <script src="../../assets/js/user-dashboard.js"></script>
</body>
</html>
