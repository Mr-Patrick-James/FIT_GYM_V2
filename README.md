# FitPay Gym Management System

A comprehensive web-based gym management system built with PHP and MySQL. Manage memberships, trainers, bookings, payments, and member progress tracking.

![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)
![License](https://img.shields.io/badge/license-MIT-green)

## 🌟 Features

### For Members
- ✅ User registration with email verification (OTP)
- 📦 Browse and purchase membership packages
- 💳 Upload payment receipts (GCash integration)
- 📊 Track workout progress and weight
- 🍎 Receive meal recommendations from trainers
- 📅 View scheduled training sessions
- 🔔 Real-time notifications
- 📱 Responsive dashboard

### For Trainers
- 👥 View assigned clients
- 💪 Create personalized workout plans
- 📈 Log member progress
- 🥗 Provide meal recommendations
- 📝 Share fitness tips
- 📅 Schedule training sessions
- 📊 Track client performance

### For Administrators
- 👨‍💼 Manage users, trainers, and members
- 📦 Create and manage membership packages
- 💰 Verify payments and bookings
- 🏋️ Manage exercise library
- 🛠️ Equipment inventory management
- ⚙️ System settings configuration
- 📧 Email notification management
- 📊 View reports and analytics
- 🎨 Customize gym branding

## 🚀 Quick Start

### Prerequisites
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server
- Composer
- 50MB+ disk space

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/fitpay-gym.git
   cd fitpay-gym
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp env.example .env
   # Edit .env with your database and email credentials
   ```

4. **Import database**
   ```bash
   mysql -u root -p
   CREATE DATABASE fitpay_gym;
   USE fitpay_gym;
   SOURCE database/fitpay_gym.sql;
   EXIT;
   ```

5. **Set permissions**
   ```bash
   chmod 755 api/
   chmod 777 assets/uploads/exercises/
   chmod 777 api/uploads/receipts/
   ```

6. **Access the application**
   ```
   http://localhost/fitpay-gym/
   ```

For detailed installation instructions, see [INSTALLATION_GUIDE.md](INSTALLATION_GUIDE.md)

## 📖 Documentation

- **[Installation Guide](INSTALLATION_GUIDE.md)** - Complete setup instructions
- **[Security Improvements](SECURITY_IMPROVEMENTS.md)** - Security analysis and recommendations
- **[API Documentation](api/README.md)** - API endpoints reference

## 🏗️ System Architecture

### Technology Stack
- **Backend:** PHP 8.0+
- **Database:** MySQL 5.7+ (MyISAM)
- **Email:** PHPMailer with SMTP
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Authentication:** Session-based with OTP verification

### Database Schema
```
users → bookings → payments
  ↓        ↓
trainers  packages → package_exercises → exercises
  ↓                                          ↓
member_exercise_plans                    equipment
member_progress
food_recommendations
notifications
```

### Key Components
- **Authentication System** - OTP-based email verification
- **Booking Management** - Package selection and payment verification
- **Trainer Portal** - Client management and progress tracking
- **Exercise Library** - Comprehensive exercise database
- **Notification System** - Email and in-app notifications
- **Settings Management** - Configurable gym settings

## 🔐 Security Features

- ✅ Password hashing with bcrypt
- ✅ Prepared statements (SQL injection prevention)
- ✅ Session management with secure cookies
- ✅ OTP-based email verification
- ✅ Role-based access control (RBAC)
- ✅ File upload validation
- ✅ Environment variable configuration
- ⚠️ CSRF protection (recommended)
- ⚠️ Rate limiting (recommended)

See [SECURITY_IMPROVEMENTS.md](SECURITY_IMPROVEMENTS.md) for detailed security analysis.

## 📱 Screenshots

### Landing Page
Modern, responsive landing page with package showcase and gym information.

### Member Dashboard
Track bookings, view workout plans, and monitor progress.

### Admin Panel
Comprehensive admin dashboard for managing all aspects of the gym.

### Trainer Portal
Manage clients, create workout plans, and track member progress.

## 🛠️ Configuration

### Environment Variables (.env)
```env
# Database
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=fitpay_gym

# Email (SMTP)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_FROM_EMAIL=noreply@martinezfitness.com
SMTP_FROM_NAME=Martinez Fitness

# Application
BASE_URL=http://localhost/fitpay-gym
TIMEZONE=Asia/Manila
```

### Admin Settings
Configure via Admin Dashboard:
- Gym name and branding
- Contact information
- Payment details (GCash)
- Operating hours
- Email notifications
- Hero images

## 📊 Database Tables

| Table | Purpose |
|-------|---------|
| `users` | User accounts (admin, trainer, member) |
| `bookings` | Membership bookings and status |
| `packages` | Membership plans and pricing |
| `trainers` | Trainer profiles and specializations |
| `exercises` | Exercise library with instructions |
| `equipment` | Gym equipment inventory |
| `package_exercises` | Exercise assignments to packages |
| `member_exercise_plans` | Personalized workout plans |
| `member_progress` | Weight and progress tracking |
| `food_recommendations` | Meal plans from trainers |
| `notifications` | In-app notification system |
| `payments` | Payment transaction records |
| `otps` | Email verification codes |
| `email_configs` | SMTP configuration |
| `gym_settings` | System settings (key-value) |

## Appendix F – Program Listing (Essential Components)

This appendix lists the most important program modules and core functions used by the system. It focuses only on the critical application logic, not every page or utility script.

### 1. Application Entry and Startup
- [index.php](index.php)
  - getSetting($key, $default = '', $settings = [])
  - Loads active package data from the database
  - Loads gym settings from `gym_settings`
  - Checks if a user is already logged in and redirects to the appropriate dashboard
  - Auto-creates the WHO Health & Fitness Plan if missing

### 2. Core Configuration and Database Access
- [api/config.php](api/config.php)
  - getEnvVar($key, $default = '')
  - getDBConnection()
  - createNotification($userId, $title, $message, $type = 'info')
  - sendResponse($success, $message, $data = null, $statusCode = 200)
  - getRequestData()
  - Handles database connection setup, JSON responses, and automatic expiry checks

### 3. Session, Authentication, and Access Control
- [api/session.php](api/session.php)
  - isLoggedIn()
  - isAdmin()
  - isManager()
  - isTrainer()
  - validateSession()
  - getCurrentUser()
  - canAccessResource($resourceType, $resourceId = null)
  - requireLogin()
  - requireAdmin()
  - setUserSession($user)
  - clearUserSession()

- [api/access-control.php](api/access-control.php)
  - hasPermission($permission, $userRole = null)
  - hasRoleLevel($requiredLevel, $userRole = null)
  - requirePermission($permission)
  - requireRoleLevel($role)
  - getAccessibleRoles($userRole = null)
  - canManageUser($targetUserId, $currentUserId = null, $currentUserRole = null)
  - sendAccessDenied($message = 'Access denied')

### 4. Authentication Modules
- [api/auth/login.php](api/auth/login.php)
- [api/auth/signup.php](api/auth/signup.php)
- [api/auth/verify-otp.php](api/auth/verify-otp.php)
- [api/auth/resend-otp.php](api/auth/resend-otp.php)
- [api/auth/logout.php](api/auth/logout.php)

Core functions and responsibilities:
- User login verification
- User registration and OTP email flow
- Account verification with OTP code
- Resending OTP codes
- Logout and session clearing

### 5. Booking Management
- [api/bookings/create.php](api/bookings/create.php)
- [api/bookings/get-all.php](api/bookings/get-all.php)
- [api/bookings/update.php](api/bookings/update.php)
- [api/bookings/check-status.php](api/bookings/check-status.php)
- [api/bookings/change-plan.php](api/bookings/change-plan.php)
- [api/bookings/upgrade.php](api/bookings/upgrade.php)

Core logic:
- Create and update bookings
- Fetch member bookings
- Check payment or package status
- Upgrade membership plans
- Validate booking data before saving

### 6. Package and Exercise Management
- [api/packages/create.php](api/packages/create.php)
- [api/packages/get-all.php](api/packages/get-all.php)
- [api/packages/update.php](api/packages/update.php)
- [api/packages/delete.php](api/packages/delete.php)
- [api/packages/get-exercises.php](api/packages/get-exercises.php)
- [api/packages/add-exercise.php](api/packages/add-exercise.php)
- [api/packages/remove-exercise.php](api/packages/remove-exercise.php)

- [api/exercises/create.php](api/exercises/create.php)
- [api/exercises/get-all.php](api/exercises/get-all.php)
- [api/exercises/update.php](api/exercises/update.php)
- [api/exercises/delete.php](api/exercises/delete.php)

Core logic:
- Create and manage membership packages
- Link exercises to packages
- Delete or update package records
- Maintain the exercise library used by training plans

### 7. Trainer and Client Functional Modules
- [api/trainers/get-clients.php](api/trainers/get-clients.php)
- [api/trainers/get-member-plan.php](api/trainers/get-member-plan.php)
- [api/trainers/save-member-plan.php](api/trainers/save-member-plan.php)
- [api/trainers/get-sessions.php](api/trainers/get-sessions.php)
- [api/trainers/save-session.php](api/trainers/save-session.php)
- [api/trainers/log-progress.php](api/trainers/log-progress.php)
- [api/trainers/get-progress-history.php](api/trainers/get-progress-history.php)

Core logic:
- Retrieve assigned clients for trainers
- Load member training plans
- Save workout plans
- Add training sessions
- Track progress and history

### 8. Payments, Reports, and Notifications
- [api/payments/get-all.php](api/payments/get-all.php)
- [api/reports/get-sales.php](api/reports/get-sales.php)
- [api/notifications/get-all.php](api/notifications/get-all.php)
- [api/notifications/mark-as-read.php](api/notifications/mark-as-read.php)

Core logic:
- Retrieve payment records
- Generate sales or reporting data
- Manage notification records
- Mark notifications as read by users

### 9. Email and Receipt Generation
- [api/email.php](api/email.php)
  - getEmailConfig()
  - sendOTPEmailSimple()
  - sendOTPEmail()
  - sendBookingNotificationEmail()
  - sendBookingVerificationEmail()
  - sendBookingRejectionEmail()
  - sendBookingExpiryEmail()
  - sendTrainerNewBookingEmail()
  - processExpiringBookings()

- [api/receipt/generate-walkin.php](api/receipt/generate-walkin.php)
  - generateWalkinReceiptHTML($booking, $payment)

Core logic:
- Send OTP and booking emails
- Notify users and trainers regarding bookings
- Generate receipts and expiry notifications

### 10. Upload and File Handling
- [api/upload/receipt.php](api/upload/receipt.php)
- [api/upload/progress-photo.php](api/upload/progress-photo.php)
- [api/upload/trainer-photo.php](api/upload/trainer-photo.php)

Core logic:
- Validate uploads
- Save receipts and progress images
- Store profile or trainer photo files

### 11. Essential System Flow
1. User registers or logs in.
2. Session and role permissions are verified.
3. Member creates a booking and uploads a payment receipt.
4. Admin verifies the payment and activates the booking.
5. Trainer manages assigned members and training plans.
6. Progress, sessions, and notifications are recorded.
7. Reports and email notifications are generated automatically.

This appendix captures the most important modules and functions in the system. It is intended to provide a clear overview of the core program structure without listing every utility script in the project.

## 🔄 Workflow

### Member Registration
1. User signs up with email
2. OTP sent to email
3. User verifies OTP
4. Account created
5. User can browse packages

### Booking Process
1. Member selects package
2. Uploads payment receipt
3. Admin receives notification
4. Admin verifies payment
5. Booking activated with expiry date
6. Member receives confirmation email

### Trainer Assignment
1. Admin creates trainer account
2. Trainer assigned to packages
3. Members with those packages see trainer
4. Trainer can create workout plans
5. Trainer logs progress

## 🚧 Known Issues & Limitations

- MyISAM engine (no foreign key constraints)
- No CSRF protection implemented
- No rate limiting on API endpoints
- File uploads stored in web-accessible directory
- Session management could be improved
- No automated testing suite

See [SECURITY_IMPROVEMENTS.md](SECURITY_IMPROVEMENTS.md) for complete list and recommendations.

## 🔮 Future Enhancements

- [ ] Migrate to InnoDB for better data integrity
- [ ] Implement REST API with JWT authentication
- [ ] Add mobile app (React Native/Flutter)
- [ ] Integrate online payment gateways (PayPal, Stripe)
- [ ] Add QR code check-in system
- [ ] Implement attendance tracking
- [ ] Add workout video library
- [ ] Create mobile-responsive PWA
- [ ] Add multi-language support
- [ ] Implement automated testing
- [ ] Add analytics dashboard
- [ ] Create member mobile app

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Coding Standards
- Follow PSR-12 coding standards
- Use prepared statements for all database queries
- Add comments for complex logic
- Write meaningful commit messages
- Test thoroughly before submitting PR

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👥 Authors

- **Original Developer** - Initial work and system design
- **Security Audit** - Kiro AI Assistant (March 2026)

## 🙏 Acknowledgments

- PHPMailer for email functionality
- Font Awesome for icons
- Google Fonts for typography
- Unsplash for placeholder images

## 📞 Support

For issues, questions, or suggestions:

1. Check the [Installation Guide](INSTALLATION_GUIDE.md)
2. Review [Security Improvements](SECURITY_IMPROVEMENTS.md)
3. Check existing issues on GitHub
4. Create a new issue with detailed information

## 📈 Version History

- **v2.0** (March 2026) - Security improvements, documentation
- **v1.0** (January 2026) - Initial release

## ⚠️ Important Notes

### Before Deployment
1. Change all default passwords
2. Configure email SMTP properly
3. Set up SSL certificate (HTTPS)
4. Review and apply security recommendations
5. Test all functionality thoroughly
6. Set up automated backups
7. Configure proper file permissions
8. Remove or protect sensitive files

### Production Checklist
- [ ] Environment variables configured
- [ ] Database credentials secured
- [ ] Email SMTP working
- [ ] HTTPS enabled
- [ ] Error logging enabled
- [ ] Display errors disabled
- [ ] Backups configured
- [ ] File permissions set
- [ ] .env file protected
- [ ] Security headers added

## 🔗 Links

- **Demo:** [Coming Soon]
- **Documentation:** [GitHub Wiki]
- **Issues:** [GitHub Issues]
- **Changelog:** [CHANGELOG.md]

---

**Built with ❤️ for fitness enthusiasts and gym owners**

**Last Updated:** March 20, 2026
