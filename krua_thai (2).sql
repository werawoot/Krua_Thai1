-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Jul 07, 2025 at 09:53 AM
-- Server version: 5.7.39
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `krua_thai`
--

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `complaint_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` enum('food_quality','delivery_late','delivery_wrong','missing_items','damaged_package','customer_service','billing','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('low','medium','high','critical') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `expected_resolution` text COLLATE utf8mb4_unicode_ci,
  `attachments` json DEFAULT NULL,
  `photos` json DEFAULT NULL,
  `status` enum('open','in_progress','resolved','closed','escalated') COLLATE utf8mb4_unicode_ci DEFAULT 'open',
  `assigned_to` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution` text COLLATE utf8mb4_unicode_ci,
  `resolution_date` timestamp NULL DEFAULT NULL,
  `last_contact_date` timestamp NULL DEFAULT NULL,
  `follow_up_required` tinyint(1) DEFAULT '0',
  `customer_satisfaction_rating` tinyint(4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_nutrition_tracking`
--

CREATE TABLE `daily_nutrition_tracking` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tracking_date` date NOT NULL,
  `total_calories` int(11) DEFAULT '0',
  `total_protein_g` decimal(5,2) DEFAULT '0.00',
  `total_carbs_g` decimal(5,2) DEFAULT '0.00',
  `total_fat_g` decimal(5,2) DEFAULT '0.00',
  `total_fiber_g` decimal(5,2) DEFAULT '0.00',
  `total_sodium_mg` decimal(7,2) DEFAULT '0.00',
  `goal_achievement_percentage` decimal(5,2) DEFAULT '0.00',
  `recommendations` json DEFAULT NULL,
  `krua_thai_meals_count` int(11) DEFAULT '0',
  `krua_thai_calories` int(11) DEFAULT '0',
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_zones`
--

CREATE TABLE `delivery_zones` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zip_codes` json NOT NULL,
  `delivery_fee` decimal(6,2) DEFAULT '0.00',
  `free_delivery_minimum` decimal(8,2) DEFAULT NULL,
  `delivery_time_slots` json DEFAULT NULL,
  `estimated_delivery_time` int(11) DEFAULT '60',
  `is_active` tinyint(1) DEFAULT '1',
  `max_orders_per_day` int(11) DEFAULT '100',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_zones`
--

INSERT INTO `delivery_zones` (`id`, `zone_name`, `zip_codes`, `delivery_fee`, `free_delivery_minimum`, `delivery_time_slots`, `estimated_delivery_time`, `is_active`, `max_orders_per_day`, `created_at`, `updated_at`) VALUES
('0bbc4849-e184-4f62-bba2-cde4e740de8e', 'Bangkok Suburbs', '[\"10240\", \"10250\", \"10260\", \"10270\", \"10280\", \"10290\"]', '80.00', '800.00', '[\"15:00-18:00\", \"18:00-21:00\"]', 90, 1, 50, '2025-07-03 15:53:34', '2025-07-03 15:53:34'),
('b0be335e-bd6a-4a07-ae44-7032dfc96068', 'Greater Bangkok', '[\"10150\", \"10160\", \"10170\", \"10200\", \"10210\", \"10220\", \"10230\"]', '50.00', '500.00', '[\"12:00-15:00\", \"15:00-18:00\", \"18:00-21:00\"]', 60, 1, 100, '2025-07-03 15:53:34', '2025-07-03 15:53:34'),
('f7dd73ac-9445-4ebc-9319-a07522b0b1d2', 'Central Bangkok', '[\"10110\", \"10120\", \"10130\", \"10140\", \"10330\", \"10400\"]', '0.00', '300.00', '[\"09:00-12:00\", \"12:00-15:00\", \"15:00-18:00\", \"18:00-21:00\"]', 45, 1, 150, '2025-07-03 15:53:34', '2025-07-03 15:53:34');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ingredient_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ingredient_name_thai` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_of_measure` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_stock` decimal(10,2) NOT NULL DEFAULT '0.00',
  `minimum_stock` decimal(10,2) NOT NULL DEFAULT '0.00',
  `maximum_stock` decimal(10,2) DEFAULT NULL,
  `cost_per_unit` decimal(8,2) DEFAULT NULL,
  `supplier_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_contact` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `storage_location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `storage_temperature` enum('frozen','refrigerated','room_temp') COLLATE utf8mb4_unicode_ci DEFAULT 'room_temp',
  `is_active` tinyint(1) DEFAULT '1',
  `last_restocked_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

CREATE TABLE `menus` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_thai` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ingredients` text COLLATE utf8mb4_unicode_ci,
  `cooking_method` text COLLATE utf8mb4_unicode_ci,
  `main_image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gallery_images` json DEFAULT NULL,
  `base_price` decimal(8,2) NOT NULL,
  `portion_size` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Regular',
  `preparation_time` int(11) DEFAULT '15',
  `calories_per_serving` int(11) DEFAULT NULL,
  `protein_g` decimal(5,2) DEFAULT NULL,
  `carbs_g` decimal(5,2) DEFAULT NULL,
  `fat_g` decimal(5,2) DEFAULT NULL,
  `fiber_g` decimal(5,2) DEFAULT NULL,
  `sodium_mg` decimal(7,2) DEFAULT NULL,
  `sugar_g` decimal(5,2) DEFAULT NULL,
  `health_benefits` json DEFAULT NULL,
  `dietary_tags` json DEFAULT NULL,
  `spice_level` enum('mild','medium','hot','extra_hot') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `is_available` tinyint(1) DEFAULT '1',
  `is_featured` tinyint(1) DEFAULT '0',
  `is_seasonal` tinyint(1) DEFAULT '0',
  `availability_start` date DEFAULT NULL,
  `availability_end` date DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menus`
--

INSERT INTO `menus` (`id`, `category_id`, `name`, `name_thai`, `description`, `ingredients`, `cooking_method`, `main_image_url`, `gallery_images`, `base_price`, `portion_size`, `preparation_time`, `calories_per_serving`, `protein_g`, `carbs_g`, `fat_g`, `fiber_g`, `sodium_mg`, `sugar_g`, `health_benefits`, `dietary_tags`, `spice_level`, `is_available`, `is_featured`, `is_seasonal`, `availability_start`, `availability_end`, `slug`, `meta_description`, `created_at`, `updated_at`) VALUES
('04c79185-31ad-4658-a740-b8bd11eaa916', '550e8400-e29b-41d4-a716-446655440005', 'ข้าว', 'แกงเขียวหวาน', NULL, NULL, NULL, 'uploads/menus/menu_68675a04b2f80.jpg', NULL, '100.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '[\"high_protein\"]', NULL, 'medium', 1, 0, 0, NULL, NULL, '', NULL, '2025-07-04 04:35:16', '2025-07-04 04:35:16'),
('74184fca-57ba-11f0-a23d-e6c5297ffe7c', '550e8400-e29b-41d4-a716-446655440005', 'Pad Thai (Shrimp)', NULL, 'Classic Pad Thai with shrimp, tofu, and peanuts.', NULL, NULL, NULL, NULL, '120.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, NULL, NULL, '2025-07-03 03:04:37', '2025-07-03 03:04:37'),
('741955aa-57ba-11f0-a23d-e6c5297ffe7c', '550e8400-e29b-41d4-a716-446655440005', 'Pad Thai (Vegan)', NULL, 'Vegan Pad Thai with tofu, vegetables, and peanuts.', NULL, NULL, NULL, NULL, '110.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, NULL, NULL, '2025-07-03 03:04:37', '2025-07-03 03:04:37'),
('74195a82-57ba-11f0-a23d-e6c5297ffe7c', '550e8400-e29b-41d4-a716-446655440005', 'Thai Basil (Chicken) + Rice', NULL, 'Spicy chicken stir-fried with basil, served with rice.', NULL, NULL, NULL, NULL, '115.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, NULL, NULL, '2025-07-03 03:04:37', '2025-07-03 03:04:37'),
('74195c1c-57ba-11f0-a23d-e6c5297ffe7c', '550e8400-e29b-41d4-a716-446655440006', 'Green Curry (Chicken) + Rice', NULL, 'Mildly spicy green curry with chicken and rice.', NULL, NULL, NULL, NULL, '125.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, NULL, NULL, '2025-07-03 03:04:37', '2025-07-03 03:04:37'),
('74195d7a-57ba-11f0-a23d-e6c5297ffe7c', '550e8400-e29b-41d4-a716-446655440005', 'Vegan Larb (Tofu)', NULL, 'Northeastern style spicy tofu salad.', NULL, NULL, NULL, NULL, '100.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, NULL, NULL, '2025-07-03 03:04:37', '2025-07-03 03:04:37'),
('74195ed8-57ba-11f0-a23d-e6c5297ffe7c', '550e8400-e29b-41d4-a716-446655440005', 'Cashew Chicken + Rice', NULL, 'Chicken stir-fried with cashews and vegetables, served with rice.', NULL, NULL, NULL, NULL, '120.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, NULL, NULL, '2025-07-03 03:04:37', '2025-07-03 03:04:37'),
('74196068-57ba-11f0-a23d-e6c5297ffe7c', '550e8400-e29b-41d4-a716-446655440006', 'Tom Kha (Chicken) + Rice', NULL, 'Coconut milk soup with chicken and herbs, served with rice.', NULL, NULL, NULL, NULL, '115.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, NULL, NULL, '2025-07-03 03:04:37', '2025-07-03 03:04:37'),
('74196cac-57ba-11f0-a23d-e6c5297ffe7c', '550e8400-e29b-41d4-a716-446655440005', 'Beef Crying Tiger + Sticky Rice', NULL, 'Grilled beef with spicy dipping sauce, served with sticky rice.', NULL, NULL, NULL, NULL, '145.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, NULL, NULL, '2025-07-03 03:04:37', '2025-07-04 04:24:45'),
('7419d55c-57ba-11f0-a23d-e6c5297ffe7c', '550e8400-e29b-41d4-a716-446655440006', 'Tom Yum (Shrimp) soup', NULL, 'Spicy and sour shrimp soup with Thai herbs.', NULL, NULL, NULL, NULL, '130.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, NULL, NULL, '2025-07-03 03:04:37', '2025-07-03 03:04:37'),
('7419d89a-57ba-11f0-a23d-e6c5297ffe7c', '550e8400-e29b-41d4-a716-446655440005', 'Chicken Satay', NULL, 'Grilled chicken skewers served with peanut sauce.', NULL, NULL, NULL, NULL, '105.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, NULL, NULL, '2025-07-03 03:04:37', '2025-07-03 03:04:37');

-- --------------------------------------------------------

--
-- Table structure for table `menu_categories`
--

CREATE TABLE `menu_categories` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_thai` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(11) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_categories`
--

INSERT INTO `menu_categories` (`id`, `name`, `name_thai`, `description`, `image_url`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
('550e8400-e29b-41d4-a716-446655440005', 'Rice Bowls', 'ข้าวกล่อง', 'Healthy rice-based meals with various proteins and vegetables', NULL, 1, 1, '2025-07-02 02:47:49', '2025-07-02 02:47:49'),
('550e8400-e29b-41d4-a716-446655440006', 'Thai Curries', 'แกงไทย', 'Traditional Thai curries with authentic flavors', NULL, 2, 1, '2025-07-02 02:47:49', '2025-07-02 02:47:49'),
('a598bb91-68eb-4b0f-9de3-174362f36f37', 'Noodle Dishes', '— เมนูเส้น', NULL, NULL, 3, 1, '2025-07-04 04:49:24', '2025-07-04 04:49:24');

-- --------------------------------------------------------

--
-- Table structure for table `menu_ingredients`
--

CREATE TABLE `menu_ingredients` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `menu_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `inventory_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_needed` decimal(8,3) NOT NULL,
  `is_main_ingredient` tinyint(1) DEFAULT '0',
  `preparation_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('order_update','delivery','payment','promotion','system','review_reminder') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `channels` json DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `is_sent` tinyint(1) DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `related_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nutrition_goals`
--

CREATE TABLE `nutrition_goals` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_calories` int(11) DEFAULT NULL,
  `target_protein_g` decimal(5,2) DEFAULT NULL,
  `target_carbs_g` decimal(5,2) DEFAULT NULL,
  `target_fat_g` decimal(5,2) DEFAULT NULL,
  `target_fiber_g` decimal(5,2) DEFAULT NULL,
  `target_sodium_mg` decimal(7,2) DEFAULT NULL,
  `health_goals` json DEFAULT NULL,
  `activity_level` enum('sedentary','lightly_active','moderately_active','very_active') COLLATE utf8mb4_unicode_ci DEFAULT 'moderately_active',
  `height_cm` decimal(5,2) DEFAULT NULL,
  `current_weight_kg` decimal(5,2) DEFAULT NULL,
  `target_weight_kg` decimal(5,2) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `medical_conditions` json DEFAULT NULL,
  `medications` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscription_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery_date` date NOT NULL,
  `delivery_time_slot` enum('09:00-12:00','12:00-15:00','15:00-18:00','18:00-21:00') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery_instructions` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','confirmed','preparing','ready','out_for_delivery','delivered','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `kitchen_status` enum('not_started','in_progress','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'not_started',
  `assigned_rider_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_time` timestamp NULL DEFAULT NULL,
  `delivery_confirmation_method` enum('photo','signature','customer_present','left_at_door') COLLATE utf8mb4_unicode_ci DEFAULT 'customer_present',
  `delivery_photo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_signature_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `total_items` int(11) DEFAULT '0',
  `estimated_prep_time` int(11) DEFAULT '30',
  `special_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `menu_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscription_menu_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `menu_price` decimal(8,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT '1',
  `customizations` json DEFAULT NULL,
  `special_requests` text COLLATE utf8mb4_unicode_ci,
  `calories_per_item` int(11) DEFAULT NULL,
  `total_calories` int(11) DEFAULT NULL,
  `item_status` enum('pending','preparing','ready','served') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `preparation_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscription_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method` enum('apple_pay','google_pay','paypal','credit_card','bank_transfer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_provider` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_payment_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(8,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'THB',
  `fee_amount` decimal(8,2) DEFAULT '0.00',
  `net_amount` decimal(8,2) NOT NULL,
  `status` enum('pending','completed','failed','refunded','partial_refund') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `billing_period_start` date DEFAULT NULL,
  `billing_period_end` date DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `failure_reason` text COLLATE utf8mb4_unicode_ci,
  `refund_amount` decimal(8,2) DEFAULT '0.00',
  `refund_reason` text COLLATE utf8mb4_unicode_ci,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `subscription_id`, `user_id`, `payment_method`, `payment_provider`, `transaction_id`, `external_payment_id`, `amount`, `currency`, `fee_amount`, `net_amount`, `status`, `payment_date`, `billing_period_start`, `billing_period_end`, `description`, `failure_reason`, `refund_amount`, `refund_reason`, `refunded_at`, `created_at`, `updated_at`) VALUES
('42d58d62-5e86-4272-9413-1040e5357fdc', '44c83ddd-b28c-447b-85cc-5eb8926e6061', '6c9391f3-0772-4393-a703-a035cc68d3d7', 'bank_transfer', NULL, 'TXN-20250707-093846-44c83d', NULL, '499.00', 'THB', '0.00', '499.00', 'completed', '2025-07-07 09:38:46', '2025-07-08', '2025-07-15', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-07 09:38:46', '2025-07-07 09:38:46'),
('8bc32412-f04a-4e07-ae19-6319f64dc0f1', '28e75278-75c2-448d-bef9-d6c2886ec531', '550e8400-e29b-41d4-a716-446655440001', 'apple_pay', 'Apple Pay', 'APL-20250703-001', 'apple_6866925a0d921', '1200.00', 'THB', '24.00', '1176.00', 'completed', '2025-07-01 07:23:22', '2025-07-03', '2025-08-03', 'Monthly Subscription - Healthy Thai Meals', NULL, '0.00', NULL, NULL, '2025-07-03 14:23:22', '2025-07-03 14:23:22'),
('8cbc5ad1-1ac5-402b-b8d1-5aa960e8d7fc', '28e75278-75c2-448d-bef9-d6c2886ec531', '550e8400-e29b-41d4-a716-446655440001', 'credit_card', 'Stripe', 'CC-20250703-003', 'stripe_6866925a0da5a', '1500.00', 'THB', '45.00', '1455.00', 'failed', NULL, '2025-07-03', '2025-08-03', 'Premium Monthly Plan', 'Insufficient funds', '0.00', NULL, NULL, '2025-07-03 14:23:22', '2025-07-03 14:23:22'),
('b3ad486c-46c3-449c-8778-45b53fe638ef', '676a5c1a-39de-4aac-bc0e-4a5729c0a022', '550e8400-e29b-41d4-a716-446655440001', 'google_pay', 'Google Pay', 'GPY-20250703-002', 'google_6866925a0da42', '800.00', 'THB', '16.00', '784.00', 'pending', NULL, '2025-07-03', '2025-07-10', 'Weekly Subscription - 3 Meals', NULL, '0.00', NULL, NULL, '2025-07-03 14:23:22', '2025-07-03 14:23:22'),
('bdb23dfd-1916-4339-89f3-9989beda1b72', '676a5c1a-39de-4aac-bc0e-4a5729c0a022', '550e8400-e29b-41d4-a716-446655440001', 'paypal', 'PayPal', 'PPL-20250703-004', 'paypal_6866925a0da70', '600.00', 'THB', '18.00', '582.00', 'refunded', '2025-06-28 07:23:22', '2025-06-26', '2025-07-03', 'Weekly Trial Plan', NULL, '600.00', 'Customer requested cancellation', '2025-07-02 07:23:22', '2025-07-03 14:23:22', '2025-07-03 14:23:22'),
('bf9bd8b5-d14a-4555-a74b-3e9575c0babc', '90a1b331-9e41-4e9f-b757-cf9b91586387', '29e6fe85-124c-480f-bcf2-0d174721936f', 'credit_card', NULL, 'TXN-20250707-094726-90a1b3', NULL, '899.00', 'THB', '0.00', '899.00', 'completed', '2025-07-07 09:47:26', '2025-07-08', '2025-07-15', 'Subscription แพ็กเกจ 8 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-07 09:47:26', '2025-07-07 09:47:26');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `menu_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overall_rating` tinyint(4) NOT NULL,
  `food_quality_rating` tinyint(4) DEFAULT NULL,
  `delivery_rating` tinyint(4) DEFAULT NULL,
  `packaging_rating` tinyint(4) DEFAULT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `pros` text COLLATE utf8mb4_unicode_ci,
  `cons` text COLLATE utf8mb4_unicode_ci,
  `would_recommend` tinyint(1) DEFAULT NULL,
  `photos` json DEFAULT NULL,
  `admin_response` text COLLATE utf8mb4_unicode_ci,
  `admin_response_at` timestamp NULL DEFAULT NULL,
  `admin_response_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `is_featured` tinyint(1) DEFAULT '0',
  `is_public` tinyint(1) DEFAULT '1',
  `moderation_status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','paused','cancelled','expired','pending_payment') COLLATE utf8mb4_unicode_ci DEFAULT 'pending_payment',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `next_billing_date` date DEFAULT NULL,
  `billing_cycle` enum('weekly','monthly') COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_amount` decimal(8,2) NOT NULL,
  `discount_applied` decimal(8,2) DEFAULT '0.00',
  `delivery_days` json DEFAULT NULL,
  `preferred_delivery_time` enum('morning','afternoon','evening') COLLATE utf8mb4_unicode_ci DEFAULT 'afternoon',
  `special_instructions` text COLLATE utf8mb4_unicode_ci,
  `pause_start_date` date DEFAULT NULL,
  `pause_end_date` date DEFAULT NULL,
  `skip_dates` json DEFAULT NULL,
  `auto_renew` tinyint(1) DEFAULT '1',
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `user_id`, `plan_id`, `status`, `start_date`, `end_date`, `next_billing_date`, `billing_cycle`, `total_amount`, `discount_applied`, `delivery_days`, `preferred_delivery_time`, `special_instructions`, `pause_start_date`, `pause_end_date`, `skip_dates`, `auto_renew`, `cancellation_reason`, `cancelled_at`, `cancelled_by`, `created_at`, `updated_at`) VALUES
('1a5d3ec4-757b-480d-838a-e1bd53034a67', '6c9391f3-0772-4393-a703-a035cc68d3d7', 'c52e2693-5121-4abe-ad95-95c068367b92', 'pending_payment', '2025-07-08', NULL, '2025-07-15', 'weekly', '800.00', '0.00', '[\"monday\", \"thursday\", \"friday\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-04 07:36:50', '2025-07-04 07:36:50'),
('28e75278-75c2-448d-bef9-d6c2886ec531', '550e8400-e29b-41d4-a716-446655440001', '9d41847d-da80-4121-9ff2-0da4efeb371d', 'active', '2025-07-03', '2025-08-03', '2025-08-03', 'monthly', '1200.00', '120.00', '[\"monday\", \"wednesday\", \"friday\"]', 'afternoon', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-03 14:23:22', '2025-07-03 14:23:22'),
('44c83ddd-b28c-447b-85cc-5eb8926e6061', '6c9391f3-0772-4393-a703-a035cc68d3d7', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-08', NULL, '2025-07-15', 'weekly', '499.00', '0.00', '[\"monday\", \"tuesday\"]', 'afternoon', 'Please call upon arrival at the lobby. Do not leave food with the security guard.', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-07 09:38:46', '2025-07-07 09:38:46'),
('676a5c1a-39de-4aac-bc0e-4a5729c0a022', '550e8400-e29b-41d4-a716-446655440001', 'c52e2693-5121-4abe-ad95-95c068367b92', 'active', '2025-06-26', '2025-07-03', '2025-07-10', 'weekly', '800.00', '0.00', '[\"tuesday\", \"thursday\"]', 'morning', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-03 14:23:22', '2025-07-03 14:23:22'),
('90a1b331-9e41-4e9f-b757-cf9b91586387', '29e6fe85-124c-480f-bcf2-0d174721936f', 'd6e98c56-5afb-11f0-8b7f-3f129bd34f14', 'active', '2025-07-08', NULL, '2025-07-15', 'weekly', '899.00', '0.00', '[\"monday\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-07 09:47:26', '2025-07-07 09:47:26');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_menus`
--

CREATE TABLE `subscription_menus` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscription_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `menu_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery_date` date NOT NULL,
  `quantity` int(11) DEFAULT '1',
  `customizations` json DEFAULT NULL,
  `special_requests` text COLLATE utf8mb4_unicode_ci,
  `status` enum('scheduled','modified','skipped','delivered') COLLATE utf8mb4_unicode_ci DEFAULT 'scheduled',
  `modified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_menus`
--

INSERT INTO `subscription_menus` (`id`, `subscription_id`, `menu_id`, `delivery_date`, `quantity`, `customizations`, `special_requests`, `status`, `modified_at`, `created_at`, `updated_at`) VALUES
('0787d3f8-94ed-42eb-a862-0947bb14cb6b', '90a1b331-9e41-4e9f-b757-cf9b91586387', '74184fca-57ba-11f0-a23d-e6c5297ffe7c', '2025-07-08', 1, NULL, NULL, 'scheduled', NULL, '2025-07-07 09:47:26', '2025-07-07 09:47:26'),
('117e5fa1-6f74-4799-8535-6e7ce44ea343', '90a1b331-9e41-4e9f-b757-cf9b91586387', '7419d89a-57ba-11f0-a23d-e6c5297ffe7c', '2025-07-08', 1, NULL, NULL, 'scheduled', NULL, '2025-07-07 09:47:26', '2025-07-07 09:47:26'),
('1ca60b69-7bce-4754-8bec-3ade4563992b', '90a1b331-9e41-4e9f-b757-cf9b91586387', '04c79185-31ad-4658-a740-b8bd11eaa916', '2025-07-08', 1, NULL, NULL, 'scheduled', NULL, '2025-07-07 09:47:26', '2025-07-07 09:47:26'),
('5163a190-f926-4a96-b9bb-aee56365c9b8', '90a1b331-9e41-4e9f-b757-cf9b91586387', '74196cac-57ba-11f0-a23d-e6c5297ffe7c', '2025-07-08', 1, NULL, NULL, 'scheduled', NULL, '2025-07-07 09:47:26', '2025-07-07 09:47:26'),
('56d5ad64-6048-4bbf-b3ca-6c874536977e', '44c83ddd-b28c-447b-85cc-5eb8926e6061', '74195a82-57ba-11f0-a23d-e6c5297ffe7c', '2025-07-08', 1, NULL, NULL, 'scheduled', NULL, '2025-07-07 09:38:46', '2025-07-07 09:38:46'),
('58ab3af6-53e1-49fc-bc1c-9210d757a995', '90a1b331-9e41-4e9f-b757-cf9b91586387', '74195d7a-57ba-11f0-a23d-e6c5297ffe7c', '2025-07-08', 1, NULL, NULL, 'scheduled', NULL, '2025-07-07 09:47:26', '2025-07-07 09:47:26'),
('8156dd80-a630-4240-85fb-7519ea0c558a', '90a1b331-9e41-4e9f-b757-cf9b91586387', '741955aa-57ba-11f0-a23d-e6c5297ffe7c', '2025-07-08', 1, NULL, NULL, 'scheduled', NULL, '2025-07-07 09:47:26', '2025-07-07 09:47:26'),
('b9133b0f-0645-4791-86f2-943d3003ab2b', '90a1b331-9e41-4e9f-b757-cf9b91586387', '74195ed8-57ba-11f0-a23d-e6c5297ffe7c', '2025-07-08', 1, NULL, NULL, 'scheduled', NULL, '2025-07-07 09:47:26', '2025-07-07 09:47:26'),
('caad37f9-5ce6-420d-b3c9-a3eab659b83d', '44c83ddd-b28c-447b-85cc-5eb8926e6061', '74195d7a-57ba-11f0-a23d-e6c5297ffe7c', '2025-07-08', 1, NULL, NULL, 'scheduled', NULL, '2025-07-07 09:38:46', '2025-07-07 09:38:46'),
('cd0deb87-e608-4969-8cfe-c678a18d4a4c', '44c83ddd-b28c-447b-85cc-5eb8926e6061', '74184fca-57ba-11f0-a23d-e6c5297ffe7c', '2025-07-08', 1, NULL, NULL, 'scheduled', NULL, '2025-07-07 09:38:46', '2025-07-07 09:38:46'),
('d04115de-b453-4946-b6d7-e90f221b18e4', '90a1b331-9e41-4e9f-b757-cf9b91586387', '74195a82-57ba-11f0-a23d-e6c5297ffe7c', '2025-07-08', 1, NULL, NULL, 'scheduled', NULL, '2025-07-07 09:47:26', '2025-07-07 09:47:26'),
('d9fbb7ad-4c79-464d-93ce-59eb98bc1e3c', '44c83ddd-b28c-447b-85cc-5eb8926e6061', '741955aa-57ba-11f0-a23d-e6c5297ffe7c', '2025-07-08', 1, NULL, NULL, 'scheduled', NULL, '2025-07-07 09:38:46', '2025-07-07 09:38:46');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_thai` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `plan_type` enum('weekly','monthly','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
  `meals_per_week` int(11) NOT NULL,
  `weeks_duration` int(11) DEFAULT '4',
  `base_price` decimal(8,2) NOT NULL,
  `discount_percentage` decimal(5,2) DEFAULT '0.00',
  `final_price` decimal(8,2) NOT NULL,
  `features` json DEFAULT NULL,
  `max_skips_per_week` int(11) DEFAULT '2',
  `advance_ordering_days` int(11) DEFAULT '7',
  `is_active` tinyint(1) DEFAULT '1',
  `is_popular` tinyint(1) DEFAULT '0',
  `sort_order` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `name`, `name_thai`, `description`, `plan_type`, `meals_per_week`, `weeks_duration`, `base_price`, `discount_percentage`, `final_price`, `features`, `max_skips_per_week`, `advance_ordering_days`, `is_active`, `is_popular`, `sort_order`, `created_at`, `updated_at`) VALUES
('74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'Mini Plan', 'แพ็กเกจ 4 มื้อ', 'สำหรับทดลองกินสุขภาพ', 'weekly', 4, 1, '499.00', '0.00', '499.00', NULL, 1, 2, 1, 0, 1, '2025-07-07 06:34:38', '2025-07-07 06:34:38'),
('9d41847d-da80-4121-9ff2-0da4efeb371d', 'Monthly Plan', 'แพ็คเกจรายเดือน', 'Healthy Thai meals for 1 month', 'monthly', 7, 4, '1200.00', '10.00', '1080.00', '[\"Fresh ingredients\", \"Nutrition tracking\", \"Free delivery\"]', 2, 7, 0, 1, 0, '2025-07-03 14:23:22', '2025-07-07 06:39:09'),
('c52e2693-5121-4abe-ad95-95c068367b92', 'Weekly Plan', 'แพ็คเกจรายสัปดาห์', 'Healthy Thai meals for 1 week', 'weekly', 7, 1, '800.00', '0.00', '800.00', '[\"Fresh ingredients\", \"Nutrition tracking\"]', 2, 7, 1, 0, 0, '2025-07-03 14:23:22', '2025-07-03 14:23:22'),
('d6e98c56-5afb-11f0-8b7f-3f129bd34f14', 'Lite Plan', 'แพ็กเกจ 8 มื้อ', 'สำหรับคนกินเบาๆ', 'weekly', 8, 1, '899.00', '0.00', '899.00', NULL, 1, 2, 1, 0, 2, '2025-07-07 06:30:13', '2025-07-07 06:30:13'),
('d6e98d96-5afb-11f0-8b7f-3f129bd34f14', 'Family Plan', 'แพ็กเกจ 12 มื้อ', 'สุขภาพทั้งบ้าน', 'weekly', 12, 1, '1499.00', '0.00', '1499.00', NULL, 2, 3, 1, 1, 3, '2025-07-07 06:30:13', '2025-07-07 06:30:13'),
('d6e98e7c-5afb-11f0-8b7f-3f129bd34f14', 'Premium Plan', 'แพ็กเกจ 15 มื้อ', 'สาย Healthy จัดเต็ม', 'weekly', 15, 1, '1799.00', '0.00', '1799.00', NULL, 3, 4, 1, 1, 4, '2025-07-07 06:30:13', '2025-07-07 06:30:13');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_type` enum('string','number','boolean','json') COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `is_public` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `category`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Krua Thai', 'string', 'Website name', 'general', 1, '2025-07-03 15:19:17', '2025-07-03 15:19:17'),
(2, 'site_description', 'Authentic Thai Meals, Made Healthy', 'string', 'Website description', 'general', 1, '2025-07-03 15:19:17', '2025-07-03 15:19:17'),
(3, 'delivery_fee', '50.00', 'number', 'Default delivery fee (THB)', 'delivery', 1, '2025-07-03 15:19:17', '2025-07-03 15:19:17'),
(4, 'free_delivery_minimum', '500.00', 'number', 'Minimum order for free delivery (THB)', 'delivery', 1, '2025-07-03 15:19:17', '2025-07-03 15:19:17'),
(5, 'max_order_items', '10', 'number', 'Maximum items per order', 'orders', 0, '2025-07-03 15:19:17', '2025-07-03 15:19:17'),
(6, 'order_preparation_time', '30', 'number', 'Default preparation time (minutes)', 'orders', 0, '2025-07-03 15:19:17', '2025-07-03 15:19:17'),
(7, 'enable_notifications', '1', 'boolean', 'Enable system notifications', 'notifications', 0, '2025-07-03 15:19:17', '2025-07-03 15:19:17'),
(8, 'email_notifications', '1', 'boolean', 'Enable email notifications', 'notifications', 0, '2025-07-03 15:19:17', '2025-07-03 15:19:17'),
(9, 'business_hours', '{\"open\":\"09:00\",\"close\":\"21:00\",\"timezone\":\"Asia/Bangkok\"}', 'json', 'Business operating hours', 'general', 1, '2025-07-03 15:19:17', '2025-07-03 15:19:17'),
(10, 'contact_phone', '02-123-4567', 'string', 'Contact phone number', 'contact', 1, '2025-07-03 15:19:17', '2025-07-03 15:19:17'),
(11, 'contact_email', 'info@kruathai.com', 'string', 'Contact email address', 'contact', 1, '2025-07-03 15:19:17', '2025-07-03 15:19:17'),
(12, 'maintenance_mode', '0', 'boolean', 'Enable maintenance mode', 'system', 0, '2025-07-03 15:19:17', '2025-07-03 15:19:17'),
(13, 'currency', 'THB', 'string', 'Default currency', 'general', 1, '2025-07-03 15:19:17', '2025-07-03 15:19:17'),
(14, 'tax_rate', '7.00', 'number', 'Tax rate percentage', 'financial', 0, '2025-07-03 15:19:17', '2025-07-03 15:19:17');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('customer','admin','kitchen','rider','support') COLLATE utf8mb4_unicode_ci DEFAULT 'customer',
  `status` enum('active','inactive','suspended','pending_verification') COLLATE utf8mb4_unicode_ci DEFAULT 'pending_verification',
  `email_verified` tinyint(1) DEFAULT '0',
  `email_verification_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_address` text COLLATE utf8mb4_unicode_ci,
  `address_line_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Thailand',
  `delivery_instructions` text COLLATE utf8mb4_unicode_ci,
  `dietary_preferences` json DEFAULT NULL,
  `allergies` json DEFAULT NULL,
  `spice_level` enum('mild','medium','hot','extra_hot') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT '0',
  `locked_until` timestamp NULL DEFAULT NULL,
  `password_reset_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_reset_expires` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `date_of_birth`, `gender`, `role`, `status`, `email_verified`, `email_verification_token`, `delivery_address`, `address_line_2`, `city`, `state`, `zip_code`, `country`, `delivery_instructions`, `dietary_preferences`, `allergies`, `spice_level`, `last_login`, `failed_login_attempts`, `locked_until`, `password_reset_token`, `password_reset_expires`, `created_at`, `updated_at`) VALUES
('29e6fe85-124c-480f-bcf2-0d174721936f', 'topkung567@gmail.com', '$2y$10$aZGBb9qE7qVzKryVGii3iuS6tz18S9ZmQDnutEZyTN.L5K2xCRhom', 'ppp', 'pp', '0899946687', '2002-01-04', 'male', 'customer', 'active', 0, NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '(Optional) Q-House Building, Room 1705', 'Bangkok', 'Bangkok', '10110', 'Thailand', '', '[\"vegan\", \"keto\"]', NULL, 'medium', '2025-07-07 09:48:03', 0, NULL, NULL, NULL, '2025-07-04 04:00:20', '2025-07-07 09:48:03'),
('550e8400-e29b-41d4-a716-446655440001', 'customer@kruathai.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'สมชาย', 'ใจดี', '0812345678', NULL, NULL, 'customer', 'active', 1, NULL, '123 ถ.สุขุมวิท แขวงคลองเตย เขตคลองเตย กรุงเทพฯ 10110', NULL, NULL, NULL, '10110', 'Thailand', NULL, '[\"vegetarian\"]', NULL, 'medium', NULL, 0, NULL, NULL, NULL, '2025-07-02 02:47:49', '2025-07-04 07:30:42'),
('550e8400-e29b-41d4-a716-446655440002', 'admin@kruathai.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'อดมิน', 'ผู้จัดการ', '0898765432', NULL, NULL, 'admin', 'active', 1, NULL, NULL, NULL, NULL, NULL, NULL, 'Thailand', NULL, NULL, NULL, 'medium', '2025-07-07 08:51:09', 0, NULL, NULL, NULL, '2025-07-02 02:47:49', '2025-07-07 08:51:09'),
('550e8400-e29b-41d4-a716-446655440003', 'kitchen@kruathai.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'เชฟ', 'มือทอง', '0887654321', NULL, NULL, 'kitchen', 'active', 1, NULL, NULL, NULL, NULL, NULL, NULL, 'Thailand', NULL, NULL, NULL, 'medium', NULL, 0, NULL, NULL, NULL, '2025-07-02 02:47:49', '2025-07-02 02:47:49'),
('550e8400-e29b-41d4-a716-446655440004', 'rider@kruathai.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ไรเดอร์', 'รวดเร็ว', '0876543210', NULL, NULL, 'rider', 'active', 1, NULL, NULL, NULL, NULL, NULL, NULL, 'Thailand', NULL, NULL, NULL, 'medium', NULL, 0, NULL, NULL, NULL, '2025-07-02 02:47:49', '2025-07-02 02:47:49'),
('6c9391f3-0772-4393-a703-a035cc68d3d7', 'topkung72@gmail.com', '$2y$10$hUbfYT.s0BlG273BzZEKuO1AvdrkIFS2C7wuXRCmkoWPGofliaqNm', 'werawutt', 'Promrunway', '0616625931', '2001-11-04', 'male', 'customer', 'active', 1, NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '(Optional) Q-House Building, Room 1705', 'Bangkok', 'Bangkok', '10110', 'Thailand', 'Please call upon arrival at the lobby. Do not leave food with the security guard.', '[\"vegetarian\"]', NULL, 'mild', '2025-07-07 09:44:17', 0, NULL, '72853211e86277cf347c8592801209a698cf59ffa2a3a295a271d66b1ed8fe3d', '2025-07-02 19:14:02', '2025-07-02 06:58:30', '2025-07-07 09:44:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `complaint_number` (`complaint_number`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status_priority` (`status`,`priority`),
  ADD KEY `idx_complaint_number` (`complaint_number`);

--
-- Indexes for table `daily_nutrition_tracking`
--
ALTER TABLE `daily_nutrition_tracking`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_tracking_date` (`user_id`,`tracking_date`),
  ADD KEY `idx_user_date` (`user_id`,`tracking_date`);

--
-- Indexes for table `delivery_zones`
--
ALTER TABLE `delivery_zones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_stock_level` (`current_stock`,`minimum_stock`),
  ADD KEY `idx_expiry` (`expiry_date`);

--
-- Indexes for table `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_available` (`is_available`),
  ADD KEY `idx_featured` (`is_featured`),
  ADD KEY `idx_price` (`base_price`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indexes for table `menu_categories`
--
ALTER TABLE `menu_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active_sort` (`is_active`,`sort_order`);

--
-- Indexes for table `menu_ingredients`
--
ALTER TABLE `menu_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_menu_ingredient` (`menu_id`,`inventory_id`),
  ADD KEY `idx_menu` (`menu_id`),
  ADD KEY `idx_inventory` (`inventory_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_sent` (`is_sent`);

--
-- Indexes for table `nutrition_goals`
--
ALTER TABLE `nutrition_goals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_active` (`user_id`,`is_active`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `subscription_id` (`subscription_id`),
  ADD KEY `idx_delivery_date_status` (`delivery_date`,`status`),
  ADD KEY `idx_user_delivery` (`user_id`,`delivery_date`),
  ADD KEY `idx_rider_date` (`assigned_rider_id`,`delivery_date`),
  ADD KEY `idx_order_number` (`order_number`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subscription_menu_id` (`subscription_menu_id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_menu` (`menu_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_subscription` (`subscription_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_order_review` (`user_id`,`order_id`),
  ADD KEY `admin_response_by` (`admin_response_by`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_menu_rating` (`menu_id`,`overall_rating`),
  ADD KEY `idx_public_approved` (`is_public`,`moderation_status`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `cancelled_by` (`cancelled_by`),
  ADD KEY `idx_user_status` (`user_id`,`status`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_billing_date` (`next_billing_date`);

--
-- Indexes for table `subscription_menus`
--
ALTER TABLE `subscription_menus`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_subscription_menu_date` (`subscription_id`,`menu_id`,`delivery_date`),
  ADD KEY `idx_subscription_date` (`subscription_id`,`delivery_date`),
  ADD KEY `idx_delivery_date` (`delivery_date`),
  ADD KEY `idx_menu` (`menu_id`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active_sort` (`is_active`,`sort_order`),
  ADD KEY `idx_type` (`plan_type`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_zip_code` (`zip_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `complaints_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `daily_nutrition_tracking`
--
ALTER TABLE `daily_nutrition_tracking`
  ADD CONSTRAINT `daily_nutrition_tracking_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `menus`
--
ALTER TABLE `menus`
  ADD CONSTRAINT `menus_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `menu_ingredients`
--
ALTER TABLE `menu_ingredients`
  ADD CONSTRAINT `menu_ingredients_ibfk_1` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `menu_ingredients_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `nutrition_goals`
--
ALTER TABLE `nutrition_goals`
  ADD CONSTRAINT `nutrition_goals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`assigned_rider_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`subscription_menu_id`) REFERENCES `subscription_menus` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_4` FOREIGN KEY (`admin_response_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`),
  ADD CONSTRAINT `subscriptions_ibfk_3` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `subscription_menus`
--
ALTER TABLE `subscription_menus`
  ADD CONSTRAINT `subscription_menus_ibfk_1` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscription_menus_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
