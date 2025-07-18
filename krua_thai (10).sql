-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Jul 18, 2025 at 06:02 AM
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
  `subscription_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` enum('food_quality','delivery_late','delivery_wrong','missing_items','damaged_package','customer_service','billing','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('low','medium','high','critical') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `expected_resolution` text COLLATE utf8mb4_unicode_ci,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `photos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
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
  `recommendations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
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
  `zip_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `delivery_fee` decimal(6,2) DEFAULT '0.00',
  `free_delivery_minimum` decimal(8,2) DEFAULT NULL,
  `delivery_time_slots` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
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

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `ingredient_name`, `ingredient_name_thai`, `category`, `unit_of_measure`, `current_stock`, `minimum_stock`, `maximum_stock`, `cost_per_unit`, `supplier_name`, `supplier_contact`, `expiry_date`, `storage_location`, `storage_temperature`, `is_active`, `last_restocked_date`, `created_at`, `updated_at`) VALUES
('10101010-1010-1010-1010-101010101001', 'Rice Vinegar', 'น้ำส้มสายชู', 'Condiments', 'bottle', '12.00', '5.00', '25.00', '25.00', 'Vinegar House', '02-890-1234', '2025-12-31', 'Storage Room B', 'room_temp', 1, '2025-07-03', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('10101010-1010-1010-1010-101010101002', 'Lime Juice', 'น้ำมะนาว', 'Condiments', 'liter', '8.00', '3.00', '15.00', '80.00', 'Fresh Fruit Co.', '02-901-2345', '2025-07-15', 'Refrigerator D3', 'refrigerated', 1, '2025-07-08', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('11101110-1110-1110-1110-111011101001', 'Peanuts (Raw)', 'ถั่วลิสงดิบ', 'Nuts', 'kg', '10.00', '4.00', '20.00', '90.00', 'Nut Supplier Co.', '02-012-3456', '2025-10-15', 'Dry Storage D', 'room_temp', 1, '2025-07-01', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('11101110-1110-1110-1110-111011101002', 'Cashews', 'เม็ดมะม่วงหิมพานต์', 'Nuts', 'kg', '5.00', '2.00', '10.00', '320.00', 'Premium Nuts Ltd.', '02-123-4567', '2025-09-30', 'Dry Storage D', 'room_temp', 1, '2025-06-30', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('11111111-1111-1111-1111-111111111001', 'Chicken Breast', 'อกไก่', 'Proteins', 'kg', '25.00', '10.00', '50.00', '180.00', 'Bangkok Fresh Meat', '02-123-4567', '2025-07-15', 'Freezer A1', 'frozen', 1, '2025-07-08', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('11111111-1111-1111-1111-111111111002', 'Pork Shoulder', 'สันคอหมู', 'Proteins', 'kg', '20.00', '8.00', '40.00', '200.00', 'Bangkok Fresh Meat', '02-123-4567', '2025-07-12', 'Freezer A2', 'frozen', 1, '2025-07-06', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('11111111-1111-1111-1111-111111111003', 'Beef Sirloin', 'เนื้อสันนอก', 'Proteins', 'kg', '15.00', '5.00', '30.00', '450.00', 'Premium Beef Co.', '02-234-5678', '2025-07-13', 'Freezer A3', 'frozen', 1, '2025-07-07', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('11111111-1111-1111-1111-111111111004', 'Prawns (Large)', 'กุ้งใหญ่', 'Proteins', 'kg', '12.00', '8.00', '25.00', '350.00', 'Ocean Fresh Seafood', '02-345-6789', '2025-07-11', 'Freezer B1', 'frozen', 1, '2025-07-08', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('11111111-1111-1111-1111-111111111005', 'White Fish Fillet', 'เนื้อปลาขาว', 'Proteins', 'kg', '10.00', '5.00', '20.00', '280.00', 'Ocean Fresh Seafood', '02-345-6789', '2025-07-12', 'Freezer B2', 'frozen', 1, '2025-07-07', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('12121212-1212-1212-1212-121212121001', 'Dried Shiitake', 'เห็ดหอมแห้ง', 'Specialty', 'kg', '3.00', '1.00', '6.00', '280.00', 'Mushroom Import Co.', '02-234-5678', '2025-11-01', 'Dry Storage E', 'room_temp', 1, '2025-06-25', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('12121212-1212-1212-1212-121212121002', 'Black Soy Sauce', 'ซีอิ๊วหวาน', 'Specialty', 'bottle', '10.00', '4.00', '20.00', '55.00', 'Specialty Sauce Co.', '02-345-6789', '2025-10-15', 'Storage Room B', 'room_temp', 1, '2025-07-04', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('22222222-2222-2222-2222-222222222001', 'Thai Basil', 'โหระพา', 'Vegetables', 'bunch', '49.00', '20.00', '100.00', '15.00', 'Organic Farm Market', '02-456-7890', '2025-07-12', 'Refrigerator C1', 'refrigerated', 1, '2025-07-08', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('22222222-2222-2222-2222-222222222002', 'Holy Basil', 'กะเพรา', 'Vegetables', 'bunch', '43.00', '15.00', '80.00', '18.00', 'Organic Farm Market', '02-456-7890', '2025-07-11', 'Refrigerator C1', 'refrigerated', 1, '2025-07-07', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('22222222-2222-2222-2222-222222222003', 'Bird\'s Eye Chili', 'พริกขี้หนู', 'Vegetables', 'kg', '2.00', '3.00', '15.00', '120.00', 'Chili Specialist', '02-567-8901', '2025-07-14', 'Refrigerator C2', 'refrigerated', 1, '2025-07-08', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('22222222-2222-2222-2222-222222222004', 'Thai Eggplant', 'มะเขือเปราะ', 'Vegetables', 'kg', '11.00', '5.00', '25.00', '60.00', 'Organic Farm Market', '02-456-7890', '2025-07-13', 'Refrigerator C2', 'refrigerated', 1, '2025-07-06', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('22222222-2222-2222-2222-222222222005', 'Bean Sprouts', 'ถั่วงอก', 'Vegetables', 'g', '0.00', '8.00', '30.00', '25.00', 'Local Vegetable Market', '02-678-9012', '2025-07-11', 'Refrigerator C3', 'refrigerated', 1, '2025-07-12', '2025-07-10 06:00:23', '2025-07-14 03:37:29'),
('22222222-2222-2222-2222-222222222006', 'Morning Glory', 'ผักบุ้ง', 'Vegetables', 'bunch', '26.00', '15.00', '60.00', '12.00', 'Local Vegetable Market', '02-678-9012', '2025-07-12', 'Refrigerator C3', 'refrigerated', 1, '2025-07-07', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('33333333-3333-3333-3333-333333333001', 'Galangal', 'ข่า', 'Herbs', 'kg', '3.00', '2.00', '12.00', '180.00', 'Herb Garden Co.', '02-789-0123', '2025-07-20', 'Refrigerator D1', 'refrigerated', 1, '2025-07-05', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('33333333-3333-3333-3333-333333333002', 'Lemongrass', 'ตะไคร้', 'Herbs', 'stalk', '98.00', '50.00', '200.00', '3.00', 'Herb Garden Co.', '02-789-0123', '2025-07-18', 'Refrigerator D1', 'refrigerated', 1, '2025-07-06', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('33333333-3333-3333-3333-333333333003', 'Kaffir Lime Leaves', 'ใบมะกรูด', 'Herbs', 'piece', '199.00', '100.00', '400.00', '1.50', 'Herb Garden Co.', '02-789-0123', '2025-07-15', 'Refrigerator D1', 'refrigerated', 1, '2025-07-07', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('33333333-3333-3333-3333-333333333004', 'Thai Ginger', 'ขิง', 'Herbs', 'kg', '4.00', '2.00', '10.00', '90.00', 'Herb Garden Co.', '02-789-0123', '2025-07-25', 'Storage Room A', 'room_temp', 1, '2025-07-04', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('33333333-3333-3333-3333-333333333005', 'Garlic', 'กระเทียม', 'Herbs', 'kg', '8.00', '3.00', '15.00', '120.00', 'Local Market', '02-890-1234', '2025-07-30', 'Storage Room A', 'room_temp', 1, '2025-07-05', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('33333333-3333-3333-3333-333333333006', 'Shallots', 'หอมแดง', 'Herbs', 'kg', '8.00', '4.00', '20.00', '80.00', 'Local Market', '02-890-1234', '2025-07-22', 'Storage Room A', 'room_temp', 1, '2025-07-06', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('44444444-4444-4444-4444-444444444001', 'Fish Sauce', 'น้ำปลา', 'Sauces', 'bottle', '20.00', '8.00', '40.00', '65.00', 'Thai Sauce Factory', '02-901-2345', '2026-01-15', 'Storage Room B', 'room_temp', 1, '2025-07-03', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('44444444-4444-4444-4444-444444444002', 'Oyster Sauce', 'น้ำมันหอย', 'Sauces', 'bottle', '15.00', '6.00', '30.00', '45.00', 'Thai Sauce Factory', '02-901-2345', '2025-12-20', 'Storage Room B', 'room_temp', 1, '2025-07-04', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('44444444-4444-4444-4444-444444444003', 'Soy Sauce (Light)', 'ซีอิ๊วขาว', 'Sauces', 'bottle', '18.00', '8.00', '35.00', '35.00', 'Thai Sauce Factory', '02-901-2345', '2025-11-30', 'Storage Room B', 'room_temp', 1, '2025-07-05', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('44444444-4444-4444-4444-444444444004', 'Soy Sauce (Dark)', 'ซีอิ๊วดำ', 'Sauces', 'bottle', '16.00', '6.00', '32.00', '40.00', 'Thai Sauce Factory', '02-901-2345', '2025-11-30', 'Storage Room B', 'room_temp', 1, '2025-07-05', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('44444444-4444-4444-4444-444444444005', 'Tamarind Paste', 'น้ำมะขามเปียก', 'Sauces', 'kg', '8.00', '3.00', '15.00', '85.00', 'Traditional Food Co.', '02-012-3456', '2025-09-15', 'Refrigerator D2', 'refrigerated', 1, '2025-07-02', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('55555555-5555-5555-5555-555555555001', 'Jasmine Rice', 'ข้าวหอมมะลิ', 'Grains', 'kg', '200.00', '50.00', '400.00', '35.00', 'Rice Mill Direct', '02-123-4567', '2026-01-01', 'Dry Storage A', 'room_temp', 1, '2025-07-01', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('55555555-5555-5555-5555-555555555002', 'Sticky Rice', 'ข้าวเหนียว', 'Grains', 'kg', '50.00', '20.00', '100.00', '40.00', 'Rice Mill Direct', '02-123-4567', '2025-12-15', 'Dry Storage A', 'room_temp', 1, '2025-07-02', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('55555555-5555-5555-5555-555555555003', 'Rice Noodles (Thin)', 'เส้นหมี่', 'Grains', 'pack', '80.00', '30.00', '150.00', '12.00', 'Noodle Factory', '02-234-5678', '2025-10-30', 'Dry Storage B', 'room_temp', 1, '2025-07-03', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('55555555-5555-5555-5555-555555555004', 'Pad Thai Noodles', 'เส้นจันท์', 'Grains', 'pack', '60.00', '25.00', '120.00', '15.00', 'Noodle Factory', '02-234-5678', '2025-10-30', 'Dry Storage B', 'room_temp', 1, '2025-07-04', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('66666666-6666-6666-6666-666666666001', 'Vegetable Oil', 'น้ำมันพืช', 'Oils', 'liter', '25.00', '10.00', '50.00', '45.00', 'Oil Distributor', '02-345-6789', '2025-12-01', 'Storage Room C', 'room_temp', 1, '2025-07-01', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('66666666-6666-6666-6666-666666666002', 'Coconut Oil', 'น้ำมันมะพร้าว', 'Oils', 'liter', '15.00', '5.00', '30.00', '180.00', 'Coconut Products Ltd.', '02-456-7890', '2025-11-15', 'Storage Room C', 'room_temp', 1, '2025-07-02', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('77777777-7777-7777-7777-777777777001', 'White Pepper', 'พริกไทยขาว', 'Spices', 'kg', '1.50', '1.00', '6.00', '450.00', 'Spice World', '02-567-8901', '2026-03-01', 'Spice Cabinet A', 'room_temp', 1, '2025-06-30', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('77777777-7777-7777-7777-777777777002', 'Coriander Seeds', 'เมลดผักชี', 'Spices', 'kg', '2.50', '1.00', '5.00', '120.00', 'Spice World', '02-567-8901', '2026-02-15', 'Spice Cabinet A', 'room_temp', 1, '2025-07-01', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('77777777-7777-7777-7777-777777777003', 'Dried Chili', 'พริกแห้ง', 'Spices', 'kg', '4.00', '2.00', '8.00', '200.00', 'Spice World', '02-567-8901', '2026-01-30', 'Spice Cabinet B', 'room_temp', 1, '2025-06-28', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('88888888-8888-8888-8888-888888888001', 'Palm Sugar', 'น้ำตาลปี๊บ', 'Sweeteners', 'kg', '20.00', '8.00', '40.00', '65.00', 'Sugar Farm Co.', '02-678-9012', '2026-06-01', 'Dry Storage C', 'room_temp', 1, '2025-07-01', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('88888888-8888-8888-8888-888888888002', 'White Sugar', 'น้ำตาลทราย', 'Sweeteners', 'kg', '30.00', '15.00', '60.00', '28.00', 'Local Market', '02-789-0123', '2026-12-31', 'Dry Storage C', 'room_temp', 1, '2025-07-02', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('99999999-9999-9999-9999-999999999001', 'Coconut Milk', 'กะทิ', 'Dairy', 'can', '100.00', '40.00', '200.00', '35.00', 'Coconut Products Ltd.', '02-456-7890', '2025-08-15', 'Storage Room D', 'room_temp', 1, '2025-07-05', '2025-07-10 06:00:23', '2025-07-10 06:00:23'),
('99999999-9999-9999-9999-999999999002', 'Coconut Cream', 'หัวกะทิ', 'Dairy', 'can', '60.00', '25.00', '120.00', '45.00', 'Coconut Products Ltd.', '02-456-7890', '2025-08-20', 'Storage Room D', 'room_temp', 1, '2025-07-06', '2025-07-10 06:00:23', '2025-07-10 06:00:23');

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
  `gallery_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
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
  `health_benefits` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `dietary_tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
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
('19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', '550e8400-e29b-41d4-a716-446655440006', 'Tom Yum (Shrimp) Soup', 'ต้มยำกุ้ง', 'Spicy-sour tom yum soup loaded with shrimp, mushrooms, lemongrass, kaffir lime, and chili oil. A metabolism-boosting bowl of wellness.', NULL, NULL, 'uploads/menus/menu_686ce51c9ec4a.jpg', NULL, '400.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'hot', 1, 0, 0, NULL, NULL, 'tom-yum-shrimp-soup', NULL, '2025-07-08 09:30:04', '2025-07-08 09:30:04'),
('275059d8-4497-43ec-b5e3-669fb18981d9', '550e8400-e29b-41d4-a716-446655440005', 'Cashew Chicken + Rice', 'ไก่ผัดเม็ดมะม่วง', 'Pasture-raised chicken breast stir-fried with roasted cashews, sweet bell peppers and chili jam. Served with bone broth rice for extra umami. Comforting and clean.', NULL, NULL, 'uploads/menus/menu_686ce481a3769.jpg', NULL, '400.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, 'cashew-chicken-rice', NULL, '2025-07-08 09:27:29', '2025-07-10 05:41:42'),
('416cc737-c6a1-4b0d-a0d0-a40f384f496e', '550e8400-e29b-41d4-a716-446655440005', 'Tom Kha (Chicken) + Rice', 'ต้มข่าไก่', 'Coconut milk-based soup with chicken, mushrooms, lemongrass, and galangal. Served with organic jasmine rice. Creamy, aromatic, and dairy-free.', NULL, NULL, 'uploads/menus/menu_686ce4d48ae3f.jpg', NULL, '340.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, 'tom-kha-chicken-rice', NULL, '2025-07-08 09:28:52', '2025-07-08 12:19:30'),
('6cae13fc-b181-43bf-babb-99cb2ce39d3c', '550e8400-e29b-41d4-a716-446655440006', 'Chicken Satay', 'ไก่สะเต๊ะ', 'Grilled chicken skewers marinated in turmeric and coconut milk. Served with creamy peanut dipping sauce. The clean-eating version of your favorite street food.', NULL, NULL, 'uploads/menus/menu_686ce54435283.jpg', NULL, '200.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, 'chicken-satay', NULL, '2025-07-08 09:30:44', '2025-07-08 09:30:44'),
('9429bc0e-f065-4ea8-9481-1bcb887e8454', '550e8400-e29b-41d4-a716-446655440005', 'Beef Crying Tiger + Sticky Rice', 'เสือร้องให้', 'Grilled beef marinated with Thai spices, served with sticky rice and jaew dipping sauce. High-protein and low-carb indulgence that hits every savory note.', NULL, NULL, 'uploads/menus/menu_686ce4ff13861.png', NULL, '300.00', 'Small', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, 'beef-crying-tiger-sticky-rice', NULL, '2025-07-08 09:29:35', '2025-07-10 05:41:43'),
('d76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', 'ลาบ(เจ)', NULL, NULL, NULL, 'uploads/menus/menu_686ce3711b6e7.jpg', NULL, '399.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, 'vegan-larb-tofu', NULL, '2025-07-08 09:22:57', '2025-07-08 09:22:57'),
('dbdb771f-bf70-4ccc-ad99-d307f9d89418', '550e8400-e29b-41d4-a716-446655440005', 'Pad Thai (Shrimp)', 'ผัดไทกุ้งสด', 'Authentic Pad Thai with juicy shrimp stir-fried in tamarind sauce, served over chewy rice noodles. Garnished with crushed peanuts and lime for a balanced, protein-rich meal. No MSG, just flavor.', NULL, NULL, 'uploads/menus/menu_686ce2223230f.jpg', NULL, '350.00', 'Regular', 15, 350, '15.00', '50.00', '15.00', '10.00', '10.00', NULL, NULL, NULL, 'mild', 1, 0, 0, NULL, NULL, 'pad-thai-shrimp', 'Dive into Thailand&#039;s most beloved dish, elevated to nourish your body and soul. Our authentic Pad Thai features plump, sustainably-sourced shrimp stir-fried wit', '2025-07-08 09:17:22', '2025-07-08 09:17:36'),
('eda83eb5-d471-45a7-ad9e-339b89e68789', 'a598bb91-68eb-4b0f-9de3-174362f36f37', 'Pad Thai (Vegan)', 'ผัดไทเจ', 'A plant-based twist on the classic! Rice noodles tossed in tamarind-peanut sauce with tofu, bean sprouts, and roasted peanuts. Clean, comforting, and 100% vegan.', NULL, NULL, 'uploads/menus/menu_686ce2dd82279.jpg', NULL, '300.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, 'pad-thai-vegan', 'Proof that plant-based eating never means compromising on flavor. Our vegan Pad Thai stars golden tofu, chewy noodles, and a rich tamarind-peanut sauce. Crisp b', '2025-07-08 09:20:29', '2025-07-08 09:20:29'),
('f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '550e8400-e29b-41d4-a716-446655440005', 'Thai Basil (Chicken) + Rice', 'ข้าวผัดกะเพราไก่', 'Stir-fried chicken with Thai holy basil, garlic, and chili. Served with jasmine rice and a runny egg on request. High protein and full of heatâ€”without greasy takeout guilt.', NULL, NULL, 'uploads/menus/menu_686ce3c206655.jpg', NULL, '320.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, 'thai-basil-chicken-rice', NULL, '2025-07-08 09:24:18', '2025-07-08 09:24:18'),
('fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', 'ข้าวแกงเขียนหวาน', 'Tender chicken simmered in our housemade green curry paste and coconut milk. Packed with eggplant, sweet basil, and served with organic jasmine rice. Gluten-free and soul-warming.', NULL, NULL, 'uploads/menus/menu_686ce3e89830e.jpg', NULL, '380.00', 'Regular', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', 1, 0, 0, NULL, NULL, 'green-curry-chicken-rice', NULL, '2025-07-08 09:24:56', '2025-07-08 09:24:56');

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
  `channels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
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
  `health_goals` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `activity_level` enum('sedentary','lightly_active','moderately_active','very_active') COLLATE utf8mb4_unicode_ci DEFAULT 'moderately_active',
  `height_cm` decimal(5,2) DEFAULT NULL,
  `current_weight_kg` decimal(5,2) DEFAULT NULL,
  `target_weight_kg` decimal(5,2) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `medical_conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `medications` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
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

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `subscription_id`, `user_id`, `delivery_date`, `delivery_time_slot`, `delivery_address`, `delivery_instructions`, `status`, `kitchen_status`, `assigned_rider_id`, `pickup_time`, `delivery_confirmation_method`, `delivery_photo_url`, `delivery_signature_url`, `delivered_at`, `total_items`, `estimated_prep_time`, `special_notes`, `created_at`, `updated_at`) VALUES
('02a16cd6-099c-4818-8e4d-e6895cdce72e', 'ORD-20250714-3024', 'fbba995f-8221-4a6f-afe7-cc29afd8d26c', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-14', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'ready', 'not_started', '550e8400-e29b-41d4-a716-446655440004', NULL, 'customer_present', NULL, NULL, NULL, 7, 30, NULL, '2025-07-14 13:57:24', '2025-07-14 15:05:42'),
('0a33774f-2984-40bf-9cd7-b351cdbef0cd', 'ORD-20250719-1084', 'efac4af3-3a57-45b8-b41f-30bec6f79334', '550e8400-e29b-41d4-a716-446655440003', '2025-07-19', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('1ec5d931-ea52-4ee5-8913-0a98aa4ecd22', 'ORD-20250719-8724', '8daae380-9b24-43d5-86e8-13aa455b09d4', '550e8400-e29b-41d4-a716-446655440004', '2025-07-19', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('2050660d-9a71-42f5-8fce-b3abc870c853', 'ORD-20250719-9472', 'bf0b8b17-60dd-43b6-bc02-574a3b4da914', '550e8400-e29b-41d4-a716-446655440003', '2025-07-19', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('2a38ad80-7a6c-4e4c-b07a-831dddf56a8f', 'ORD-20250719-5439', '4f718c97-3c23-4cc2-9f99-cd485c9bab13', '550e8400-e29b-41d4-a716-446655440003', '2025-07-19', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('3052b2fb-fe2d-41d4-a6de-9bf4297541c4', 'ORD-20250719-8296', 'a586353a-4092-48ea-a663-e75479330d20', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-19', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 7, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('3b5c08dd-2874-4ed3-9f05-aa10e43b9a5e', 'ORD-20250720-8359', '36ecb782-ce8c-428a-b8a9-afd7e02bcfe8', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-20', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('40b40679-6554-480c-8552-b42bce3d2447', 'ORD-20250720-6013', 'a586353a-4092-48ea-a663-e75479330d20', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-20', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 7, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('481c2b4b-2207-49ef-8099-26bec81710ef', 'ORD-20250719-8579', 'ecaa5b9d-44dc-4564-b077-248692b8e812', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-19', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 7, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('4870950c-8cb7-45d6-b0c9-aee2fe4dfb43', 'ORD-20250714-6578', 'f4a3040e-2316-483a-8914-e0faceaaf58b', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-14', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('57ec03e3-0a2e-45bd-849c-8aeac1d6b4cb', 'ORD-20250719-7519', '97b9e7bd-e350-4d19-8ea5-022bcfdf74d0', '550e8400-e29b-41d4-a716-446655440003', '2025-07-19', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 5, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('659e76ad-f2cc-4509-a08d-91cad46845b6', 'ORD-20250719-2749', '5e4785f7-1825-400f-b096-daac3ac8a5a4', '550e8400-e29b-41d4-a716-446655440003', '2025-07-19', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('6e71641a-8991-4dcb-93ed-2b57de4ba521', 'ORD-20250720-1763', '1fa52d11-2f99-451e-8151-0914626b0d58', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-20', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('6eb0f522-2f3c-4561-b99c-35fcd0e72a91', 'ORD-20250719-9042', '1fa52d11-2f99-451e-8151-0914626b0d58', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-19', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('77ea423d-cf59-4005-924e-359b477a7d51', 'ORD-20250719-4920', 'e0f8eeba-f3d0-48bc-bb21-7bb716f6f145', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-19', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('7d7f366b-d5bc-4c3c-bb00-4dfb3b270b34', 'ORD-20250719-3420', '7a76610b-74ae-4312-8489-c37d065083f2', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-19', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 7, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('866505c7-66f2-4e88-93a5-5b99a1349577', 'ORD-20250714-3780', '1b8fa3b4-6c61-475d-bd90-47b11657b92e', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-14', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 7, 30, NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('97f0b028-4632-4868-8255-19446904717d', 'ORD-20250720-7402', 'ed9c0493-82b6-497f-af16-796a2d1b8c95', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-20', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('9f77113f-ee07-40fb-be8e-d34a8083aec1', 'ORD-20250720-3012', 'be8c3b24-114f-49e7-a816-e60e43899cde', '550e8400-e29b-41d4-a716-446655440003', '2025-07-20', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('c3ed7e1b-415d-4fd2-b4f0-db370be914d8', 'ORD-20250720-6047', 'ecaa5b9d-44dc-4564-b077-248692b8e812', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-20', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 7, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('d6c2493c-26b4-4c47-b382-a121f26deaf5', 'ORD-20250720-0923', '94e1d12d-a30f-4c99-b13d-dc801f7d26de', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-20', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('d81d805c-2f70-4fe2-8cc6-e1b7c5f8702b', 'ORD-20250719-6749', '36ecb782-ce8c-428a-b8a9-afd7e02bcfe8', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-19', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('dad53817-9ed3-41d3-9c97-4a1e84e037de', 'ORD-20250714-7854', '08088294-f964-4dad-9615-786155382e06', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-14', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('de0ce3d0-5ba4-11f0-96aa-e4b3d1018dc3', 'ORD-20250708-0791', '44c83ddd-b28c-447b-85cc-5eb8926e6061', '6c9391f3-0772-4393-a703-a035cc68d3d7', '2025-07-12', '12:00-15:00', '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei, Bangkok, 10110', 'Please call upon arrival at the lobby. Do not leave food with the security guard.', 'confirmed', 'in_progress', '550e8400-e29b-41d4-a716-446655440004', NULL, 'customer_present', NULL, NULL, NULL, 5, 30, 'Please call upon arrival at the lobby. Do not leave food with the security guard.', '2025-07-08 02:40:10', '2025-07-14 03:53:09'),
('de0d0ca2-5ba4-11f0-96aa-e4b3d1018dc3', 'ORD-20250708-3452', '90a1b331-9e41-4e9f-b757-cf9b91586387', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-12', '12:00-15:00', '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei, Bangkok, 10110', '', 'preparing', 'in_progress', NULL, NULL, 'customer_present', NULL, NULL, NULL, 8, 30, '', '2025-07-08 02:40:10', '2025-07-12 08:16:55'),
('de800bc9-9e1e-42eb-adb8-2fc5762200a8', 'ORD-20250719-2840', '0bf75805-f0ab-4207-a172-25912a12ecb4', '550e8400-e29b-41d4-a716-446655440003', '2025-07-19', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 7, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('e2fa2889-68b7-4fa0-a5a2-ab6851b1c044', 'ORD-20250719-8415', '94e1d12d-a30f-4c99-b13d-dc801f7d26de', '29e6fe85-124c-480f-bcf2-0d174721936f', '2025-07-19', NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '', 'confirmed', 'not_started', NULL, NULL, 'customer_present', NULL, NULL, NULL, 4, 30, NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16');

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
  `customizations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `special_requests` text COLLATE utf8mb4_unicode_ci,
  `calories_per_item` int(11) DEFAULT NULL,
  `total_calories` int(11) DEFAULT NULL,
  `item_status` enum('pending','preparing','ready','served') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `preparation_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_id`, `subscription_menu_id`, `menu_name`, `menu_price`, `quantity`, `customizations`, `special_requests`, `calories_per_item`, `total_calories`, `item_status`, `preparation_notes`, `created_at`, `updated_at`) VALUES
('0105523b-ffee-4a21-8755-960af1464bbf', '866505c7-66f2-4e88-93a5-5b99a1349577', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('0787f811-6772-4369-af62-c6204afacc26', '1ec5d931-ea52-4ee5-8913-0a98aa4ecd22', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('0a7e0a35-0d2e-44aa-8292-a88176031aeb', '57ec03e3-0a2e-45bd-849c-8aeac1d6b4cb', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('0d8a199a-2734-48e4-8e23-fa0516478ddb', '7d7f366b-d5bc-4c3c-bb00-4dfb3b270b34', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('0e68d07a-66d8-496d-8202-c2fbe39000bd', '866505c7-66f2-4e88-93a5-5b99a1349577', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', NULL, 'Thai Basil (Chicken) + Rice', '320.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('10076d9f-3415-43d8-8e31-78f3face6776', '3052b2fb-fe2d-41d4-a6de-9bf4297541c4', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('15da1f50-7591-461e-8cba-b99c5ca686d5', '481c2b4b-2207-49ef-8099-26bec81710ef', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', NULL, 'Chicken Satay', '200.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('173e3477-2ec5-4307-8ad7-bed8ce769c7f', '97f0b028-4632-4868-8255-19446904717d', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('183bb264-7fbd-44dc-8856-01b54cb224a4', 'd6c2493c-26b4-4c47-b382-a121f26deaf5', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', NULL, 'Thai Basil (Chicken) + Rice', '320.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('1878d85f-eeb7-4f7b-8bbf-8601ec4e057e', '2a38ad80-7a6c-4e4c-b07a-831dddf56a8f', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('19e8f9d8-0d83-4d95-a379-7553a88aa5bf', 'd81d805c-2f70-4fe2-8cc6-e1b7c5f8702b', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', NULL, 'Thai Basil (Chicken) + Rice', '320.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('1a44482e-19f8-4251-a26c-d36d343fa9e7', '3052b2fb-fe2d-41d4-a6de-9bf4297541c4', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', NULL, 'Tom Yum (Shrimp) Soup', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('1cc19f16-8167-409f-8615-bb597b60c410', '481c2b4b-2207-49ef-8099-26bec81710ef', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', NULL, 'Tom Yum (Shrimp) Soup', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('1fd92c41-e411-43d9-ae2c-007d8f0d4f98', 'c3ed7e1b-415d-4fd2-b4f0-db370be914d8', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('22bda8be-a55e-4c27-b0f1-fbc43fe8ca6c', '97f0b028-4632-4868-8255-19446904717d', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('27c29585-1490-41c3-b5fa-58f610cedc43', '866505c7-66f2-4e88-93a5-5b99a1349577', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', NULL, 'Tom Kha (Chicken) + Rice', '340.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('2a52dcae-a0b6-4a76-9a1d-dcdd0948c0ac', 'd81d805c-2f70-4fe2-8cc6-e1b7c5f8702b', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('2b84d911-6f15-4851-b40a-aa63013202b2', '2050660d-9a71-42f5-8fce-b3abc870c853', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('2e5d80e6-77aa-4b73-8377-0fc129c7b9ff', '2050660d-9a71-42f5-8fce-b3abc870c853', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('2ed41894-9f0b-45ec-bc68-3401e51bbe68', '02a16cd6-099c-4818-8e4d-e6895cdce72e', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('30366e3c-82e6-4ff2-89ec-bb7310f8b651', '2a38ad80-7a6c-4e4c-b07a-831dddf56a8f', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('31168393-e126-4a2a-9f85-7d5f16ba4dd0', '6eb0f522-2f3c-4561-b99c-35fcd0e72a91', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', NULL, 'Chicken Satay', '200.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('37179128-067b-46f3-baaa-e4fde45df5f1', '3b5c08dd-2874-4ed3-9f05-aa10e43b9a5e', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('3764f923-71bb-4572-84ad-3218fef8b0e2', '02a16cd6-099c-4818-8e4d-e6895cdce72e', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('37dd8426-247c-4d4b-8440-140244d45203', '6eb0f522-2f3c-4561-b99c-35fcd0e72a91', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('39e50d51-d4fd-4844-bbe1-f1063e74a2f0', 'c3ed7e1b-415d-4fd2-b4f0-db370be914d8', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', NULL, 'Thai Basil (Chicken) + Rice', '320.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('3d1184f2-862e-401d-8d4c-85fffc7cc310', 'e2fa2889-68b7-4fa0-a5a2-ab6851b1c044', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', NULL, 'Thai Basil (Chicken) + Rice', '320.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('3f05121d-c85e-41fe-8317-6fbf9ca79395', '6eb0f522-2f3c-4561-b99c-35fcd0e72a91', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', NULL, 'Tom Yum (Shrimp) Soup', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('436f6c41-b322-4b5d-98df-472e82284de8', '97f0b028-4632-4868-8255-19446904717d', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('45a506d8-449c-42f4-97cb-6f4408b575b2', '4870950c-8cb7-45d6-b0c9-aee2fe4dfb43', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('47813e91-4f22-4ace-8a31-7c05d0f05a02', '866505c7-66f2-4e88-93a5-5b99a1349577', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('4c38b365-fb98-4cf8-a93b-d08b8e0cf63d', '02a16cd6-099c-4818-8e4d-e6895cdce72e', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', NULL, 'Tom Kha (Chicken) + Rice', '340.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('4dc89853-40f4-45a6-bbff-d533f2abcade', '0a33774f-2984-40bf-9cd7-b351cdbef0cd', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('4e533698-1fc6-41f2-a339-5076b07f740e', 'd6c2493c-26b4-4c47-b382-a121f26deaf5', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('50fe7f5d-4c6b-483c-86e7-2f5622f7c8e5', '7d7f366b-d5bc-4c3c-bb00-4dfb3b270b34', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', NULL, 'Thai Basil (Chicken) + Rice', '320.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('5751629c-2d2e-44cd-a97c-a2b160a9ed47', '57ec03e3-0a2e-45bd-849c-8aeac1d6b4cb', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('5a488c4f-0f49-41ce-b292-325327803a98', '659e76ad-f2cc-4509-a08d-91cad46845b6', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('5af3616b-2532-40fe-95a0-b630bc239374', 'd81d805c-2f70-4fe2-8cc6-e1b7c5f8702b', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('5e5cf219-04af-4642-baae-67faba530e90', '3052b2fb-fe2d-41d4-a6de-9bf4297541c4', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('5fa546d3-39fb-4801-b1f3-addf5049dd4b', '57ec03e3-0a2e-45bd-849c-8aeac1d6b4cb', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('60625a47-4e72-4853-8413-80554ed3623d', '3052b2fb-fe2d-41d4-a6de-9bf4297541c4', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('64c7909a-2dd5-4a81-b7b3-c25aa11eaad0', 'de800bc9-9e1e-42eb-adb8-2fc5762200a8', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', NULL, 'Tom Kha (Chicken) + Rice', '340.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('67823180-e63e-4859-a003-b94b4b1405b8', 'd81d805c-2f70-4fe2-8cc6-e1b7c5f8702b', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('6a2b2aa2-5f43-4116-8742-dea6debfa997', '02a16cd6-099c-4818-8e4d-e6895cdce72e', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('6b2cca4f-a92c-45eb-83f2-259033fb2c46', '0a33774f-2984-40bf-9cd7-b351cdbef0cd', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('6d8679fd-0e5f-46c0-b78c-f638c4c3e124', '4870950c-8cb7-45d6-b0c9-aee2fe4dfb43', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', NULL, 'Thai Basil (Chicken) + Rice', '320.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('70a256c8-be29-49cb-b43b-4ebddce0a078', '6e71641a-8991-4dcb-93ed-2b57de4ba521', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', NULL, 'Tom Yum (Shrimp) Soup', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('71f54f56-d369-40d8-ba16-4f5f7b169959', '481c2b4b-2207-49ef-8099-26bec81710ef', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('72162cc1-c32c-47cd-bb70-623386c57794', '40b40679-6554-480c-8552-b42bce3d2447', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('73fff80a-301c-4d45-a1b6-f263cd1c66fd', '57ec03e3-0a2e-45bd-849c-8aeac1d6b4cb', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('744e4eb8-8f2b-4e78-b0c3-f79ff7cae8f8', '4870950c-8cb7-45d6-b0c9-aee2fe4dfb43', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('74fa81b5-6b62-4b38-9f87-ebccefd796dc', 'c3ed7e1b-415d-4fd2-b4f0-db370be914d8', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', NULL, 'Tom Yum (Shrimp) Soup', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('75d867a8-32a6-4288-940d-e1ac3269316c', '6eb0f522-2f3c-4561-b99c-35fcd0e72a91', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', NULL, 'Tom Kha (Chicken) + Rice', '340.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('76b6c031-cb4d-43c4-a72d-3806e4551500', 'de800bc9-9e1e-42eb-adb8-2fc5762200a8', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('779d1fe5-f708-4f97-b7f9-4ac1f4dd5cd1', '659e76ad-f2cc-4509-a08d-91cad46845b6', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('77b18eec-e9e5-456c-9f9c-b4de93f3574f', '3b5c08dd-2874-4ed3-9f05-aa10e43b9a5e', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('7b313dfa-dc55-4f38-a213-55f9ec8699a7', 'dad53817-9ed3-41d3-9c97-4a1e84e037de', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('7df3deb3-50fd-45a9-b9f5-a47da16d1ca5', 'e2fa2889-68b7-4fa0-a5a2-ab6851b1c044', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('894c56dc-7e59-4ad4-88a6-a974b3b78c63', '6e71641a-8991-4dcb-93ed-2b57de4ba521', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', NULL, 'Tom Kha (Chicken) + Rice', '340.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('898b36be-8e7e-4d52-9632-92c51ad06481', '1ec5d931-ea52-4ee5-8913-0a98aa4ecd22', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('8fe5789f-3461-45f8-b57d-a56df53dcdcf', '6e71641a-8991-4dcb-93ed-2b57de4ba521', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', NULL, 'Chicken Satay', '200.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('90b805e4-e2fd-42bc-aa6e-74aa4ac9fa8f', '9f77113f-ee07-40fb-be8e-d34a8083aec1', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('930e69f2-dd5b-4207-a0a1-71343e957115', '97f0b028-4632-4868-8255-19446904717d', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('956c638c-2dda-4b93-be00-4103cf56aac6', '02a16cd6-099c-4818-8e4d-e6895cdce72e', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', NULL, 'Thai Basil (Chicken) + Rice', '320.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('98d98c28-c2ba-4a50-8b8b-262d26c1eb41', '659e76ad-f2cc-4509-a08d-91cad46845b6', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('9ac1af9c-0a5f-47f3-be05-2b3f23f456c9', '866505c7-66f2-4e88-93a5-5b99a1349577', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('9ec54af9-17e6-4ec3-b1dc-06ec3ae16b13', '77ea423d-cf59-4005-924e-359b477a7d51', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('a0f5a089-a6a7-46ec-a836-ec0d8a896276', '866505c7-66f2-4e88-93a5-5b99a1349577', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('a2004db5-e354-4505-9197-cf593187800d', '659e76ad-f2cc-4509-a08d-91cad46845b6', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('a3733f04-7c8e-4d4a-8d69-5d10010bfaef', '7d7f366b-d5bc-4c3c-bb00-4dfb3b270b34', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('aac347ed-3322-4d40-ae27-a4bd8c9ecbc6', '40b40679-6554-480c-8552-b42bce3d2447', 'eda83eb5-d471-45a7-ad9e-339b89e68789', NULL, 'Pad Thai (Vegan)', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('ab86ec36-8733-4165-aa43-18a7fb6ce983', '481c2b4b-2207-49ef-8099-26bec81710ef', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', NULL, 'Thai Basil (Chicken) + Rice', '320.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('ac4b26ae-66f8-4a80-a8a4-37d0c85aa9ad', '481c2b4b-2207-49ef-8099-26bec81710ef', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('ae43c67d-cc2f-4ac0-be94-d55e5ea7a241', '3052b2fb-fe2d-41d4-a6de-9bf4297541c4', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', NULL, 'Chicken Satay', '200.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('b074eccb-99ad-449a-bd7d-3309747c11e8', '7d7f366b-d5bc-4c3c-bb00-4dfb3b270b34', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('b3189066-56a0-48c3-b122-3d86f0784136', '40b40679-6554-480c-8552-b42bce3d2447', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('b38b207e-c440-4107-8bae-2ae378d3bc30', 'd6c2493c-26b4-4c47-b382-a121f26deaf5', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('b4961a10-167f-44d1-b19d-00339da1be99', '2a38ad80-7a6c-4e4c-b07a-831dddf56a8f', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('b6141a9a-9167-4bd1-8bf1-d75743c4a42a', 'c3ed7e1b-415d-4fd2-b4f0-db370be914d8', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('b660d68f-dfbd-43af-a0c7-f499dbed3b40', '9f77113f-ee07-40fb-be8e-d34a8083aec1', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('b7aa0ccb-43a3-400a-9d88-774e0aa91cc4', 'de800bc9-9e1e-42eb-adb8-2fc5762200a8', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', NULL, 'Thai Basil (Chicken) + Rice', '320.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('b81840e7-2ede-42a2-b0b5-b9d3d50303d8', 'c3ed7e1b-415d-4fd2-b4f0-db370be914d8', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('b9b26929-b507-4f7c-bbfc-2b8ae0c1a973', '57ec03e3-0a2e-45bd-849c-8aeac1d6b4cb', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('bab98a8b-c873-4f55-8ab6-720e678da00c', '4870950c-8cb7-45d6-b0c9-aee2fe4dfb43', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('bc4d9805-d676-41ee-91f4-ddd5d788709c', 'dad53817-9ed3-41d3-9c97-4a1e84e037de', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('bcdc7423-565b-4ae9-8540-75b7e0f1dd27', '2050660d-9a71-42f5-8fce-b3abc870c853', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('bdfe2230-2646-4c87-b96f-39991572ae32', '3052b2fb-fe2d-41d4-a6de-9bf4297541c4', 'eda83eb5-d471-45a7-ad9e-339b89e68789', NULL, 'Pad Thai (Vegan)', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('bf04c689-9020-4ff4-af92-20d0c5d99cd0', '2050660d-9a71-42f5-8fce-b3abc870c853', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('bf22a7c5-ad08-4737-88cb-23428547ca8d', '40b40679-6554-480c-8552-b42bce3d2447', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('c166ad62-278a-48f9-9276-074d98af2980', 'de800bc9-9e1e-42eb-adb8-2fc5762200a8', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('c38062e9-19b8-4401-96cb-0a44efd2ddd7', 'de800bc9-9e1e-42eb-adb8-2fc5762200a8', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('c3cc3db1-7b70-47e4-b683-9282c453fd54', '7d7f366b-d5bc-4c3c-bb00-4dfb3b270b34', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', NULL, 'Tom Kha (Chicken) + Rice', '340.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('c5d4e479-8a66-4f71-8e1b-e0decaa3e549', '3052b2fb-fe2d-41d4-a6de-9bf4297541c4', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('ca87a227-7f6f-49da-b419-30fb508f44f4', '40b40679-6554-480c-8552-b42bce3d2447', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('cc416d48-74d7-4e14-92fc-2839c4b4db55', '77ea423d-cf59-4005-924e-359b477a7d51', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', NULL, 'Thai Basil (Chicken) + Rice', '320.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('cc5c88f2-a773-48cd-9052-7f553490207a', 'd6c2493c-26b4-4c47-b382-a121f26deaf5', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('d01ee194-4291-45db-844c-35fd0f29afcb', 'de800bc9-9e1e-42eb-adb8-2fc5762200a8', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('d1395bec-b59d-443d-9ddd-ac636016f137', '7d7f366b-d5bc-4c3c-bb00-4dfb3b270b34', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('d19bd88c-7321-47d1-9886-9ebd98e9062f', '3b5c08dd-2874-4ed3-9f05-aa10e43b9a5e', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', NULL, 'Thai Basil (Chicken) + Rice', '320.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('d5ad34b6-3aea-45c4-9370-2bb97a88d363', '02a16cd6-099c-4818-8e4d-e6895cdce72e', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('d5ea5b0f-7b04-41c6-86e2-fb5a15341f04', '0a33774f-2984-40bf-9cd7-b351cdbef0cd', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('da8df7c8-377d-484b-959c-39559269dd86', '77ea423d-cf59-4005-924e-359b477a7d51', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('dbb0156f-fd8d-4d74-9bf8-21c3d93ee57e', '9f77113f-ee07-40fb-be8e-d34a8083aec1', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('dbb27292-7c54-4ff9-83bc-36b877391baf', '77ea423d-cf59-4005-924e-359b477a7d51', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('dd66bbfc-7ecb-458d-b9e4-ab2ed11ef796', '9f77113f-ee07-40fb-be8e-d34a8083aec1', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('ddd47ef8-467c-4c92-bc8e-366570868646', '02a16cd6-099c-4818-8e4d-e6895cdce72e', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('e1262398-fd3b-41da-86c0-f2cebac090e6', 'e2fa2889-68b7-4fa0-a5a2-ab6851b1c044', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('e3ac8cb3-0ccb-45ba-974f-3988ed8b39fe', 'de800bc9-9e1e-42eb-adb8-2fc5762200a8', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', NULL, 'Chicken Satay', '200.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('e62822e5-432d-4f61-a9eb-7fd4aed12ee6', '2a38ad80-7a6c-4e4c-b07a-831dddf56a8f', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('e80ba1f6-84b4-4458-b0b0-42a0cb175ed2', 'dad53817-9ed3-41d3-9c97-4a1e84e037de', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('e83b2709-1d15-494f-9b7f-c11d15a4bf9b', '866505c7-66f2-4e88-93a5-5b99a1349577', '275059d8-4497-43ec-b5e3-669fb18981d9', NULL, 'Cashew Chicken + Rice', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('e8dd2b70-9bee-46d0-8c9c-79ee81e1b14a', '40b40679-6554-480c-8552-b42bce3d2447', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', NULL, 'Tom Yum (Shrimp) Soup', '400.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('e8e96ef3-a716-4921-8e9f-2f8d560090eb', '7d7f366b-d5bc-4c3c-bb00-4dfb3b270b34', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('e8ef196a-2659-4b06-96cd-b099c1f354e7', '3b5c08dd-2874-4ed3-9f05-aa10e43b9a5e', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('ec1351cd-8781-451d-8e5c-fb6d3084c610', '481c2b4b-2207-49ef-8099-26bec81710ef', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('ec2f90b4-a3fe-4e75-a454-6417e281499d', '481c2b4b-2207-49ef-8099-26bec81710ef', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('edcc42fe-c234-4fb7-8784-06c4210b4e1a', '1ec5d931-ea52-4ee5-8913-0a98aa4ecd22', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('ef052f1e-b388-4d4e-8814-d182ada4b88d', '6e71641a-8991-4dcb-93ed-2b57de4ba521', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', NULL, 'Pad Thai (Shrimp)', '350.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('f35c3ed1-0c21-4e95-8c6b-34889f5795f6', '1ec5d931-ea52-4ee5-8913-0a98aa4ecd22', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('f58cfe36-8bb3-4127-8622-d8bad05b7911', 'e2fa2889-68b7-4fa0-a5a2-ab6851b1c044', '9429bc0e-f065-4ea8-9481-1bcb887e8454', NULL, 'Beef Crying Tiger + Sticky Rice', '300.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('f5bededa-1b27-41f7-ae80-bc6504175086', 'dad53817-9ed3-41d3-9c97-4a1e84e037de', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-14 13:57:24', '2025-07-14 13:57:24'),
('f5efb0ef-26ce-4b19-9788-3a334d6bc14c', '0a33774f-2984-40bf-9cd7-b351cdbef0cd', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', NULL, 'Green Curry (Chicken) + Rice', '380.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 03:23:35', '2025-07-15 03:23:35'),
('f6eb484a-c761-420d-a51a-94f5269b1ad6', 'c3ed7e1b-415d-4fd2-b4f0-db370be914d8', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', NULL, 'Chicken Satay', '200.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('fd7fe82c-7dd4-44c7-afc1-a88b8640733d', '40b40679-6554-480c-8552-b42bce3d2447', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', NULL, 'Chicken Satay', '200.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16'),
('fdd03a68-f3cf-49ce-a6df-47195a40129c', 'c3ed7e1b-415d-4fd2-b4f0-db370be914d8', 'd76f0a21-6b41-490a-8dba-249481978176', NULL, 'Vegan Larb (Tofu)', '399.00', 1, NULL, NULL, NULL, NULL, 'pending', NULL, '2025-07-15 02:32:16', '2025-07-15 02:32:16');

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
('0617d6c8-1f31-4cde-a896-aa72a3a4dd31', 'd5f927b4-1a43-4fa9-a859-f0fe06b6d5ca', '29e6fe85-124c-480f-bcf2-0d174721936f', 'credit_card', NULL, 'TXN-20250715-155248-43facb', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-15 15:52:48', '2025-07-26', '2025-08-02', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-15 15:52:48', '2025-07-15 15:52:48'),
('10012052-b169-490e-95dd-7bb679b1a2de', '002a5635-19c2-496c-97f6-7d9b867d9ceb', '29e6fe85-124c-480f-bcf2-0d174721936f', 'bank_transfer', NULL, 'TXN-20250713-123959-002a56', NULL, '399.00', 'USD', '0.00', '399.00', 'completed', '2025-07-13 12:39:59', '2025-07-14', '2025-07-21', 'Subscription แพ็กเกจ 5 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-13 12:39:59', '2025-07-13 12:39:59'),
('123d681a-4b73-4513-a0a0-8666168bd1f8', '0463a886-ab5b-4447-ad7e-f42ee0c70433', '29e6fe85-124c-480f-bcf2-0d174721936f', 'paypal', NULL, 'TXN-20250715-113333-38efa7', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-15 11:33:33', '2025-07-20', '2025-07-27', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-15 11:33:33', '2025-07-15 11:33:33'),
('1be75df4-9f51-4560-b3d3-94f8153f38cd', 'f8234164-aa26-469f-82d1-8d91f50a4407', '550e8400-e29b-41d4-a716-446655440003', 'google_pay', NULL, 'TXN-20250715-033516-b685e5', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-15 03:35:16', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-15 03:35:16', '2025-07-15 03:35:16'),
('209edee0-f7d8-4f13-85cd-1adf0d46f6e1', 'db29bea4-1c9a-4124-8242-e1f5500a606a', '29e6fe85-124c-480f-bcf2-0d174721936f', 'paypal', NULL, 'TXN-20250710-082750-db29be', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-10 08:27:50', '2025-07-11', '2025-07-18', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-10 08:27:50', '2025-07-10 08:27:50'),
('2e07023a-18e1-4051-8242-f0eb6c6e62d0', '36ecb782-ce8c-428a-b8a9-afd7e02bcfe8', '29e6fe85-124c-480f-bcf2-0d174721936f', 'paypal', NULL, 'TXN-20250714-014225-36ecb7', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-14 01:42:25', '2025-07-15', '2025-07-22', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 01:42:25', '2025-07-14 01:42:25'),
('32464d4f-bb56-447a-9c72-2c4fe971ab40', '460ab3f2-889e-4a16-80a6-4e951cb7d3c6', '29e6fe85-124c-480f-bcf2-0d174721936f', 'credit_card', NULL, 'TXN-20250714-070134-5304df', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-14 07:01:34', '2025-07-27', '2025-08-03', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 07:01:34', '2025-07-14 07:01:34'),
('33eba8ff-5558-467a-9ba7-eb004352dd27', 'bf0b8b17-60dd-43b6-bc02-574a3b4da914', '550e8400-e29b-41d4-a716-446655440003', 'google_pay', NULL, 'TXN-20250715-030522-c24b5e', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-15 03:05:22', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-15 03:05:22', '2025-07-15 03:05:22'),
('3cedc8de-0331-4a92-80a5-ee5c9eacd3c5', '94e1d12d-a30f-4c99-b13d-dc801f7d26de', '29e6fe85-124c-480f-bcf2-0d174721936f', 'apple_pay', NULL, 'TXN-20250714-014151-94e1d1', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-14 01:41:52', '2025-07-15', '2025-07-22', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 01:41:52', '2025-07-14 01:41:52'),
('42d58d62-5e86-4272-9413-1040e5357fdc', '44c83ddd-b28c-447b-85cc-5eb8926e6061', '6c9391f3-0772-4393-a703-a035cc68d3d7', 'bank_transfer', NULL, 'TXN-20250707-093846-44c83d', NULL, '499.00', 'THB', '0.00', '499.00', 'completed', '2025-07-07 09:38:46', '2025-07-08', '2025-07-15', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-07 09:38:46', '2025-07-07 09:38:46'),
('47670778-8c5b-4db0-a8fc-e2e4eca350e1', 'c539a7cc-b04f-4a5c-ba64-944f52feded2', '29e6fe85-124c-480f-bcf2-0d174721936f', 'paypal', NULL, 'TXN-20250710-094918-c539a7', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-10 09:49:18', '2025-07-11', '2025-07-18', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-10 09:49:18', '2025-07-10 09:49:18'),
('4a983b6d-fe57-4151-916c-ef0e9eeb1eed', '1fa52d11-2f99-451e-8151-0914626b0d58', '29e6fe85-124c-480f-bcf2-0d174721936f', 'google_pay', NULL, 'TXN-20250714-021322-1fa52d', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-14 02:13:22', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 02:13:22', '2025-07-14 02:13:22'),
('5024f19e-ba07-4fcf-8bf3-8298b46b723b', '5249821f-18f8-4fd4-85d7-91f2546d7089', '29e6fe85-124c-480f-bcf2-0d174721936f', 'credit_card', NULL, 'TXN-20250710-081616-524982', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-10 08:16:16', '2025-07-11', '2025-07-18', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-10 08:16:16', '2025-07-10 08:16:16'),
('5ba3641c-1e19-4a62-b5c3-9db6b83076ac', 'efac4af3-3a57-45b8-b41f-30bec6f79334', '550e8400-e29b-41d4-a716-446655440003', 'google_pay', NULL, 'TXN-20250715-030050-b028f9', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-15 03:00:50', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-15 03:00:50', '2025-07-15 03:00:50'),
('5fa8bddf-0ff5-4743-a929-11647642fbc5', 'a586353a-4092-48ea-a663-e75479330d20', '29e6fe85-124c-480f-bcf2-0d174721936f', 'credit_card', NULL, 'TXN-20250714-014432-a58635', NULL, '599.00', 'USD', '0.00', '599.00', 'completed', '2025-07-14 01:44:32', '2025-07-15', '2025-07-22', 'Subscription แพ็กเกจ 7 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('64d50980-7ac1-4772-9c9f-92e3d16696be', '9aea8db1-bb47-4143-9b5d-b91d0e377a6c', '550e8400-e29b-41d4-a716-446655440004', 'apple_pay', NULL, 'TXN-20250715-043230-73e107', NULL, '399.00', 'USD', '0.00', '399.00', 'completed', '2025-07-15 04:32:30', '2025-07-27', '2025-08-03', 'Subscription แพ็กเกจ 5 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-15 04:32:30', '2025-07-15 04:32:30'),
('71bbf323-942f-4173-ac96-9c543c5db82c', '4f718c97-3c23-4cc2-9f99-cd485c9bab13', '550e8400-e29b-41d4-a716-446655440003', 'credit_card', NULL, 'TXN-20250715-022206-e4dc31', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-15 02:22:06', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-15 02:22:06', '2025-07-15 02:22:06'),
('7470f924-26f7-4d1c-9619-6447ce8b3cc3', '0bf75805-f0ab-4207-a172-25912a12ecb4', '550e8400-e29b-41d4-a716-446655440003', 'apple_pay', NULL, 'TXN-20250714-021649-0bf758', NULL, '599.00', 'USD', '0.00', '599.00', 'completed', '2025-07-14 02:16:49', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 7 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 02:16:49', '2025-07-14 02:16:49'),
('7aaad2f2-4f36-4813-991a-baf441230955', 'aa0d4793-daaf-4af7-a63a-b19993ea67f8', 'aa526847-33b7-4113-b8db-9cf03c97a656', 'credit_card', NULL, 'TXN-20250708-093111-aa0d47', NULL, '800.00', 'THB', '0.00', '800.00', 'completed', '2025-07-08 07:31:11', '2025-07-09', '2025-07-16', 'Subscription แพ็คเกจรายสัปดาห์', NULL, '0.00', NULL, NULL, '2025-07-08 07:31:11', '2025-07-08 07:31:11'),
('80247401-5e51-4580-8809-f28d7a9a4ae2', 'e0f8eeba-f3d0-48bc-bb21-7bb716f6f145', '29e6fe85-124c-480f-bcf2-0d174721936f', 'paypal', NULL, 'TXN-20250714-065933-6c7ee9', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-14 06:59:33', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 06:59:33', '2025-07-14 06:59:33'),
('80310c73-8326-45f5-ad98-4c1604bc33ad', '97b9e7bd-e350-4d19-8ea5-022bcfdf74d0', '550e8400-e29b-41d4-a716-446655440003', 'apple_pay', NULL, 'TXN-20250715-020932-8b252e', NULL, '399.00', 'USD', '0.00', '399.00', 'completed', '2025-07-15 02:09:32', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 5 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-15 02:09:32', '2025-07-15 02:09:32'),
('81834752-48ec-438c-82a1-dbe90819e541', '46def7f0-dd69-4e62-af4d-da389cfbc6bb', '29e6fe85-124c-480f-bcf2-0d174721936f', 'bank_transfer', NULL, 'TXN-20250714-020714-46def7', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-14 02:07:14', '2025-07-26', '2025-08-02', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 02:07:14', '2025-07-14 02:07:14'),
('8bc32412-f04a-4e07-ae19-6319f64dc0f1', '28e75278-75c2-448d-bef9-d6c2886ec531', '550e8400-e29b-41d4-a716-446655440001', 'apple_pay', 'Apple Pay', 'APL-20250703-001', 'apple_6866925a0d921', '1200.00', 'THB', '24.00', '1176.00', 'completed', '2025-07-01 07:23:22', '2025-07-03', '2025-08-03', 'Monthly Subscription - Healthy Thai Meals', NULL, '0.00', NULL, NULL, '2025-07-03 14:23:22', '2025-07-03 14:23:22'),
('8cbc5ad1-1ac5-402b-b8d1-5aa960e8d7fc', '28e75278-75c2-448d-bef9-d6c2886ec531', '550e8400-e29b-41d4-a716-446655440001', 'credit_card', 'Stripe', 'CC-20250703-003', 'stripe_6866925a0da5a', '1500.00', 'THB', '45.00', '1455.00', 'failed', NULL, '2025-07-03', '2025-08-03', 'Premium Monthly Plan', 'Insufficient funds', '0.00', NULL, NULL, '2025-07-03 14:23:22', '2025-07-03 14:23:22'),
('8e9d775e-dbee-4fa5-b6ed-05e6a9548c7c', '8daae380-9b24-43d5-86e8-13aa455b09d4', '550e8400-e29b-41d4-a716-446655440004', 'credit_card', NULL, 'TXN-20250714-023401-8daae3', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-14 02:34:01', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 02:34:01', '2025-07-14 02:34:01'),
('8efad5c6-b2b4-4b97-b608-80aa8f640219', 'ecaa5b9d-44dc-4564-b077-248692b8e812', '29e6fe85-124c-480f-bcf2-0d174721936f', 'paypal', NULL, 'TXN-20250714-014710-ecaa5b', NULL, '599.00', 'USD', '0.00', '599.00', 'completed', '2025-07-14 01:47:10', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 7 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('92946896-8e82-4325-8b8c-dba577beef70', 'd6831992-8062-4cbe-b90a-b5c7f2fd3ffb', '550e8400-e29b-41d4-a716-446655440003', 'paypal', NULL, 'TXN-20250714-021601-d68319', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-14 02:16:01', '2025-08-02', '2025-08-09', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 02:16:01', '2025-07-14 02:16:01'),
('9603bf18-49ca-4e2c-8bfc-08f121f8ef0b', '350663c4-d1b2-42f2-ab87-ed36aab9e7d0', '29e6fe85-124c-480f-bcf2-0d174721936f', 'apple_pay', NULL, 'TXN-20250715-111308-1c637e', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-15 11:13:08', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-15 11:13:08', '2025-07-15 11:13:08'),
('99b4f162-351f-4918-afc2-ab2ba0c65589', '09d2a30f-8ae6-4956-8b6a-400bc4d2a7eb', '29e6fe85-124c-480f-bcf2-0d174721936f', 'credit_card', NULL, 'TXN-20250716-070400-b8fea2', NULL, '599.00', 'USD', '0.00', '599.00', 'completed', '2025-07-16 07:04:00', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 7 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-16 07:04:00', '2025-07-16 07:04:00'),
('9ae17f36-8951-47d8-870b-932b8bb3cdf7', 'e684d621-c7e6-4eed-a8a5-38abc0d72366', '29e6fe85-124c-480f-bcf2-0d174721936f', 'credit_card', NULL, 'TXN-20250716-070433-8e24f9', NULL, '399.00', 'USD', '0.00', '399.00', 'completed', '2025-07-16 07:04:33', '2025-08-03', '2025-08-10', 'Subscription แพ็กเกจ 5 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-16 07:04:33', '2025-07-16 07:04:33'),
('9afd51a1-f088-49a4-a233-e98937a0107f', 'febed4c8-d71c-4b57-bc75-4a8276a00e2c', '29e6fe85-124c-480f-bcf2-0d174721936f', 'bank_transfer', NULL, 'TXN-20250717-031344-cc219b', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-17 03:13:44', '2025-08-10', '2025-08-17', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-17 03:13:44', '2025-07-17 03:13:44'),
('9c28d736-7d25-48a5-b37c-ba00dfb2e4ae', '1b8fa3b4-6c61-475d-bd90-47b11657b92e', '29e6fe85-124c-480f-bcf2-0d174721936f', 'apple_pay', NULL, 'TXN-20250713-123611-1b8fa3', NULL, '599.00', 'USD', '0.00', '599.00', 'completed', '2025-07-13 12:36:11', '2025-07-14', '2025-07-21', 'Subscription แพ็กเกจ 7 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-13 12:36:11', '2025-07-13 12:36:11'),
('a0295854-6e09-4b39-9e99-bcf4f07a0de4', '9a5ba398-ddd5-4c07-8016-145eb42618e8', '29e6fe85-124c-480f-bcf2-0d174721936f', 'paypal', NULL, 'TXN-20250710-095032-9a5ba3', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-10 09:50:32', '2025-07-11', '2025-07-18', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-10 09:50:32', '2025-07-10 09:50:32'),
('a3f6fb1d-807c-4595-9928-646c034e7291', '6f580044-7bbc-4e2a-a171-653b9b4df57c', 'aa526847-33b7-4113-b8db-9cf03c97a656', 'credit_card', NULL, 'TXN-20250708-093041-6f5800', NULL, '499.00', 'THB', '0.00', '499.00', 'completed', '2025-07-08 07:30:41', '2025-07-09', '2025-07-16', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-08 07:30:41', '2025-07-08 07:30:41'),
('ad789510-1eb7-4c8b-bbf7-aeae6baafd8c', '92e80649-9c4a-4649-aac1-f559b4861834', '29e6fe85-124c-480f-bcf2-0d174721936f', 'credit_card', NULL, 'TXN-20250714043900-92e806', NULL, '399.00', 'USD', '0.00', '399.00', 'completed', '2025-07-14 04:39:00', '2025-07-15', '2025-07-22', 'Subscription แพ็กเกจ 5 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 04:39:00', '2025-07-14 04:39:00'),
('adfb18aa-5896-4d79-9740-bd2f58f9350d', '476b9536-ab53-42a7-98c9-e224d368f12d', '29e6fe85-124c-480f-bcf2-0d174721936f', 'apple_pay', NULL, 'TXN-20250714-151311-476b95', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-14 15:13:11', '2025-07-15', '2025-07-22', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 15:13:11', '2025-07-14 16:32:52'),
('b3ad486c-46c3-449c-8778-45b53fe638ef', '676a5c1a-39de-4aac-bc0e-4a5729c0a022', '550e8400-e29b-41d4-a716-446655440001', 'google_pay', 'Google Pay', 'GPY-20250703-002', 'google_6866925a0da42', '800.00', 'THB', '16.00', '784.00', 'pending', NULL, '2025-07-03', '2025-07-10', 'Weekly Subscription - 3 Meals', NULL, '0.00', NULL, NULL, '2025-07-03 14:23:22', '2025-07-03 14:23:22'),
('b3ca8c46-6e94-4646-adee-c2a01afaf18d', 'fbba995f-8221-4a6f-afe7-cc29afd8d26c', '29e6fe85-124c-480f-bcf2-0d174721936f', 'paypal', NULL, 'TXN-20250713-131102-fbba99', NULL, '599.00', 'USD', '0.00', '599.00', 'completed', '2025-07-13 13:11:02', '2025-07-14', '2025-07-21', 'Subscription แพ็กเกจ 7 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-13 13:11:02', '2025-07-13 13:11:02'),
('b570a44f-9ee6-43e2-8352-f4fd78dad442', '7a76610b-74ae-4312-8489-c37d065083f2', '29e6fe85-124c-480f-bcf2-0d174721936f', 'paypal', NULL, 'TXN-20250714-070652-5b9831', NULL, '599.00', 'USD', '0.00', '599.00', 'completed', '2025-07-14 07:06:52', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 7 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 07:06:52', '2025-07-14 07:06:52'),
('b771e64d-cd13-41d6-a156-a3c989702077', 'f4a3040e-2316-483a-8914-e0faceaaf58b', '29e6fe85-124c-480f-bcf2-0d174721936f', 'bank_transfer', NULL, 'TXN-20250713-125555-f4a304', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-13 12:55:55', '2025-07-14', '2025-07-21', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-13 12:55:55', '2025-07-13 12:55:55'),
('b9cb3bc0-e41e-49e8-9405-2ada761c1c11', '08088294-f964-4dad-9615-786155382e06', '29e6fe85-124c-480f-bcf2-0d174721936f', 'google_pay', NULL, 'TXN-20250713-125510-080882', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-13 12:55:10', '2025-07-14', '2025-07-21', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-13 12:55:10', '2025-07-13 12:55:10'),
('bbcafb25-49d2-4fad-aa95-1ccab77cd7f1', 'e2f94945-f45f-4f01-aff1-4fb48c593266', 'aa526847-33b7-4113-b8db-9cf03c97a656', 'credit_card', NULL, 'TXN-20250708-113827-e2f949', NULL, '800.00', 'THB', '0.00', '800.00', 'completed', '2025-07-08 09:38:27', '2025-07-09', '2025-07-16', 'Subscription แพ็คเกจรายสัปดาห์', NULL, '0.00', NULL, NULL, '2025-07-08 09:38:27', '2025-07-08 09:38:27'),
('bdb23dfd-1916-4339-89f3-9989beda1b72', '676a5c1a-39de-4aac-bc0e-4a5729c0a022', '550e8400-e29b-41d4-a716-446655440001', 'paypal', 'PayPal', 'PPL-20250703-004', 'paypal_6866925a0da70', '600.00', 'THB', '18.00', '582.00', 'refunded', '2025-06-28 07:23:22', '2025-06-26', '2025-07-03', 'Weekly Trial Plan', NULL, '600.00', 'Customer requested cancellation', '2025-07-02 07:23:22', '2025-07-03 14:23:22', '2025-07-03 14:23:22'),
('bf9bd8b5-d14a-4555-a74b-3e9575c0babc', '90a1b331-9e41-4e9f-b757-cf9b91586387', '29e6fe85-124c-480f-bcf2-0d174721936f', 'credit_card', NULL, 'TXN-20250707-094726-90a1b3', NULL, '899.00', 'THB', '0.00', '899.00', 'completed', '2025-07-07 09:47:26', '2025-07-08', '2025-07-15', 'Subscription แพ็กเกจ 8 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-07 09:47:26', '2025-07-07 09:47:26'),
('c2b441e2-000c-4781-9488-3a26213beddf', '35cf6ed8-05d1-4da6-b069-72c2dfb665a4', '550e8400-e29b-41d4-a716-446655440003', 'apple_pay', NULL, 'TXN-20250715-030402-3ed721', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-15 03:04:02', '2025-08-10', '2025-08-17', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-15 03:04:02', '2025-07-15 03:04:02'),
('c97700cc-70f3-47d4-8a8a-e17a7d06db25', '5e4785f7-1825-400f-b096-daac3ac8a5a4', '550e8400-e29b-41d4-a716-446655440003', 'credit_card', NULL, 'TXN-20250715-030442-b20afe', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-15 03:04:42', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-15 03:04:42', '2025-07-15 03:04:42'),
('cbaf96be-7fff-4fac-821c-f7ed5e0f59b2', 'bc11f476-4fe9-44b1-a4bb-8d5f51ca1d20', '29e6fe85-124c-480f-bcf2-0d174721936f', 'paypal', NULL, 'TXN-20250712-092349-bc11f4', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-12 09:23:49', '2025-07-13', '2025-07-20', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-12 09:23:49', '2025-07-12 09:23:49'),
('d4cdeaaf-1825-4e74-bb9e-c8b91ecddf68', 'fc4a97dc-71e1-4a4a-b7ab-3f8b8b40c6ed', '29e6fe85-124c-480f-bcf2-0d174721936f', 'paypal', NULL, 'TXN-20250714-110237-d1ab54', NULL, '399.00', 'USD', '0.00', '399.00', 'failed', '2025-07-14 11:02:37', '2025-07-27', '2025-08-03', 'Subscription แพ็กเกจ 5 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 11:02:37', '2025-07-14 11:51:14'),
('d6800c82-7c1c-4f74-9a01-9114b56a6969', 'e3591792-336a-4ca7-8377-b5cdad5628c8', '29e6fe85-124c-480f-bcf2-0d174721936f', 'paypal', NULL, 'TXN-20250710-075706-e35917', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-10 07:57:06', '2025-07-11', '2025-07-18', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-10 07:57:06', '2025-07-10 07:57:06'),
('d6b94d49-dcc1-4cad-9a8b-ec2ca9034cf3', 'ed9c0493-82b6-497f-af16-796a2d1b8c95', '29e6fe85-124c-480f-bcf2-0d174721936f', 'paypal', NULL, 'TXN-20250714-045603-ed9c04', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-14 04:56:03', '2025-07-20', '2025-07-27', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 04:56:03', '2025-07-14 04:56:03'),
('ddb17a17-880c-46b5-bb2e-e3afc6e3b48b', '766168a3-f202-449e-a16a-44e8cdd19010', '29e6fe85-124c-480f-bcf2-0d174721936f', 'apple_pay', NULL, 'TXN-20250714-150835-766168', NULL, '399.00', 'USD', '0.00', '399.00', 'completed', '2025-07-14 15:08:35', '2025-07-15', '2025-07-22', 'Subscription แพ็กเกจ 5 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 15:08:35', '2025-07-14 15:08:35'),
('df0b32dc-a76a-4917-9571-8d617cac1c77', '476a2565-e455-41a2-bdc8-c0e7e4534761', '29e6fe85-124c-480f-bcf2-0d174721936f', 'apple_pay', NULL, 'TXN-20250715-034952-9e64b3', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-15 03:49:52', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-15 03:49:52', '2025-07-15 03:49:52'),
('dfb239af-9ad8-4626-b4d5-7413e889ffb7', '7927a4a2-30c9-44ae-a2ea-3294cc64d60a', '550e8400-e29b-41d4-a716-446655440003', 'bank_transfer', NULL, 'TXN-20250715-034116-8134a2', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-15 03:41:16', '2025-08-10', '2025-08-17', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-15 03:41:16', '2025-07-15 03:41:16'),
('f88286bf-6096-4617-b702-3f7533024282', 'be8c3b24-114f-49e7-a816-e60e43899cde', '550e8400-e29b-41d4-a716-446655440003', 'google_pay', NULL, 'TXN-20250714-103651-9c04aa', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-14 10:36:51', '2025-07-20', '2025-07-27', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-14 10:36:51', '2025-07-14 10:36:51'),
('fba58ba4-deb6-45ef-8ec8-d1fc1c30eb17', 'a9955447-3212-4995-8abd-9be1c313f537', '29e6fe85-124c-480f-bcf2-0d174721936f', 'paypal', NULL, 'TXN-20250715-161852-3f7f3e', NULL, '499.00', 'USD', '0.00', '499.00', 'completed', '2025-07-15 16:18:52', '2025-07-19', '2025-07-26', 'Subscription แพ็กเกจ 4 มื้อ', NULL, '0.00', NULL, NULL, '2025-07-15 16:18:52', '2025-07-15 16:18:52');

-- --------------------------------------------------------

--
-- Table structure for table `payment_status_log`
--

CREATE TABLE `payment_status_log` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_status_log`
--

INSERT INTO `payment_status_log` (`id`, `payment_id`, `old_status`, `new_status`, `changed_by`, `notes`, `created_at`) VALUES
('0c0e06ac9d9c6d7e812b9d38aae36142', 'd4cdeaaf-1825-4e74-bb9e-c8b91ecddf68', 'failed', 'pending', '550e8400-e29b-41d4-a716-446655440002', '', '2025-07-14 11:45:30'),
('0d2a54d60682f04d0f25d1f44f02c192', 'd4cdeaaf-1825-4e74-bb9e-c8b91ecddf68', 'completed', 'failed', '550e8400-e29b-41d4-a716-446655440002', '', '2025-07-14 11:51:14'),
('0e19e1373933e1019595ebce9f16d1a2', 'd4cdeaaf-1825-4e74-bb9e-c8b91ecddf68', 'completed', 'pending', '550e8400-e29b-41d4-a716-446655440002', '', '2025-07-14 11:48:34'),
('2a07a6464a9408a2e3dcc68f48f2caba', 'adfb18aa-5896-4d79-9740-bd2f58f9350d', 'completed', 'pending', '550e8400-e29b-41d4-a716-446655440002', '', '2025-07-14 16:32:43'),
('2d9e28e741c87a6c4afe1f0feb16a35c', 'd4cdeaaf-1825-4e74-bb9e-c8b91ecddf68', 'completed', 'completed', '550e8400-e29b-41d4-a716-446655440002', '', '2025-07-14 11:48:29'),
('3684ead2856049c2859b1b060197e0fc', 'd4cdeaaf-1825-4e74-bb9e-c8b91ecddf68', 'failed', 'completed', '550e8400-e29b-41d4-a716-446655440002', '', '2025-07-14 11:50:51'),
('54dec67e5f28d473d523b5b348a5ee38', 'd4cdeaaf-1825-4e74-bb9e-c8b91ecddf68', 'completed', 'failed', '550e8400-e29b-41d4-a716-446655440002', '', '2025-07-14 11:45:06'),
('647bd8e57060fae4b8309f4db9fc22f4', 'adfb18aa-5896-4d79-9740-bd2f58f9350d', 'pending', 'completed', '550e8400-e29b-41d4-a716-446655440002', '', '2025-07-14 16:32:52'),
('73f64dd80157b11bdc004051d0d42e04', 'd4cdeaaf-1825-4e74-bb9e-c8b91ecddf68', 'failed', 'completed', '550e8400-e29b-41d4-a716-446655440002', '', '2025-07-14 11:48:21'),
('76e65dc6b3db062cfba4c8d8e5833b15', 'd4cdeaaf-1825-4e74-bb9e-c8b91ecddf68', 'pending', 'failed', '550e8400-e29b-41d4-a716-446655440002', '', '2025-07-14 11:48:17'),
('a4207d832579be1fa97ce6ca0f6fb7dc', 'd4cdeaaf-1825-4e74-bb9e-c8b91ecddf68', 'completed', 'pending', '550e8400-e29b-41d4-a716-446655440002', '', '2025-07-14 11:50:59'),
('ceeec41d9e8306cd2766666eace7886b', 'd4cdeaaf-1825-4e74-bb9e-c8b91ecddf68', 'pending', 'failed', '550e8400-e29b-41d4-a716-446655440002', '', '2025-07-14 11:48:40'),
('f5d0efc17650ba3f74bd7d4131441d0c', 'd4cdeaaf-1825-4e74-bb9e-c8b91ecddf68', 'pending', 'completed', '550e8400-e29b-41d4-a716-446655440002', '', '2025-07-14 11:51:05');

-- --------------------------------------------------------

--
-- Table structure for table `payment_status_logs`
--

CREATE TABLE `payment_status_logs` (
  `id` int(11) NOT NULL,
  `payment_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `photos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
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
  `delivery_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `preferred_delivery_time` enum('morning','afternoon','evening','flexible') COLLATE utf8mb4_unicode_ci DEFAULT 'afternoon',
  `special_instructions` text COLLATE utf8mb4_unicode_ci,
  `pause_start_date` date DEFAULT NULL,
  `pause_end_date` date DEFAULT NULL,
  `skip_dates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
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
('002a5635-19c2-496c-97f6-7d9b867d9ceb', '29e6fe85-124c-480f-bcf2-0d174721936f', 'a601efdf-cb35-427d-a077-02d82e4d2673', 'paused', '2025-07-14', NULL, '2025-07-21', 'weekly', '399.00', '0.00', '[\"sat_1\",\"sun_1\"]', 'afternoon', '', '2025-07-14', '2025-07-15', NULL, 1, NULL, NULL, NULL, '2025-07-13 12:39:59', '2025-07-14 05:23:23'),
('0463a886-ab5b-4447-ad7e-f42ee0c70433', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-20', NULL, '2025-07-27', 'weekly', '499.00', '0.00', '[\"sun_0\"]', 'morning', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-15 11:33:33', '2025-07-15 11:33:33'),
('08088294-f964-4dad-9615-786155382e06', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-14', NULL, '2025-07-21', 'weekly', '499.00', '0.00', '[\"sat_1\",\"sun_1\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-13 12:55:10', '2025-07-13 12:55:10'),
('09d2a30f-8ae6-4956-8b6a-400bc4d2a7eb', '29e6fe85-124c-480f-bcf2-0d174721936f', '0f047d6f-9d0a-4296-985b-3b4090b8ca8a', 'cancelled', '2025-07-19', NULL, '2025-07-26', 'weekly', '599.00', '0.00', '[\"sat_0\"]', 'flexible', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-16 07:04:00', '2025-07-16 07:04:10'),
('0bf75805-f0ab-4207-a172-25912a12ecb4', '550e8400-e29b-41d4-a716-446655440003', '0f047d6f-9d0a-4296-985b-3b4090b8ca8a', 'active', '2025-07-19', NULL, '2025-07-26', 'weekly', '599.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 02:16:49', '2025-07-14 02:16:49'),
('1a5d3ec4-757b-480d-838a-e1bd53034a67', '6c9391f3-0772-4393-a703-a035cc68d3d7', 'c52e2693-5121-4abe-ad95-95c068367b92', 'pending_payment', '2025-07-08', NULL, '2025-07-15', 'weekly', '800.00', '0.00', '[\"monday\", \"thursday\", \"friday\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-04 07:36:50', '2025-07-04 07:36:50'),
('1b8fa3b4-6c61-475d-bd90-47b11657b92e', '29e6fe85-124c-480f-bcf2-0d174721936f', '0f047d6f-9d0a-4296-985b-3b4090b8ca8a', 'active', '2025-07-14', NULL, '2025-07-21', 'weekly', '599.00', '0.00', '[\"sat_1\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-13 12:36:11', '2025-07-13 12:36:11'),
('1fa52d11-2f99-451e-8151-0914626b0d58', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-19', NULL, '2025-07-26', 'weekly', '499.00', '0.00', '[\"sat_0\",\"sun_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 02:13:22', '2025-07-14 02:13:22'),
('28e75278-75c2-448d-bef9-d6c2886ec531', '550e8400-e29b-41d4-a716-446655440001', '9d41847d-da80-4121-9ff2-0da4efeb371d', 'active', '2025-07-03', '2025-08-03', '2025-08-03', 'monthly', '1200.00', '120.00', '[\"monday\", \"wednesday\", \"friday\"]', 'afternoon', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-03 14:23:22', '2025-07-03 14:23:22'),
('350663c4-d1b2-42f2-ab87-ed36aab9e7d0', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-19', NULL, '2025-07-26', 'weekly', '499.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-15 11:13:08', '2025-07-15 11:13:08'),
('35cf6ed8-05d1-4da6-b069-72c2dfb665a4', '550e8400-e29b-41d4-a716-446655440003', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-08-10', NULL, '2025-08-17', 'weekly', '499.00', '0.00', '[\"sun_3\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-15 03:04:02', '2025-07-15 03:04:02'),
('36ecb782-ce8c-428a-b8a9-afd7e02bcfe8', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-15', NULL, '2025-07-22', 'weekly', '499.00', '0.00', '[\"sat_0\",\"sun_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 01:42:25', '2025-07-14 01:42:25'),
('44c83ddd-b28c-447b-85cc-5eb8926e6061', '6c9391f3-0772-4393-a703-a035cc68d3d7', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-08', NULL, '2025-07-15', 'weekly', '499.00', '0.00', '[\"monday\", \"tuesday\"]', 'afternoon', 'Please call upon arrival at the lobby. Do not leave food with the security guard.', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-07 09:38:46', '2025-07-07 09:38:46'),
('460ab3f2-889e-4a16-80a6-4e951cb7d3c6', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-27', NULL, '2025-08-03', 'weekly', '499.00', '0.00', '[\"sun_1\",\"sat_2\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 07:01:34', '2025-07-14 07:01:34'),
('46def7f0-dd69-4e62-af4d-da389cfbc6bb', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-26', NULL, '2025-08-02', 'weekly', '499.00', '0.00', '[\"sat_1\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 02:07:14', '2025-07-14 02:07:14'),
('476a2565-e455-41a2-bdc8-c0e7e4534761', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-19', NULL, '2025-07-26', 'weekly', '499.00', '0.00', '[\"sat_0\",\"sun_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-15 03:49:52', '2025-07-15 11:12:38'),
('476b9536-ab53-42a7-98c9-e224d368f12d', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'cancelled', '2025-07-15', NULL, '2025-07-22', 'weekly', '499.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 15:13:11', '2025-07-15 11:12:35'),
('4f718c97-3c23-4cc2-9f99-cd485c9bab13', '550e8400-e29b-41d4-a716-446655440003', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-19', NULL, '2025-07-26', 'weekly', '499.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-15 02:22:06', '2025-07-15 02:22:06'),
('5249821f-18f8-4fd4-85d7-91f2546d7089', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-11', NULL, '2025-07-18', 'weekly', '499.00', '0.00', '[\"sat_0\",\"sun_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-10 08:16:16', '2025-07-10 08:16:16'),
('5e4785f7-1825-400f-b096-daac3ac8a5a4', '550e8400-e29b-41d4-a716-446655440003', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-19', NULL, '2025-07-26', 'weekly', '499.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-15 03:04:42', '2025-07-15 03:04:42'),
('676a5c1a-39de-4aac-bc0e-4a5729c0a022', '550e8400-e29b-41d4-a716-446655440001', 'c52e2693-5121-4abe-ad95-95c068367b92', 'active', '2025-06-26', '2025-07-03', '2025-07-10', 'weekly', '800.00', '0.00', '[\"tuesday\", \"thursday\"]', 'morning', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-03 14:23:22', '2025-07-03 14:23:22'),
('6f580044-7bbc-4e2a-a171-653b9b4df57c', 'aa526847-33b7-4113-b8db-9cf03c97a656', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'cancelled', '2025-07-09', NULL, '2025-07-16', 'weekly', '499.00', '0.00', '[\"monday\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-08 07:30:41', '2025-07-08 09:38:38'),
('766168a3-f202-449e-a16a-44e8cdd19010', '29e6fe85-124c-480f-bcf2-0d174721936f', '6a30109d-0cdb-4967-b3da-6df686c440c8', 'active', '2025-07-15', NULL, '2025-07-22', 'weekly', '399.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 15:08:35', '2025-07-14 15:08:35'),
('7927a4a2-30c9-44ae-a2ea-3294cc64d60a', '550e8400-e29b-41d4-a716-446655440003', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'paused', '2025-08-10', NULL, '2025-08-17', 'weekly', '499.00', '0.00', '[\"sun_3\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-15 03:41:16', '2025-07-15 03:42:35'),
('7a76610b-74ae-4312-8489-c37d065083f2', '29e6fe85-124c-480f-bcf2-0d174721936f', '0f047d6f-9d0a-4296-985b-3b4090b8ca8a', 'active', '2025-07-19', NULL, '2025-07-26', 'weekly', '599.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 07:06:52', '2025-07-14 07:06:52'),
('8daae380-9b24-43d5-86e8-13aa455b09d4', '550e8400-e29b-41d4-a716-446655440004', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-19', NULL, '2025-07-26', 'weekly', '499.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 02:34:01', '2025-07-14 02:34:01'),
('90a1b331-9e41-4e9f-b757-cf9b91586387', '29e6fe85-124c-480f-bcf2-0d174721936f', 'd6e98c56-5afb-11f0-8b7f-3f129bd34f14', 'active', '2025-07-08', NULL, '2025-07-15', 'weekly', '899.00', '0.00', '[\"monday\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-07 09:47:26', '2025-07-07 09:47:26'),
('92e80649-9c4a-4649-aac1-f559b4861834', '29e6fe85-124c-480f-bcf2-0d174721936f', '4010421c-79a8-4eb7-9d05-aad41cad5103', 'active', '2025-07-15', NULL, '2025-07-22', 'weekly', '399.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 04:39:00', '2025-07-14 04:39:00'),
('94e1d12d-a30f-4c99-b13d-dc801f7d26de', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-15', NULL, '2025-07-22', 'weekly', '499.00', '0.00', '[\"sat_0\",\"sun_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 01:41:51', '2025-07-14 01:41:51'),
('97b9e7bd-e350-4d19-8ea5-022bcfdf74d0', '550e8400-e29b-41d4-a716-446655440003', 'a601efdf-cb35-427d-a077-02d82e4d2673', 'active', '2025-07-19', NULL, '2025-07-26', 'weekly', '399.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-15 02:09:32', '2025-07-15 02:09:32'),
('9a5ba398-ddd5-4c07-8016-145eb42618e8', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-11', NULL, '2025-07-18', 'weekly', '499.00', '0.00', '[\"sat_1\",\"sun_1\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-10 09:50:32', '2025-07-10 09:50:32'),
('9aea8db1-bb47-4143-9b5d-b91d0e377a6c', '550e8400-e29b-41d4-a716-446655440004', '4010421c-79a8-4eb7-9d05-aad41cad5103', 'paused', '2025-07-27', NULL, '2025-08-03', 'weekly', '399.00', '0.00', '[\"sun_1\"]', 'morning', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-15 04:32:30', '2025-07-15 07:41:42'),
('a586353a-4092-48ea-a663-e75479330d20', '29e6fe85-124c-480f-bcf2-0d174721936f', '0f047d6f-9d0a-4296-985b-3b4090b8ca8a', 'active', '2025-07-15', NULL, '2025-07-22', 'weekly', '599.00', '0.00', '[\"sat_0\",\"sun_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('a9955447-3212-4995-8abd-9be1c313f537', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-19', NULL, '2025-07-26', 'weekly', '499.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-15 16:18:52', '2025-07-15 16:18:52'),
('aa0d4793-daaf-4af7-a63a-b19993ea67f8', 'aa526847-33b7-4113-b8db-9cf03c97a656', 'c52e2693-5121-4abe-ad95-95c068367b92', 'cancelled', '2025-07-09', NULL, '2025-07-16', 'weekly', '800.00', '0.00', '[\"monday\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-08 07:31:11', '2025-07-08 09:38:35'),
('bc11f476-4fe9-44b1-a4bb-8d5f51ca1d20', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-13', NULL, '2025-07-20', 'weekly', '499.00', '0.00', '[\"sun_0\",\"sat_1\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-12 09:23:49', '2025-07-12 09:23:49'),
('be8c3b24-114f-49e7-a816-e60e43899cde', '550e8400-e29b-41d4-a716-446655440003', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-20', NULL, '2025-07-27', 'weekly', '499.00', '0.00', '[\"sun_0\",\"sat_2\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 10:36:51', '2025-07-14 10:36:51'),
('bf0b8b17-60dd-43b6-bc02-574a3b4da914', '550e8400-e29b-41d4-a716-446655440003', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'paused', '2025-07-19', NULL, '2025-07-26', 'weekly', '499.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-15 03:05:22', '2025-07-15 03:33:22'),
('c539a7cc-b04f-4a5c-ba64-944f52feded2', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-11', NULL, '2025-07-18', 'weekly', '499.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-10 09:49:18', '2025-07-10 09:49:18'),
('d5f927b4-1a43-4fa9-a859-f0fe06b6d5ca', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-26', NULL, '2025-08-02', 'weekly', '499.00', '0.00', '[\"sat_1\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-15 15:52:48', '2025-07-15 15:52:48'),
('d6831992-8062-4cbe-b90a-b5c7f2fd3ffb', '550e8400-e29b-41d4-a716-446655440003', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-08-02', NULL, '2025-08-09', 'weekly', '499.00', '0.00', '[\"sat_2\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 02:16:01', '2025-07-14 02:16:01'),
('db29bea4-1c9a-4124-8242-e1f5500a606a', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-11', NULL, '2025-07-18', 'weekly', '499.00', '0.00', '[\"sat_0\",\"sun_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-10 08:27:50', '2025-07-10 08:27:50'),
('e0f8eeba-f3d0-48bc-bb21-7bb716f6f145', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-19', NULL, '2025-07-26', 'weekly', '499.00', '0.00', '[\"sat_0\",\"sun_1\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 06:59:33', '2025-07-14 06:59:33'),
('e2f94945-f45f-4f01-aff1-4fb48c593266', 'aa526847-33b7-4113-b8db-9cf03c97a656', 'c52e2693-5121-4abe-ad95-95c068367b92', 'active', '2025-07-09', NULL, '2025-07-16', 'weekly', '800.00', '0.00', '[\"monday\",\"tuesday\",\"wednesday\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-08 09:38:27', '2025-07-08 09:38:27'),
('e3591792-336a-4ca7-8377-b5cdad5628c8', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-11', NULL, '2025-07-18', 'weekly', '499.00', '0.00', '[\"monday\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-10 07:57:06', '2025-07-10 07:57:06'),
('e684d621-c7e6-4eed-a8a5-38abc0d72366', '29e6fe85-124c-480f-bcf2-0d174721936f', '6a30109d-0cdb-4967-b3da-6df686c440c8', 'active', '2025-08-03', NULL, '2025-08-10', 'weekly', '399.00', '0.00', '[\"sun_2\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-16 07:04:33', '2025-07-16 07:04:33'),
('ecaa5b9d-44dc-4564-b077-248692b8e812', '29e6fe85-124c-480f-bcf2-0d174721936f', '0f047d6f-9d0a-4296-985b-3b4090b8ca8a', 'active', '2025-07-19', NULL, '2025-07-26', 'weekly', '599.00', '0.00', '[\"sat_0\",\"sun_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('ed9c0493-82b6-497f-af16-796a2d1b8c95', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-20', NULL, '2025-07-27', 'weekly', '499.00', '0.00', '[\"sun_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 04:56:03', '2025-07-14 04:56:03'),
('efac4af3-3a57-45b8-b41f-30bec6f79334', '550e8400-e29b-41d4-a716-446655440003', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-19', NULL, '2025-07-26', 'weekly', '499.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-15 03:00:50', '2025-07-15 03:00:50'),
('f4a3040e-2316-483a-8914-e0faceaaf58b', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-14', NULL, '2025-07-21', 'weekly', '499.00', '0.00', '[\"sat_1\",\"sun_1\",\"sat_2\",\"sun_2\",\"sat_3\",\"sun_3\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-13 12:55:55', '2025-07-13 12:55:55'),
('f8234164-aa26-469f-82d1-8d91f50a4407', '550e8400-e29b-41d4-a716-446655440003', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-07-19', NULL, '2025-07-26', 'weekly', '499.00', '0.00', '[\"sat_0\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-15 03:35:16', '2025-07-15 03:35:16'),
('fbba995f-8221-4a6f-afe7-cc29afd8d26c', '29e6fe85-124c-480f-bcf2-0d174721936f', '0f047d6f-9d0a-4296-985b-3b4090b8ca8a', 'active', '2025-07-14', NULL, '2025-07-21', 'weekly', '599.00', '0.00', '[\"sat_1\",\"sun_1\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-13 13:11:02', '2025-07-13 13:11:02'),
('fc4a97dc-71e1-4a4a-b7ab-3f8b8b40c6ed', '29e6fe85-124c-480f-bcf2-0d174721936f', '4010421c-79a8-4eb7-9d05-aad41cad5103', 'active', '2025-07-27', NULL, '2025-08-03', 'weekly', '399.00', '0.00', '[\"sun_1\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-14 11:02:37', '2025-07-14 11:02:37'),
('febed4c8-d71c-4b57-bc75-4a8276a00e2c', '29e6fe85-124c-480f-bcf2-0d174721936f', '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'active', '2025-08-10', NULL, '2025-08-17', 'weekly', '499.00', '0.00', '[\"sun_3\"]', 'afternoon', '', NULL, NULL, NULL, 1, NULL, NULL, NULL, '2025-07-17 03:13:44', '2025-07-17 03:13:44');

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
  `customizations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
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
('021b94f1-2f11-4595-bf30-afac08666775', '4f718c97-3c23-4cc2-9f99-cd485c9bab13', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 02:22:06', '2025-07-15 02:22:06'),
('02834ce0-a1ec-41dc-a07d-a2fcfe1848de', 'c539a7cc-b04f-4a5c-ba64-944f52feded2', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 09:49:18', '2025-07-10 09:49:18'),
('02e69413-2730-414b-bd1f-6b1fa0cb55fc', 'ecaa5b9d-44dc-4564-b077-248692b8e812', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('05137c5e-05b9-470b-bfa6-168d94056369', 'be8c3b24-114f-49e7-a816-e60e43899cde', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-08-02', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 10:36:51', '2025-07-14 10:36:51'),
('052b9edc-bb26-4c7f-948e-49379e8a3257', 'a9955447-3212-4995-8abd-9be1c313f537', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 16:18:52', '2025-07-15 16:18:52'),
('07164f44-f78f-463d-98b3-a710413e25f5', 'a586353a-4092-48ea-a663-e75479330d20', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('07d6bb54-f6af-4880-8423-76688c58510a', '5249821f-18f8-4fd4-85d7-91f2546d7089', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 08:16:16', '2025-07-10 08:16:16'),
('0874c5cf-9777-4b92-a8fe-35a5cf28736c', '36ecb782-ce8c-428a-b8a9-afd7e02bcfe8', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:42:25', '2025-07-14 01:42:25'),
('09301cc5-732e-4669-bd1b-2aaca22c134c', '002a5635-19c2-496c-97f6-7d9b867d9ceb', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:39:59', '2025-07-13 12:39:59'),
('0947d0c9-70f7-4f3a-9ae3-0e63bc5e2074', '0463a886-ab5b-4447-ad7e-f42ee0c70433', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 11:33:33', '2025-07-15 11:33:33'),
('096816d4-8771-48f4-9e88-72981f77a3f4', '1b8fa3b4-6c61-475d-bd90-47b11657b92e', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:36:11', '2025-07-13 12:36:11'),
('0a2a5508-a6f2-4fa6-b5a1-976d7b079c55', 'a586353a-4092-48ea-a663-e75479330d20', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('0d9c0b0b-e86a-4d38-b897-612f83c5bfb8', 'f8234164-aa26-469f-82d1-8d91f50a4407', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:35:16', '2025-07-15 03:35:16'),
('0e734e9a-dea9-432a-be97-b4d177cbd636', 'd5f927b4-1a43-4fa9-a859-f0fe06b6d5ca', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', '2025-07-26', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 15:52:48', '2025-07-15 15:52:48'),
('0f4d83d3-7a9a-4e95-9e78-d93b886c0481', 'be8c3b24-114f-49e7-a816-e60e43899cde', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 10:36:51', '2025-07-14 10:36:51'),
('0fbc4644-6c52-4688-9dea-40f8dca3e159', '5e4785f7-1825-400f-b096-daac3ac8a5a4', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:04:42', '2025-07-15 03:04:42'),
('10643ec4-e757-4b99-84cb-4a13ea42212c', '9aea8db1-bb47-4143-9b5d-b91d0e377a6c', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 04:32:30', '2025-07-15 04:32:30'),
('10f925c3-3e08-42bb-978b-3dd515748fe2', 'c539a7cc-b04f-4a5c-ba64-944f52feded2', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 09:49:18', '2025-07-10 09:49:18'),
('13030d88-407c-439f-9133-8f9a41bc3e08', '9a5ba398-ddd5-4c07-8016-145eb42618e8', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 09:50:32', '2025-07-10 09:50:32'),
('13049f49-ace6-4f00-a70a-41930ced628c', 'ecaa5b9d-44dc-4564-b077-248692b8e812', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('151a5157-fb78-4817-a9ff-f7fcf174a048', '002a5635-19c2-496c-97f6-7d9b867d9ceb', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:39:59', '2025-07-13 12:39:59'),
('15783ad8-9216-4964-8d78-1879dcc438a2', '476a2565-e455-41a2-bdc8-c0e7e4534761', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:49:52', '2025-07-15 03:49:52'),
('164d31de-6e9b-4e9a-95c1-70bd32aa8d76', 'e3591792-336a-4ca7-8377-b5cdad5628c8', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 07:57:06', '2025-07-10 07:57:06'),
('16513e17-1081-4d82-abe5-72c59e04a572', 'f8234164-aa26-469f-82d1-8d91f50a4407', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:35:16', '2025-07-15 03:35:16'),
('177a4db1-d0f7-4592-a1a3-56677def0b84', '460ab3f2-889e-4a16-80a6-4e951cb7d3c6', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:01:34', '2025-07-14 07:01:34'),
('17f4b8b7-e9aa-4905-ba02-c5627ea1dfec', 'bf0b8b17-60dd-43b6-bc02-574a3b4da914', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:05:22', '2025-07-15 03:05:22'),
('19203bd0-e52a-41a7-9b7f-fe437b986cc0', '1fa52d11-2f99-451e-8151-0914626b0d58', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:13:22', '2025-07-14 02:13:22'),
('19714f9c-efa2-4ec2-8417-dc7aea24f9cb', 'ecaa5b9d-44dc-4564-b077-248692b8e812', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('198dc342-eb6c-4372-bfe1-f00a0835e39c', 'ecaa5b9d-44dc-4564-b077-248692b8e812', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('1a1a3a2d-7381-466b-8e93-2c29c2b10b04', 'e3591792-336a-4ca7-8377-b5cdad5628c8', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 07:57:06', '2025-07-10 07:57:06'),
('1bd97f0b-ec01-47f1-8f9f-c2145b92b397', '09d2a30f-8ae6-4956-8b6a-400bc4d2a7eb', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-16 07:04:00', '2025-07-16 07:04:00'),
('1dca17ad-ee3b-4376-9731-30d629c710e1', '9a5ba398-ddd5-4c07-8016-145eb42618e8', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 09:50:32', '2025-07-10 09:50:32'),
('1f5adee6-3652-410a-a0b7-edc7ae954038', '7927a4a2-30c9-44ae-a2ea-3294cc64d60a', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-08-10', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:41:16', '2025-07-15 03:41:16'),
('21e0f16f-ce8d-407d-b5ba-0694e4fd4dd2', '0bf75805-f0ab-4207-a172-25912a12ecb4', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:16:49', '2025-07-14 02:16:49'),
('22bb9089-62f0-4e5f-9b05-e5d145a734c0', '350663c4-d1b2-42f2-ab87-ed36aab9e7d0', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 11:13:08', '2025-07-15 11:13:08'),
('2452d2ac-d069-4a18-a70a-98fd82548ebe', 'e3591792-336a-4ca7-8377-b5cdad5628c8', 'eda83eb5-d471-45a7-ad9e-339b89e68789', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 07:57:06', '2025-07-10 07:57:06'),
('2811b161-ebd4-46cc-856d-fd1681cfd351', 'e3591792-336a-4ca7-8377-b5cdad5628c8', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 07:57:06', '2025-07-10 07:57:06'),
('28db4cf9-ab4b-45c5-a22e-8ff87956c014', '476a2565-e455-41a2-bdc8-c0e7e4534761', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:49:52', '2025-07-15 03:49:52'),
('296397f1-6142-47aa-be38-d290173d918c', '1b8fa3b4-6c61-475d-bd90-47b11657b92e', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:36:11', '2025-07-13 12:36:11'),
('29dcaa4b-8a39-4f2c-93d1-9e7e58a1f658', 'c539a7cc-b04f-4a5c-ba64-944f52feded2', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 09:49:18', '2025-07-10 09:49:18'),
('2a0dad10-661e-443f-9aaa-cabbcb683586', 'e0f8eeba-f3d0-48bc-bb21-7bb716f6f145', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 06:59:33', '2025-07-14 06:59:33'),
('2b7441ba-642b-4409-8f72-5ec794e134ba', '35cf6ed8-05d1-4da6-b069-72c2dfb665a4', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-08-10', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:04:02', '2025-07-15 03:04:02'),
('2b95c6ca-db9d-4bb3-a88f-8cb142aefc5d', '766168a3-f202-449e-a16a-44e8cdd19010', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-15', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 15:08:35', '2025-07-14 15:08:35'),
('2d353b93-19cc-4c44-bbbe-3e9fd91037dc', '7927a4a2-30c9-44ae-a2ea-3294cc64d60a', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-08-10', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:41:16', '2025-07-15 03:41:16'),
('2dccde11-089a-4ec4-b838-cdc3807bca12', '476a2565-e455-41a2-bdc8-c0e7e4534761', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:49:52', '2025-07-15 03:49:52'),
('2f57c874-65a3-47e8-a7ca-ec9d693e5dc2', '09d2a30f-8ae6-4956-8b6a-400bc4d2a7eb', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-16 07:04:00', '2025-07-16 07:04:00'),
('2fce251e-7b79-4312-a9b9-ed8e0491d055', '8daae380-9b24-43d5-86e8-13aa455b09d4', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:34:01', '2025-07-14 02:34:01'),
('30519af8-033f-45c3-8aad-9c1162d43753', '7927a4a2-30c9-44ae-a2ea-3294cc64d60a', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-08-10', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:41:16', '2025-07-15 03:41:16'),
('326a3662-474f-4081-906d-152bc01fd3ff', 'f4a3040e-2316-483a-8914-e0faceaaf58b', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:55:55', '2025-07-13 12:55:55'),
('32bb11f8-d5ba-427e-8565-ada320c1d7cd', '94e1d12d-a30f-4c99-b13d-dc801f7d26de', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:41:52', '2025-07-14 01:41:52'),
('331c2c38-4df0-46fa-b664-7451daf64527', '1b8fa3b4-6c61-475d-bd90-47b11657b92e', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:36:11', '2025-07-13 12:36:11'),
('3359c14b-e07a-460e-91cb-5c799c39a0fc', 'db29bea4-1c9a-4124-8242-e1f5500a606a', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 08:27:50', '2025-07-10 08:27:50'),
('33b58ebc-b820-49b1-a5d3-3ccb5e931586', 'be8c3b24-114f-49e7-a816-e60e43899cde', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 10:36:51', '2025-07-14 10:36:51'),
('33eff1c6-d15f-413c-85c6-8859f85a82a5', '476b9536-ab53-42a7-98c9-e224d368f12d', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-15', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 15:13:11', '2025-07-14 15:13:11'),
('36d3628f-fc74-4133-b519-d1cfff9acd2a', '9aea8db1-bb47-4143-9b5d-b91d0e377a6c', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 04:32:30', '2025-07-15 04:32:30'),
('370c5844-430d-4f90-9fba-13e84a6d952c', '0bf75805-f0ab-4207-a172-25912a12ecb4', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:16:49', '2025-07-14 02:16:49'),
('37545831-060a-41e3-9ca3-1890c750659e', '36ecb782-ce8c-428a-b8a9-afd7e02bcfe8', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:42:25', '2025-07-14 01:42:25'),
('3832e310-820d-4c7f-98b2-bd349b72c99b', '0bf75805-f0ab-4207-a172-25912a12ecb4', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:16:49', '2025-07-14 02:16:49'),
('3c7309a5-0149-4145-a0dd-a88653d19b8e', '09d2a30f-8ae6-4956-8b6a-400bc4d2a7eb', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-16 07:04:00', '2025-07-16 07:04:00'),
('3c81fc94-3f33-424a-a45a-301172c5fd51', 'e2f94945-f45f-4f01-aff1-4fb48c593266', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-09', 1, NULL, NULL, 'scheduled', NULL, '2025-07-08 09:38:27', '2025-07-08 09:38:27'),
('3c9e4faa-c1eb-40bd-83e4-86482e6b532d', 'be8c3b24-114f-49e7-a816-e60e43899cde', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-08-02', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 10:36:51', '2025-07-14 10:36:51'),
('3cf82c22-78fd-4277-8a03-e4e2382a0359', '7a76610b-74ae-4312-8489-c37d065083f2', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:06:52', '2025-07-14 07:06:52'),
('3e36a084-cdcd-4cab-b22b-ebddc6fec1d3', 'febed4c8-d71c-4b57-bc75-4a8276a00e2c', 'eda83eb5-d471-45a7-ad9e-339b89e68789', '2025-08-10', 1, NULL, NULL, 'scheduled', NULL, '2025-07-17 03:13:44', '2025-07-17 03:13:44'),
('3e4915cb-c6b9-4984-a8ad-0216e86c640a', 'efac4af3-3a57-45b8-b41f-30bec6f79334', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:00:50', '2025-07-15 03:00:50'),
('3f8015df-7ad8-4dee-9e47-574102489ac4', 'db29bea4-1c9a-4124-8242-e1f5500a606a', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 08:27:50', '2025-07-10 08:27:50'),
('404b5929-b8c3-40af-bcf5-6337f415a501', 'e0f8eeba-f3d0-48bc-bb21-7bb716f6f145', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 06:59:33', '2025-07-14 06:59:33'),
('40fb9d33-d601-446e-a481-3774fadf01e3', 'ecaa5b9d-44dc-4564-b077-248692b8e812', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('41c3350b-27ee-47b4-96ec-a2eeb9caefb8', '5e4785f7-1825-400f-b096-daac3ac8a5a4', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:04:42', '2025-07-15 03:04:42'),
('43b4edb8-baf5-4e7f-ad7e-e97c2995bd39', 'a9955447-3212-4995-8abd-9be1c313f537', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 16:18:52', '2025-07-15 16:18:52'),
('445eaebe-0a2b-428a-9c38-ef84bc58b4c9', '766168a3-f202-449e-a16a-44e8cdd19010', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-15', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 15:08:35', '2025-07-14 15:08:35'),
('45bd1b4b-cbcc-47f0-b18b-c397dd4f2eb8', '766168a3-f202-449e-a16a-44e8cdd19010', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-15', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 15:08:35', '2025-07-14 15:08:35'),
('468e84e4-75de-4ffe-a9e7-0dc0aff4ea05', 'e0f8eeba-f3d0-48bc-bb21-7bb716f6f145', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 06:59:33', '2025-07-14 06:59:33'),
('46aa386a-dc04-4981-806c-2ba7c0dd13db', 'e684d621-c7e6-4eed-a8a5-38abc0d72366', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-08-03', 1, NULL, NULL, 'scheduled', NULL, '2025-07-16 07:04:33', '2025-07-16 07:04:33'),
('46bf6aa7-d5eb-437d-ac8e-12655c100d27', '46def7f0-dd69-4e62-af4d-da389cfbc6bb', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-26', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:07:14', '2025-07-14 02:07:14'),
('46ca8213-55ad-47fc-8534-6b9408b2270a', 'd5f927b4-1a43-4fa9-a859-f0fe06b6d5ca', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-26', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 15:52:48', '2025-07-15 15:52:48'),
('475724d3-d8aa-44fb-8d84-d9de8d513428', 'fbba995f-8221-4a6f-afe7-cc29afd8d26c', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 13:11:02', '2025-07-13 13:11:02'),
('480bb4ca-be44-4536-a3fd-2189a2a13eb6', 'f4a3040e-2316-483a-8914-e0faceaaf58b', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:55:55', '2025-07-13 12:55:55'),
('4910bce3-7cd4-4908-a3a6-92dcfc39381e', '0bf75805-f0ab-4207-a172-25912a12ecb4', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:16:49', '2025-07-14 02:16:49'),
('497cc885-6986-44f5-9ccb-8f7fb8a02722', 'd6831992-8062-4cbe-b90a-b5c7f2fd3ffb', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-08-02', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:16:01', '2025-07-14 02:16:01'),
('4af9b241-4487-4e79-bddf-c1ca12a852d0', 'd6831992-8062-4cbe-b90a-b5c7f2fd3ffb', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-08-02', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:16:01', '2025-07-14 02:16:01'),
('4b1d237a-8dc4-4442-8d25-1e2735589ffc', '1fa52d11-2f99-451e-8151-0914626b0d58', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:13:22', '2025-07-14 02:13:22'),
('4b4d3ebb-2840-4df0-98af-ed57d6353332', 'a586353a-4092-48ea-a663-e75479330d20', 'eda83eb5-d471-45a7-ad9e-339b89e68789', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('4d25f79e-22b1-40a4-a4f0-947fe7077a08', 'ecaa5b9d-44dc-4564-b077-248692b8e812', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('4e28cd48-c8ee-4496-9173-e51cba84250c', 'e0f8eeba-f3d0-48bc-bb21-7bb716f6f145', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 06:59:33', '2025-07-14 06:59:33'),
('50bbf228-98d1-498c-bc9b-6c847a884c2b', '08088294-f964-4dad-9615-786155382e06', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:55:10', '2025-07-13 12:55:10'),
('528f4140-a92b-4e08-8eed-7ca0fa157818', '350663c4-d1b2-42f2-ab87-ed36aab9e7d0', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 11:13:08', '2025-07-15 11:13:08'),
('538fb099-2543-49da-ae04-a67c0a6faf73', '0bf75805-f0ab-4207-a172-25912a12ecb4', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:16:49', '2025-07-14 02:16:49'),
('5398e6b4-f439-46f6-80d7-0663796d5fd2', 'a586353a-4092-48ea-a663-e75479330d20', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('547c5c64-9620-4cca-a077-74af8302bc13', '94e1d12d-a30f-4c99-b13d-dc801f7d26de', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:41:52', '2025-07-14 01:41:52'),
('55a128a6-028c-492d-b322-d807c4acc7b6', 'be8c3b24-114f-49e7-a816-e60e43899cde', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-08-02', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 10:36:51', '2025-07-14 10:36:51'),
('55c1b559-d000-41ca-b931-672f8dd59569', 'a586353a-4092-48ea-a663-e75479330d20', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('56ebf010-3708-4a67-9b70-21642f24d95c', '97b9e7bd-e350-4d19-8ea5-022bcfdf74d0', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 02:09:32', '2025-07-15 02:09:32'),
('573b65b1-b278-4547-b021-76c4ca1769db', '9aea8db1-bb47-4143-9b5d-b91d0e377a6c', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 04:32:30', '2025-07-15 04:32:30'),
('581f3a6f-c109-4ad8-9f55-885adf5513c6', 'febed4c8-d71c-4b57-bc75-4a8276a00e2c', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', '2025-08-10', 1, NULL, NULL, 'scheduled', NULL, '2025-07-17 03:13:44', '2025-07-17 03:13:44'),
('59a42290-7873-4e2d-90b5-95edad7ff467', 'ed9c0493-82b6-497f-af16-796a2d1b8c95', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 04:56:03', '2025-07-14 04:56:03'),
('59a77ec2-3c83-4361-b16c-14fe9380e799', 'e684d621-c7e6-4eed-a8a5-38abc0d72366', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-08-03', 1, NULL, NULL, 'scheduled', NULL, '2025-07-16 07:04:33', '2025-07-16 07:04:33'),
('5b358cc0-092f-473e-bb2d-daa91cb0eb75', '460ab3f2-889e-4a16-80a6-4e951cb7d3c6', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-08-02', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:01:34', '2025-07-14 07:01:34'),
('5fecb695-b981-4faf-9698-b449babee320', '08088294-f964-4dad-9615-786155382e06', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:55:10', '2025-07-13 12:55:10'),
('601da770-fb80-4f80-a0eb-8baf787e346e', 'e2f94945-f45f-4f01-aff1-4fb48c593266', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-09', 1, NULL, NULL, 'scheduled', NULL, '2025-07-08 09:38:27', '2025-07-08 09:38:27'),
('65fbca15-253c-4d5b-b59b-5c1d59b9af49', '9aea8db1-bb47-4143-9b5d-b91d0e377a6c', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 04:32:30', '2025-07-15 04:32:30'),
('67b8542b-1cf8-49e0-af87-ae73e6a3f005', 'efac4af3-3a57-45b8-b41f-30bec6f79334', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:00:50', '2025-07-15 03:00:50'),
('696ad4d1-3cb3-45fb-ab74-b79e88f4681f', 'ecaa5b9d-44dc-4564-b077-248692b8e812', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('696c8f9e-6de0-420c-a027-6945f8a1ef53', '36ecb782-ce8c-428a-b8a9-afd7e02bcfe8', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:42:25', '2025-07-14 01:42:25'),
('6bdd9da1-4e98-4ed0-9f48-a0146587ff2b', '36ecb782-ce8c-428a-b8a9-afd7e02bcfe8', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:42:25', '2025-07-14 01:42:25'),
('6bfb0e7d-c227-4f20-b997-b8f75ad75c0f', 'ecaa5b9d-44dc-4564-b077-248692b8e812', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('6d2cdcdd-2e3b-42e7-9f7a-325a56c6664a', '36ecb782-ce8c-428a-b8a9-afd7e02bcfe8', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:42:25', '2025-07-14 01:42:25'),
('6d4862b9-d1c1-4111-bc81-f17e1fe225bc', '0463a886-ab5b-4447-ad7e-f42ee0c70433', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 11:33:33', '2025-07-15 11:33:33'),
('6e00dde3-17db-4d88-8c8c-cd3897704751', '1fa52d11-2f99-451e-8151-0914626b0d58', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:13:22', '2025-07-14 02:13:22'),
('6fe0a416-4b17-4704-bad9-e6f7bb802b25', '09d2a30f-8ae6-4956-8b6a-400bc4d2a7eb', 'eda83eb5-d471-45a7-ad9e-339b89e68789', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-16 07:04:00', '2025-07-16 07:04:00'),
('707a7bbe-1a5c-4f74-9f6a-9821bd35509f', '5e4785f7-1825-400f-b096-daac3ac8a5a4', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:04:42', '2025-07-15 03:04:42'),
('707e4532-9ea5-4762-be71-a0a8641e7a0b', 'fc4a97dc-71e1-4a4a-b7ab-3f8b8b40c6ed', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 11:02:37', '2025-07-14 11:02:37'),
('711b1897-123f-4d18-9fb2-d1bc97cd0a46', 'e2f94945-f45f-4f01-aff1-4fb48c593266', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-09', 1, NULL, NULL, 'scheduled', NULL, '2025-07-08 09:38:27', '2025-07-08 09:38:27'),
('724bb48a-07d4-42c7-b9b3-d54a83aec43f', 'bf0b8b17-60dd-43b6-bc02-574a3b4da914', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:05:22', '2025-07-15 03:05:22'),
('732d9645-fbcd-4e1b-93a9-b40933b10a3d', '460ab3f2-889e-4a16-80a6-4e951cb7d3c6', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:01:34', '2025-07-14 07:01:34'),
('74a8726a-c5d7-463d-a078-9efbbaeff14d', '002a5635-19c2-496c-97f6-7d9b867d9ceb', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:39:59', '2025-07-13 12:39:59'),
('7501d7c3-64f2-4f3c-a546-76fee3f40614', 'f8234164-aa26-469f-82d1-8d91f50a4407', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:35:16', '2025-07-15 03:35:16'),
('750da633-0e28-4305-8067-848e47675fbd', '94e1d12d-a30f-4c99-b13d-dc801f7d26de', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:41:52', '2025-07-14 01:41:52'),
('75ddcb1b-dfde-4f35-b46c-c6a784057c36', '35cf6ed8-05d1-4da6-b069-72c2dfb665a4', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-08-10', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:04:02', '2025-07-15 03:04:02'),
('76296cbc-98af-4fdc-b6e8-d6f2d5b93d33', 'be8c3b24-114f-49e7-a816-e60e43899cde', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 10:36:51', '2025-07-14 10:36:51'),
('76ea49bc-8336-4c40-90d0-134acde014b1', 'a586353a-4092-48ea-a663-e75479330d20', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('77e89abb-c638-41ed-8aee-52b9f2d83f1d', '460ab3f2-889e-4a16-80a6-4e951cb7d3c6', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:01:34', '2025-07-14 07:01:34'),
('782bcbfb-0093-4042-b7d0-c49c3ea377cd', '1b8fa3b4-6c61-475d-bd90-47b11657b92e', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:36:11', '2025-07-13 12:36:11'),
('787a7e60-3959-467a-9e7c-7e24f29e5e12', '08088294-f964-4dad-9615-786155382e06', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:55:10', '2025-07-13 12:55:10'),
('7896507e-b792-4ebf-ae1c-7ccc69f75437', 'ecaa5b9d-44dc-4564-b077-248692b8e812', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('78dea31f-69c7-4260-b999-3a6df48d13a7', 'efac4af3-3a57-45b8-b41f-30bec6f79334', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:00:50', '2025-07-15 03:00:50'),
('795f5631-9d80-4f55-9658-c200ee7bbae0', '94e1d12d-a30f-4c99-b13d-dc801f7d26de', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:41:52', '2025-07-14 01:41:52'),
('7a16e710-5a52-4399-9e94-bfdcd808c0d0', '0bf75805-f0ab-4207-a172-25912a12ecb4', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:16:49', '2025-07-14 02:16:49'),
('7a375a85-de55-4f36-9dad-24474023ab21', 'e0f8eeba-f3d0-48bc-bb21-7bb716f6f145', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 06:59:33', '2025-07-14 06:59:33'),
('7c421c4c-2bfb-41d1-92d9-1f5b225132df', '09d2a30f-8ae6-4956-8b6a-400bc4d2a7eb', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-16 07:04:00', '2025-07-16 07:04:00'),
('7c7a9d65-9f57-4153-bdac-75b275cb647f', '766168a3-f202-449e-a16a-44e8cdd19010', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-15', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 15:08:35', '2025-07-14 15:08:35'),
('7e22a8df-746b-4326-8259-6010de5537ed', '476a2565-e455-41a2-bdc8-c0e7e4534761', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:49:52', '2025-07-15 03:49:52'),
('7f0ce082-84a2-49e3-8ae0-02c90426ebdd', 'd5f927b4-1a43-4fa9-a859-f0fe06b6d5ca', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-26', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 15:52:48', '2025-07-15 15:52:48'),
('7fcf2a33-aae3-4e38-9433-188750116dc9', '94e1d12d-a30f-4c99-b13d-dc801f7d26de', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:41:52', '2025-07-14 01:41:52'),
('80e9baa8-19e2-45dc-85a0-4ae3a7e64e85', 'bf0b8b17-60dd-43b6-bc02-574a3b4da914', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:05:22', '2025-07-15 03:05:22'),
('81b99ac3-f8b4-423e-a306-059680d26588', '7a76610b-74ae-4312-8489-c37d065083f2', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:06:52', '2025-07-14 07:06:52'),
('82a8882c-bff5-4f3d-b048-581c19b4a18a', 'e684d621-c7e6-4eed-a8a5-38abc0d72366', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-08-03', 1, NULL, NULL, 'scheduled', NULL, '2025-07-16 07:04:33', '2025-07-16 07:04:33'),
('82e8d0f9-18cb-4760-8f21-6d90090ecaa1', '5249821f-18f8-4fd4-85d7-91f2546d7089', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 08:16:16', '2025-07-10 08:16:16'),
('850988b8-092f-427c-845e-bcc34d46589a', 'ecaa5b9d-44dc-4564-b077-248692b8e812', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('8519afb3-8175-4d35-a7de-ee148e78cfd0', '1fa52d11-2f99-451e-8151-0914626b0d58', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:13:22', '2025-07-14 02:13:22'),
('8745501e-d5f3-4bd4-a0e3-a3f88e4a6951', 'ed9c0493-82b6-497f-af16-796a2d1b8c95', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 04:56:03', '2025-07-14 04:56:03'),
('88b8f5ac-9b33-48e6-b083-49c7293a3192', 'ecaa5b9d-44dc-4564-b077-248692b8e812', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('89e0a589-14a8-427e-8895-fb9e4b0e6313', '4f718c97-3c23-4cc2-9f99-cd485c9bab13', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 02:22:06', '2025-07-15 02:22:06'),
('8a0b0d2a-db43-4432-bc3d-d142e5d56f0b', '460ab3f2-889e-4a16-80a6-4e951cb7d3c6', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-08-02', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:01:34', '2025-07-14 07:01:34'),
('8a981f75-4af2-47a4-82ff-a59763075ff9', 'd6831992-8062-4cbe-b90a-b5c7f2fd3ffb', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-08-02', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:16:01', '2025-07-14 02:16:01'),
('8f730646-553e-49ea-8504-468795511305', 'e2f94945-f45f-4f01-aff1-4fb48c593266', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-09', 1, NULL, NULL, 'scheduled', NULL, '2025-07-08 09:38:27', '2025-07-08 09:38:27'),
('8fa1126a-9800-4c0f-a2dc-cf8c9463f8ea', '7927a4a2-30c9-44ae-a2ea-3294cc64d60a', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-08-10', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:41:16', '2025-07-15 03:41:16'),
('8fe60603-c0ed-44b2-b938-70b7aa29db50', '09d2a30f-8ae6-4956-8b6a-400bc4d2a7eb', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-16 07:04:00', '2025-07-16 07:04:00'),
('90525330-b07c-4819-8367-83942cd5ada7', '476a2565-e455-41a2-bdc8-c0e7e4534761', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:49:52', '2025-07-15 03:49:52'),
('906a7a56-1f7e-47f3-8d0f-e077de9b04f9', 'febed4c8-d71c-4b57-bc75-4a8276a00e2c', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', '2025-08-10', 1, NULL, NULL, 'scheduled', NULL, '2025-07-17 03:13:44', '2025-07-17 03:13:44'),
('909dd8f6-3565-45f1-90cd-4523fe90ba9b', '7a76610b-74ae-4312-8489-c37d065083f2', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:06:52', '2025-07-14 07:06:52'),
('9254f3bf-f5b3-445b-8202-0f2e3ce2e8f8', '460ab3f2-889e-4a16-80a6-4e951cb7d3c6', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-08-02', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:01:34', '2025-07-14 07:01:34'),
('950f92a7-dddf-4ea2-a59f-e44062a19097', 'e0f8eeba-f3d0-48bc-bb21-7bb716f6f145', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 06:59:33', '2025-07-14 06:59:33'),
('96f14aa1-7ed0-4974-bd47-4f39d6078d47', '94e1d12d-a30f-4c99-b13d-dc801f7d26de', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:41:52', '2025-07-14 01:41:52'),
('976a8f9c-44dd-4c12-9aee-37730d35c1cf', 'd6831992-8062-4cbe-b90a-b5c7f2fd3ffb', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-08-02', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:16:01', '2025-07-14 02:16:01'),
('98319a44-eb6f-4cac-9285-43f31d4a5fd4', '7a76610b-74ae-4312-8489-c37d065083f2', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:06:52', '2025-07-14 07:06:52'),
('98696d40-e43f-4d4c-8430-3a902ce29e05', '460ab3f2-889e-4a16-80a6-4e951cb7d3c6', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:01:34', '2025-07-14 07:01:34'),
('99e61a8a-9cc6-43b3-8f35-ae189b29461c', '4f718c97-3c23-4cc2-9f99-cd485c9bab13', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 02:22:06', '2025-07-15 02:22:06'),
('99fe08a2-dff2-42b2-9dd1-d2539996c67f', 'e0f8eeba-f3d0-48bc-bb21-7bb716f6f145', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 06:59:33', '2025-07-14 06:59:33'),
('9a45ee41-ff4c-4549-ab11-bf117fb5b88f', 'a586353a-4092-48ea-a663-e75479330d20', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('9a7c4187-aa49-45bd-a70a-dffd35763d52', '1fa52d11-2f99-451e-8151-0914626b0d58', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:13:22', '2025-07-14 02:13:22'),
('9affb3f0-669f-4e47-9a05-dd9c9a83d923', 'e0f8eeba-f3d0-48bc-bb21-7bb716f6f145', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 06:59:33', '2025-07-14 06:59:33'),
('9c58e51f-791f-475b-b70f-9b373b150f60', '1b8fa3b4-6c61-475d-bd90-47b11657b92e', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:36:11', '2025-07-13 12:36:11'),
('9f8e5d28-fd8b-491c-9995-5bb66379102e', '002a5635-19c2-496c-97f6-7d9b867d9ceb', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:39:59', '2025-07-13 12:39:59'),
('a11069f1-18a7-4744-b82d-e8c6cc94909f', '476b9536-ab53-42a7-98c9-e224d368f12d', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-15', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 15:13:11', '2025-07-14 15:13:11'),
('a29d3964-dd1b-4b35-96a4-b6a8c5bcd826', 'fbba995f-8221-4a6f-afe7-cc29afd8d26c', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 13:11:02', '2025-07-13 13:11:02'),
('a2f6130e-bb30-4ec4-bb63-1e7f0c49f29e', 'be8c3b24-114f-49e7-a816-e60e43899cde', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 10:36:51', '2025-07-14 10:36:51'),
('a3d76299-0eba-4c9b-804a-a20f58bb807c', '8daae380-9b24-43d5-86e8-13aa455b09d4', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:34:01', '2025-07-14 02:34:01'),
('a3f6c8b1-4cb8-4824-a9af-e8210dc3de3e', 'fbba995f-8221-4a6f-afe7-cc29afd8d26c', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 13:11:02', '2025-07-13 13:11:02'),
('a4c60430-80e5-4489-b232-04d832df9883', 'e684d621-c7e6-4eed-a8a5-38abc0d72366', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-08-03', 1, NULL, NULL, 'scheduled', NULL, '2025-07-16 07:04:33', '2025-07-16 07:04:33'),
('a54fea28-0546-46bb-ae33-17d9437efdf6', '350663c4-d1b2-42f2-ab87-ed36aab9e7d0', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 11:13:08', '2025-07-15 11:13:08'),
('a7654f2f-cc33-4809-a9ec-f0ac908849a9', '1b8fa3b4-6c61-475d-bd90-47b11657b92e', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:36:11', '2025-07-13 12:36:11'),
('a7d6522f-217f-40f2-93b7-6ab3f0a2a556', '97b9e7bd-e350-4d19-8ea5-022bcfdf74d0', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 02:09:32', '2025-07-15 02:09:32'),
('a9638b66-8a9b-4586-b1b6-70034aac3776', '5e4785f7-1825-400f-b096-daac3ac8a5a4', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:04:42', '2025-07-15 03:04:42'),
('a9f2c850-3c85-498b-aa2b-b196c5822ce1', 'ecaa5b9d-44dc-4564-b077-248692b8e812', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('aa14c4e9-c8ba-4b1e-8f29-a60c2c20ba2d', 'e2f94945-f45f-4f01-aff1-4fb48c593266', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-09', 1, NULL, NULL, 'scheduled', NULL, '2025-07-08 09:38:27', '2025-07-08 09:38:27'),
('aaab66d5-bffe-4077-9c13-22b0b70eb85e', '8daae380-9b24-43d5-86e8-13aa455b09d4', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:34:01', '2025-07-14 02:34:01'),
('ae136d87-fa63-436c-a4c8-d1d83363fbb3', 'a586353a-4092-48ea-a663-e75479330d20', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('ae970f8f-c2cc-4816-9ddd-05773658c982', 'ecaa5b9d-44dc-4564-b077-248692b8e812', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('aff6b792-cb71-48d6-a605-e04540120d98', 'fbba995f-8221-4a6f-afe7-cc29afd8d26c', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 13:11:02', '2025-07-13 13:11:02'),
('b2f32220-1e48-47f2-b266-dbf0371a447a', '35cf6ed8-05d1-4da6-b069-72c2dfb665a4', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-08-10', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:04:02', '2025-07-15 03:04:02'),
('b4057a7b-5031-497d-80f9-840bbd0312cd', 'a9955447-3212-4995-8abd-9be1c313f537', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 16:18:52', '2025-07-15 16:18:52'),
('b40c3a8f-c34e-437f-a37a-35d9e042674a', 'db29bea4-1c9a-4124-8242-e1f5500a606a', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 08:27:50', '2025-07-10 08:27:50'),
('b46fb4a6-610c-4cf0-a57f-ae014846adc7', '36ecb782-ce8c-428a-b8a9-afd7e02bcfe8', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:42:25', '2025-07-14 01:42:25'),
('b689e12d-02cf-4138-8f81-558e0d255014', '766168a3-f202-449e-a16a-44e8cdd19010', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-15', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 15:08:35', '2025-07-14 15:08:35'),
('b6963a47-0fe0-4db4-babb-4d1df9709763', '1b8fa3b4-6c61-475d-bd90-47b11657b92e', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:36:11', '2025-07-13 12:36:11'),
('b6a02acb-3176-47bc-9cbc-3144ea973cb7', '97b9e7bd-e350-4d19-8ea5-022bcfdf74d0', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 02:09:32', '2025-07-15 02:09:32'),
('b6c2ffd7-f018-4ca9-95e8-527424aa20c7', 'ed9c0493-82b6-497f-af16-796a2d1b8c95', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 04:56:03', '2025-07-14 04:56:03'),
('b8ae2309-3c58-4910-bfec-2d71667286cb', 'f4a3040e-2316-483a-8914-e0faceaaf58b', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:55:55', '2025-07-13 12:55:55'),
('b9542571-fe7a-4c17-aece-b1354696bfd2', '08088294-f964-4dad-9615-786155382e06', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:55:10', '2025-07-13 12:55:10'),
('b9acd88f-0d8d-40c9-be9c-df07ddf8fc9f', '7a76610b-74ae-4312-8489-c37d065083f2', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:06:52', '2025-07-14 07:06:52'),
('b9d43605-0d58-4f27-9ac9-e41a52fb3740', '350663c4-d1b2-42f2-ab87-ed36aab9e7d0', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 11:13:08', '2025-07-15 11:13:08'),
('ba673d98-3252-47af-8b20-00545a989378', 'febed4c8-d71c-4b57-bc75-4a8276a00e2c', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', '2025-08-10', 1, NULL, NULL, 'scheduled', NULL, '2025-07-17 03:13:44', '2025-07-17 03:13:44'),
('bba5bfe9-a69a-4bb0-811f-51c238c3d89b', '36ecb782-ce8c-428a-b8a9-afd7e02bcfe8', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:42:25', '2025-07-14 01:42:25'),
('bc25ef75-eddc-492a-a6a0-d3a66e8282a2', 'e684d621-c7e6-4eed-a8a5-38abc0d72366', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-08-03', 1, NULL, NULL, 'scheduled', NULL, '2025-07-16 07:04:33', '2025-07-16 07:04:33'),
('bc7e8775-9ddf-4d9a-9368-4284e821cc9c', 'a586353a-4092-48ea-a663-e75479330d20', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('bde9a874-c384-40e0-8b5b-7dcba86ff5bf', '5249821f-18f8-4fd4-85d7-91f2546d7089', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 08:16:16', '2025-07-10 08:16:16'),
('be5d3d4e-d0e4-45fd-878e-f44fc7380a79', '1fa52d11-2f99-451e-8151-0914626b0d58', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:13:22', '2025-07-14 02:13:22'),
('c01ffc22-b2da-46ae-bfae-94c496c74022', 'a586353a-4092-48ea-a663-e75479330d20', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('c056bf3a-2cf9-4e00-87d9-4e7b23502360', 'fbba995f-8221-4a6f-afe7-cc29afd8d26c', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 13:11:02', '2025-07-13 13:11:02'),
('c0d2dfc5-7bd0-4937-a767-4a8b98c78631', 'a586353a-4092-48ea-a663-e75479330d20', 'eda83eb5-d471-45a7-ad9e-339b89e68789', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('c1ed12e5-3c63-41c3-a096-a6b5d9418407', '8daae380-9b24-43d5-86e8-13aa455b09d4', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:34:01', '2025-07-14 02:34:01'),
('c264aa52-39b9-48ac-a71d-90f709d7fd35', 'fc4a97dc-71e1-4a4a-b7ab-3f8b8b40c6ed', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 11:02:37', '2025-07-14 11:02:37'),
('c318f9a6-50d1-4c96-999e-679a60c604e7', '1fa52d11-2f99-451e-8151-0914626b0d58', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:13:22', '2025-07-14 02:13:22'),
('c447b452-8cb1-4b92-bb59-b334573d7cf7', '97b9e7bd-e350-4d19-8ea5-022bcfdf74d0', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 02:09:32', '2025-07-15 02:09:32'),
('c4d47482-9e06-4456-901b-55a4051b6d44', 'fc4a97dc-71e1-4a4a-b7ab-3f8b8b40c6ed', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 11:02:37', '2025-07-14 11:02:37'),
('c5d36cc2-eaeb-4d6d-9ab8-ff7120ea06dc', 'bf0b8b17-60dd-43b6-bc02-574a3b4da914', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:05:22', '2025-07-15 03:05:22'),
('c6f669cc-d795-4bf8-8cb8-8962a3d4ceb4', '35cf6ed8-05d1-4da6-b069-72c2dfb665a4', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-08-10', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:04:02', '2025-07-15 03:04:02'),
('c7c06470-25e0-42c7-9bde-566169e64c71', '4f718c97-3c23-4cc2-9f99-cd485c9bab13', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 02:22:06', '2025-07-15 02:22:06'),
('c9c49a23-c87d-48a3-93cc-71dbc0d606ad', '476b9536-ab53-42a7-98c9-e224d368f12d', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-15', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 15:13:11', '2025-07-14 15:13:11'),
('ca6c4e4b-5875-4553-b097-c4d7a9a4f564', 'bc11f476-4fe9-44b1-a4bb-8d5f51ca1d20', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-13', 1, NULL, NULL, 'scheduled', NULL, '2025-07-12 09:23:49', '2025-07-12 09:23:49'),
('cbae9e89-26cd-45f7-a333-ca0e73c649a1', '46def7f0-dd69-4e62-af4d-da389cfbc6bb', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-26', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:07:14', '2025-07-14 02:07:14'),
('cd97a27e-4940-47de-8d8f-345e444fc1a6', '460ab3f2-889e-4a16-80a6-4e951cb7d3c6', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-08-02', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:01:34', '2025-07-14 07:01:34'),
('cdd934e8-8f5d-423a-beac-92fbcf4c5c19', 'e2f94945-f45f-4f01-aff1-4fb48c593266', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-09', 1, NULL, NULL, 'scheduled', NULL, '2025-07-08 09:38:27', '2025-07-08 09:38:27'),
('ceb30900-05ca-4aa0-8bc6-8d38683c384e', '94e1d12d-a30f-4c99-b13d-dc801f7d26de', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:41:52', '2025-07-14 01:41:52'),
('cf13c3ca-0518-4c80-9d89-1f91cf2e4f81', 'a586353a-4092-48ea-a663-e75479330d20', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('cfbfb37d-7adb-43df-b28c-de2df7654b57', 'a9955447-3212-4995-8abd-9be1c313f537', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 16:18:52', '2025-07-15 16:18:52'),
('d0d5ff97-302f-433b-98c7-57eebdee90eb', 'c539a7cc-b04f-4a5c-ba64-944f52feded2', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 09:49:18', '2025-07-10 09:49:18'),
('d4b618d6-52bb-49ce-84ea-16047302e387', 'bc11f476-4fe9-44b1-a4bb-8d5f51ca1d20', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-13', 1, NULL, NULL, 'scheduled', NULL, '2025-07-12 09:23:49', '2025-07-12 09:23:49'),
('dad6a0fb-90b1-4cda-8ffc-8791208d7c8d', 'fbba995f-8221-4a6f-afe7-cc29afd8d26c', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 13:11:02', '2025-07-13 13:11:02'),
('dc156b61-82c1-4afd-932f-07e920e0c61f', '09d2a30f-8ae6-4956-8b6a-400bc4d2a7eb', '19f1b8d3-7d75-4cc6-b804-58cf8d136d9d', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-16 07:04:00', '2025-07-16 07:04:00'),
('dc4e20f2-3dd8-461c-ae8e-3e67fae00584', 'a586353a-4092-48ea-a663-e75479330d20', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('dc656c26-79a0-4077-bcbf-db2d8d54781a', '7a76610b-74ae-4312-8489-c37d065083f2', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:06:52', '2025-07-14 07:06:52'),
('dcff5b23-435f-4c0c-97de-95d4851885c7', '476a2565-e455-41a2-bdc8-c0e7e4534761', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:49:52', '2025-07-15 03:49:52'),
('de65dcf8-05c6-439d-80f1-fa930b186bf5', 'bc11f476-4fe9-44b1-a4bb-8d5f51ca1d20', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-13', 1, NULL, NULL, 'scheduled', NULL, '2025-07-12 09:23:49', '2025-07-12 09:23:49'),
('e0b24c3b-1469-4ed1-89db-2d5fb99ce867', 'fc4a97dc-71e1-4a4a-b7ab-3f8b8b40c6ed', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 11:02:37', '2025-07-14 11:02:37'),
('e0c526c0-0889-493d-b8ee-1b55346cf6b6', '5249821f-18f8-4fd4-85d7-91f2546d7089', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 08:16:16', '2025-07-10 08:16:16'),
('e2655a71-90bf-4a0f-915b-7c686228adaf', '0bf75805-f0ab-4207-a172-25912a12ecb4', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:16:49', '2025-07-14 02:16:49'),
('e39f36fb-3ae1-4118-8d47-cbc2012d6d7e', 'fc4a97dc-71e1-4a4a-b7ab-3f8b8b40c6ed', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 11:02:37', '2025-07-14 11:02:37'),
('e3f50758-d43a-41a9-883b-902a469ec441', '476a2565-e455-41a2-bdc8-c0e7e4534761', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:49:52', '2025-07-15 03:49:52'),
('e49ba2f2-12f5-4b90-b17f-5715ddbd75c7', 'fbba995f-8221-4a6f-afe7-cc29afd8d26c', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 13:11:02', '2025-07-13 13:11:02'),
('e72dffc2-2305-45f3-9e41-403e981f89ff', '97b9e7bd-e350-4d19-8ea5-022bcfdf74d0', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 02:09:32', '2025-07-15 02:09:32'),
('e877eed6-c8a1-4a88-954d-11886a09e0d9', 'f4a3040e-2316-483a-8914-e0faceaaf58b', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:55:55', '2025-07-13 12:55:55');
INSERT INTO `subscription_menus` (`id`, `subscription_id`, `menu_id`, `delivery_date`, `quantity`, `customizations`, `special_requests`, `status`, `modified_at`, `created_at`, `updated_at`) VALUES
('e8ade5c0-f9ce-4226-b14c-3be501ef55a2', '7a76610b-74ae-4312-8489-c37d065083f2', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 07:06:52', '2025-07-14 07:06:52'),
('e97ce485-9ca9-4bc4-91e5-97065bb86f84', '46def7f0-dd69-4e62-af4d-da389cfbc6bb', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-26', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:07:14', '2025-07-14 02:07:14'),
('ea037268-6002-4c0c-8acb-bc810327384b', '002a5635-19c2-496c-97f6-7d9b867d9ceb', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-14', 1, NULL, NULL, 'scheduled', NULL, '2025-07-13 12:39:59', '2025-07-13 12:39:59'),
('ea11eaa5-ef46-46de-9498-d75d723e979d', '476a2565-e455-41a2-bdc8-c0e7e4534761', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:49:52', '2025-07-15 03:49:52'),
('ea2108d0-bb70-42b0-8318-26f14f1988b7', '9a5ba398-ddd5-4c07-8016-145eb42618e8', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 09:50:32', '2025-07-10 09:50:32'),
('eb74c6e8-a3b5-42fd-808e-ff23ce5af433', 'f8234164-aa26-469f-82d1-8d91f50a4407', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:35:16', '2025-07-15 03:35:16'),
('ebda7f00-bc6d-44b3-a4d6-b8aa6afcddf0', '36ecb782-ce8c-428a-b8a9-afd7e02bcfe8', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:42:25', '2025-07-14 01:42:25'),
('ec03005d-4a5a-4d58-a76b-0241c1c0a39c', 'db29bea4-1c9a-4124-8242-e1f5500a606a', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 08:27:50', '2025-07-10 08:27:50'),
('ecf08d20-699f-4343-a024-565a56faa0c9', '9a5ba398-ddd5-4c07-8016-145eb42618e8', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', '2025-07-11', 1, NULL, NULL, 'scheduled', NULL, '2025-07-10 09:50:32', '2025-07-10 09:50:32'),
('ed24a6f9-c151-490e-b03d-cf750fefc6c8', 'be8c3b24-114f-49e7-a816-e60e43899cde', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-08-02', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 10:36:51', '2025-07-14 10:36:51'),
('ef3b11cc-5841-4e9d-9419-c24264aa3522', '1fa52d11-2f99-451e-8151-0914626b0d58', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:13:22', '2025-07-14 02:13:22'),
('f4b31e60-9b62-4ae7-8ec7-172fd0e35c90', '46def7f0-dd69-4e62-af4d-da389cfbc6bb', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-26', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 02:07:14', '2025-07-14 02:07:14'),
('f60c4086-99d2-40b0-934f-c34ba66e2906', 'a586353a-4092-48ea-a663-e75479330d20', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:44:32', '2025-07-14 01:44:32'),
('f73fce31-456f-42e2-9333-43bc2cce80a8', 'ed9c0493-82b6-497f-af16-796a2d1b8c95', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 04:56:03', '2025-07-14 04:56:03'),
('f7b7d3de-072b-49c8-b3ad-b19c5520dd0f', 'efac4af3-3a57-45b8-b41f-30bec6f79334', 'd76f0a21-6b41-490a-8dba-249481978176', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 03:00:50', '2025-07-15 03:00:50'),
('f7fd6737-c465-40f3-af70-37619ab10db9', '476b9536-ab53-42a7-98c9-e224d368f12d', 'dbdb771f-bf70-4ccc-ad99-d307f9d89418', '2025-07-15', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 15:13:11', '2025-07-14 15:13:11'),
('fa22d5c5-9d0b-43da-b11f-f0d3c70ea346', '0463a886-ab5b-4447-ad7e-f42ee0c70433', 'fbcf20f5-b77e-4d14-b560-8c3b1baac0fd', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 11:33:33', '2025-07-15 11:33:33'),
('fac6b1fd-10f1-43b8-97a4-b2f4c0af68d8', '0463a886-ab5b-4447-ad7e-f42ee0c70433', '9429bc0e-f065-4ea8-9481-1bcb887e8454', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 11:33:33', '2025-07-15 11:33:33'),
('fb0afe3f-9d27-42c1-a42a-3bfbac2bf7d8', '94e1d12d-a30f-4c99-b13d-dc801f7d26de', 'f01cd2bf-4a3b-4311-90b2-5f35a48a394d', '2025-07-20', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:41:52', '2025-07-14 01:41:52'),
('fc0b9892-e165-415e-980f-02910b301cc9', 'd5f927b4-1a43-4fa9-a859-f0fe06b6d5ca', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', '2025-07-26', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 15:52:48', '2025-07-15 15:52:48'),
('fe839b78-deb6-4f7a-84be-b388d91c059a', 'bc11f476-4fe9-44b1-a4bb-8d5f51ca1d20', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-13', 1, NULL, NULL, 'scheduled', NULL, '2025-07-12 09:23:49', '2025-07-12 09:23:49'),
('fead2efb-4bfb-4e9f-b2be-e8bbcdf48653', 'ecaa5b9d-44dc-4564-b077-248692b8e812', '6cae13fc-b181-43bf-babb-99cb2ce39d3c', '2025-07-19', 1, NULL, NULL, 'scheduled', NULL, '2025-07-14 01:47:10', '2025-07-14 01:47:10'),
('feebef36-8f45-4a44-93ee-f680c4a56d2f', 'e2f94945-f45f-4f01-aff1-4fb48c593266', '416cc737-c6a1-4b0d-a0d0-a40f384f496e', '2025-07-09', 1, NULL, NULL, 'scheduled', NULL, '2025-07-08 09:38:27', '2025-07-08 09:38:27'),
('ff30b570-103b-4854-a1d9-f79f54802117', '9aea8db1-bb47-4143-9b5d-b91d0e377a6c', '275059d8-4497-43ec-b5e3-669fb18981d9', '2025-07-27', 1, NULL, NULL, 'scheduled', NULL, '2025-07-15 04:32:30', '2025-07-15 04:32:30');

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
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
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
('0f047d6f-9d0a-4296-985b-3b4090b8ca8a', 'Weekly Premium', 'แพ็กเกจ 7 มื้อ', NULL, 'weekly', 7, 1, '599.00', '0.00', '599.00', NULL, 2, 7, 1, 0, 0, '2025-07-12 14:11:26', '2025-07-12 14:11:26'),
('2a0f552c-be51-44c2-8b28-f68dcb2080e1', 'Monthly Family', 'แพ็กเกจครอบครัว', NULL, 'monthly', 14, 4, '1599.00', '0.00', '1599.00', NULL, 2, 7, 1, 0, 0, '2025-07-12 14:11:18', '2025-07-12 14:11:18'),
('4010421c-79a8-4eb7-9d05-aad41cad5103', 'Weekly Basic', 'แพ็กเกจ 5 มื้อ', NULL, 'weekly', 5, 1, '399.00', '0.00', '399.00', NULL, 2, 7, 1, 0, 0, '2025-07-12 14:11:07', '2025-07-12 14:11:07'),
('6a30109d-0cdb-4967-b3da-6df686c440c8', 'Weekly Basic', 'แพ็กเกจ 5 มื้อ', NULL, 'weekly', 5, 1, '399.00', '0.00', '399.00', NULL, 2, 7, 1, 0, 0, '2025-07-12 14:11:26', '2025-07-12 14:11:26'),
('6b9a750b-a1b4-4384-b6d6-2b5e8d4c061d', 'Monthly Family', 'แพ็กเกจครอบครัว', NULL, 'monthly', 14, 4, '1599.00', '0.00', '1599.00', NULL, 2, 7, 1, 0, 0, '2025-07-12 14:11:07', '2025-07-12 14:11:07'),
('6ef7d815-f13c-4f1f-b180-aa0ed3d7ef1d', 'Monthly Family', 'แพ็กเกจครอบครัว', NULL, 'monthly', 14, 4, '1599.00', '0.00', '1599.00', NULL, 2, 7, 1, 0, 0, '2025-07-12 14:11:15', '2025-07-12 14:11:15'),
('74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'Mini Plan', 'แพ็กเกจ 4 มื้อ', 'สำหรับทดลองกินสุขภาพ', 'weekly', 4, 1, '499.00', '0.00', '499.00', NULL, 1, 2, 1, 0, 1, '2025-07-07 06:34:38', '2025-07-07 06:34:38'),
('9303a458-afa2-4ee0-aeb0-e70a54a794d5', 'Monthly Family', 'แพ็กเกจครอบครัว', NULL, 'monthly', 14, 4, '1599.00', '0.00', '1599.00', NULL, 2, 7, 1, 0, 0, '2025-07-12 14:11:26', '2025-07-12 14:11:26'),
('9d41847d-da80-4121-9ff2-0da4efeb371d', 'Monthly Plan', 'แพ็คเกจรายเดือน', 'Healthy Thai meals for 1 month', 'monthly', 7, 4, '1200.00', '10.00', '1080.00', '[\"Fresh ingredients\", \"Nutrition tracking\", \"Free delivery\"]', 2, 7, 0, 1, 0, '2025-07-03 14:23:22', '2025-07-07 06:39:09'),
('a601efdf-cb35-427d-a077-02d82e4d2673', 'Weekly Basic', 'แพ็กเกจ 5 มื้อ', NULL, 'weekly', 5, 1, '399.00', '0.00', '399.00', NULL, 2, 7, 1, 0, 0, '2025-07-12 14:11:15', '2025-07-12 14:11:15'),
('a9129f4a-6c73-461b-9814-6888ab8ebe73', 'Weekly Premium', 'แพ็กเกจ 7 มื้อ', NULL, 'weekly', 7, 1, '599.00', '0.00', '599.00', NULL, 2, 7, 1, 0, 0, '2025-07-12 14:11:18', '2025-07-12 14:11:18'),
('ae3f098d-c260-4252-9ff8-ad45b1751298', 'Monthly Family', 'แพ็กเกจครอบครัว', NULL, 'monthly', 14, 4, '1599.00', '0.00', '1599.00', NULL, 2, 7, 1, 0, 0, '2025-07-12 14:11:22', '2025-07-12 14:11:22'),
('c52e2693-5121-4abe-ad95-95c068367b92', 'Weekly Plan', 'แพ็คเกจรายสัปดาห์', 'Healthy Thai meals for 1 week', 'weekly', 7, 1, '800.00', '0.00', '800.00', '[\"Fresh ingredients\", \"Nutrition tracking\"]', 2, 7, 1, 0, 0, '2025-07-03 14:23:22', '2025-07-03 14:23:22'),
('d67f9601-cadc-42ec-ae13-52145ed005d2', 'Weekly Premium', 'แพ็กเกจ 7 มื้อ', NULL, 'weekly', 7, 1, '599.00', '0.00', '599.00', NULL, 2, 7, 1, 0, 0, '2025-07-12 14:11:22', '2025-07-12 14:11:22'),
('d6e98c56-5afb-11f0-8b7f-3f129bd34f14', 'Lite Plan', 'แพ็กเกจ 8 มื้อ', 'สำหรับคนกินเบาๆ', 'weekly', 8, 1, '899.00', '0.00', '899.00', NULL, 1, 2, 1, 0, 2, '2025-07-07 06:30:13', '2025-07-07 06:30:13'),
('d6e98d96-5afb-11f0-8b7f-3f129bd34f14', 'Family Plan', 'แพ็กเกจ 12 มื้อ', 'สุขภาพทั้งบ้าน', 'weekly', 12, 1, '1499.00', '0.00', '1499.00', NULL, 2, 3, 1, 1, 3, '2025-07-07 06:30:13', '2025-07-07 06:30:13'),
('d6e98e7c-5afb-11f0-8b7f-3f129bd34f14', 'Premium Plan', 'แพ็กเกจ 15 มื้อ', 'สาย Healthy จัดเต็ม', 'weekly', 15, 1, '1799.00', '0.00', '1799.00', NULL, 3, 4, 1, 1, 4, '2025-07-07 06:30:13', '2025-07-07 06:30:13'),
('e12526f5-9de5-4f55-80f8-f6c556c11bac', 'Weekly Premium', 'แพ็กเกจ 7 มื้อ', NULL, 'weekly', 7, 1, '599.00', '0.00', '599.00', NULL, 2, 7, 1, 0, 0, '2025-07-12 14:11:07', '2025-07-12 14:11:07'),
('f4de5a33-e117-4f6f-8b98-dae2b2d3ef75', 'Weekly Basic', 'แพ็กเกจ 5 มื้อ', NULL, 'weekly', 5, 1, '399.00', '0.00', '399.00', NULL, 2, 7, 1, 0, 0, '2025-07-12 14:11:22', '2025-07-12 14:11:22');

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
  `dietary_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `allergies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
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
('29e6fe85-124c-480f-bcf2-0d174721936f', 'topkung567@gmail.com', '$2y$10$aZGBb9qE7qVzKryVGii3iuS6tz18S9ZmQDnutEZyTN.L5K2xCRhom', 'ppp', 'pp', '0899946687', '2002-01-04', 'male', 'customer', 'active', 0, NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '(Optional) Q-House Building, Room 1705', 'Bangkok', 'Bangkok', '10110', 'Thailand', '', '[\"vegan\", \"keto\"]', NULL, 'medium', '2025-07-17 23:27:08', 0, NULL, NULL, NULL, '2025-07-04 04:00:20', '2025-07-17 23:27:08'),
('550e8400-e29b-41d4-a716-446655440001', 'customer@kruathai.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'สมชาย', 'ใจดี', '0812345678', NULL, NULL, 'customer', 'active', 1, NULL, '123 ถ.สุขุมวิท แขวงคลองเตย เขตคลองเตย กรุงเทพฯ 10110', NULL, NULL, NULL, '10110', 'Thailand', NULL, '[\"vegetarian\"]', NULL, 'medium', NULL, 0, NULL, NULL, NULL, '2025-07-02 02:47:49', '2025-07-04 07:30:42'),
('550e8400-e29b-41d4-a716-446655440002', 'admin@kruathai.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'อดมิน', 'ผู้จัดการ', '0898765432', NULL, NULL, 'admin', 'active', 1, NULL, NULL, NULL, NULL, NULL, NULL, 'Thailand', NULL, NULL, NULL, 'medium', '2025-07-18 02:53:27', 0, NULL, NULL, NULL, '2025-07-02 02:47:49', '2025-07-18 02:53:27'),
('550e8400-e29b-41d4-a716-446655440003', 'kitchen@kruathai.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'เชฟ', 'มือทอง', '0887654321', NULL, NULL, 'kitchen', 'active', 1, NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', NULL, 'Bangkok', NULL, '10110', 'Thailand', '', NULL, NULL, 'medium', '2025-07-15 16:42:24', 0, NULL, NULL, NULL, '2025-07-02 02:47:49', '2025-07-15 16:42:24'),
('550e8400-e29b-41d4-a716-446655440004', 'rider@kruathai.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ไรเดอร์', 'รวดเร็ว', '0876543210', NULL, NULL, 'rider', 'active', 1, NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', NULL, 'Bangkok', NULL, '10110', 'Thailand', '', NULL, NULL, 'medium', '2025-07-15 04:27:26', 0, NULL, NULL, NULL, '2025-07-02 02:47:49', '2025-07-15 04:32:30'),
('6c9391f3-0772-4393-a703-a035cc68d3d7', 'topkung72@gmail.com', '$2y$10$hUbfYT.s0BlG273BzZEKuO1AvdrkIFS2C7wuXRCmkoWPGofliaqNm', 'werawutt', 'Promrunway', '0616625931', '2001-11-04', 'male', 'customer', 'active', 0, NULL, '123 Sukhumvit Road, Q-House Building, Room 1705, Floor 17, Khlong Toei', '(Optional) Q-House Building, Room 1705', 'Bangkok', 'Bangkok', '10110', 'Thailand', 'Please call upon arrival at the lobby. Do not leave food with the security guard.', '[\"vegetarian\"]', NULL, 'mild', '2025-07-08 03:08:34', 7, '2025-07-15 04:24:15', '72853211e86277cf347c8592801209a698cf59ffa2a3a295a271d66b1ed8fe3d', '2025-07-02 19:14:02', '2025-07-02 06:58:30', '2025-07-15 11:09:15'),
('9d394e99-d616-4989-922b-b86178acc56c', 'customer5@test.com', '$2y$10$tZpi84BHCzp1acQDKBkjPeNaalt1mQvgq98jCmzyVpHhr1hs.3Fse', 'Customer', 'Test5', NULL, NULL, NULL, 'customer', 'active', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'Thailand', NULL, NULL, NULL, 'medium', NULL, 0, NULL, NULL, NULL, '2025-07-07 07:11:07', '2025-07-12 14:11:07'),
('a76786a5-90ca-4f62-ba3c-7a6b3f2012d5', 'customer4@test.com', '$2y$10$RiW6dj3h7JdmmQ2BLhQfU.10rNXMa0lIR11in4sLy7IGIJoxmx7mS', 'Customer', 'Test4', NULL, NULL, NULL, 'customer', 'active', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'Thailand', NULL, NULL, NULL, 'medium', NULL, 0, NULL, NULL, NULL, '2025-07-08 07:11:06', '2025-07-12 14:11:06'),
('aa526847-33b7-4113-b8db-9cf03c97a656', 'oak.thanakorn@gmail.com', '$2y$10$0eEHCsSdQ7SvDPRGh4Ajou/1zD6IgDTC8O1P8AFDzhclLQOhTLy1C', 'Thanakorn', 'Tuanwachat', '0970502913', '1991-08-21', 'male', 'customer', 'active', 0, '77a081af54c536376173b40ed8278d8c2157fe9a0a39b2d1ddb21b536ef276ad', '13th Street 47 W 13th St, New York, NY 10011, USA', '', 'NY', 'New York', '10011', 'Thailand', '', NULL, NULL, 'medium', '2025-07-08 09:42:48', 0, NULL, NULL, NULL, '2025-07-08 07:15:48', '2025-07-08 09:42:48'),
('aedb1144-5ba5-11f0-96aa-e4b3d1018dc3', 'rider2@kruathai.com', '$2y$10$...', 'ไรเดอร์', 'คนที่ 2', NULL, NULL, NULL, 'rider', 'pending_verification', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'Thailand', NULL, NULL, NULL, 'medium', NULL, 0, NULL, NULL, NULL, '2025-07-08 02:46:00', '2025-07-08 02:46:00'),
('bf9b88f6-f234-4926-b6af-edd90329b066', 'customer2@test.com', '$2y$10$30FeY6oa8oR7GylidX7UDO5UYDPKy6ZN7FeRKd9cHhsxJSpbWLhDi', 'Customer', 'Test2', NULL, NULL, NULL, 'customer', 'active', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'Thailand', NULL, NULL, NULL, 'medium', NULL, 0, NULL, NULL, NULL, '2025-07-10 07:11:05', '2025-07-12 14:11:05'),
('f1793c27-c7d7-4ef5-b988-96235e48be83', 'customer3@test.com', '$2y$10$YjMBxiFGVnZasmMf5.zIbOt1XmtV8o1ww0GpWNAha7DFmb0cAz00.', 'Customer', 'Test3', NULL, NULL, NULL, 'customer', 'active', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'Thailand', NULL, NULL, NULL, 'medium', NULL, 0, NULL, NULL, NULL, '2025-07-09 07:11:05', '2025-07-12 14:11:05'),
('fef92636-5399-40c2-ada7-aa6b4ce07343', 'customer1@test.com', '$2y$10$0//s8yNp97RRn9v/83RLuO4OsFfpbl/PmOFayaMAQwfpfgnM04rXm', 'Customer', 'Test1', NULL, NULL, NULL, 'customer', 'active', 0, NULL, NULL, NULL, NULL, NULL, NULL, 'Thailand', NULL, NULL, NULL, 'medium', NULL, 0, NULL, NULL, NULL, '2025-07-11 07:11:04', '2025-07-18 03:24:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `complaint_number` (`complaint_number`),
  ADD KEY `order_id` (`subscription_id`),
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
-- Indexes for table `payment_status_log`
--
ALTER TABLE `payment_status_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `payment_status_logs`
--
ALTER TABLE `payment_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_created_at` (`created_at`);

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
-- AUTO_INCREMENT for table `payment_status_logs`
--
ALTER TABLE `payment_status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL,
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
