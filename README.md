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

```
MIT License

Copyright (c) 2026 Martinez Fitness Gym

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

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
