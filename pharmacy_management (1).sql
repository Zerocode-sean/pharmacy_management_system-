-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 10:05 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pharmacy_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `api_rate_limits`
--

CREATE TABLE `api_rate_limits` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `request_count` int(11) DEFAULT 1,
  `window_start` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_blocked` tinyint(1) DEFAULT 0,
  `blocked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `name`, `description`, `sort_order`, `is_active`, `image_path`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Antibiotics', 'Medications that fight bacterial infections', 0, 1, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(2, NULL, 'Pain Relief', 'Over-the-counter and prescription pain medications', 0, 1, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(3, NULL, 'Vitamins', 'Dietary supplements and vitamins', 0, 1, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(4, NULL, 'Cold & Flu', 'Medications for cold and flu symptoms', 0, 1, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(5, NULL, 'Digestive Health', 'Medications for digestive issues', 0, 1, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(6, NULL, 'Antibiotics', 'Prescription antibiotics and antimicrobials', 0, 1, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(7, NULL, 'Pain Relief', 'Pain relievers and anti-inflammatory medications', 0, 1, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(8, NULL, 'Vitamins', 'Vitamins and dietary supplements', 0, 1, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(9, NULL, 'Cold & Flu', 'Cold, flu, and respiratory medications', 0, 1, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(10, NULL, 'Digestive', 'Digestive health and antacids', 0, 1, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(11, NULL, 'syrup', 'Auto-created category', 0, 1, NULL, '2025-11-14 15:30:48', '2025-11-14 15:30:48'),
(12, NULL, 'capsule', 'Auto-created category', 0, 1, NULL, '2025-11-14 15:31:57', '2025-11-14 15:31:57'),
(13, NULL, 'injection', 'Auto-created category', 0, 1, NULL, '2025-11-14 15:51:17', '2025-11-14 15:51:17'),
(14, NULL, 'tablet', 'Auto-created category', 0, 1, NULL, '2025-11-16 05:02:25', '2025-11-16 05:02:25'),
(15, NULL, 'drops', NULL, 0, 1, NULL, '2025-11-16 22:37:39', '2025-11-16 22:37:39');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_code` varchar(100) DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expires` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `total_orders` int(11) DEFAULT 0,
  `total_spent` decimal(12,2) DEFAULT 0.00,
  `last_order_date` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `date_of_birth` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `first_name`, `last_name`, `phone`, `email`, `password`, `email_verified`, `verification_code`, `reset_token`, `reset_token_expires`, `remember_token`, `last_login`, `login_attempts`, `locked_until`, `preferences`, `address`, `city`, `zip_code`, `is_verified`, `total_orders`, `total_spent`, `last_order_date`, `is_deleted`, `date_of_birth`, `created_at`, `updated_at`, `deleted_at`) VALUES
(2, 'John Mutua', 'John', 'Mutua', '+254798587203', 'ndindajohn22@gmail.com', '$2y$10$nJ.W8c3BFisxeUIl85GTiOCcpPXCKcNDZRcZyH0FiAP6w1EuHZKqC', 1, NULL, NULL, NULL, NULL, '2025-11-16 21:37:15', 0, NULL, NULL, 'Permanent Residence', NULL, NULL, 0, 0, 0.00, NULL, 0, '2003-11-25', '2025-11-14 14:54:13', '2025-11-16 21:37:15', NULL),
(4, 'Test Customer', NULL, NULL, '0712345678', 'test1763280350@test.com', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '123 Test Street', 'Nairobi', '00100', 0, 0, 0.00, NULL, 1, NULL, '2025-11-16 08:05:50', '2025-11-16 15:47:48', '2025-11-16 18:47:48'),
(5, 'Test Customer', NULL, NULL, '0712345678', 'test1763280398@test.com', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '123 Test Street', 'Nairobi', '00100', 0, 0, 0.00, NULL, 0, NULL, '2025-11-16 08:06:38', '2025-11-16 08:06:38', NULL),
(6, 'John Mutua', NULL, NULL, '+254798587203', 'xymykafa@mailinator.com', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'Embakasi', 'Nairobi', '00100', 0, 0, 0.00, NULL, 1, '2011-06-06', '2025-11-16 08:07:10', '2025-11-16 15:47:38', '2025-11-16 18:47:38'),
(7, 'Test Customer', NULL, NULL, '0712345678', 'test1763280496@test.com', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '123 Test Street', 'Nairobi', '00100', 0, 0, 0.00, NULL, 0, NULL, '2025-11-16 08:08:16', '2025-11-16 08:08:16', NULL),
(8, 'Test Customer', NULL, NULL, '0712345678', 'test@customer.com', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '123 Test Street, Nairobi', 'Nairobi', '00100', 0, 0, 0.00, NULL, 1, NULL, '2025-11-16 08:09:49', '2025-11-17 08:10:35', '2025-11-17 11:10:35'),
(9, 'John Mutua', 'John', 'Mutua', '+254723396228', 'ndindajohnmutua22@gmail.com', '$2y$10$3HvAi.DvNd.9e2Vx1dceFO9ABEWMETKtSr9aNz4DT7kc0RHA4OL16', 1, NULL, NULL, NULL, NULL, '2025-11-16 11:56:52', 0, NULL, NULL, '', NULL, NULL, 0, 0, 0.00, NULL, 0, '2025-11-16', '2025-11-16 11:52:28', '2025-11-16 12:06:20', NULL),
(10, 'Lilah Cameron', NULL, NULL, '+1 (265) 127-4827', 'xopopo@mailinator.com', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'Nobis eos qui sequi ', NULL, NULL, 0, 0, 0.00, NULL, 1, '1980-11-16', '2025-11-16 12:06:50', '2025-11-16 15:47:45', '2025-11-16 18:47:45'),
(11, 'John Doe', NULL, NULL, '+254700123456', 'john.doe@example.com', '$2y$10$B29A8tOARfvUfbRU1oGNh.sc8fh2Y3R2LxYlvqZuz5pOg1ds.bRKi', 1, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '123 Main Street, Nairobi', NULL, NULL, 0, 0, 0.00, NULL, 0, '1990-05-15', '2025-11-16 17:29:19', '2025-11-16 17:29:19', NULL),
(12, 'Jane Smith', NULL, NULL, '+254700654321', 'jane.smith@example.com', '$2y$10$HrSc2FIHFl/G1QXAkBBiouMsbrTLV88qTD8Movf6ZIzRv3jbqNBS2', 1, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '456 Oak Avenue, Mombasa', NULL, NULL, 0, 0, 0.00, NULL, 0, '1985-08-22', '2025-11-16 17:29:19', '2025-11-16 17:29:19', NULL),
(13, 'Michael Johnson', NULL, NULL, '+254700987654', 'michael.johnson@example.com', '$2y$10$3zCD91rCIPo/25Z6aJ7rf.gPXQxN6usttxg9fsGr1h/Yet/DFkAY.', 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '789 Pine Road, Kisumu', NULL, NULL, 0, 0, 0.00, NULL, 1, '1992-12-08', '2025-11-16 17:29:19', '2025-11-17 08:01:34', '2025-11-17 11:01:34');

-- --------------------------------------------------------

--
-- Table structure for table `customer_feedback`
--

CREATE TABLE `customer_feedback` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `feedback_type` enum('suggestion','complaint','compliment','bug_report','feature_request') NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `status` enum('new','in_review','resolved','closed') DEFAULT 'new',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `assigned_to` int(11) DEFAULT NULL,
  `response` text DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `customer_lifetime_value`
-- (See below for the actual view)
--
CREATE TABLE `customer_lifetime_value` (
`customer_id` int(11)
,`customer_name` varchar(100)
,`email` varchar(100)
,`phone` varchar(20)
,`registration_date` timestamp
,`total_orders` int(11)
,`lifetime_value` decimal(12,2)
,`loyalty_points` int(11)
,`tier_level` varchar(8)
,`days_since_last_order` int(7)
,`customer_status` varchar(13)
);

-- --------------------------------------------------------

--
-- Table structure for table `customer_login_logs`
--

CREATE TABLE `customer_login_logs` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_successful` tinyint(1) NOT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_login_logs`
--

INSERT INTO `customer_login_logs` (`id`, `customer_id`, `email`, `ip_address`, `user_agent`, `login_successful`, `failure_reason`, `created_at`) VALUES
(1, NULL, 'himy@mailinator.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, NULL, '2025-11-14 14:51:04'),
(2, 2, 'ndindajohn22@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, NULL, '2025-11-14 14:54:17'),
(3, 2, 'ndindajohn22@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, 'Email verification', '2025-11-14 14:54:52'),
(4, 2, 'ndindajohn22@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, NULL, '2025-11-14 14:55:12'),
(5, 2, 'ndindajohn22@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, NULL, '2025-11-14 15:46:38'),
(6, 2, 'ndindajohn22@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, NULL, '2025-11-16 05:17:22'),
(7, 9, 'ndindajohnmutua22@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, NULL, '2025-11-16 11:52:42'),
(8, 9, 'ndindajohnmutua22@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, 'Email verification', '2025-11-16 11:56:37'),
(9, 9, 'ndindajohnmutua22@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, NULL, '2025-11-16 11:56:53'),
(11, 2, 'ndindajohn22@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, NULL, '2025-11-16 21:37:16');

-- --------------------------------------------------------

--
-- Table structure for table `customer_loyalty_points`
--

CREATE TABLE `customer_loyalty_points` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `points_earned` int(11) DEFAULT 0,
  `points_spent` int(11) DEFAULT 0,
  `points_balance` int(11) DEFAULT 0,
  `tier_level` enum('bronze','silver','gold','platinum') DEFAULT 'bronze',
  `last_tier_update` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_loyalty_transactions`
--

CREATE TABLE `customer_loyalty_transactions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `transaction_type` enum('earned','spent','expired','bonus','adjustment') NOT NULL,
  `points` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_notifications`
--

CREATE TABLE `customer_notifications` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `type` enum('order_confirmed','payment_received','order_ready','order_completed','order_cancelled','general') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_via` enum('email','sms','push','system') DEFAULT 'system',
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_orders`
--

CREATE TABLE `customer_orders` (
  `id` int(11) NOT NULL,
  `order_reference` varchar(50) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `order_date` datetime DEFAULT current_timestamp(),
  `total_amount` decimal(12,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `shipping_amount` decimal(10,2) DEFAULT 0.00,
  `final_amount` decimal(12,2) NOT NULL,
  `status` enum('payment_pending','confirmed','processing','ready_for_pickup','completed','cancelled','refunded') DEFAULT 'payment_pending',
  `payment_status` enum('pending','paid','failed','refunded','partial') DEFAULT 'pending',
  `payment_method` varchar(20) DEFAULT 'cash',
  `delivery_method` enum('pickup','delivery','shipping') DEFAULT 'pickup',
  `delivery_address` text DEFAULT NULL,
  `delivery_city` varchar(100) DEFAULT NULL,
  `delivery_zip` varchar(20) DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `processed_by` int(11) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL COMMENT 'Links to sales table when order is processed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_orders`
--

INSERT INTO `customer_orders` (`id`, `order_reference`, `customer_id`, `order_date`, `total_amount`, `discount_amount`, `tax_amount`, `shipping_amount`, `final_amount`, `status`, `payment_status`, `payment_method`, `delivery_method`, `delivery_address`, `delivery_city`, `delivery_zip`, `special_instructions`, `notes`, `created_at`, `updated_at`, `processed_by`, `completed_at`, `sale_id`) VALUES
(1, 'ORD20251116925171', 4, '2025-11-16 11:05:50', 100.00, 0.00, 0.00, 0.00, 100.00, 'cancelled', 'pending', 'mpesa', 'pickup', NULL, NULL, NULL, NULL, 'Customer portal order', '2025-11-16 08:05:50', '2025-11-16 15:56:15', 1, NULL, NULL),
(2, 'ORD20251116738156', 5, '2025-11-16 11:06:38', 100.00, 0.00, 0.00, 0.00, 100.00, 'payment_pending', 'pending', 'mpesa', 'pickup', NULL, NULL, NULL, NULL, 'Customer portal order', '2025-11-16 08:06:38', '2025-11-16 08:06:38', NULL, NULL, NULL),
(3, 'ORD20251116913915', 6, '2025-11-16 11:07:10', 100.00, 0.00, 0.00, 0.00, 100.00, 'processing', 'pending', 'mpesa', 'pickup', NULL, NULL, NULL, NULL, 'Customer portal order', '2025-11-16 08:07:10', '2025-11-17 08:02:42', 1, NULL, NULL),
(4, 'ORD20251116397770', 7, '2025-11-16 11:08:16', 100.00, 0.00, 0.00, 0.00, 100.00, 'processing', 'pending', 'mpesa', 'pickup', NULL, NULL, NULL, NULL, 'Customer portal order', '2025-11-16 08:08:16', '2025-11-16 12:08:07', 1, NULL, NULL),
(5, 'ORD20251116524234', 8, '2025-11-16 11:09:49', 300.00, 0.00, 0.00, 0.00, 300.00, 'processing', 'pending', 'mpesa', 'pickup', NULL, NULL, NULL, NULL, 'Customer portal order', '2025-11-16 08:09:49', '2025-11-16 08:12:45', 1, NULL, NULL);

--
-- Triggers `customer_orders`
--
DELIMITER $$
CREATE TRIGGER `log_order_status_change` AFTER UPDATE ON `customer_orders` FOR EACH ROW BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, change_reason)
        VALUES (NEW.id, OLD.status, NEW.status, NEW.processed_by, CONCAT('Status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_customer_stats_after_order` AFTER UPDATE ON `customer_orders` FOR EACH ROW BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE customers 
        SET 
            total_orders = total_orders + 1,
            total_spent = total_spent + NEW.final_amount,
            last_order_date = NEW.completed_at
        WHERE id = NEW.customer_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `customer_order_items`
--

CREATE TABLE `customer_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `medicine_name` varchar(200) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_order_items`
--

INSERT INTO `customer_order_items` (`id`, `order_id`, `medicine_id`, `medicine_name`, `quantity`, `unit_price`, `total_price`, `cost_price`, `created_at`) VALUES
(1, 1, 1, '', 1, 100.00, 100.00, 0.00, '2025-11-16 08:05:50'),
(2, 2, 1, '', 1, 100.00, 100.00, 0.00, '2025-11-16 08:06:38'),
(3, 3, 1, '', 1, 100.00, 100.00, 0.00, '2025-11-16 08:07:10'),
(4, 4, 1, '', 1, 100.00, 100.00, 0.00, '2025-11-16 08:08:16'),
(5, 5, 1, '', 2, 150.00, 300.00, 0.00, '2025-11-16 08:09:49');

-- --------------------------------------------------------

--
-- Stand-in structure for view `customer_order_summary`
-- (See below for the actual view)
--
CREATE TABLE `customer_order_summary` (
`customer_id` int(11)
,`customer_name` varchar(100)
,`email` varchar(100)
,`phone` varchar(20)
,`total_orders` bigint(21)
,`total_spent` decimal(34,2)
,`avg_order_value` decimal(16,6)
,`last_order_date` datetime
,`completed_orders` bigint(21)
,`cancelled_orders` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `customer_password_resets`
--

CREATE TABLE `customer_password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_password_resets`
--

INSERT INTO `customer_password_resets` (`id`, `email`, `token`, `created_at`, `expires_at`, `used_at`, `ip_address`) VALUES
(1, 'ndindajohn22@gmail.com', '15c2e1e6f9d580022f17cfa5be812abe599218da5629e1c9d0b0adf1bfbe77a1', '2025-11-14 15:12:29', '2025-11-14 11:12:29', NULL, '::1');

-- --------------------------------------------------------

--
-- Table structure for table `customer_portal_analytics`
--

CREATE TABLE `customer_portal_analytics` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `unique_visitors` int(11) DEFAULT 0,
  `page_views` int(11) DEFAULT 0,
  `orders_placed` int(11) DEFAULT 0,
  `orders_completed` int(11) DEFAULT 0,
  `total_revenue` decimal(12,2) DEFAULT 0.00,
  `conversion_rate` decimal(5,2) DEFAULT 0.00,
  `avg_order_value` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_sessions`
--

CREATE TABLE `customer_sessions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_sales_summary`
-- (See below for the actual view)
--
CREATE TABLE `daily_sales_summary` (
`sale_date` date
,`total_orders` bigint(21)
,`completed_orders` bigint(21)
,`cancelled_orders` bigint(21)
,`total_revenue` decimal(34,2)
,`avg_order_value` decimal(16,6)
,`unique_customers` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `email_campaigns`
--

CREATE TABLE `email_campaigns` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `template_name` varchar(100) DEFAULT NULL,
  `target_audience` enum('all_customers','active_customers','inactive_customers','by_tier','custom') NOT NULL,
  `target_criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_criteria`)),
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `status` enum('draft','scheduled','sending','sent','cancelled') DEFAULT 'draft',
  `total_recipients` int(11) DEFAULT 0,
  `sent_count` int(11) DEFAULT 0,
  `delivered_count` int(11) DEFAULT 0,
  `opened_count` int(11) DEFAULT 0,
  `clicked_count` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_campaign_recipients`
--

CREATE TABLE `email_campaign_recipients` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `email_address` varchar(255) NOT NULL,
  `status` enum('pending','sent','delivered','opened','clicked','bounced','failed') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `category` enum('rent','utilities','salaries','maintenance','marketing','transport','insurance','taxes','licenses','office_supplies','professional_fees','repairs','fuel','security','cleaning','communication','training','other') NOT NULL DEFAULT 'other',
  `subcategory` varchar(100) DEFAULT NULL COMMENT 'e.g., Electricity, Water, Internet',
  `description` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','cheque','mpesa','credit_card','other') NOT NULL DEFAULT 'cash',
  `reference_number` varchar(100) DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `receipt_url` varchar(255) DEFAULT NULL COMMENT 'Path to uploaded receipt',
  `vendor_name` varchar(200) DEFAULT NULL COMMENT 'Who we paid (if not a supplier)',
  `vendor_contact` varchar(100) DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT 0 COMMENT 'Monthly recurring expense?',
  `recurrence_period` enum('daily','weekly','monthly','quarterly','yearly') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `expense_date`, `category`, `subcategory`, `description`, `amount`, `payment_method`, `reference_number`, `receipt_number`, `receipt_url`, `vendor_name`, `vendor_contact`, `is_recurring`, `recurrence_period`, `notes`, `created_by`, `approved_by`, `created_at`, `updated_at`) VALUES
(1, '2025-11-01', 'rent', 'Shop Rent', 'November rent payment', 50000.00, 'bank_transfer', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 05:19:45', '2025-11-16 05:19:45'),
(2, '2025-11-05', 'utilities', 'Electricity', 'KPLC bill for October', 8500.00, 'mpesa', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 05:19:45', '2025-11-16 05:19:45'),
(3, '2025-11-05', 'utilities', 'Water', 'Nairobi Water bill', 2300.00, 'mpesa', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 05:19:45', '2025-11-16 05:19:45'),
(4, '2025-11-10', 'salaries', 'Staff Salaries', 'November salaries', 120000.00, 'bank_transfer', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 05:19:45', '2025-11-16 05:19:45'),
(5, '2025-11-12', 'maintenance', 'AC Repair', 'Air conditioning repair', 15000.00, 'cash', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 05:19:45', '2025-11-16 05:19:45'),
(6, '2025-11-01', 'rent', 'Shop Rent', 'November rent payment', 50000.00, 'bank_transfer', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 06:07:07', '2025-11-16 06:07:07'),
(7, '2025-11-05', 'utilities', 'Electricity', 'KPLC bill for October', 8500.00, 'mpesa', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 06:07:07', '2025-11-16 06:07:07'),
(8, '2025-11-05', 'utilities', 'Water', 'Nairobi Water bill', 2300.00, 'mpesa', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 06:07:07', '2025-11-16 06:07:07'),
(9, '2025-11-10', 'salaries', 'Staff Salaries', 'November salaries', 120000.00, 'bank_transfer', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 06:07:07', '2025-11-16 06:07:07'),
(10, '2025-11-12', 'maintenance', 'AC Repair', 'Air conditioning repair', 15000.00, 'cash', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 06:07:07', '2025-11-16 06:07:07'),
(11, '2025-11-01', 'rent', 'Shop Rent', 'November rent payment', 50000.00, 'bank_transfer', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 06:15:51', '2025-11-16 06:15:51'),
(12, '2025-11-05', 'utilities', 'Electricity', 'KPLC bill for October', 8500.00, 'mpesa', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 06:15:51', '2025-11-16 06:15:51'),
(13, '2025-11-05', 'utilities', 'Water', 'Nairobi Water bill', 2300.00, 'mpesa', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 06:15:51', '2025-11-16 06:15:51'),
(14, '2025-11-10', 'salaries', 'Staff Salaries', 'November salaries', 120000.00, 'bank_transfer', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 06:15:51', '2025-11-16 06:15:51'),
(15, '2025-11-12', 'maintenance', 'AC Repair', 'Air conditioning repair', 15000.00, 'cash', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, '2025-11-16 06:15:51', '2025-11-16 06:15:51');

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

CREATE TABLE `medicines` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `generic_name` varchar(200) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `min_stock_level` int(11) DEFAULT 10,
  `expiry_date` date DEFAULT NULL,
  `manufacture_date` date DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `minimum_stock` int(11) DEFAULT 10,
  `reorder_level` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicines`
--

INSERT INTO `medicines` (`id`, `name`, `generic_name`, `brand`, `category_id`, `supplier_id`, `barcode`, `unit_price`, `selling_price`, `stock_quantity`, `min_stock_level`, `expiry_date`, `manufacture_date`, `batch_number`, `description`, `created_at`, `updated_at`, `is_active`, `cost_price`, `minimum_stock`, `reorder_level`) VALUES
(1, 'Lani Bray', 'Isaiah Weeks', NULL, 12, 3, NULL, 574.02, 0.00, 873, 10, '2027-10-11', NULL, '', 'Anim velit voluptat', '2025-11-14 15:33:02', '2025-11-16 19:29:50', 1, 0.00, 10, 23),
(2, 'Fleur Moss', 'Helen Johnson', NULL, 13, 4, NULL, 643.00, 0.00, 929, 10, '2026-11-14', NULL, '', 'Doloremque eligendi ', '2025-11-14 15:51:17', '2025-11-17 09:04:14', 1, 0.00, 10, 20),
(3, 'Ezra Dale', 'Amber Shaw', NULL, 14, 5, NULL, 240.00, 0.00, 0, 10, '2026-09-24', NULL, '', 'Et culpa error veli', '2025-11-16 05:02:25', '2025-11-16 18:27:48', 1, 0.00, 10, 29),
(6, 'Paracetamol', 'Acetaminophen', 'Generic', 1, 1, 'TAB500', 5.00, 8.00, 93, 10, NULL, NULL, NULL, NULL, '2025-11-16 17:36:21', '2025-11-16 19:29:50', 1, 3.00, 10, 0),
(7, 'Amoxicillin', 'Amoxicillin', 'Amoxil', 1, 1, 'CAP250', 15.00, 20.00, 40, 5, NULL, NULL, NULL, NULL, '2025-11-16 17:36:21', '2025-11-17 09:04:14', 1, 10.00, 10, 0),
(8, 'Ibuprofen', 'Ibuprofen', 'Advil', 1, 1, 'TAB400', 8.00, 12.00, 67, 8, NULL, NULL, NULL, NULL, '2025-11-16 17:36:21', '2025-11-17 09:04:14', 1, 5.00, 10, 0),
(9, 'Test Medicine', 'Test Generic', NULL, 14, 10, NULL, 10.50, 0.00, 100, 10, NULL, NULL, NULL, '', '2025-11-16 22:14:49', '2025-11-16 22:14:49', 1, 0.00, 10, 10),
(10, 'Test Medicine 2', 'Test Generic', NULL, 14, 10, NULL, 15.50, 0.00, 50, 10, NULL, NULL, NULL, '', '2025-11-16 22:39:25', '2025-11-16 22:39:25', 1, 0.00, 10, 10),
(11, 'Test Medicine 3', 'Test Generic', NULL, 14, 10, NULL, 20.00, 20.00, 75, 10, NULL, NULL, NULL, '', '2025-11-16 22:53:05', '2025-11-16 22:53:05', 1, 0.00, 10, 10);

--
-- Triggers `medicines`
--
DELIMITER $$
CREATE TRIGGER `check_stock_levels_after_update` AFTER UPDATE ON `medicines` FOR EACH ROW BEGIN
    -- Check for low stock
    IF NEW.stock_quantity <= NEW.min_stock_level AND OLD.stock_quantity > OLD.min_stock_level THEN
        INSERT INTO medicine_stock_alerts (medicine_id, alert_type, threshold_value, alert_message)
        VALUES (NEW.id, 'low_stock', NEW.min_stock_level, 
                CONCAT('Medicine "', NEW.name, '" is running low. Current stock: ', NEW.stock_quantity, ', Minimum: ', NEW.min_stock_level));
    END IF;
    
    -- Check for out of stock
    IF NEW.stock_quantity = 0 AND OLD.stock_quantity > 0 THEN
        INSERT INTO medicine_stock_alerts (medicine_id, alert_type, threshold_value, alert_message)
        VALUES (NEW.id, 'out_of_stock', 0, 
                CONCAT('Medicine "', NEW.name, '" is out of stock.'));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `medicine_batches`
--

CREATE TABLE `medicine_batches` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `batch_number` varchar(100) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `quantity_received` int(11) NOT NULL,
  `quantity_remaining` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `manufacture_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `received_date` date DEFAULT curdate(),
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medicine_images`
--

CREATE TABLE `medicine_images` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `image_type` enum('primary','secondary','thumbnail') DEFAULT 'secondary',
  `alt_text` varchar(255) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `medicine_profitability_analysis`
-- (See below for the actual view)
--
CREATE TABLE `medicine_profitability_analysis` (
`medicine_id` int(11)
,`medicine_name` varchar(200)
,`selling_price` decimal(10,2)
,`cost_price` decimal(10,2)
,`profit_per_unit` decimal(11,2)
,`profit_margin_percent` decimal(17,2)
,`stock_quantity` int(11)
,`potential_profit` decimal(21,2)
,`times_sold` bigint(21)
,`total_quantity_sold` decimal(32,0)
,`total_revenue` decimal(32,2)
,`total_cost` decimal(42,2)
,`total_profit` decimal(43,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `medicine_reviews`
--

CREATE TABLE `medicine_reviews` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_title` varchar(200) DEFAULT NULL,
  `review_text` text DEFAULT NULL,
  `is_verified_purchase` tinyint(1) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 1,
  `helpful_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `medicine_sales_analytics`
-- (See below for the actual view)
--
CREATE TABLE `medicine_sales_analytics` (
`medicine_id` int(11)
,`medicine_name` varchar(200)
,`category_id` int(11)
,`category_name` varchar(100)
,`stock_quantity` int(11)
,`unit_price` decimal(10,2)
,`times_ordered` bigint(21)
,`total_quantity_sold` decimal(32,0)
,`total_revenue` decimal(32,2)
,`last_sold_date` datetime
);

-- --------------------------------------------------------

--
-- Table structure for table `medicine_search_analytics`
--

CREATE TABLE `medicine_search_analytics` (
  `id` int(11) NOT NULL,
  `search_term` varchar(255) NOT NULL,
  `results_count` int(11) DEFAULT 0,
  `clicked_medicine_id` int(11) DEFAULT NULL,
  `customer_ip` varchar(45) DEFAULT NULL,
  `search_date` date DEFAULT curdate(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medicine_stock_alerts`
--

CREATE TABLE `medicine_stock_alerts` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `alert_type` enum('low_stock','out_of_stock','expiring_soon','expired') NOT NULL,
  `threshold_value` int(11) DEFAULT NULL,
  `alert_message` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_resolved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine_stock_alerts`
--

INSERT INTO `medicine_stock_alerts` (`id`, `medicine_id`, `alert_type`, `threshold_value`, `alert_message`, `is_active`, `is_resolved`, `created_at`, `resolved_at`, `resolved_by`) VALUES
(1, 3, 'low_stock', 10, 'Medicine \"Ezra Dale\" is running low. Current stock: 1, Minimum: 10', 1, 0, '2025-11-16 05:03:30', NULL, NULL),
(2, 3, 'out_of_stock', 0, 'Medicine \"Ezra Dale\" is out of stock.', 1, 0, '2025-11-16 18:27:48', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `medicine_tags`
--

CREATE TABLE `medicine_tags` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#007bff',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine_tags`
--

INSERT INTO `medicine_tags` (`id`, `name`, `description`, `color`, `is_active`, `created_at`) VALUES
(1, 'Over-the-Counter', 'Medicines available without prescription', '#28a745', 1, '2025-11-14 14:20:16'),
(2, 'Prescription-Only', 'Medicines requiring doctor prescription', '#dc3545', 1, '2025-11-14 14:20:16'),
(3, 'Generic', 'Generic version of branded medicine', '#17a2b8', 1, '2025-11-14 14:20:16'),
(4, 'Brand', 'Branded medicine', '#ffc107', 1, '2025-11-14 14:20:16'),
(5, 'Fast-Acting', 'Quick relief medicines', '#fd7e14', 1, '2025-11-14 14:20:16'),
(6, 'Long-Lasting', 'Extended release medicines', '#6610f2', 1, '2025-11-14 14:20:16'),
(7, 'Natural', 'Natural or herbal medicines', '#20c997', 1, '2025-11-14 14:20:16'),
(8, 'Imported', 'Imported medicines', '#6c757d', 1, '2025-11-14 14:20:16');

-- --------------------------------------------------------

--
-- Table structure for table `medicine_tag_mappings`
--

CREATE TABLE `medicine_tag_mappings` (
  `medicine_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `monthly_business_performance`
-- (See below for the actual view)
--
CREATE TABLE `monthly_business_performance` (
`month` varchar(7)
,`total_orders` bigint(21)
,`unique_customers` bigint(21)
,`unique_medicines_sold` bigint(21)
,`avg_order_value` decimal(16,6)
,`total_revenue` decimal(34,2)
,`total_cost` decimal(42,2)
,`gross_profit` decimal(43,2)
,`completed_orders` bigint(21)
,`cancelled_orders` bigint(21)
,`completion_rate` decimal(26,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `monthly_expense_summary`
-- (See below for the actual view)
--
CREATE TABLE `monthly_expense_summary` (
`month` varchar(7)
,`category` enum('rent','utilities','salaries','maintenance','marketing','transport','insurance','taxes','licenses','office_supplies','professional_fees','repairs','fuel','security','cleaning','communication','training','other')
,`transaction_count` bigint(21)
,`total_amount` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `change_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_status_history`
--

INSERT INTO `order_status_history` (`id`, `order_id`, `old_status`, `new_status`, `changed_by`, `change_reason`, `created_at`) VALUES
(1, 5, 'payment_pending', 'processing', 1, 'Status changed from payment_pending to processing', '2025-11-16 08:12:45'),
(2, 4, 'payment_pending', 'processing', 1, 'Status changed from payment_pending to processing', '2025-11-16 12:08:07'),
(3, 1, 'payment_pending', 'cancelled', 1, 'Status changed from payment_pending to cancelled', '2025-11-16 15:56:15'),
(4, 3, 'payment_pending', 'processing', 1, 'Status changed from payment_pending to processing', '2025-11-17 08:02:42');

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `payment_method` enum('mpesa','card','cash','bank_transfer','other') DEFAULT 'mpesa',
  `phone_number` varchar(20) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'KES',
  `status` enum('pending','processing','completed','failed','cancelled','refunded') DEFAULT 'pending',
  `checkout_request_id` varchar(255) DEFAULT NULL,
  `merchant_request_id` varchar(255) DEFAULT NULL,
  `mpesa_receipt` varchar(100) DEFAULT NULL,
  `mpesa_transaction_id` varchar(100) DEFAULT NULL,
  `transaction_date` varchar(20) DEFAULT NULL,
  `mpesa_response` text DEFAULT NULL,
  `callback_response` text DEFAULT NULL,
  `external_transaction_id` varchar(255) DEFAULT NULL,
  `gateway_response` text DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `profit_loss_summary`
-- (See below for the actual view)
--
CREATE TABLE `profit_loss_summary` (
`month` varchar(7)
,`total_revenue` decimal(32,2)
,`cogs` decimal(42,2)
,`gross_profit` decimal(43,2)
,`operating_expenses` decimal(34,2)
,`net_profit` decimal(44,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `promotional_offers`
--

CREATE TABLE `promotional_offers` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `offer_type` enum('percentage','fixed_amount','buy_x_get_y','free_shipping') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `minimum_order_amount` decimal(10,2) DEFAULT 0.00,
  `maximum_discount` decimal(10,2) DEFAULT NULL,
  `promo_code` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `usage_count` int(11) DEFAULT 0,
  `applicable_to` enum('all','category','medicine','customer_tier') DEFAULT 'all',
  `target_value` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promotional_offers`
--

INSERT INTO `promotional_offers` (`id`, `name`, `description`, `offer_type`, `discount_value`, `minimum_order_amount`, `maximum_discount`, `promo_code`, `start_date`, `end_date`, `usage_limit`, `usage_count`, `applicable_to`, `target_value`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'New Customer Discount', '10% off for first-time customers', 'percentage', 10.00, 0.00, NULL, 'WELCOME10', '2025-11-14', '2026-02-12', NULL, 0, 'all', NULL, 1, 1, '2025-11-14 14:20:16', '2025-11-14 14:20:16'),
(2, 'Free Delivery', 'Free delivery on orders above KES 1000', 'free_shipping', 0.00, 0.00, NULL, 'FREEDEL', '2025-11-14', '2025-12-14', NULL, 0, 'all', NULL, 1, 1, '2025-11-14 14:20:16', '2025-11-14 14:20:16');

-- --------------------------------------------------------

--
-- Table structure for table `promotional_offer_usage`
--

CREATE TABLE `promotional_offer_usage` (
  `id` int(11) NOT NULL,
  `offer_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `discount_applied` decimal(10,2) NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_date` date NOT NULL,
  `received_date` date DEFAULT NULL,
  `status` enum('pending','partial','completed','cancelled') NOT NULL DEFAULT 'pending',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `purchase_orders`
--
DELIMITER $$
CREATE TRIGGER `update_supplier_balance_on_po` AFTER INSERT ON `purchase_orders` FOR EACH ROW BEGIN
    UPDATE suppliers 
    SET outstanding_balance = outstanding_balance + NEW.total_amount
    WHERE id = NEW.supplier_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `description` varchar(300) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `received_quantity` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role` varchar(50) NOT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`permissions`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role`, `permissions`, `updated_at`) VALUES
('admin', '[\"sales.create\",\"sales.view\",\"sales.refund\",\"inventory.create\",\"inventory.edit\",\"inventory.delete\",\"inventory.adjust\",\"customers.create\",\"customers.edit\",\"customers.delete\",\"reports.sales\",\"reports.inventory\",\"reports.financial\",\"reports.export\",\"users.create\",\"users.edit\",\"users.delete\",\"users.permissions\",\"system.backup\",\"system.settings\",\"system.logs\"]', '2025-11-14 14:20:15'),
('cashier', '[\"sales.create\",\"sales.view\",\"customers.create\",\"reports.sales\"]', '2025-11-14 14:20:15'),
('pharmacist', '[\"sales.create\",\"sales.view\",\"inventory.create\",\"inventory.edit\",\"inventory.adjust\",\"customers.create\",\"customers.edit\",\"reports.sales\",\"reports.inventory\"]', '2025-11-14 14:20:15');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','digital') DEFAULT 'cash',
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `customer_id`, `user_id`, `total_amount`, `discount_amount`, `tax_amount`, `final_amount`, `payment_method`, `sale_date`, `notes`) VALUES
(1, NULL, 3, 643.00, 0.00, 0.00, 643.00, 'cash', '2025-11-16 18:12:27', 'Walk-in customer'),
(2, NULL, 3, 255.00, 0.00, 0.00, 255.00, 'cash', '2025-11-16 18:27:48', 'Walk-in customer'),
(3, NULL, 3, 1237.02, 0.00, 0.00, 1237.02, 'cash', '2025-11-16 18:29:42', 'Walk-in customer'),
(4, NULL, 3, 1831.04, 0.00, 0.00, 1831.04, 'cash', '2025-11-16 18:30:10', 'Walk-in customer'),
(5, NULL, 3, 8.00, 0.00, 0.00, 8.00, 'cash', '2025-11-16 18:44:56', 'Walk-in customer'),
(6, 2, 3, 1240.02, 0.00, 0.00, 1240.02, 'cash', '2025-11-16 19:04:50', 'Sale to John Mutua'),
(7, 2, 3, 671.00, 0.00, 0.00, 671.00, 'cash', '2025-11-16 19:08:08', 'Sale to John Mutua'),
(8, 2, 3, 629.00, 50.00, 0.00, 579.00, 'cash', '2025-11-16 19:22:29', 'Sale to John Mutua'),
(9, NULL, 3, 646.00, 5.00, 0.00, 641.00, '', '2025-11-16 19:24:39', 'Walk-in customer'),
(10, NULL, 3, 489.02, 90.00, 0.00, 399.02, '', '2025-11-16 19:25:30', 'Walk-in customer'),
(11, NULL, 3, 2913.06, 100.00, 0.00, 2813.06, '', '2025-11-16 19:29:50', 'Walk-in customer'),
(12, 2, 3, 3153.00, 100.00, 0.00, 3053.00, '', '2025-11-16 21:52:36', 'Sale to John Mutua'),
(13, 2, 2, 666.00, 0.00, 0.00, 666.00, 'cash', '2025-11-17 09:04:14', 'Sale to John Mutua');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) DEFAULT 0.00 COMMENT 'What we paid for this item',
  `profit` decimal(10,2) DEFAULT 0.00 COMMENT 'Profit per item (selling - cost)',
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `medicine_id`, `quantity`, `unit_price`, `cost_price`, `profit`, `total_price`) VALUES
(1, 1, 2, 1, 643.00, 643.00, 0.00, 643.00),
(2, 2, 7, 1, 15.00, 15.00, 0.00, 15.00),
(3, 2, 3, 1, 240.00, 240.00, 0.00, 240.00),
(4, 3, 7, 1, 15.00, 15.00, 0.00, 15.00),
(5, 3, 2, 1, 643.00, 643.00, 0.00, 643.00),
(6, 3, 6, 1, 5.00, 5.00, 0.00, 5.00),
(7, 3, 1, 1, 574.02, 574.02, 0.00, 574.02),
(8, 4, 7, 1, 15.00, 15.00, 0.00, 15.00),
(9, 4, 6, 1, 5.00, 5.00, 0.00, 5.00),
(10, 4, 1, 1, 574.02, 574.02, 0.00, 574.02),
(11, 4, 6, 1, 5.00, 5.00, 0.00, 5.00),
(12, 4, 2, 1, 643.00, 643.00, 0.00, 643.00),
(13, 4, 7, 1, 15.00, 15.00, 0.00, 15.00),
(14, 4, 1, 1, 574.02, 574.02, 0.00, 574.02),
(15, 5, 8, 1, 8.00, 8.00, 0.00, 8.00),
(16, 6, 7, 1, 15.00, 15.00, 0.00, 15.00),
(17, 6, 2, 1, 643.00, 643.00, 0.00, 643.00),
(18, 6, 8, 1, 8.00, 8.00, 0.00, 8.00),
(19, 6, 1, 1, 574.02, 574.02, 0.00, 574.02),
(20, 7, 7, 1, 15.00, 15.00, 0.00, 15.00),
(21, 7, 2, 1, 643.00, 643.00, 0.00, 643.00),
(22, 7, 8, 1, 8.00, 8.00, 0.00, 8.00),
(23, 7, 6, 1, 5.00, 5.00, 0.00, 5.00),
(24, 8, 7, 1, 15.00, 15.00, 0.00, 15.00),
(25, 8, 2, 1, 643.00, 643.00, 0.00, 643.00),
(26, 8, 8, 2, 8.00, 8.00, 0.00, 16.00),
(27, 8, 6, 1, 5.00, 5.00, 0.00, 5.00),
(28, 9, 2, 1, 643.00, 643.00, 0.00, 643.00),
(29, 9, 8, 1, 8.00, 8.00, 0.00, 8.00),
(30, 10, 6, 1, 5.00, 5.00, 0.00, 5.00),
(31, 10, 1, 1, 574.02, 574.02, 0.00, 574.02),
(32, 11, 6, 1, 5.00, 5.00, 0.00, 5.00),
(33, 11, 1, 3, 574.02, 574.02, 0.00, 1722.06),
(34, 11, 2, 2, 643.00, 643.00, 0.00, 1286.00),
(35, 12, 7, 2, 15.00, 15.00, 0.00, 30.00),
(36, 12, 2, 5, 643.00, 643.00, 0.00, 3215.00),
(37, 12, 8, 1, 8.00, 8.00, 0.00, 8.00),
(38, 13, 7, 1, 15.00, 15.00, 0.00, 15.00),
(39, 13, 2, 1, 643.00, 643.00, 0.00, 643.00),
(40, 13, 8, 1, 8.00, 8.00, 0.00, 8.00);

--
-- Triggers `sale_items`
--
DELIMITER $$
CREATE TRIGGER `calculate_profit_on_sale_item` BEFORE INSERT ON `sale_items` FOR EACH ROW BEGIN
    -- Get cost price from medicines table
    SET NEW.cost_price = (SELECT unit_price FROM medicines WHERE id = NEW.medicine_id);
    -- Calculate profit
    SET NEW.profit = (NEW.unit_price - NEW.cost_price) * NEW.quantity;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `shopping_carts`
--

CREATE TABLE `shopping_carts` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `medicine_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `movement_type` enum('in','out','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_type` enum('sale','purchase','adjustment','expiry') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `contact_person` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `category` enum('medicines','equipment','supplies','other') NOT NULL DEFAULT 'medicines',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `credit_terms` int(11) DEFAULT 30,
  `tax_id` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `outstanding_balance` decimal(12,2) DEFAULT 0.00 COMMENT 'Total amount owed to supplier',
  `last_payment_date` date DEFAULT NULL COMMENT 'Date of last payment',
  `credit_limit` decimal(12,2) DEFAULT 0.00 COMMENT 'Maximum credit allowed',
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `company_name`, `contact_person`, `phone`, `email`, `address`, `category`, `status`, `credit_terms`, `tax_id`, `notes`, `outstanding_balance`, `last_payment_date`, `credit_limit`, `is_deleted`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 'MedSupply Inc.', 'John Smith', '555-0123', 'orders@medsupply.com', '123 Medical Drive, Healthcare City, HC 12345', 'medicines', 'active', 30, '', '', -500000.00, '2025-11-15', 0.00, 0, NULL, '2025-11-14 14:20:15', '2025-11-17 08:02:01'),
(3, 'Eos esse non occaec', 'Auto Contact', '000-000-0000', 'eosessenonoccaec@example.com', NULL, 'medicines', 'active', 30, NULL, NULL, 0.00, NULL, 0.00, 0, NULL, '2025-11-14 15:31:57', '2025-11-14 15:31:57'),
(4, 'Impedit dolore pers', 'Auto Contact', '000-000-0000', 'impeditdolorepers@example.com', NULL, 'medicines', 'active', 30, NULL, NULL, 0.00, NULL, 0.00, 1, '2025-11-17 08:02:07', '2025-11-14 15:51:17', '2025-11-17 08:02:07'),
(5, 'Do et unde officia h', 'Auto Contact', '000-000-0000', 'doetundeofficiah@example.com', NULL, 'medicines', 'active', 30, NULL, NULL, -30.00, '2025-11-16', 0.00, 1, '2025-11-16 15:54:57', '2025-11-16 05:02:25', '2025-11-16 15:54:57'),
(7, 'Enim adipisci harum ', 'Auto Contact', '000-000-0000', 'enimadipisciharum@example.com', NULL, 'medicines', 'active', 30, NULL, NULL, 0.00, NULL, 0.00, 1, '2025-11-16 15:55:20', '2025-11-16 12:03:28', '2025-11-16 15:55:20'),
(8, 'Dolore sunt impedit', 'Auto Contact', '000-000-0000', 'doloresuntimpedit@example.com', 'Fugit quis duis ame', 'medicines', 'active', 30, 'Doloribus exercitati', 'Ad illo quo est sunt', -89.00, '2025-11-17', 0.00, 0, NULL, '2025-11-16 12:15:55', '2025-11-17 08:11:17'),
(9, 'Gordon and Walls Traders', 'Sed est nisi itaque', '+1 (396) 204-9196', 'qelygore@mailinator.com', 'Aspernatur iste in d', 'equipment', 'active', 30, 'Odio molestiae aliqu', 'Hic id consequuntur', 0.00, NULL, 0.00, 1, '2025-11-16 20:15:25', '2025-11-16 15:55:36', '2025-11-16 20:15:25'),
(10, 'Test Manufacturer', '', '', '', NULL, 'medicines', 'active', 30, NULL, NULL, 0.00, NULL, 0.00, 0, NULL, '2025-11-16 22:14:49', '2025-11-16 22:14:49'),
(14, 'Gross and Boyer Plc', 'Veniam laborum enim', '+1 (718) 542-9666', 'nelele@mailinator.com', 'Dolorem omnis qui mo', 'supplies', 'active', 30, 'Doloribus labore est', 'Qui occaecat modi pl', 0.00, NULL, 0.00, 0, NULL, '2025-11-17 08:02:21', '2025-11-17 08:02:21'),
(15, 'Rodgers and Bass Plc', 'In quam in ratione b', '+1 (157) 564-5197', 'cavuso@mailinator.com', 'Amet alias aliqua', 'equipment', 'active', 30, 'Tempora atque in lau', 'Provident porro ea', 0.00, NULL, 0.00, 0, NULL, '2025-11-17 08:10:52', '2025-11-17 08:10:52');

-- --------------------------------------------------------

--
-- Stand-in structure for view `supplier_balance_summary`
-- (See below for the actual view)
--
CREATE TABLE `supplier_balance_summary` (
`id` int(11)
,`company_name` varchar(200)
,`contact_person` varchar(100)
,`phone` varchar(20)
,`email` varchar(100)
,`outstanding_balance` decimal(12,2)
,`credit_limit` decimal(12,2)
,`last_payment_date` date
,`total_purchases` decimal(32,2)
,`total_payments` decimal(34,2)
,`calculated_balance` decimal(35,2)
,`total_orders` bigint(21)
,`total_payments_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `supplier_payments`
--

CREATE TABLE `supplier_payments` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `purchase_order_id` int(11) DEFAULT NULL COMMENT 'Link to specific purchase order if applicable',
  `payment_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','cheque','mpesa','other') NOT NULL DEFAULT 'bank_transfer',
  `reference_number` varchar(100) DEFAULT NULL COMMENT 'Cheque number, transfer ID, M-Pesa code, etc.',
  `bank_name` varchar(100) DEFAULT NULL,
  `cheque_number` varchar(50) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `receipt_url` varchar(255) DEFAULT NULL COMMENT 'Path to uploaded receipt/proof',
  `status` enum('pending','cleared','bounced','cancelled') DEFAULT 'cleared',
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_payments`
--

INSERT INTO `supplier_payments` (`id`, `supplier_id`, `purchase_order_id`, `payment_date`, `amount`, `payment_method`, `reference_number`, `bank_name`, `cheque_number`, `account_number`, `notes`, `receipt_url`, `status`, `created_by`, `approved_by`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, '2025-11-15', 250000.00, 'bank_transfer', 'TXN987654321', NULL, NULL, NULL, 'Payment for October invoice', NULL, 'cleared', 1, NULL, '2025-11-16 05:19:45', '2025-11-16 05:19:45'),
(2, 1, NULL, '2025-11-15', 250000.00, 'bank_transfer', 'TXN987654321', NULL, NULL, NULL, 'Payment for October invoice', NULL, 'cleared', 1, NULL, '2025-11-16 06:07:07', '2025-11-16 06:07:07'),
(3, 1, NULL, '2025-11-15', 250000.00, 'bank_transfer', 'TXN987654321', NULL, NULL, NULL, 'Payment for October invoice', NULL, 'cleared', 1, NULL, '2025-11-16 06:15:51', '2025-11-16 06:15:51'),
(4, 5, NULL, '2025-11-16', 30.00, 'other', '480', '', '', '', 'Numquam sit adipisci', NULL, 'cleared', 1, NULL, '2025-11-16 12:11:06', '2025-11-16 12:11:06'),
(5, 8, NULL, '2025-11-17', 8.00, 'cheque', '21', '', '976', '', 'Voluptatem Nostrud ', NULL, 'cleared', 1, NULL, '2025-11-17 08:03:16', '2025-11-17 08:03:16'),
(6, 8, NULL, '2025-11-17', 81.00, 'other', '834', '', '', '', 'Enim aliquid unde au', NULL, 'cleared', 1, NULL, '2025-11-17 08:11:17', '2025-11-17 08:11:17');

--
-- Triggers `supplier_payments`
--
DELIMITER $$
CREATE TRIGGER `update_supplier_balance_on_payment` AFTER INSERT ON `supplier_payments` FOR EACH ROW BEGIN
    UPDATE suppliers 
    SET 
        outstanding_balance = outstanding_balance - NEW.amount,
        last_payment_date = NEW.payment_date
    WHERE id = NEW.supplier_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','decimal','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'store_name', 'PharmaCare Pharmacy', 'string', 'Store name displayed on customer portal', 1, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(2, 'store_phone', '+254-700-000-000', 'string', 'Store contact phone number', 1, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(3, 'store_email', 'info@pharmacare.co.ke', 'string', 'Store contact email', 1, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(4, 'store_address', '123 Health Street, Nairobi, Kenya', 'string', 'Store physical address', 1, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(5, 'customer_portal_enabled', 'true', 'boolean', 'Enable/disable customer portal', 0, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(6, 'online_ordering_enabled', 'true', 'boolean', 'Enable/disable online ordering', 0, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(7, 'mpesa_enabled', 'true', 'boolean', 'Enable/disable M-Pesa payments', 0, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(8, 'mpesa_shortcode', '174379', 'string', 'M-Pesa business shortcode', 0, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(9, 'mpesa_environment', 'sandbox', 'string', 'M-Pesa environment (sandbox/production)', 0, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(10, 'order_auto_confirm', 'false', 'boolean', 'Auto-confirm orders after payment', 0, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(11, 'low_stock_threshold', '10', 'integer', 'Default low stock alert threshold', 0, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(12, 'currency_code', 'KES', 'string', 'Store currency code', 1, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(13, 'currency_symbol', 'KES', 'string', 'Store currency symbol', 1, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(14, 'tax_rate', '16.0', 'decimal', 'Default tax rate percentage', 0, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(15, 'delivery_fee', '200.0', 'decimal', 'Standard delivery fee', 1, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(16, 'free_delivery_threshold', '2000.0', 'decimal', 'Minimum order for free delivery', 1, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(17, 'order_confirmation_email', 'true', 'boolean', 'Send order confirmation emails', 0, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(18, 'payment_timeout_minutes', '10', 'integer', 'Payment timeout in minutes', 0, '2025-11-14 14:20:15', '2025-11-16 15:52:25'),
(19, 'loyalty_points_rate', '1', 'integer', 'Points earned per KES spent (1 point per KES)', 0, '2025-11-14 14:20:16', '2025-11-14 14:20:16'),
(20, 'loyalty_redemption_rate', '10', 'integer', 'Points needed for 1 KES discount', 0, '2025-11-14 14:20:16', '2025-11-14 14:20:16'),
(21, 'email_notifications_enabled', 'true', 'boolean', 'Enable email notifications', 0, '2025-11-14 14:20:16', '2025-11-14 14:20:16'),
(22, 'sms_notifications_enabled', 'false', 'boolean', 'Enable SMS notifications', 0, '2025-11-14 14:20:16', '2025-11-14 14:20:16'),
(23, 'customer_registration_enabled', 'true', 'boolean', 'Allow customer registration', 0, '2025-11-14 14:20:16', '2025-11-14 14:20:16'),
(24, 'customer_reviews_enabled', 'true', 'boolean', 'Allow customer medicine reviews', 1, '2025-11-14 14:20:16', '2025-11-14 14:20:16'),
(25, 'search_analytics_enabled', 'true', 'boolean', 'Track search analytics', 0, '2025-11-14 14:20:16', '2025-11-14 14:20:16'),
(26, 'promotional_offers_enabled', 'true', 'boolean', 'Enable promotional offers', 1, '2025-11-14 14:20:16', '2025-11-14 14:20:16'),
(27, 'api_rate_limit_requests', '100', 'integer', 'API requests per minute per IP', 0, '2025-11-14 14:20:16', '2025-11-14 14:20:16'),
(28, 'api_rate_limit_window', '60', 'integer', 'Rate limit window in seconds', 0, '2025-11-14 14:20:16', '2025-11-14 14:20:16');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','pharmacist','cashier') NOT NULL DEFAULT 'cashier',
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `full_name`, `phone`, `hire_date`, `birth_date`, `department`, `employee_id`, `salary`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'admin', 'admin@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', '1234567890', '2020-01-01', '1985-06-15', 'Administration', 'EMP001', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15', 1),
(2, 'pharmacist1', 'pharmacist1@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pharmacist', 'Dr. Sarah Johnson', '555-0101', '2021-03-15', '1988-09-22', 'Pharmacy', 'EMP002', 65000.00, '2025-11-14 14:20:15', '2025-11-14 14:20:15', 1),
(3, 'pharmacist2', 'pharmacist2@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pharmacist', 'Dr. Michael Chen', '555-0102', '2020-08-01', '1985-12-10', 'Pharmacy', 'EMP003', 68000.00, '2025-11-14 14:20:15', '2025-11-14 14:20:15', 1),
(4, 'cashier1', 'cashier1@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', 'Emma Williams', '555-0103', '2022-01-10', '1995-04-18', 'Sales', 'EMP004', 35000.00, '2025-11-14 14:20:15', '2025-11-14 14:20:15', 1),
(5, 'cashier2', 'cashier2@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', 'David Rodriguez', '555-0104', '2021-11-20', '1992-07-08', 'Sales', 'EMP005', 36000.00, '2025-11-14 14:20:15', '2025-11-14 14:20:15', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_activity`
--

CREATE TABLE `user_activity` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` enum('login','logout','sales','inventory','user_management','system') NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_activity`
--

INSERT INTO `user_activity` (`id`, `user_id`, `activity_type`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, '', 'Created supplier payment #4 for KES 30.00', '::1', NULL, '2025-11-16 12:11:06'),
(2, 1, '', 'Created supplier payment #5 for KES 8.00', '::1', NULL, '2025-11-17 08:03:16'),
(3, 1, '', 'Created supplier payment #6 for KES 81.00', '::1', NULL, '2025-11-17 08:11:17');

-- --------------------------------------------------------

--
-- Table structure for table `user_attendance`
--

CREATE TABLE `user_attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `hours_worked` decimal(4,2) DEFAULT 0.00,
  `status` enum('present','absent','late','half_day','holiday','sick_leave') DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_attendance`
--

INSERT INTO `user_attendance` (`id`, `user_id`, `date`, `check_in`, `check_out`, `hours_worked`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-11-05', '08:00:00', '17:00:00', 8.00, 'present', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(2, 1, '2025-11-06', '08:15:00', '17:15:00', 8.00, 'late', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(3, 1, '2025-11-07', '08:00:00', '17:00:00', 8.00, 'present', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(4, 1, '2025-11-08', NULL, NULL, 0.00, 'sick_leave', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(5, 1, '2025-11-09', '08:00:00', '17:00:00', 8.00, 'present', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(6, 2, '2025-11-05', '08:30:00', '17:30:00', 8.00, 'present', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(7, 2, '2025-11-06', '08:00:00', '16:00:00', 7.00, 'present', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(8, 2, '2025-11-07', '08:00:00', '17:00:00', 8.00, 'present', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(9, 2, '2025-11-08', '08:00:00', '17:00:00', 8.00, 'present', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(10, 2, '2025-11-09', '08:00:00', '17:00:00', 8.00, 'present', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(11, 4, '2025-11-05', '09:00:00', '18:00:00', 8.00, 'present', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(12, 4, '2025-11-06', '09:00:00', '18:00:00', 8.00, 'present', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(13, 4, '2025-11-07', '09:30:00', '18:00:00', 7.50, 'late', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(14, 4, '2025-11-08', '09:00:00', '18:00:00', 8.00, 'present', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(15, 4, '2025-11-09', '09:00:00', '18:00:00', 8.00, 'present', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15');

-- --------------------------------------------------------

--
-- Table structure for table `user_evaluations`
--

CREATE TABLE `user_evaluations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `evaluator_id` int(11) NOT NULL,
  `evaluation_date` date NOT NULL,
  `year` int(11) NOT NULL,
  `quarter` int(11) DEFAULT NULL,
  `overall_score` decimal(5,2) NOT NULL,
  `technical_skills` decimal(5,2) DEFAULT 0.00,
  `communication_skills` decimal(5,2) DEFAULT 0.00,
  `teamwork` decimal(5,2) DEFAULT 0.00,
  `punctuality` decimal(5,2) DEFAULT 0.00,
  `customer_service` decimal(5,2) DEFAULT 0.00,
  `comments` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_evaluations`
--

INSERT INTO `user_evaluations` (`id`, `user_id`, `evaluator_id`, `evaluation_date`, `year`, `quarter`, `overall_score`, `technical_skills`, `communication_skills`, `teamwork`, `punctuality`, `customer_service`, `comments`, `recommendations`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '2024-03-31', 2024, 1, 92.50, 95.00, 90.00, 88.00, 96.00, 94.00, 'Excellent pharmaceutical knowledge and customer service. Strong team leader.', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(2, 3, 1, '2024-03-31', 2024, 1, 91.00, 93.00, 89.00, 92.00, 88.00, 92.00, 'Very knowledgeable and reliable. Occasional punctuality issues.', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(3, 4, 1, '2024-03-31', 2024, 1, 89.50, 85.00, 92.00, 90.00, 88.00, 95.00, 'Excellent customer service skills. Growing in technical knowledge.', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(4, 5, 1, '2024-03-31', 2024, 1, 88.00, 82.00, 90.00, 88.00, 90.00, 92.00, 'Good progress and improvement. Strong communication skills.', NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15');

-- --------------------------------------------------------

--
-- Table structure for table `user_login_log`
--

CREATE TABLE `user_login_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `is_successful` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_training`
--

CREATE TABLE `user_training` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `training_name` varchar(200) NOT NULL,
  `training_type` enum('certification','workshop','seminar','online_course','internal_training') NOT NULL,
  `provider` varchar(200) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `status` enum('enrolled','in_progress','completed','cancelled') DEFAULT 'enrolled',
  `certificate_number` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_training`
--

INSERT INTO `user_training` (`id`, `user_id`, `training_name`, `training_type`, `provider`, `start_date`, `end_date`, `completion_date`, `status`, `certificate_number`, `expiry_date`, `cost`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 'Advanced Pharmaceutical Care', 'certification', 'Pharmacy Board', '2024-01-15', '2024-03-15', '2024-03-10', 'completed', 'APC2024-001', NULL, 1200.00, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(2, 2, 'Drug Interaction Seminar', 'seminar', 'Medical Association', '2024-02-20', '2024-02-20', '2024-02-20', 'completed', NULL, NULL, 250.00, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(3, 3, 'Clinical Pharmacy Update', 'workshop', 'Pharmacy College', '2024-01-10', '2024-01-12', '2024-01-12', 'completed', 'CPU2024-002', NULL, 800.00, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(4, 4, 'Customer Service Excellence', 'internal_training', 'Pharmacy Team', '2024-02-01', '2024-02-03', '2024-02-03', 'completed', NULL, NULL, 0.00, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(5, 4, 'Point of Sale Systems', 'online_course', 'Tech Academy', '2024-03-01', '2024-03-15', NULL, 'in_progress', NULL, NULL, 150.00, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(6, 5, 'Basic Pharmacy Operations', 'internal_training', 'Pharmacy Team', '2024-01-15', '2024-01-17', '2024-01-17', 'completed', NULL, NULL, 0.00, NULL, '2025-11-14 14:20:15', '2025-11-14 14:20:15');

-- --------------------------------------------------------

--
-- Table structure for table `user_yearly_goals`
--

CREATE TABLE `user_yearly_goals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `sales_target` decimal(12,2) DEFAULT 0.00,
  `transactions_target` int(11) DEFAULT 0,
  `customers_target` int(11) DEFAULT 0,
  `performance_target` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_yearly_goals`
--

INSERT INTO `user_yearly_goals` (`id`, `user_id`, `year`, `sales_target`, `transactions_target`, `customers_target`, `performance_target`, `created_at`, `updated_at`) VALUES
(1, 2, 2024, 300000.00, 2300, 2000, 95.00, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(2, 2, 2025, 320000.00, 2400, 2100, 96.00, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(3, 3, 2024, 290000.00, 2200, 1900, 93.00, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(4, 3, 2025, 310000.00, 2300, 2000, 94.00, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(5, 4, 2024, 210000.00, 2100, 1850, 92.00, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(6, 4, 2025, 225000.00, 2200, 1950, 93.00, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(7, 5, 2024, 190000.00, 2000, 1700, 90.00, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(8, 5, 2025, 205000.00, 2100, 1800, 91.00, '2025-11-14 14:20:15', '2025-11-14 14:20:15');

-- --------------------------------------------------------

--
-- Table structure for table `user_yearly_performance`
--

CREATE TABLE `user_yearly_performance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `sales_total` decimal(12,2) DEFAULT 0.00,
  `transactions_count` int(11) DEFAULT 0,
  `customers_served` int(11) DEFAULT 0,
  `work_hours` decimal(8,2) DEFAULT 0.00,
  `performance_score` decimal(5,2) DEFAULT 0.00,
  `attendance_rate` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_yearly_performance`
--

INSERT INTO `user_yearly_performance` (`id`, `user_id`, `year`, `sales_total`, `transactions_count`, `customers_served`, `work_hours`, `performance_score`, `attendance_rate`, `created_at`, `updated_at`) VALUES
(1, 1, 2023, 0.00, 0, 0, 2080.00, 95.50, 98.20, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(2, 1, 2024, 0.00, 0, 0, 1560.00, 96.20, 99.10, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(3, 2, 2023, 285000.50, 2150, 1890, 2040.00, 92.80, 96.50, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(4, 2, 2024, 298750.25, 2280, 1950, 1520.00, 94.20, 97.80, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(5, 3, 2023, 275000.00, 2050, 1820, 2060.00, 90.50, 95.20, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(6, 3, 2024, 287500.75, 2180, 1875, 1540.00, 91.80, 96.40, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(7, 4, 2022, 180000.25, 1850, 1620, 1920.00, 88.20, 94.10, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(8, 4, 2023, 195000.50, 1950, 1705, 2000.00, 89.50, 95.80, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(9, 4, 2024, 205000.75, 2080, 1840, 1480.00, 90.80, 96.20, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(10, 5, 2022, 165000.00, 1720, 1480, 1880.00, 87.50, 93.80, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(11, 5, 2023, 175000.25, 1820, 1560, 1960.00, 88.20, 94.50, '2025-11-14 14:20:15', '2025-11-14 14:20:15'),
(12, 5, 2024, 185000.50, 1920, 1680, 1460.00, 89.10, 95.20, '2025-11-14 14:20:15', '2025-11-14 14:20:15');

-- --------------------------------------------------------

--
-- Structure for view `customer_lifetime_value`
--
DROP TABLE IF EXISTS `customer_lifetime_value`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `customer_lifetime_value`  AS SELECT `c`.`id` AS `customer_id`, `c`.`name` AS `customer_name`, `c`.`email` AS `email`, `c`.`phone` AS `phone`, `c`.`created_at` AS `registration_date`, coalesce(`c`.`total_orders`,0) AS `total_orders`, coalesce(`c`.`total_spent`,0) AS `lifetime_value`, coalesce(`clp`.`points_balance`,0) AS `loyalty_points`, coalesce(`clp`.`tier_level`,'bronze') AS `tier_level`, to_days(curdate()) - to_days(`c`.`last_order_date`) AS `days_since_last_order`, CASE WHEN `c`.`last_order_date` is null THEN 'never_ordered' WHEN to_days(curdate()) - to_days(`c`.`last_order_date`) <= 30 THEN 'active' WHEN to_days(curdate()) - to_days(`c`.`last_order_date`) <= 90 THEN 'inactive' ELSE 'dormant' END AS `customer_status` FROM (`customers` `c` left join `customer_loyalty_points` `clp` on(`c`.`id` = `clp`.`customer_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `customer_order_summary`
--
DROP TABLE IF EXISTS `customer_order_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `customer_order_summary`  AS SELECT `c`.`id` AS `customer_id`, `c`.`name` AS `customer_name`, `c`.`email` AS `email`, `c`.`phone` AS `phone`, count(`co`.`id`) AS `total_orders`, coalesce(sum(case when `co`.`status` = 'completed' then `co`.`final_amount` end),0) AS `total_spent`, coalesce(avg(case when `co`.`status` = 'completed' then `co`.`final_amount` end),0) AS `avg_order_value`, max(`co`.`order_date`) AS `last_order_date`, count(case when `co`.`status` = 'completed' then 1 end) AS `completed_orders`, count(case when `co`.`status` = 'cancelled' then 1 end) AS `cancelled_orders` FROM (`customers` `c` left join `customer_orders` `co` on(`c`.`id` = `co`.`customer_id`)) GROUP BY `c`.`id`, `c`.`name`, `c`.`email`, `c`.`phone` ;

-- --------------------------------------------------------

--
-- Structure for view `daily_sales_summary`
--
DROP TABLE IF EXISTS `daily_sales_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_sales_summary`  AS SELECT cast(`co`.`order_date` as date) AS `sale_date`, count(`co`.`id`) AS `total_orders`, count(case when `co`.`status` = 'completed' then 1 end) AS `completed_orders`, count(case when `co`.`status` = 'cancelled' then 1 end) AS `cancelled_orders`, coalesce(sum(case when `co`.`status` = 'completed' then `co`.`final_amount` end),0) AS `total_revenue`, coalesce(avg(case when `co`.`status` = 'completed' then `co`.`final_amount` end),0) AS `avg_order_value`, count(distinct `co`.`customer_id`) AS `unique_customers` FROM `customer_orders` AS `co` WHERE `co`.`order_date` >= curdate() - interval 30 day GROUP BY cast(`co`.`order_date` as date) ORDER BY cast(`co`.`order_date` as date) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `medicine_profitability_analysis`
--
DROP TABLE IF EXISTS `medicine_profitability_analysis`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `medicine_profitability_analysis`  AS SELECT `m`.`id` AS `medicine_id`, `m`.`name` AS `medicine_name`, `m`.`selling_price` AS `selling_price`, `m`.`cost_price` AS `cost_price`, `m`.`selling_price`- `m`.`cost_price` AS `profit_per_unit`, CASE WHEN `m`.`cost_price` > 0 THEN round((`m`.`selling_price` - `m`.`cost_price`) / `m`.`cost_price` * 100,2) ELSE 0 END AS `profit_margin_percent`, `m`.`stock_quantity` AS `stock_quantity`, `m`.`stock_quantity`* (`m`.`selling_price` - `m`.`cost_price`) AS `potential_profit`, count(`coi`.`id`) AS `times_sold`, coalesce(sum(`coi`.`quantity`),0) AS `total_quantity_sold`, coalesce(sum(`coi`.`total_price`),0) AS `total_revenue`, coalesce(sum(`coi`.`quantity` * `coi`.`cost_price`),0) AS `total_cost`, coalesce(sum(`coi`.`total_price`) - sum(`coi`.`quantity` * `coi`.`cost_price`),0) AS `total_profit` FROM ((`medicines` `m` left join `customer_order_items` `coi` on(`m`.`id` = `coi`.`medicine_id`)) left join `customer_orders` `co` on(`coi`.`order_id` = `co`.`id` and `co`.`status` = 'completed')) WHERE `m`.`is_active` = 1 GROUP BY `m`.`id`, `m`.`name`, `m`.`selling_price`, `m`.`cost_price`, `m`.`stock_quantity` ;

-- --------------------------------------------------------

--
-- Structure for view `medicine_sales_analytics`
--
DROP TABLE IF EXISTS `medicine_sales_analytics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `medicine_sales_analytics`  AS SELECT `m`.`id` AS `medicine_id`, `m`.`name` AS `medicine_name`, `m`.`category_id` AS `category_id`, `cat`.`name` AS `category_name`, `m`.`stock_quantity` AS `stock_quantity`, `m`.`unit_price` AS `unit_price`, count(`coi`.`id`) AS `times_ordered`, coalesce(sum(`coi`.`quantity`),0) AS `total_quantity_sold`, coalesce(sum(`coi`.`total_price`),0) AS `total_revenue`, max(`co`.`order_date`) AS `last_sold_date` FROM (((`medicines` `m` left join `categories` `cat` on(`m`.`category_id` = `cat`.`id`)) left join `customer_order_items` `coi` on(`m`.`id` = `coi`.`medicine_id`)) left join `customer_orders` `co` on(`coi`.`order_id` = `co`.`id` and `co`.`status` = 'completed')) WHERE `m`.`is_active` = 1 GROUP BY `m`.`id`, `m`.`name`, `m`.`category_id`, `cat`.`name`, `m`.`stock_quantity`, `m`.`unit_price` ;

-- --------------------------------------------------------

--
-- Structure for view `monthly_business_performance`
--
DROP TABLE IF EXISTS `monthly_business_performance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `monthly_business_performance`  AS SELECT date_format(`co`.`order_date`,'%Y-%m') AS `month`, count(distinct `co`.`id`) AS `total_orders`, count(distinct `co`.`customer_id`) AS `unique_customers`, count(distinct `coi`.`medicine_id`) AS `unique_medicines_sold`, avg(`co`.`final_amount`) AS `avg_order_value`, sum(`co`.`final_amount`) AS `total_revenue`, sum(`coi`.`quantity` * `coi`.`cost_price`) AS `total_cost`, sum(`co`.`final_amount`) - sum(`coi`.`quantity` * `coi`.`cost_price`) AS `gross_profit`, count(case when `co`.`status` = 'completed' then 1 end) AS `completed_orders`, count(case when `co`.`status` = 'cancelled' then 1 end) AS `cancelled_orders`, round(count(case when `co`.`status` = 'completed' then 1 end) * 100.0 / count(0),2) AS `completion_rate` FROM (`customer_orders` `co` join `customer_order_items` `coi` on(`co`.`id` = `coi`.`order_id`)) WHERE `co`.`order_date` >= curdate() - interval 12 month GROUP BY date_format(`co`.`order_date`,'%Y-%m') ORDER BY date_format(`co`.`order_date`,'%Y-%m') DESC ;

-- --------------------------------------------------------

--
-- Structure for view `monthly_expense_summary`
--
DROP TABLE IF EXISTS `monthly_expense_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `monthly_expense_summary`  AS SELECT date_format(`expenses`.`expense_date`,'%Y-%m') AS `month`, `expenses`.`category` AS `category`, count(0) AS `transaction_count`, sum(`expenses`.`amount`) AS `total_amount` FROM `expenses` GROUP BY date_format(`expenses`.`expense_date`,'%Y-%m'), `expenses`.`category` ORDER BY date_format(`expenses`.`expense_date`,'%Y-%m') DESC, sum(`expenses`.`amount`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `profit_loss_summary`
--
DROP TABLE IF EXISTS `profit_loss_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `profit_loss_summary`  AS SELECT date_format(`s`.`sale_date`,'%Y-%m') AS `month`, sum(`s`.`final_amount`) AS `total_revenue`, sum(`si`.`quantity` * `si`.`cost_price`) AS `cogs`, sum(`s`.`final_amount`) - sum(`si`.`quantity` * `si`.`cost_price`) AS `gross_profit`, (select coalesce(sum(`expenses`.`amount`),0) from `expenses` where date_format(`expenses`.`expense_date`,'%Y-%m') = date_format(`s`.`sale_date`,'%Y-%m')) AS `operating_expenses`, sum(`s`.`final_amount`) - sum(`si`.`quantity` * `si`.`cost_price`) - (select coalesce(sum(`expenses`.`amount`),0) from `expenses` where date_format(`expenses`.`expense_date`,'%Y-%m') = date_format(`s`.`sale_date`,'%Y-%m')) AS `net_profit` FROM (`sales` `s` left join `sale_items` `si` on(`s`.`id` = `si`.`sale_id`)) GROUP BY date_format(`s`.`sale_date`,'%Y-%m') ORDER BY date_format(`s`.`sale_date`,'%Y-%m') DESC ;

-- --------------------------------------------------------

--
-- Structure for view `supplier_balance_summary`
--
DROP TABLE IF EXISTS `supplier_balance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `supplier_balance_summary`  AS SELECT `s`.`id` AS `id`, `s`.`company_name` AS `company_name`, `s`.`contact_person` AS `contact_person`, `s`.`phone` AS `phone`, `s`.`email` AS `email`, `s`.`outstanding_balance` AS `outstanding_balance`, `s`.`credit_limit` AS `credit_limit`, `s`.`last_payment_date` AS `last_payment_date`, coalesce(sum(`po`.`total_amount`),0) AS `total_purchases`, coalesce(sum(`sp`.`amount`),0) AS `total_payments`, coalesce(sum(`po`.`total_amount`),0) - coalesce(sum(`sp`.`amount`),0) AS `calculated_balance`, count(distinct `po`.`id`) AS `total_orders`, count(distinct `sp`.`id`) AS `total_payments_count` FROM ((`suppliers` `s` left join `purchase_orders` `po` on(`s`.`id` = `po`.`supplier_id`)) left join `supplier_payments` `sp` on(`s`.`id` = `sp`.`supplier_id`)) GROUP BY `s`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `api_rate_limits`
--
ALTER TABLE `api_rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_api_rate_limits_ip` (`ip_address`),
  ADD KEY `idx_api_rate_limits_endpoint` (`endpoint`),
  ADD KEY `idx_api_rate_limits_window` (`window_start`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_categories_parent` (`parent_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_customers_created` (`created_at`),
  ADD KEY `idx_customers_email` (`email`),
  ADD KEY `idx_customers_verification` (`verification_code`),
  ADD KEY `idx_customers_reset_token` (`reset_token`),
  ADD KEY `idx_customers_remember_token` (`remember_token`);

--
-- Indexes for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `idx_customer_feedback_customer` (`customer_id`),
  ADD KEY `idx_customer_feedback_type` (`feedback_type`),
  ADD KEY `idx_customer_feedback_status` (`status`),
  ADD KEY `idx_customer_feedback_status_date` (`status`,`created_at`);

--
-- Indexes for table `customer_login_logs`
--
ALTER TABLE `customer_login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_login_logs_customer` (`customer_id`),
  ADD KEY `idx_customer_login_logs_email` (`email`),
  ADD KEY `idx_customer_login_logs_ip` (`ip_address`),
  ADD KEY `idx_customer_login_logs_created` (`created_at`);

--
-- Indexes for table `customer_loyalty_points`
--
ALTER TABLE `customer_loyalty_points`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_loyalty_customer` (`customer_id`),
  ADD KEY `idx_customer_loyalty_tier` (`tier_level`),
  ADD KEY `idx_customer_loyalty_points_balance` (`points_balance`);

--
-- Indexes for table `customer_loyalty_transactions`
--
ALTER TABLE `customer_loyalty_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `idx_loyalty_transactions_customer` (`customer_id`),
  ADD KEY `idx_loyalty_transactions_type` (`transaction_type`),
  ADD KEY `idx_loyalty_transactions_date` (`created_at`);

--
-- Indexes for table `customer_notifications`
--
ALTER TABLE `customer_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_notifications_customer` (`customer_id`),
  ADD KEY `idx_customer_notifications_order` (`order_id`),
  ADD KEY `idx_customer_notifications_type` (`type`),
  ADD KEY `idx_customer_notifications_read` (`is_read`);

--
-- Indexes for table `customer_orders`
--
ALTER TABLE `customer_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_reference` (`order_reference`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_customer_orders_customer` (`customer_id`),
  ADD KEY `idx_customer_orders_status` (`status`),
  ADD KEY `idx_customer_orders_date` (`order_date`),
  ADD KEY `idx_customer_orders_payment` (`payment_status`),
  ADD KEY `idx_customer_orders_status_date` (`status`,`order_date`),
  ADD KEY `idx_customer_orders_customer_status` (`customer_id`,`status`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `idx_order_reference` (`order_reference`);

--
-- Indexes for table `customer_order_items`
--
ALTER TABLE `customer_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_order_items_order` (`order_id`),
  ADD KEY `idx_customer_order_items_medicine` (`medicine_id`);

--
-- Indexes for table `customer_password_resets`
--
ALTER TABLE `customer_password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_password_resets_email` (`email`),
  ADD KEY `idx_password_resets_token` (`token`),
  ADD KEY `idx_password_resets_expires` (`expires_at`);

--
-- Indexes for table `customer_portal_analytics`
--
ALTER TABLE `customer_portal_analytics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date` (`date`),
  ADD KEY `idx_customer_portal_analytics_date` (`date`);

--
-- Indexes for table `customer_sessions`
--
ALTER TABLE `customer_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_customer_sessions_token` (`session_token`),
  ADD KEY `idx_customer_sessions_customer` (`customer_id`),
  ADD KEY `idx_customer_sessions_expires` (`expires_at`);

--
-- Indexes for table `email_campaigns`
--
ALTER TABLE `email_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_email_campaigns_status` (`status`),
  ADD KEY `idx_email_campaigns_scheduled` (`scheduled_at`);

--
-- Indexes for table `email_campaign_recipients`
--
ALTER TABLE `email_campaign_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_campaign_recipients_campaign` (`campaign_id`),
  ADD KEY `idx_campaign_recipients_customer` (`customer_id`),
  ADD KEY `idx_campaign_recipients_status` (`status`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_expenses_date` (`expense_date`),
  ADD KEY `idx_expenses_category` (`category`),
  ADD KEY `idx_expenses_recurring` (`is_recurring`);

--
-- Indexes for table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_medicines_stock` (`stock_quantity`,`minimum_stock`),
  ADD KEY `idx_medicines_expiry` (`expiry_date`),
  ADD KEY `idx_medicines_active_stock` (`is_active`,`stock_quantity`),
  ADD KEY `idx_medicines_category_active` (`category_id`,`is_active`),
  ADD KEY `idx_medicines_expiry_active` (`expiry_date`,`is_active`);

--
-- Indexes for table `medicine_batches`
--
ALTER TABLE `medicine_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_medicine_batch` (`medicine_id`,`batch_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_medicine_batches_medicine` (`medicine_id`),
  ADD KEY `idx_medicine_batches_batch` (`batch_number`),
  ADD KEY `idx_medicine_batches_expiry` (`expiry_date`),
  ADD KEY `idx_medicine_batches_expiry_active` (`expiry_date`,`is_active`);

--
-- Indexes for table `medicine_images`
--
ALTER TABLE `medicine_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medicine_images_medicine` (`medicine_id`),
  ADD KEY `idx_medicine_images_type` (`image_type`);

--
-- Indexes for table `medicine_reviews`
--
ALTER TABLE `medicine_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `idx_medicine_reviews_medicine` (`medicine_id`),
  ADD KEY `idx_medicine_reviews_customer` (`customer_id`),
  ADD KEY `idx_medicine_reviews_rating` (`rating`);

--
-- Indexes for table `medicine_search_analytics`
--
ALTER TABLE `medicine_search_analytics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medicine_search_term` (`search_term`),
  ADD KEY `idx_medicine_search_date` (`search_date`),
  ADD KEY `idx_medicine_search_clicked` (`clicked_medicine_id`),
  ADD KEY `idx_medicine_search_term_date` (`search_term`,`search_date`);

--
-- Indexes for table `medicine_stock_alerts`
--
ALTER TABLE `medicine_stock_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resolved_by` (`resolved_by`),
  ADD KEY `idx_medicine_stock_alerts_medicine` (`medicine_id`),
  ADD KEY `idx_medicine_stock_alerts_type` (`alert_type`),
  ADD KEY `idx_medicine_stock_alerts_status` (`is_active`,`is_resolved`);

--
-- Indexes for table `medicine_tags`
--
ALTER TABLE `medicine_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `medicine_tag_mappings`
--
ALTER TABLE `medicine_tag_mappings`
  ADD PRIMARY KEY (`medicine_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_order_status_history_order` (`order_id`),
  ADD KEY `idx_order_status_history_date` (`created_at`);

--
-- Indexes for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_transactions_order` (`order_id`),
  ADD KEY `idx_payment_transactions_customer` (`customer_id`),
  ADD KEY `idx_payment_transactions_status` (`status`),
  ADD KEY `idx_payment_transactions_method` (`payment_method`),
  ADD KEY `idx_payment_transactions_checkout` (`checkout_request_id`),
  ADD KEY `idx_payment_transactions_date` (`created_at`),
  ADD KEY `idx_payment_transactions_status_date` (`status`,`created_at`);

--
-- Indexes for table `promotional_offers`
--
ALTER TABLE `promotional_offers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `promo_code` (`promo_code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_promotional_offers_code` (`promo_code`),
  ADD KEY `idx_promotional_offers_dates` (`start_date`,`end_date`),
  ADD KEY `idx_promotional_offers_active` (`is_active`),
  ADD KEY `idx_promotional_offers_dates_active` (`start_date`,`end_date`,`is_active`);

--
-- Indexes for table `promotional_offer_usage`
--
ALTER TABLE `promotional_offer_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `idx_promo_usage_offer` (`offer_id`),
  ADD KEY `idx_promo_usage_customer` (`customer_id`),
  ADD KEY `idx_promo_usage_date` (`used_at`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_sales_date` (`sale_date`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `medicine_id` (`medicine_id`);

--
-- Indexes for table `shopping_carts`
--
ALTER TABLE `shopping_carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shopping_carts_customer` (`customer_id`),
  ADD KEY `idx_shopping_carts_session` (`session_id`),
  ADD KEY `idx_shopping_carts_medicine` (`medicine_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicine_id` (`medicine_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_supplier_payments_supplier` (`supplier_id`),
  ADD KEY `idx_supplier_payments_date` (`payment_date`),
  ADD KEY `idx_supplier_payments_status` (`status`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_system_settings_key` (`setting_key`),
  ADD KEY `idx_system_settings_public` (`is_public`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_activity_user` (`user_id`),
  ADD KEY `idx_user_activity_type` (`activity_type`),
  ADD KEY `idx_user_activity_date` (`created_at`);

--
-- Indexes for table `user_attendance`
--
ALTER TABLE `user_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`date`);

--
-- Indexes for table `user_evaluations`
--
ALTER TABLE `user_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `evaluator_id` (`evaluator_id`);

--
-- Indexes for table `user_login_log`
--
ALTER TABLE `user_login_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_log_user` (`user_id`),
  ADD KEY `idx_login_log_session` (`session_id`),
  ADD KEY `idx_login_log_time` (`login_time`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_user_sessions_user` (`user_id`),
  ADD KEY `idx_user_sessions_activity` (`last_activity`);

--
-- Indexes for table `user_training`
--
ALTER TABLE `user_training`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_yearly_goals`
--
ALTER TABLE `user_yearly_goals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_goal_year` (`user_id`,`year`);

--
-- Indexes for table `user_yearly_performance`
--
ALTER TABLE `user_yearly_performance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_year` (`user_id`,`year`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `api_rate_limits`
--
ALTER TABLE `api_rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_login_logs`
--
ALTER TABLE `customer_login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `customer_loyalty_points`
--
ALTER TABLE `customer_loyalty_points`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_loyalty_transactions`
--
ALTER TABLE `customer_loyalty_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_notifications`
--
ALTER TABLE `customer_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_orders`
--
ALTER TABLE `customer_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customer_order_items`
--
ALTER TABLE `customer_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customer_password_resets`
--
ALTER TABLE `customer_password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_portal_analytics`
--
ALTER TABLE `customer_portal_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_sessions`
--
ALTER TABLE `customer_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_campaigns`
--
ALTER TABLE `email_campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_campaign_recipients`
--
ALTER TABLE `email_campaign_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `medicine_batches`
--
ALTER TABLE `medicine_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medicine_images`
--
ALTER TABLE `medicine_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medicine_reviews`
--
ALTER TABLE `medicine_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medicine_search_analytics`
--
ALTER TABLE `medicine_search_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medicine_stock_alerts`
--
ALTER TABLE `medicine_stock_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `medicine_tags`
--
ALTER TABLE `medicine_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promotional_offers`
--
ALTER TABLE `promotional_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `promotional_offer_usage`
--
ALTER TABLE `promotional_offer_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `shopping_carts`
--
ALTER TABLE `shopping_carts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_activity`
--
ALTER TABLE `user_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_attendance`
--
ALTER TABLE `user_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `user_evaluations`
--
ALTER TABLE `user_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_login_log`
--
ALTER TABLE `user_login_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_training`
--
ALTER TABLE `user_training`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_yearly_goals`
--
ALTER TABLE `user_yearly_goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_yearly_performance`
--
ALTER TABLE `user_yearly_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  ADD CONSTRAINT `customer_feedback_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_feedback_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_login_logs`
--
ALTER TABLE `customer_login_logs`
  ADD CONSTRAINT `customer_login_logs_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_login_logs_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_loyalty_points`
--
ALTER TABLE `customer_loyalty_points`
  ADD CONSTRAINT `customer_loyalty_points_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_loyalty_transactions`
--
ALTER TABLE `customer_loyalty_transactions`
  ADD CONSTRAINT `customer_loyalty_transactions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_loyalty_transactions_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_notifications`
--
ALTER TABLE `customer_notifications`
  ADD CONSTRAINT `customer_notifications_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_notifications_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_orders`
--
ALTER TABLE `customer_orders`
  ADD CONSTRAINT `customer_orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `customer_orders_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_orders_ibfk_3` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_order_items`
--
ALTER TABLE `customer_order_items`
  ADD CONSTRAINT `customer_order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_order_items_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`);

--
-- Constraints for table `customer_sessions`
--
ALTER TABLE `customer_sessions`
  ADD CONSTRAINT `customer_sessions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_sessions_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_campaigns`
--
ALTER TABLE `email_campaigns`
  ADD CONSTRAINT `email_campaigns_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `email_campaign_recipients`
--
ALTER TABLE `email_campaign_recipients`
  ADD CONSTRAINT `email_campaign_recipients_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `email_campaigns` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `email_campaign_recipients_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medicines`
--
ALTER TABLE `medicines`
  ADD CONSTRAINT `medicines_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `medicines_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `medicine_batches`
--
ALTER TABLE `medicine_batches`
  ADD CONSTRAINT `medicine_batches_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicine_batches_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medicine_images`
--
ALTER TABLE `medicine_images`
  ADD CONSTRAINT `medicine_images_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medicine_reviews`
--
ALTER TABLE `medicine_reviews`
  ADD CONSTRAINT `medicine_reviews_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicine_reviews_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicine_reviews_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medicine_search_analytics`
--
ALTER TABLE `medicine_search_analytics`
  ADD CONSTRAINT `medicine_search_analytics_ibfk_1` FOREIGN KEY (`clicked_medicine_id`) REFERENCES `medicines` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medicine_stock_alerts`
--
ALTER TABLE `medicine_stock_alerts`
  ADD CONSTRAINT `medicine_stock_alerts_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicine_stock_alerts_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medicine_tag_mappings`
--
ALTER TABLE `medicine_tag_mappings`
  ADD CONSTRAINT `medicine_tag_mappings_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicine_tag_mappings_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `medicine_tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD CONSTRAINT `payment_transactions_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`id`),
  ADD CONSTRAINT `payment_transactions_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `promotional_offers`
--
ALTER TABLE `promotional_offers`
  ADD CONSTRAINT `promotional_offers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `promotional_offer_usage`
--
ALTER TABLE `promotional_offer_usage`
  ADD CONSTRAINT `promotional_offer_usage_ibfk_1` FOREIGN KEY (`offer_id`) REFERENCES `promotional_offers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `promotional_offer_usage_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `promotional_offer_usage_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`);

--
-- Constraints for table `shopping_carts`
--
ALTER TABLE `shopping_carts`
  ADD CONSTRAINT `shopping_carts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shopping_carts_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD CONSTRAINT `supplier_payments_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `supplier_payments_ibfk_2` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `supplier_payments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `supplier_payments_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD CONSTRAINT `user_activity_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_attendance`
--
ALTER TABLE `user_attendance`
  ADD CONSTRAINT `user_attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_evaluations`
--
ALTER TABLE `user_evaluations`
  ADD CONSTRAINT `user_evaluations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_evaluations_ibfk_2` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_login_log`
--
ALTER TABLE `user_login_log`
  ADD CONSTRAINT `user_login_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_training`
--
ALTER TABLE `user_training`
  ADD CONSTRAINT `user_training_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_yearly_goals`
--
ALTER TABLE `user_yearly_goals`
  ADD CONSTRAINT `user_yearly_goals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_yearly_performance`
--
ALTER TABLE `user_yearly_performance`
  ADD CONSTRAINT `user_yearly_performance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
