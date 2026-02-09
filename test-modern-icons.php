<?php
// Test page to show off the new modern icons
require_once 'api/session.php';
requireAdmin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Icons Demo | FitPay Admin</title>
    
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
        
        .demo-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .icon-showcase {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin: 32px 0;
        }
        
        .icon-card {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: var(--radius-lg);
            padding: 32px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .icon-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
        }
        
        .icon-display {
            font-size: 3rem;
            margin-bottom: 16px;
            color: var(--primary);
        }
        
        .icon-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--primary);
        }
        
        .icon-description {
            color: var(--dark-text-secondary);
            font-size: 0.9rem;
        }
        
        .comparison {
            background: var(--glass);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin: 32px 0;
        }
        
        .comparison h3 {
            color: var(--primary);
            margin-bottom: 16px;
        }
        
        .badge-showcase {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            justify-content: center;
            margin: 24px 0;
        }
        
        .before-after {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin: 24px 0;
        }
        
        .before, .after {
            text-align: center;
            padding: 20px;
            border-radius: var(--radius-md);
        }
        
        .before {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .after {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        .emoji-icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        
        .fas-icon {
            font-size: 2rem;
            margin-bottom: 8px;
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
    <div class="demo-container">
        <h1><i class="fas fa-icons"></i> Modern Icons Upgrade</h1>
        <p>Upgraded from basic emojis to professional Font Awesome icons for a more polished look!</p>
        
        <div class="icon-showcase">
            <div class="icon-card">
                <div class="icon-display">
                    <i class="fas fa-person-walking"></i>
                </div>
                <div class="icon-title">Walk-in Icon</div>
                <div class="icon-description">
                    Professional walking icon for walk-in customers
                </div>
            </div>
            
            <div class="icon-card">
                <div class="icon-display">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="icon-title">Member Icon</div>
                <div class="icon-description">
                    Verified user icon for regular members
                </div>
            </div>
        </div>
        
        <div class="comparison">
            <h3><i class="fas fa-exchange-alt"></i> Icon Comparison</h3>
            
            <div class="before-after">
                <div class="before">
                    <h4 style="color: #f59e0b; margin-bottom: 16px;">
                        <i class="fas fa-times-circle"></i> Before (Emojis)
                    </h4>
                    <div class="emoji-icon">🚶</div>
                    <p style="color: var(--dark-text-secondary);">Basic walking emoji</p>
                    <div style="margin-top: 16px;">
                        <span class="walkin-badge">🚶 Walk-in</span>
                    </div>
                </div>
                
                <div class="after">
                    <h4 style="color: #22c55e; margin-bottom: 16px;">
                        <i class="fas fa-check-circle"></i> After (Font Awesome)
                    </h4>
                    <div class="fas-icon">
                        <i class="fas fa-person-walking"></i>
                    </div>
                    <p style="color: var(--dark-text-secondary);">Professional vector icon</p>
                    <div style="margin-top: 16px;">
                        <span class="walkin-badge"><i class="fas fa-person-walking"></i> Walk-in</span>
                    </div>
                </div>
            </div>
            
            <div class="before-after">
                <div class="before">
                    <h4 style="color: #f59e0b; margin-bottom: 16px;">
                        <i class="fas fa-times-circle"></i> Before (Emojis)
                    </h4>
                    <div class="emoji-icon">👤</div>
                    <p style="color: var(--dark-text-secondary);">Basic person emoji</p>
                    <div style="margin-top: 16px;">
                        <span class="regular-badge">👤 Member</span>
                    </div>
                </div>
                
                <div class="after">
                    <h4 style="color: #22c55e; margin-bottom: 16px;">
                        <i class="fas fa-check-circle"></i> After (Font Awesome)
                    </h4>
                    <div class="fas-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <p style="color: var(--dark-text-secondary);">Verified user icon</p>
                    <div style="margin-top: 16px;">
                        <span class="regular-badge"><i class="fas fa-user-check"></i> Member</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="badge-showcase">
            <span class="walkin-badge"><i class="fas fa-person-walking"></i> Walk-in</span>
            <span class="regular-badge"><i class="fas fa-user-check"></i> Member</span>
        </div>
        
        <div style="background: var(--glass); padding: 24px; border-radius: var(--radius-lg); margin: 32px 0;">
            <h3 style="color: var(--primary); margin-bottom: 16px;">
                <i class="fas fa-sparkles"></i> Benefits of Modern Icons
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                <div>
                    <h4 style="color: var(--dark-text); margin-bottom: 8px;">
                        <i class="fas fa-palette"></i> Professional Look
                    </h4>
                    <p style="color: var(--dark-text-secondary); margin: 0;">
                        Vector icons scale perfectly and look crisp on all devices
                    </p>
                </div>
                <div>
                    <h4 style="color: var(--dark-text); margin-bottom: 8px;">
                        <i class="fas fa-mobile-alt"></i> Consistent Across Devices
                    </h4>
                    <p style="color: var(--dark-text-secondary); margin: 0;">
                        No emoji compatibility issues across different platforms
                    </p>
                </div>
                <div>
                    <h4 style="color: var(--dark-text); margin-bottom: 8px;">
                        <i class="fas fa-paint-brush"></i> Better Styling Control
                    </h4>
                    <p style="color: var(--dark-text-secondary); margin: 0;">
                        Can be styled with CSS for hover effects and animations
                    </p>
                </div>
                <div>
                    <h4 style="color: var(--dark-text); margin-bottom: 8px;">
                        <i class="fas fa-universal-access"></i> Accessibility
                    </h4>
                    <p style="color: var(--dark-text-secondary); margin: 0;">
                        Better screen reader support and semantic meaning
                    </p>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin: 40px 0;">
            <a href="views/admin/bookings.php" class="action-btn" target="_blank">
                <i class="fas fa-eye"></i>
                See Modern Icons in Action
            </a>
        </div>
    </div>
</body>
</html>
