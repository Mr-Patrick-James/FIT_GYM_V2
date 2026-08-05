# Student Discount Feature Guide

## Overview
The Student Discount feature allows students to get discounted gym memberships upon verification of their student ID.

## Default Configuration
- **Default Discount**: 10% off all packages
- **Customizable**: Discount percentage can be customized per package by administrators

## Setup Instructions

### Step 1: Run the Migration
Execute the migration script to add the necessary database columns:

**Via Web Browser:**
```
http://your-gym-app.com/migrate_student_discount.php?allow_web=1
```

**Via Command Line:**
```bash
php /path/to/migrate_student_discount.php
```

This will add the following columns:
- `bookings.is_student` (boolean)
- `bookings.student_id_url` (student ID image URL)
- `bookings.student_discount_applied` (discount amount applied)
- `packages.student_discount` (percentage discount, default 10%)

### Step 2: Configure Package Discounts (Optional)
Each package can have its own student discount percentage (default is 10%).

**API Endpoint:**
```
PATCH /api/packages/update-student-discount.php
Content-Type: application/json

{
  "package_id": 1,
  "student_discount": 15
}
```

Response:
```json
{
  "success": true,
  "message": "Student discount updated successfully",
  "data": {
    "package_id": 1,
    "package_name": "Basic Package",
    "original_price": 1500,
    "student_discount_percentage": 15,
    "discount_amount": 225,
    "student_price": 1275
  }
}
```

## User Workflow

### For Students:
1. **Select a Package**: Browse and select a gym package
2. **Upload Student ID**: When booking, check the "I'm a Student" checkbox
3. **Upload Proof**: Upload a clear photo of your student ID (front side showing name, institution, and validity)
4. **Automatic Discount**: The discount is automatically applied to your booking amount
5. **Pay Discounted Price**: Pay the reduced amount shown

### For Admins:
1. **Verify Student Bookings**: Admin dashboard shows bookings with student flag
2. **Review Student ID**: Admin can view the uploaded student ID photo
3. **Approve/Reject**: Process the booking as normal; discount is already applied
4. **Manage Discounts**: Customize discount percentages per package in package settings

## API Endpoints

### 1. Create Booking with Student Discount
**POST** `/api/bookings/create.php`
```json
{
  "package": "Basic Package",
  "date": "2026-08-04",
  "contact": "09123456789",
  "is_student": true,
  "student_id_url": "uploads/student-ids/student_13_abc123.jpg",
  "notes": "Optional notes"
}
```

Response will include booking with student discount applied:
```json
{
  "success": true,
  "data": {
    "booking_id": 42,
    "original_amount": 1500,
    "student_discount_applied": 150,
    "final_amount": 1350,
    "is_student": true,
    "student_id_url": "uploads/student-ids/student_13_abc123.jpg"
  }
}
```

### 2. Get Student Discount Info
**GET** `/api/packages/get-student-discount.php?package_id=1`

Response:
```json
{
  "success": true,
  "data": {
    "package_id": 1,
    "package_name": "Basic Package",
    "original_price": 1500,
    "student_discount_percentage": 10,
    "discount_amount": 150,
    "student_price": 1350,
    "has_student_discount": true
  }
}
```

### 3. Update Package Student Discount
**PATCH** `/api/packages/update-student-discount.php`
```json
{
  "package_id": 1,
  "student_discount": 15
}
```

### 4. Verify Student After Booking
**POST** `/api/bookings/verify-student.php`
```json
{
  "booking_id": 42,
  "student_id_url": "uploads/student-ids/student_13_xyz789.jpg"
}
```

## Database Schema

### Bookings Table Additions
```sql
-- New columns added to bookings table
ALTER TABLE bookings ADD COLUMN is_student TINYINT(1) NOT NULL DEFAULT 0 AFTER is_upgrade;
ALTER TABLE bookings ADD COLUMN student_id_url VARCHAR(255) DEFAULT NULL AFTER is_student;
ALTER TABLE bookings ADD COLUMN student_discount_applied DECIMAL(10,2) DEFAULT NULL AFTER student_id_url;
```

### Packages Table Addition
```sql
-- New column added to packages table
ALTER TABLE packages ADD COLUMN student_discount DECIMAL(5,2) DEFAULT 10.00 AFTER is_trainer_assisted;
```

## Features

✓ **Automatic Discount Application**
- Discount is automatically applied when booking with student verification

✓ **Customizable per Package**
- Each package can have different discount percentages

✓ **Student ID Verification**
- Students must upload ID photo as proof
- Admin can review uploaded ID images

✓ **Discount Tracking**
- Discount amount is recorded in booking records
- Full audit trail available in admin panel

✓ **Email Notifications**
- Student receives booking confirmation with discount details
- Admin receives notification of student booking

## Security Considerations

1. **Student ID Verification**
   - Always verify student IDs are current and valid
   - Check institution matches legitimate educational organization
   - Reject unclear or suspicious IDs

2. **File Upload Security**
   - Student ID images are stored in `/uploads/student-ids/`
   - Implement file type validation (JPG, PNG only)
   - Set maximum file size limits

3. **Access Control**
   - Only admins can view uploaded student IDs
   - Student data is protected under privacy policies
   - File access is logged

## Best Practices

1. **Set Reasonable Discounts**
   - Typical range: 10-20% off
   - Balance business needs with student affordability

2. **Regular Audits**
   - Periodically review student bookings
   - Verify a sample of student IDs
   - Check for discount abuse

3. **Communication**
   - Clearly communicate discount eligibility
   - Inform students of ID requirements
   - Provide expected approval timeline

4. **Retention**
   - Keep student ID copies for 6-12 months
   - Maintain booking records with discount info
   - Document any rejected verifications

## Troubleshooting

### Discount Not Applied
- Verify column exists: `SHOW COLUMNS FROM packages LIKE 'student_discount'`
- Check package has discount > 0: `SELECT * FROM packages WHERE id = X`
- Verify `is_student = 1` in booking

### Student ID Upload Fails
- Check file size limits in `api/upload/student-id.php`
- Verify `/uploads/student-ids/` directory exists and is writable
- Check file type is JPG or PNG

### Migration Errors
- Ensure database has proper permissions
- Check columns don't already exist
- Verify MySQL version compatibility

## Support
For issues or questions about the student discount feature, contact the admin panel or check the system logs at `/VSCODE_TARGET_SESSION_LOG/` for detailed error messages.
