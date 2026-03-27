-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 27, 2026 at 01:54 PM
-- Server version: 8.3.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fitpay_gym`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `package_id` int DEFAULT NULL,
  `trainer_id` int DEFAULT NULL,
  `is_upgrade` tinyint(1) DEFAULT '0',
  `package_name` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `booking_date` date DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verified_at` timestamp NULL DEFAULT NULL,
  `receipt_url` varchar(255) DEFAULT NULL,
  `invoice_url` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_package_id` (`package_id`),
  KEY `fk_booking_trainer` (`trainer_id`)
) ENGINE=MyISAM AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `name`, `email`, `contact`, `package_id`, `trainer_id`, `is_upgrade`, `package_name`, `amount`, `booking_date`, `expires_at`, `status`, `verified_at`, `receipt_url`, `invoice_url`, `notes`, `created_at`, `updated_at`) VALUES
(70, 13, 'user', 'user@martinezfitness.com', '09989134598', 22, 2, 0, 'Annual Membership', 800.00, '2026-03-24', '2026-04-23 10:00:03', 'verified', '2026-03-24 02:00:03', 'uploads/receipts/receipt_13_1774346305_69c26041d65f3.jpg', NULL, '', '2026-03-24 09:58:25', '2026-03-24 10:00:03'),
(69, 20, 'pat', 'ventiletos12@gmail.com', '09989134598', 22, 2, 0, 'Annual Membership', 800.00, '2026-03-23', '2026-04-22 06:45:36', 'verified', '2026-03-22 22:45:36', 'uploads/receipts/receipt_20_1774248299_69c0e16b493c9.jpg', NULL, '', '2026-03-23 06:44:59', '2026-03-23 06:45:36');

-- --------------------------------------------------------

--
-- Table structure for table `email_configs`
--

DROP TABLE IF EXISTS `email_configs`;
CREATE TABLE IF NOT EXISTS `email_configs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `smtp_host` varchar(100) NOT NULL DEFAULT 'smtp.gmail.com',
  `smtp_port` int NOT NULL DEFAULT '587',
  `smtp_username` varchar(100) NOT NULL,
  `smtp_password` varchar(100) NOT NULL,
  `from_email` varchar(100) NOT NULL,
  `from_name` varchar(100) NOT NULL DEFAULT 'Martinez Fitness',
  `is_active` tinyint(1) DEFAULT '1',
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_default` (`is_default`),
  KEY `idx_active` (`is_active`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `email_configs`
--

INSERT INTO `email_configs` (`id`, `name`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `from_email`, `from_name`, `is_active`, `is_default`, `created_at`, `updated_at`) VALUES
(2, 'Primary Gmail', 'smtp.gmail.com', 587, 'ventiletos@gmail.com', 'njqjkaxuysdwyrsy', 'ventiletos@gmail.com', 'Martinez Fitness', 1, 1, '2026-01-23 04:51:58', '2026-03-22 08:13:58');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

DROP TABLE IF EXISTS `equipment`;
CREATE TABLE IF NOT EXISTS `equipment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `category` enum('Strength','Cardio','Free Weights','Functional','Other') DEFAULT 'Strength',
  `status` enum('active','maintenance','out_of_order') DEFAULT 'active',
  `description` text,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_unique` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=254 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `name`, `category`, `status`, `description`, `image_url`, `created_at`) VALUES
(1, 'Dumbbells', 'Free Weights', 'active', 'Various weights for versatile strength training', NULL, '2026-03-01 09:19:56'),
(2, 'Barbells', 'Free Weights', 'active', 'Olympic bars for heavy lifting', NULL, '2026-03-01 09:19:56'),
(3, 'Bench Press', 'Strength', 'active', 'Standard bench for chest workouts', NULL, '2026-03-01 09:19:56'),
(4, 'Squat Rack', 'Strength', 'active', 'Essential for squats and compound movements', NULL, '2026-03-01 09:19:56'),
(5, 'Treadmill', 'Cardio', 'active', 'Modern treadmill for cardiovascular endurance', NULL, '2026-03-01 09:19:56'),
(6, 'Stationary Bike', 'Cardio', 'active', 'For low-impact cardio and leg conditioning', NULL, '2026-03-01 09:19:56'),
(7, 'Leg Press Machine', 'Strength', 'active', 'Targets quads, hamstrings, and glutes', NULL, '2026-03-01 09:19:56'),
(8, 'Lat Pulldown Machine', 'Strength', 'active', 'Builds back and arm strength', NULL, '2026-03-01 09:19:56'),
(9, 'Kettlebells', 'Functional', 'active', 'For explosive movements and core stability', NULL, '2026-03-01 09:19:56'),
(10, 'Cable Crossover', 'Strength', 'active', 'Functional cable trainer for isolation exercises', NULL, '2026-03-01 09:19:56'),
(11, 'Dumbbells (Standard Iron)', 'Free Weights', 'active', 'Various iron dumbbells for versatile strength training', NULL, '2026-03-01 09:24:51'),
(12, 'Olympic Barbells', 'Free Weights', 'active', '2\" Olympic bars for heavy compound lifting', NULL, '2026-03-01 09:24:51'),
(13, 'Flat Bench Press', 'Strength', 'active', 'Standard flat bench for chest pressing', NULL, '2026-03-01 09:24:51'),
(14, 'Incline Bench Press', 'Strength', 'active', 'Adjustable bench for upper chest development', NULL, '2026-03-01 09:24:51'),
(15, 'Smith Machine', 'Strength', 'active', 'Guided barbell for safe squats and presses', NULL, '2026-03-01 09:24:51'),
(16, 'Leg Press (Plate-loaded)', 'Strength', 'active', '45-degree leg press for quad and glute training', NULL, '2026-03-01 09:24:51'),
(17, 'Lat Pulldown / Seated Row', 'Strength', 'active', 'Multi-purpose cable machine for back strength', NULL, '2026-03-01 09:24:51'),
(19, 'Leg Extension / Curl', 'Strength', 'active', 'Machine for isolating quads and hamstrings', NULL, '2026-03-01 09:24:51'),
(20, 'Pec Deck (Chest Fly)', 'Strength', 'active', 'Isolates the pectoral muscles', NULL, '2026-03-01 09:24:51'),
(21, 'Preacher Curl Bench', 'Strength', 'active', 'Isolates the biceps with an EZ bar', NULL, '2026-03-01 09:24:51'),
(22, 'Pull-up / Dip Tower', 'Functional', 'active', 'Bodyweight station for upper body strength', NULL, '2026-03-01 09:24:51'),
(25, 'Kettlebells (Iron)', 'Functional', 'active', 'Traditional iron kettlebells for functional movements', NULL, '2026-03-01 09:24:51'),
(236, 'Rowing Machine', 'Cardio', 'active', 'Full-body cardiovascular conditioning', NULL, '2026-03-01 10:47:35'),
(237, 'Hack Squat Machine', 'Strength', 'active', 'Fixed-angle squat for leg development', NULL, '2026-03-01 10:47:35'),
(238, 'Hyperextension Bench', 'Strength', 'active', 'For lower back and glute isolation', NULL, '2026-03-01 10:47:35'),
(239, 'EZ Bar', 'Free Weights', 'active', 'Curved bar for bicep and tricep isolation', NULL, '2026-03-01 10:47:35'),
(240, 'Adjustable Bench', 'Free Weights', 'active', 'Multi-angle bench for various exercises', NULL, '2026-03-01 10:47:35'),
(241, 'Medicine Balls', 'Functional', 'active', 'Weighted balls for power and core training', NULL, '2026-03-01 10:47:35'),
(242, 'Battle Ropes', 'Functional', 'active', 'High-intensity metabolic conditioning', NULL, '2026-03-01 10:47:35'),
(243, 'Yoga Mats', 'Other', 'active', 'For floor exercises and stretching', NULL, '2026-03-01 10:47:35'),
(244, 'Foam Rollers', 'Other', 'active', 'For recovery and myofascial release', NULL, '2026-03-01 10:47:35'),
(245, 'TRX Suspension Trainer', 'Functional', 'active', 'Bodyweight resistance training', NULL, '2026-03-01 10:47:35'),
(246, 'Elliptical Trainer', 'Cardio', 'active', 'Low-impact total body cardio', NULL, '2026-03-01 10:47:35'),
(247, 'Squat Rack (Power Cage)', 'Free Weights', 'active', 'For heavy squats and barbell work', NULL, '2026-03-01 10:47:35'),
(248, 'Decline Bench Press', 'Strength', 'active', 'Targets lower pectoral development', NULL, '2026-03-01 10:47:35'),
(249, 'Barbell', 'Free Weights', 'active', 'Olympic barbell for compound lifts', NULL, '2026-03-16 14:32:14'),
(250, 'Kettlebell', 'Free Weights', 'active', 'Cast iron kettlebells', NULL, '2026-03-16 14:32:14'),
(251, 'Resistance Bands', '', 'active', 'Various tension levels for mobility', NULL, '2026-03-16 14:32:14'),
(252, 'Yoga Mat', '', 'active', 'Standard cushioning mat', NULL, '2026-03-16 14:32:14'),
(253, 'Pull-up Bar', '', 'active', 'Wall-mounted pull-up bar', NULL, '2026-03-16 14:32:14');

-- --------------------------------------------------------

--
-- Table structure for table `exercises`
--

DROP TABLE IF EXISTS `exercises`;
CREATE TABLE IF NOT EXISTS `exercises` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `category` enum('Chest','Back','Legs','Shoulders','Arms','Core','Cardio','Full Body') DEFAULT 'Full Body',
  `equipment_id` int DEFAULT NULL,
  `description` text,
  `instructions` text,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_exercise_name` (`name`),
  KEY `equipment_id` (`equipment_id`)
) ENGINE=MyISAM AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `exercises`
--

INSERT INTO `exercises` (`id`, `name`, `category`, `equipment_id`, `description`, `instructions`, `image_url`, `created_at`) VALUES
(107, 'Barbell Squat', 'Chest', NULL, 'sda', 'sad', '../../assets/uploads/exercises/ex_69b813688e514.jpg', '2026-03-16 14:27:52'),
(108, 'Seated Cable Row', 'Back', 246, '', '', '../../assets/uploads/exercises/ex_69b89102a752b.jpg', '2026-03-16 23:23:36');

-- --------------------------------------------------------

--
-- Table structure for table `food_recommendations`
--

DROP TABLE IF EXISTS `food_recommendations`;
CREATE TABLE IF NOT EXISTS `food_recommendations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `trainer_id` int NOT NULL,
  `member_id` int NOT NULL,
  `meal_type` enum('breakfast','lunch','dinner','snack','pre-workout','post-workout') NOT NULL,
  `food_items` text NOT NULL,
  `calories` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `trainer_id` (`trainer_id`),
  KEY `member_id` (`member_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `food_recommendations`
--

INSERT INTO `food_recommendations` (`id`, `trainer_id`, `member_id`, `meal_type`, `food_items`, `calories`, `notes`, `created_at`) VALUES
(1, 2, 13, 'breakfast', 'rice', 0, NULL, '2026-03-16 23:26:19');

-- --------------------------------------------------------

--
-- Table structure for table `gym_settings`
--

DROP TABLE IF EXISTS `gym_settings`;
CREATE TABLE IF NOT EXISTS `gym_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=MyISAM AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `gym_settings`
--

INSERT INTO `gym_settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'gym_name', 'Martinez Fitness Gym', '2026-02-05 13:43:33', '2026-02-05 13:43:33'),
(2, 'gym_address', 'Apitong, Naujan, Oriental Mindoro', '2026-02-05 13:43:33', '2026-02-27 09:06:02'),
(3, 'gym_contact', '0917-123-4567', '2026-02-05 13:43:33', '2026-02-05 13:43:33'),
(4, 'gym_email', 'ventiletos12@gmail.com', '2026-02-05 13:43:33', '2026-03-01 14:15:10'),
(5, 'gcash_number', '0956-081-82580', '2026-02-05 13:43:33', '2026-02-09 15:39:27'),
(6, 'gcash_name', 'JE*BE*N M.', '2026-02-05 13:43:33', '2026-02-05 14:08:16'),
(7, 'gcash_qr_path', 'uploads/settings/gcash_qr_1770301417.jpg', '2026-02-05 13:43:33', '2026-02-05 14:23:37'),
(8, 'payment_instructions', 'Please send payment via GCash to the number above. Include your name and booking reference in the payment notes.', '2026-02-05 13:43:33', '2026-02-05 13:43:33'),
(9, 'about_title', 'About Martinez Fitness', '2026-02-09 15:00:24', '2026-02-09 15:00:24'),
(10, 'about_description', 'Martinez Fitness is a premier gym dedicated to helping you achieve your fitness goals. Our state-of-the-art facility and expert trainers are here to support you every step of the way.', '2026-02-09 15:00:24', '2026-02-09 15:00:24'),
(11, 'about_image', 'assets/img/about.jpg', '2026-02-09 15:00:24', '2026-02-09 15:00:24'),
(12, 'about_text', 'Martinez Fitness Gym is more than just a place to work out. We are a community dedicated to helping you reach your peak physical condition through elite training, state-of-the-art equipment, and a supportive environment.', '2026-02-10 05:54:55', '2026-02-10 05:54:55'),
(13, 'mission_text', 'Founded with the mission to provide high-quality fitness access to everyone, we offer flexible membership plans and expert guidance to ensure you get the most out of every session.', '2026-02-10 05:54:55', '2026-02-10 05:54:55'),
(14, 'years_experience', '3+', '2026-02-10 05:54:55', '2026-02-27 09:05:21'),
(15, 'footer_tagline', 'Pushing your limits since 2025. Join the elite fitness community today.', '2026-02-10 05:54:55', '2026-02-10 05:58:20'),
(16, 'about_images', '[]', '2026-02-10 05:55:15', '2026-03-01 12:09:12'),
(17, 'admin_name', 'Admin Martinez', '2026-02-10 10:44:16', '2026-02-10 10:44:16'),
(18, 'admin_email', 'admin@martinezfitness.com', '2026-02-10 10:44:16', '2026-02-10 10:44:16'),
(19, 'email_new_booking', 'true', '2026-02-10 10:44:16', '2026-02-10 10:44:16'),
(20, 'email_payment_verified', 'true', '2026-02-10 10:44:16', '2026-02-10 10:44:16'),
(21, 'email_daily_report', 'false', '2026-02-10 10:44:16', '2026-02-10 10:44:16'),
(22, 'browser_new_booking', 'true', '2026-02-10 10:44:16', '2026-02-10 10:44:16'),
(23, 'browser_payment_verified', 'true', '2026-02-10 10:44:16', '2026-02-10 10:44:16'),
(24, 'notification_sound', 'true', '2026-02-10 10:44:16', '2026-02-10 10:44:16'),
(25, 'opening_time', '06:00', '2026-02-10 10:45:06', '2026-02-10 10:45:06'),
(26, 'closing_time', '22:00', '2026-02-10 10:45:06', '2026-02-10 10:45:06'),
(27, 'timezone', 'Asia/Manila', '2026-02-10 10:45:06', '2026-02-10 10:45:06'),
(28, 'last_expiry_check', '2026-03-27', '2026-02-10 13:13:29', '2026-03-27 00:07:03'),
(29, 'home_background_images', '[]', '2026-03-16 06:07:11', '2026-03-16 06:07:11'),
(30, 'hero_bg_image', 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?q=80&w=2070&auto=format&fit=crop', '2026-03-17 00:49:43', '2026-03-17 00:49:43'),
(31, 'hero_images', '[\"uploads\\/settings\\/hero_bg_69b8e0afd38159.07214953.webp\",\"uploads\\/settings\\/hero_bg_69b8e2a785f060.83529062.webp\",\"uploads\\/settings\\/hero_bg_69b8e2a7863473.72386538.webp\",\"uploads\\/settings\\/hero_bg_69b8e2a7865e55.96416334.webp\",\"uploads\\/settings\\/hero_bg_69b8e2a7868127.79057633.webp\",\"uploads\\/settings\\/hero_bg_69b8e2a786a567.04521492.webp\",\"uploads\\/settings\\/hero_bg_69b8e2a786cb14.19228465.webp\"]', '2026-03-17 04:35:55', '2026-03-17 05:12:07');

-- --------------------------------------------------------

--
-- Table structure for table `member_exercise_plans`
--

DROP TABLE IF EXISTS `member_exercise_plans`;
CREATE TABLE IF NOT EXISTS `member_exercise_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `exercise_id` int NOT NULL,
  `sets` int DEFAULT '3',
  `reps` varchar(50) DEFAULT '10-12',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `exercise_id` (`exercise_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `member_exercise_plans`
--

INSERT INTO `member_exercise_plans` (`id`, `booking_id`, `exercise_id`, `sets`, `reps`, `notes`, `created_at`) VALUES
(1, 49, 107, 3, '10 munites', '', '2026-03-16 16:10:10'),
(2, 54, 107, 3, '10 munites', '', '2026-03-17 16:18:31');

-- --------------------------------------------------------

--
-- Table structure for table `member_progress`
--

DROP TABLE IF EXISTS `member_progress`;
CREATE TABLE IF NOT EXISTS `member_progress` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `trainer_id` int NOT NULL,
  `weight` decimal(5,1) DEFAULT NULL,
  `height` decimal(5,1) DEFAULT NULL,
  `remarks` text,
  `photo_url` varchar(500) DEFAULT NULL,
  `logged_by` varchar(20) DEFAULT 'trainer',
  `logged_at` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `trainer_id` (`trainer_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `member_progress`
--

INSERT INTO `member_progress` (`id`, `booking_id`, `trainer_id`, `weight`, `height`, `remarks`, `photo_url`, `logged_by`, `logged_at`, `created_at`) VALUES
(1, 54, 2, 4.0, NULL, 'nice', NULL, 'trainer', '2026-03-17', '2026-03-17 16:27:58'),
(2, 70, 2, NULL, NULL, '', 'assets/uploads/progress/progress_1774348565_69c269152cb0e.jpg', 'user', '2026-03-24', '2026-03-24 10:36:05');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`)
) ENGINE=MyISAM AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 16, 'New Package Assignment', 'You have been assigned to handle the package: Annual Membership.', 'assignment', 1, '2026-03-16 23:22:01'),
(2, 13, 'New Fitness Tip', 'Coach Jane Smith shared a new tip: eat rice daily...', 'tip', 0, '2026-03-16 23:26:09'),
(3, 13, 'New Meal Plan Recommendation', 'Coach Jane Smith updated your breakfast recommendation.', 'food', 0, '2026-03-16 23:26:19'),
(4, 9, 'New Fitness Tip', 'Coach Jane Smith shared a new tip: eat rice...', 'tip', 0, '2026-03-17 16:28:11'),
(5, 9, 'New Workout Session Scheduled', 'Coach Jane Smith scheduled a session for you on 2026-03-18 at 08:00:00.', 'session', 0, '2026-03-17 16:40:06'),
(6, 9, 'New Workout Session Scheduled', 'Coach Jane Smith scheduled a session for you on 2026-03-19 at 08:00:00.', 'session', 0, '2026-03-17 16:40:42'),
(7, 9, 'Rest Day Scheduled', 'Coach Jane Smith scheduled a rest day for you on 2026-03-20.', 'session', 0, '2026-03-17 16:40:52'),
(8, 9, 'New Workout Session Scheduled', 'Coach Jane Smith scheduled a session for you on 2026-03-21 at 08:00:00.', 'session', 0, '2026-03-18 06:32:15'),
(9, 9, 'New Workout Session Scheduled', 'Coach Jane Smith scheduled a session for you on 2026-03-19 at 08:00:00.', 'session', 0, '2026-03-19 05:51:36'),
(10, 13, 'New Workout Session Scheduled', 'Coach Jane Smith scheduled a session for you on 2026-03-20 at 08:00:00.', 'session', 0, '2026-03-19 05:53:48'),
(11, 15, 'New Package Assignment', 'You have been assigned to handle the package: Monthly Membership.', 'assignment', 0, '2026-03-20 12:22:28'),
(12, 17, 'Package Assignment Updated', 'You are assigned to handle the package: Monthly Membership.', 'assignment', 0, '2026-03-22 07:27:58'),
(13, 17, 'Package Assignment Updated', 'You are assigned to handle the package: Monthly Membership.', 'assignment', 0, '2026-03-22 07:28:00'),
(14, 17, 'Package Assignment Updated', 'You are assigned to handle the package: Monthly Membership.', 'assignment', 0, '2026-03-22 07:28:01'),
(15, 17, 'Package Assignment Updated', 'You are assigned to handle the package: Monthly Membership.', 'assignment', 0, '2026-03-22 07:28:01'),
(16, 17, 'Package Assignment Updated', 'You are assigned to handle the package: Monthly Membership.', 'assignment', 0, '2026-03-22 07:28:01'),
(17, 17, 'Package Assignment Updated', 'You are assigned to handle the package: Monthly Membership.', 'assignment', 0, '2026-03-22 07:28:01'),
(18, 17, 'Package Assignment Updated', 'You are assigned to handle the package: Monthly Membership.', 'assignment', 0, '2026-03-22 07:30:57'),
(19, 17, 'Package Assignment Updated', 'You are assigned to handle the package: Monthly Membership.', 'assignment', 0, '2026-03-22 07:36:47'),
(20, 17, 'Package Assignment Updated', 'You are assigned to handle the package: Monthly Membership.', 'assignment', 0, '2026-03-22 07:38:53'),
(21, 17, 'Package Assignment Updated', 'You are assigned to handle the package: Monthly Membership.', 'assignment', 0, '2026-03-22 07:44:58'),
(22, 17, 'Package Assignment Updated', 'You are assigned to handle the package: Monthly Membership.', 'assignment', 0, '2026-03-22 07:46:22'),
(23, 21, 'Package Assignment Updated', 'You are assigned to handle the package: Monthly Membership.', 'assignment', 0, '2026-03-22 08:20:54'),
(24, 21, 'New Booking Pending', 'pat submitted a booking for your package: Monthly Membership', 'info', 0, '2026-03-22 08:23:50'),
(25, 16, 'New Booking Pending', 'user submitted a booking for your package: Annual Membership', 'info', 0, '2026-03-23 06:19:28'),
(26, 16, 'New Client Assigned', 'user has been verified on your package: Annual Membership', 'info', 0, '2026-03-23 06:20:14'),
(27, 16, 'New Booking Pending', 'pat submitted a booking for your package: Annual Membership', 'info', 0, '2026-03-23 06:24:23'),
(28, 16, 'New Client Assigned', 'pat has been verified on your package: Annual Membership', 'info', 0, '2026-03-23 06:25:33'),
(29, 16, 'New Booking Pending', 'user submitted a booking for your package: Annual Membership', 'info', 0, '2026-03-23 06:29:45'),
(30, 16, 'New Client Assigned', 'user has been verified on your package: Annual Membership', 'info', 0, '2026-03-23 06:30:38'),
(31, 16, 'New Client Assigned', 'user has been verified on your package: Annual Membership', 'info', 0, '2026-03-23 06:31:03'),
(32, 16, 'New Booking Pending', 'pat submitted a booking for your package: Annual Membership', 'info', 0, '2026-03-23 06:34:45'),
(33, 16, 'New Client Assigned', 'pat has been verified on your package: Annual Membership', 'info', 0, '2026-03-23 06:36:37'),
(34, 20, 'Booking Verified', 'Your booking has been verified! Your membership is now active.', 'success', 0, '2026-03-23 06:36:41'),
(35, 16, 'New Booking Pending', 'pat submitted a booking for your package: Annual Membership', 'info', 0, '2026-03-23 06:40:34'),
(36, 16, 'New Client Assigned', 'pat has been verified on your package: Annual Membership', 'info', 0, '2026-03-23 06:41:30'),
(37, 20, 'Booking Verified', 'Your booking has been verified! Your membership is now active.', 'success', 0, '2026-03-23 06:41:34'),
(38, 16, 'New Booking Pending', 'pat submitted a booking for your package: Annual Membership', 'info', 0, '2026-03-23 06:45:07'),
(39, 16, 'New Client Assigned', 'pat has been verified on your package: Annual Membership', 'info', 0, '2026-03-23 06:45:40'),
(40, 20, 'Booking Verified', 'Your booking has been verified! Your membership is now active.', 'success', 0, '2026-03-23 06:45:45'),
(41, 20, 'New Workout Session Scheduled', 'Coach Geraldine scheduled a session for you on 2026-03-23 at 08:00:00.', 'session', 0, '2026-03-23 06:47:39'),
(42, 16, 'New Booking Pending', 'user submitted a booking for your package: Annual Membership', 'info', 0, '2026-03-24 09:58:36'),
(43, 16, 'New Client Assigned', 'user has been verified on your package: Annual Membership', 'info', 0, '2026-03-24 10:00:08'),
(44, 13, 'Booking Verified', 'Your booking has been verified! Your membership is now active.', 'success', 0, '2026-03-24 10:00:15'),
(45, 16, 'Package Assignment Updated', 'You are assigned to handle the package: Annual Membership.', 'assignment', 0, '2026-03-27 00:07:37'),
(46, 16, 'Package Assignment Updated', 'You are assigned to handle the package: Annual Membership.', 'assignment', 0, '2026-03-27 00:07:51'),
(47, 16, 'Package Assignment Updated', 'You are assigned to handle the package: Annual Membership.', 'assignment', 0, '2026-03-27 00:07:57'),
(48, 16, 'Package Assignment Updated', 'You are assigned to handle the package: Annual Membership.', 'assignment', 0, '2026-03-27 00:15:02'),
(49, 16, 'Package Assignment Updated', 'You are assigned to handle the package: Annual Membership.', 'assignment', 1, '2026-03-27 00:15:20');

-- --------------------------------------------------------

--
-- Table structure for table `otps`
--

DROP TABLE IF EXISTS `otps`;
CREATE TABLE IF NOT EXISTS `otps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_code` (`code`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `otps`
--

INSERT INTO `otps` (`id`, `email`, `code`, `expires_at`, `used`, `created_at`) VALUES
(9, 'patrickmontero833@gmail.com', '983350', '2026-02-05 22:33:16', 1, '2026-02-05 14:28:16'),
(26, 'ventiletos12@gmail.com', '944232', '2026-03-22 16:21:21', 1, '2026-03-22 08:16:21'),
(3, 'monalizawaing41@gmail.com', '765414', '2026-01-19 08:25:12', 1, '2026-01-19 00:20:12'),
(5, 'raphaelbugayong848@gmail.com', '711863', '2026-01-19 14:47:03', 0, '2026-01-19 06:42:03'),
(25, 'patrickromasanta296@gmail.com', '480694', '2026-03-22 15:55:04', 0, '2026-03-22 07:50:04'),
(7, 'admin@gmail.com', '224746', '2026-02-03 16:04:47', 1, '2026-02-03 07:59:47'),
(8, 'user@gmail.com', '846488', '2026-02-03 16:08:15', 1, '2026-02-03 08:03:15'),
(10, 'try@gmail.com', '799000', '2026-02-05 22:36:17', 1, '2026-02-05 14:31:17'),
(11, 'geraldinegaran70@gmail.com', '241919', '2026-02-06 09:18:52', 1, '2026-02-06 01:13:52'),
(12, 'admin@martinezfitness.com', '583008', '2026-02-26 23:19:01', 1, '2026-02-26 15:14:01'),
(13, 'user@martinezfitness.com', '619481', '2026-02-26 23:36:13', 1, '2026-02-26 15:31:13'),
(16, 'patrickmontero8133@gmail.com', '838607', '2026-03-09 18:34:07', 0, '2026-03-09 10:29:07');

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

DROP TABLE IF EXISTS `packages`;
CREATE TABLE IF NOT EXISTS `packages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `duration` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `tag` varchar(50) DEFAULT NULL,
  `description` text,
  `is_trainer_assisted` tinyint(1) DEFAULT '0',
  `goal` varchar(50) DEFAULT 'General Fitness',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `diet_info` text,
  `guidance_info` text,
  `common_questions` text,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_tag` (`tag`)
) ENGINE=MyISAM AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `name`, `duration`, `price`, `tag`, `description`, `is_trainer_assisted`, `goal`, `is_active`, `created_at`, `updated_at`, `diet_info`, `guidance_info`, `common_questions`) VALUES
(20, 'WHO Health & Fitness Plan', 'Weekly (WHO Standard)', 450.00, 'Health Standard', 'Scientifically designed plan based on WHO (World Health Organization) physical activity guidelines for adults. Focuses on 150-300 minutes of moderate aerobic activity and 2+ days of strength training per week.', 0, 'General Fitness', 0, '2026-03-16 12:14:55', '2026-03-16 13:48:49', NULL, NULL, NULL),
(21, 'WEEKLY PASS', '7 Days', 150.00, 'Basic', '', 1, 'General Fitness', 1, '2026-03-16 12:29:43', '2026-03-16 12:29:43', NULL, NULL, NULL),
(22, 'Annual Membership', '30 Day', 800.00, 'Best Value', '', 1, 'General Fitness', 1, '2026-03-16 23:22:01', '2026-03-27 00:15:20', 'test', 'test', NULL),
(23, 'Monthly Membership', '30 Day', 1500.00, 'Premium', '', 1, 'Weight Loss', 1, '2026-03-20 12:22:28', '2026-03-22 08:20:54', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `package_exercises`
--

DROP TABLE IF EXISTS `package_exercises`;
CREATE TABLE IF NOT EXISTS `package_exercises` (
  `id` int NOT NULL AUTO_INCREMENT,
  `package_id` int NOT NULL,
  `exercise_id` int NOT NULL,
  `sets` int DEFAULT '3',
  `reps` varchar(50) DEFAULT '10-12',
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pkg_exercise` (`package_id`,`exercise_id`),
  KEY `exercise_id` (`exercise_id`)
) ENGINE=MyISAM AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `package_exercises`
--

INSERT INTO `package_exercises` (`id`, `package_id`, `exercise_id`, `sets`, `reps`, `notes`) VALUES
(66, 22, 107, 3, '10 munites', ''),
(65, 22, 108, 3, '10 munites', ''),
(64, 21, 107, 3, '10 munites', ''),
(26, 17, 19, 3, '10', NULL),
(27, 17, 22, 4, '20', NULL),
(28, 16, 25, 1, '20 mins', NULL),
(29, 16, 13, 4, '8', NULL),
(30, 16, 14, 3, '12', NULL),
(31, 16, 11, 4, '8', NULL),
(32, 16, 12, 3, '10', NULL),
(33, 16, 15, 4, '10', NULL),
(34, 16, 16, 3, '12', NULL),
(35, 16, 23, 3, 'Failure', NULL),
(36, 16, 19, 3, '10', NULL),
(37, 16, 21, 3, '15', NULL),
(38, 16, 17, 4, '10', NULL),
(39, 16, 20, 4, '12', NULL),
(40, 16, 26, 3, '20', NULL),
(41, 16, 22, 4, '20', NULL),
(42, 18, 24, 1, '30 mins', NULL),
(43, 18, 13, 5, '5', NULL),
(44, 18, 14, 4, '10', NULL),
(45, 18, 11, 5, '5', NULL),
(46, 18, 12, 4, '8', NULL),
(47, 18, 15, 5, '8', NULL),
(48, 18, 16, 4, '10', NULL),
(49, 18, 23, 4, 'Failure', NULL),
(50, 18, 19, 4, '8', NULL),
(51, 18, 21, 4, '12', NULL),
(52, 18, 17, 4, '8', NULL),
(53, 18, 20, 4, '10', NULL),
(54, 18, 26, 4, '15', NULL),
(55, 18, 22, 5, '20', NULL),
(56, 18, 25, 1, '15 mins', NULL),
(57, 19, 24, 1, '30 mins (Aerobic)', 'WHO Standard'),
(58, 19, 25, 1, '20 mins (Aerobic)', 'WHO Standard'),
(59, 19, 13, 3, '12-15 (Strength)', 'WHO Standard'),
(60, 19, 11, 3, '12-15 (Strength)', 'WHO Standard'),
(61, 19, 15, 3, '12-15 (Strength)', 'WHO Standard'),
(62, 19, 22, 3, '15-20 (Core)', 'WHO Standard'),
(63, 19, 26, 3, '20 (Full Body)', 'WHO Standard');

-- --------------------------------------------------------

--
-- Table structure for table `package_trainers`
--

DROP TABLE IF EXISTS `package_trainers`;
CREATE TABLE IF NOT EXISTS `package_trainers` (
  `package_id` int NOT NULL,
  `trainer_id` int NOT NULL,
  PRIMARY KEY (`package_id`,`trainer_id`),
  KEY `trainer_id` (`trainer_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `package_trainers`
--

INSERT INTO `package_trainers` (`package_id`, `trainer_id`) VALUES
(21, 2),
(22, 2),
(23, 5);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `booking_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `receipt_url` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_booking` (`booking_id`),
  UNIQUE KEY `unique_transaction` (`transaction_id`),
  KEY `idx_status` (`status`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_booking_id` (`booking_id`)
) ENGINE=MyISAM AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `booking_id`, `amount`, `status`, `payment_method`, `transaction_id`, `receipt_url`, `notes`, `created_at`, `updated_at`) VALUES
(52, 13, 70, 800.00, 'completed', 'Booking Payment', 'BK_70', 'uploads/receipts/receipt_13_1774346305_69c26041d65f3.jpg', NULL, '2026-03-24 10:00:03', '2026-03-24 10:00:03'),
(51, 20, 69, 800.00, 'completed', 'Booking Payment', 'BK_69', 'uploads/receipts/receipt_20_1774248299_69c0e16b493c9.jpg', NULL, '2026-03-23 06:45:36', '2026-03-23 06:45:36');

-- --------------------------------------------------------

--
-- Table structure for table `trainers`
--

DROP TABLE IF EXISTS `trainers`;
CREATE TABLE IF NOT EXISTS `trainers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `specialization` varchar(100) NOT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `bio` text,
  `photo_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `availability` varchar(500) DEFAULT NULL,
  `certifications` text,
  `max_clients` int DEFAULT '10',
  `experience` varchar(50) DEFAULT '1-2 years',
  `image_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `fk_trainer_user` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `trainers`
--

INSERT INTO `trainers` (`id`, `user_id`, `name`, `specialization`, `contact`, `email`, `bio`, `photo_url`, `is_active`, `created_at`, `updated_at`, `availability`, `certifications`, `max_clients`, `experience`, `image_url`) VALUES
(2, 16, 'Geraldine', 'Yoga & Flexibility', '09987654321', 'geraldinegaran70@gmail.com', 'Yoga instructor specializing in Vinyasa and Hatha.', '', 1, '2026-03-16 11:03:03', '2026-03-23 06:22:06', '{\"days\":[\"Mon\",\"Tue\",\"Wed\",\"Thu\",\"Fri\",\"Sat\",\"Sun\"],\"from\":\"06:00\",\"until\":\"18:00\"}', '', 10, '1-2 years', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `trainer_sessions`
--

DROP TABLE IF EXISTS `trainer_sessions`;
CREATE TABLE IF NOT EXISTS `trainer_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `trainer_id` int NOT NULL,
  `member_id` int NOT NULL,
  `booking_id` int NOT NULL,
  `session_date` date NOT NULL,
  `session_time` time NOT NULL,
  `duration` int DEFAULT '60',
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `type` enum('workout','assessment','consultation','rest_day') DEFAULT 'workout',
  `title` varchar(100) DEFAULT 'Workout Session',
  `notes` text,
  `exercises` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `trainer_id` (`trainer_id`),
  KEY `member_id` (`member_id`),
  KEY `booking_id` (`booking_id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `trainer_sessions`
--

INSERT INTO `trainer_sessions` (`id`, `trainer_id`, `member_id`, `booking_id`, `session_date`, `session_time`, `duration`, `status`, `type`, `title`, `notes`, `exercises`, `created_at`) VALUES
(7, 2, 20, 69, '2026-03-23', '08:00:00', 60, 'scheduled', 'workout', 'Workout A', '', '108', '2026-03-23 06:47:39');

-- --------------------------------------------------------

--
-- Table structure for table `trainer_tips`
--

DROP TABLE IF EXISTS `trainer_tips`;
CREATE TABLE IF NOT EXISTS `trainer_tips` (
  `id` int NOT NULL AUTO_INCREMENT,
  `trainer_id` int NOT NULL,
  `member_id` int NOT NULL,
  `tip_text` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `trainer_id` (`trainer_id`),
  KEY `member_id` (`member_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `role` enum('admin','user','trainer') DEFAULT 'user',
  `contact` varchar(50) DEFAULT NULL,
  `address` text,
  `email_verified` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `contact`, `address`, `email_verified`, `created_at`, `updated_at`) VALUES
(12, 'admin', 'admin@martinezfitness.com', '$2y$10$H.22KkotBIDq3ifRNHQMRuOY4wHYNpuuErIkO.p0/BYccc41LHEga', 'admin', '', '', 1, '2026-02-26 15:14:27', '2026-02-26 15:15:11'),
(13, 'user', 'user@martinezfitness.com', '$2y$10$rgUEFkUmKgXiX4nsI9sS8eq9PNG7obwzld8GCYDxcqK9ZNZXnfMIa', 'user', '', '', 1, '2026-02-26 15:31:56', '2026-02-26 15:31:56'),
(20, 'pat', 'ventiletos12@gmail.com', '$2y$10$dhxLF0aXwOf1GyPDfJeZyOS7KPSEuzmt1i1sn8nXAyjiw7m84zp.a', 'user', '', '', 1, '2026-03-22 08:17:11', '2026-03-22 08:17:11'),
(18, 'subadmin', 'subadmin@martinezfitness.com', '$2y$10$o49JO5YERP6GcT.04idpF.Mqcwg.jP6ItmvZd2/gExwxjnZI6jptm', 'admin', NULL, NULL, 1, '2026-03-17 23:27:17', '2026-03-17 23:27:17'),
(9, 'patrick', 'patrickmontero833@gmail.com', '$2y$10$/n.VssP8ZxX8vxZQI1w9Ye1yks5gHqwR0KH7nRj.bJfIffvrLBmIi', 'user', '', '', 1, '2026-02-05 14:28:45', '2026-02-05 14:28:45'),
(16, 'Geraldine', 'geraldinegaran70@gmail.com', '$2y$10$0jxk17q/dWfeEW8bD/h8HuuaGOVxJpZ2SJqivd4jzjew6QTJ/JuA6', 'trainer', '09987654321', NULL, 1, '2026-03-16 11:24:55', '2026-03-23 06:22:24');

-- --------------------------------------------------------

--
-- Table structure for table `user_questionnaire`
--

DROP TABLE IF EXISTS `user_questionnaire`;
CREATE TABLE IF NOT EXISTS `user_questionnaire` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `age` int DEFAULT NULL,
  `sex` enum('Male','Female','Other') DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `medical_conditions` text,
  `exercise_history` enum('Beginner','Intermediate','Advanced') DEFAULT 'Beginner',
  `primary_goal` enum('Lose weight','Gain muscle','Improve endurance','Stay fit / general health') DEFAULT 'Stay fit / general health',
  `goal_pace` enum('Slowly','Moderately','Intensively') DEFAULT 'Moderately',
  `workout_days_per_week` enum('1-2 days','3-4 days','5+ days') DEFAULT '1-2 days',
  `preferred_workout_time` enum('Morning','Afternoon','Evening') DEFAULT 'Morning',
  `injuries_limitations` text,
  `focus_areas` varchar(255) DEFAULT 'Full body',
  `workout_type` enum('Cardio','Strength training','Mixed') DEFAULT 'Mixed',
  `trainer_guidance` enum('With trainer guidance','Independent workout') DEFAULT 'Independent workout',
  `equipment_confidence` enum('Not confident','Somewhat confident','Very') DEFAULT 'Not confident',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_questionnaire`
--

INSERT INTO `user_questionnaire` (`id`, `user_id`, `age`, `sex`, `height`, `weight`, `medical_conditions`, `exercise_history`, `primary_goal`, `goal_pace`, `workout_days_per_week`, `preferred_workout_time`, `injuries_limitations`, `focus_areas`, `workout_type`, `trainer_guidance`, `equipment_confidence`, `created_at`, `updated_at`) VALUES
(1, 13, 19, 'Male', 190.00, 50.00, 'None', 'Beginner', 'Lose weight', 'Slowly', '1-2 days', 'Morning', 'None', 'Arms', 'Cardio', 'With trainer guidance', 'Not confident', '2026-03-27 10:58:34', '2026-03-27 10:58:34');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
