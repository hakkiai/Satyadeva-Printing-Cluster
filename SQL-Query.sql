-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 29, 2025 at 06:54 PM
-- Server version: 8.0.36
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `admin_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `ctp`
--

CREATE TABLE `ctp` (
  `id` int NOT NULL,
  `job_sheet_id` int NOT NULL,
  `ctp_plate` varchar(50) DEFAULT NULL,
  `ctp_quantity` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `firm_name` varchar(255) NOT NULL,
  `firm_location` varchar(255) NOT NULL,
  `gst_number` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `address` text,
  `member_status` tinyint(1) DEFAULT '0',
  `is_member` enum('member','non-member') NOT NULL,
  `balance_limit` decimal(15,2) DEFAULT '0.00',
  `total_balance` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_name`, `firm_name`, `firm_location`, `gst_number`, `email`, `phone_number`, `address`, `member_status`, `is_member`, `balance_limit`, `total_balance`) VALUES
(1, 'Abhishek', 'Galactic Solutions', 'Mars', '22K1A5544223', 'askaladdd@gmail.com', '1111111111', 'near andromeda galaxy', 0, 'member', 10.00, 50.00),
(2, 'Karthik', 'Mars Solution', 'Mars', '22K1A5544224', 'karthik@gmail.com', '2222222222', 'some where far', 0, 'member', 0.00, 120.00),
(3, 'Siri', 'Mercury Interprises', 'Mercury', '234SDLKFA23S', 'siri@gmail.com', '5555555555', 'Somewhere far', 0, 'member', 0.00, 14000.00),
(4, 'Sowmya', 'Earth Solutions', 'Earth', '234SLDFKJ234LS', 'Sowmya@gmail.com', '6766666666', 'Mr. Everest', 0, 'member', 0.00, 0.00),
(5, 'Havyasha', 'Havyasha Enterprises', 'Atlantic Ocen', '34234SLKDFJ2234', 'Havyasha@gmail.com', '3423232323', 'Deep ocen, Earth core', 0, 'member', 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `customer_jobs`
--

CREATE TABLE `customer_jobs` (
  `id` int NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `job_name` varchar(255) DEFAULT NULL,
  `paper_subcategory` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `printing_type` varchar(255) DEFAULT NULL,
  `striking` int DEFAULT NULL,
  `machine` varchar(50) DEFAULT NULL,
  `ryobi_type` varchar(50) DEFAULT NULL,
  `web_type` varchar(50) DEFAULT NULL,
  `web_size` int DEFAULT NULL,
  `ctp_plate` varchar(50) DEFAULT NULL,
  `ctp_quantity` int DEFAULT NULL,
  `customer_type` varchar(50) DEFAULT NULL,
  `paper_charges` decimal(10,2) DEFAULT NULL,
  `plating_charges` decimal(10,2) DEFAULT NULL,
  `lamination_charges` decimal(10,2) DEFAULT NULL,
  `pinning_charges` decimal(10,2) DEFAULT NULL,
  `binding_charges` decimal(10,2) DEFAULT NULL,
  `finishing_charges` decimal(10,2) DEFAULT NULL,
  `other_charges` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `total_charges` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dispatch_jobs`
--

CREATE TABLE `dispatch_jobs` (
  `id` int NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `job_name` varchar(255) NOT NULL,
  `total_charges` decimal(10,2) NOT NULL,
  `description` text,
  `payment_status` enum('incomplete','partially_paid','uncredit','completed') DEFAULT NULL,
  `balance` decimal(10,2) NOT NULL,
  `dispatched_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `dispatch_jobs`
--

INSERT INTO `dispatch_jobs` (`id`, `customer_name`, `job_name`, `total_charges`, `description`, `payment_status`, `balance`, `dispatched_at`, `updated_at`) VALUES
(2, 'Abhishek', '', 50.00, '0', 'completed', 0.00, '2025-04-12 17:58:35', '2025-04-12 17:59:21');

--
-- Triggers `dispatch_jobs`
--
DELIMITER $$
CREATE TRIGGER `after_dispatch_insert` AFTER INSERT ON `dispatch_jobs` FOR EACH ROW BEGIN
    INSERT INTO jobsheet_progress_history (job_sheet_id, stage)
    VALUES (NEW.id, 'Dispatched');
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int NOT NULL,
  `invoice_number` int NOT NULL,
  `vendor_id` int NOT NULL,
  `item_id` int NOT NULL,
  `quantity` int NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `invoice_number`, `vendor_id`, `item_id`, `quantity`, `price_per_unit`, `created_at`) VALUES
(1, 1, 1, 1, 1000, 10.00, '2025-04-12 12:50:11'),
(2, 2, 1, 4, 1000, 1.50, '2025-04-15 13:23:09');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_categories`
--

CREATE TABLE `inventory_categories` (
  `id` int NOT NULL,
  `category_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory_categories`
--

INSERT INTO `inventory_categories` (`id`, `category_name`) VALUES
(1, 'Paper'),
(2, 'Plates'),
(3, 'Ink');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `subcategory_id` int DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT '0.00',
  `unit` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_name`, `subcategory_id`, `quantity`, `unit`) VALUES
(1, '7.2 kg (dummy)', 1, 0.00, 'kg'),
(2, '8 kg (DFC)', 1, 0.00, 'kg'),
(3, '10.5 kg (Double Crown)', 1, 0.00, 'kg'),
(4, '9 kg (dummy)', 2, 0.00, 'kg'),
(5, '10 kg (DFC)', 2, 0.00, 'kg'),
(6, '13.6 kg (Double Crown)', 2, 0.00, 'kg'),
(7, '90 GSM', 3, 0.00, 'GSM'),
(8, '130 GSM', 3, 0.00, 'GSM'),
(9, '170 GSM', 3, 0.00, 'GSM'),
(10, '250 GSM', 3, 0.00, 'GSM'),
(11, '300 GSM', 3, 0.00, 'GSM'),
(12, '90 GSM', 4, 0.00, 'GSM'),
(13, '130 GSM', 4, 0.00, 'GSM'),
(14, '170 GSM', 4, 0.00, 'GSM'),
(15, '700 x 945', 5, 0.00, 'piece'),
(16, '610 x 890', 5, 0.00, 'piece'),
(17, '605 x 760', 5, 0.00, 'piece'),
(18, '560 x 670', 5, 0.00, 'piece'),
(19, '335 x 485', 5, 0.00, 'piece'),
(20, 'Cyan', 6, 0.00, 'kg'),
(21, 'Magenta', 6, 0.00, 'kg'),
(22, 'Yellow', 6, 0.00, 'kg'),
(23, 'Black', 6, 0.00, 'kg'),
(24, 'Web Ink', 7, 0.00, 'kg'),
(25, 'Black (Well Print)', 7, 0.00, 'kg'),
(26, 'Royale Blue', 7, 0.00, 'kg'),
(27, 'Green', 7, 0.00, 'kg'),
(28, 'Red', 7, 0.00, 'kg'),
(29, 'Brown', 7, 0.00, 'kg'),
(30, 'Yellow', 7, 0.00, 'kg'),
(31, '300 GSM ART BOARD', 8, 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items_copy`
--

CREATE TABLE `inventory_items_copy` (
  `id` int NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `subcategory_id` int DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT '0.00',
  `unit` varchar(50) DEFAULT NULL,
  `utilised_quantity` decimal(10,2) DEFAULT '0.00',
  `balance` decimal(10,2) GENERATED ALWAYS AS ((`quantity` - `utilised_quantity`)) STORED,
  `active_status` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory_items_copy`
--

INSERT INTO `inventory_items_copy` (`id`, `item_name`, `subcategory_id`, `quantity`, `unit`, `utilised_quantity`, `active_status`) VALUES
(1, '7.2 kg (dummy)', 1, 100.00, 'kg', 100.00, 0),
(2, '8 kg (DFC)', 1, 0.00, 'kg', 0.00, 0),
(3, '10.5 kg (Double Crown)', 1, 0.00, 'kg', 0.00, 0),
(4, '9 kg (dummy)', 2, 0.00, 'kg', 0.00, 0),
(5, '10 kg (DFC)', 2, 0.00, 'kg', 0.00, 0),
(6, '13.6 kg (Double Crown)', 2, 0.00, 'kg', 0.00, 0),
(7, '90 GSM', 3, 0.00, 'GSM', 0.00, 0),
(8, '130 GSM', 3, 0.00, 'GSM', 0.00, 0),
(9, '170 GSM', 3, 0.00, 'GSM', 0.00, 0),
(10, '250 GSM', 3, 0.00, 'GSM', 0.00, 0),
(11, '300 GSM', 3, 0.00, 'GSM', 0.00, 0),
(12, '90 GSM', 4, 0.00, 'GSM', 0.00, 0),
(13, '130 GSM', 4, 0.00, 'GSM', 0.00, 0),
(14, '170 GSM', 4, 0.00, 'GSM', 0.00, 0),
(15, '700 x 945', 5, 0.00, 'piece', 0.00, 0),
(16, '610 x 890', 5, 0.00, 'piece', 0.00, 0),
(17, '605 x 760', 5, 0.00, 'piece', 0.00, 0),
(18, '560 x 670', 5, 0.00, 'piece', 0.00, 0),
(19, '335 x 485', 5, 0.00, 'piece', 0.00, 0),
(20, 'Cyan', 6, 0.00, 'kg', 0.00, 1),
(21, 'Magenta', 6, 0.00, 'kg', 0.00, 1),
(22, 'Yellow', 6, 0.00, 'kg', 0.00, 1),
(23, 'Black', 6, 0.00, 'kg', 0.00, 1),
(24, 'Web Ink', 7, 0.00, 'kg', 0.00, 1),
(25, 'Black (Well Print)', 7, 0.00, 'kg', 0.00, 1),
(26, 'Royale Blue', 7, 0.00, 'kg', 0.00, 1),
(27, 'Green', 7, 0.00, 'kg', 0.00, 1),
(28, 'Red', 7, 0.00, 'kg', 0.00, 1),
(29, 'Brown', 7, 0.00, 'kg', 0.00, 1),
(30, 'Yellow', 7, 0.00, 'kg', 0.00, 1);

--
-- Triggers `inventory_items_copy`
--
DELIMITER $$
CREATE TRIGGER `update_active_status` BEFORE UPDATE ON `inventory_items_copy` FOR EACH ROW BEGIN
    IF (NEW.quantity - NEW.utilised_quantity) <= 0 THEN
        SET NEW.active_status = 0;
    ELSE
        SET NEW.active_status = 1;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_subcategories`
--

CREATE TABLE `inventory_subcategories` (
  `id` int NOT NULL,
  `subcategory_name` varchar(255) NOT NULL,
  `category_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory_subcategories`
--

INSERT INTO `inventory_subcategories` (`id`, `subcategory_name`, `category_id`) VALUES
(1, 'White Paper', 1),
(2, 'Maplito', 1),
(3, 'Hardpaper D/D', 1),
(4, 'Hardpaper D/C', 1),
(5, 'Halfset', 2),
(6, 'Multicolor', 3),
(7, 'Single Color', 3),
(8, 'board', 1);

-- --------------------------------------------------------

--
-- Table structure for table `invoice`
--

CREATE TABLE `invoice` (
  `invoice_number` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `invoice`
--

INSERT INTO `invoice` (`invoice_number`) VALUES
(1),
(2);

-- --------------------------------------------------------

--
-- Table structure for table `jobsheet_progress_history`
--

CREATE TABLE `jobsheet_progress_history` (
  `id` int NOT NULL,
  `job_sheet_id` int NOT NULL,
  `stage` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `jobsheet_progress_history`
--

INSERT INTO `jobsheet_progress_history` (`id`, `job_sheet_id`, `stage`, `created_at`) VALUES
(1, 2, 'Finalized', '2025-04-12 13:01:30'),
(2, 2, 'Sent to CTP', '2025-04-12 13:01:30'),
(3, 2, 'Completed CTP', '2025-04-12 13:01:30'),
(4, 2, 'Sent to Delivery', '2025-04-12 13:01:30'),
(5, 2, 'Dispatched', '2025-04-12 17:58:35'),
(8, 3, 'Sent to Digital', '2025-04-14 05:38:51'),
(9, 3, 'Completed Digital', '2025-04-14 05:39:57'),
(10, 3, 'Sent to Delivery', '2025-04-14 05:41:16'),
(11, 4, 'Sent to CTP', '2025-04-14 13:56:20'),
(12, 4, 'Sent to Multicolour', '2025-04-14 13:56:20'),
(13, 5, 'Sent to CTP', '2025-04-15 13:30:25'),
(14, 5, 'Sent to Multicolour', '2025-04-15 13:30:25'),
(15, 5, 'Completed CTP', '2025-04-15 13:32:32');

-- --------------------------------------------------------

--
-- Table structure for table `job_sheets`
--

CREATE TABLE `job_sheets` (
  `id` int NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `phone_number` varchar(15) NOT NULL,
  `job_name` varchar(255) NOT NULL,
  `paper_subcategory` int DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `striking` varchar(50) DEFAULT NULL,
  `machine` varchar(50) DEFAULT NULL,
  `ryobi_type` varchar(50) DEFAULT NULL,
  `web_type` varchar(50) DEFAULT NULL,
  `web_size` int DEFAULT NULL,
  `ctp_plate` varchar(50) DEFAULT NULL,
  `ctp_quantity` int DEFAULT NULL,
  `plating_charges` decimal(10,2) DEFAULT NULL,
  `paper_charges` decimal(10,2) DEFAULT NULL,
  `printing_charges` decimal(10,2) DEFAULT NULL,
  `lamination_charges` decimal(10,2) DEFAULT NULL,
  `pinning_charges` decimal(10,2) DEFAULT NULL,
  `binding_charges` decimal(10,2) DEFAULT NULL,
  `finishing_charges` decimal(10,2) DEFAULT NULL,
  `other_charges` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `total_charges` decimal(10,2) DEFAULT NULL,
  `status` enum('Draft','Approved','Finalized') NOT NULL DEFAULT 'Draft',
  `ctp` tinyint(1) DEFAULT '0',
  `multicolour` tinyint(1) DEFAULT '0',
  `description` text,
  `file_path` varchar(500) DEFAULT NULL,
  `completed` tinyint(1) DEFAULT '0',
  `payment_status` enum('incomplete','partially_paid','completed','partially_paid_credit') NOT NULL DEFAULT 'incomplete',
  `cash_amount` decimal(10,2) DEFAULT '0.00',
  `credit_amount` decimal(10,2) DEFAULT '0.00',
  `partial_amount` decimal(10,2) DEFAULT '0.00',
  `completed_ctp` tinyint(1) DEFAULT '0',
  `completed_multicolour` tinyint(1) DEFAULT '0',
  `completed_delivery` tinyint(1) DEFAULT '0',
  `digital` tinyint(1) DEFAULT '0',
  `completed_digital` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `job_sheets`
--

INSERT INTO `job_sheets` (`id`, `customer_name`, `phone_number`, `job_name`, `paper_subcategory`, `type`, `quantity`, `striking`, `machine`, `ryobi_type`, `web_type`, `web_size`, `ctp_plate`, `ctp_quantity`, `plating_charges`, `paper_charges`, `printing_charges`, `lamination_charges`, `pinning_charges`, `binding_charges`, `finishing_charges`, `other_charges`, `discount`, `total_charges`, `status`, `ctp`, `multicolour`, `description`, `file_path`, `completed`, `payment_status`, `cash_amount`, `credit_amount`, `partial_amount`, `completed_ctp`, `completed_multicolour`, `completed_delivery`, `digital`, `completed_digital`, `created_at`) VALUES
(2, 'Abhishek', '1111111111', '', 1, '1', 5, '5', 'DC', '', '', 0, '700', 0, 0.00, 50.00, 1900.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 50.00, 'Finalized', 1, 0, '', '../uploads/2_ctp_1744462944_activity-accountant.png', 0, 'completed', 0.00, 0.00, 0.00, 1, 0, 1, 0, 0, '2025-04-12 13:01:30'),
(3, 'Karthik', '2222222222', 'Sending people to mars', 8, '31', 10, '10', 'Digital', '', '', 0, '700', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 120.00, 'Finalized', 0, 0, '', '../uploads/3_1744609131_ideal ghibli.png', 0, 'completed', 0.00, 0.00, 0.00, 0, 0, 1, 1, 1, '2025-04-14 05:38:35'),
(4, 'Siri', '5555555555', 'Entering inside a black hole', 1, '1', 200, '200', 'DC', '', '', 0, '700', 2, 0.00, 2000.00, 1900.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2000.00, 'Finalized', 1, 1, '', '../uploads/4_1744638980_ChatGPT Image Apr 7, 2025, 01_31_56 PM.png', 0, 'incomplete', 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, '2025-04-14 13:37:40'),
(5, 'Siri', '5555555555', 'test-1', 2, '4', 6000, '12000', 'SDD', '', '', 0, '700', 4, 0.00, 12000.00, 6700.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 12000.00, 'Finalized', 1, 1, '', '../uploads/5_ctp_1744723947_ideal ghibli.png', 0, 'incomplete', 0.00, 0.00, 0.00, 1, 0, 0, 0, 0, '2025-04-15 13:30:00');

--
-- Triggers `job_sheets`
--
DELIMITER $$
CREATE TRIGGER `after_jobsheet_update` AFTER UPDATE ON `job_sheets` FOR EACH ROW BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO jobsheet_progress_history (job_sheet_id, stage)
        VALUES (NEW.id, NEW.status);
    END IF;

    IF NEW.ctp = 1 AND OLD.ctp = 0 THEN
        INSERT INTO jobsheet_progress_history (job_sheet_id, stage)
        VALUES (NEW.id, 'Sent to CTP');
    END IF;

    IF NEW.completed_ctp = 1 AND OLD.completed_ctp = 0 THEN
        INSERT INTO jobsheet_progress_history (job_sheet_id, stage)
        VALUES (NEW.id, 'Completed CTP');
    END IF;

    IF NEW.multicolour = 1 AND OLD.multicolour = 0 THEN
        INSERT INTO jobsheet_progress_history (job_sheet_id, stage)
        VALUES (NEW.id, 'Sent to Multicolour');
    END IF;

    IF NEW.completed_multicolour = 1 AND OLD.completed_multicolour = 0 THEN
        INSERT INTO jobsheet_progress_history (job_sheet_id, stage)
        VALUES (NEW.id, 'Completed Multicolour');
    END IF;

    IF NEW.digital = 1 AND OLD.digital = 0 THEN
        INSERT INTO jobsheet_progress_history (job_sheet_id, stage)
        VALUES (NEW.id, 'Sent to Digital');
    END IF;

    IF NEW.completed_digital = 1 AND OLD.completed_digital = 0 THEN
        INSERT INTO jobsheet_progress_history (job_sheet_id, stage)
        VALUES (NEW.id, 'Completed Digital');
    END IF;

    IF NEW.completed_delivery = 1 AND OLD.completed_delivery = 0 THEN
        INSERT INTO jobsheet_progress_history (job_sheet_id, stage)
        VALUES (NEW.id, 'Sent to Delivery');
    END IF;

    IF NEW.completed = 1 AND OLD.completed = 0 THEN
        INSERT INTO jobsheet_progress_history (job_sheet_id, stage)
        VALUES (NEW.id, 'Accounts');

        INSERT INTO jobsheet_progress_history (job_sheet_id, stage)
        VALUES (NEW.id, 'Completed');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payment_records`
--

CREATE TABLE `payment_records` (
  `id` int NOT NULL,
  `job_sheet_id` int NOT NULL,
  `job_sheet_name` varchar(255) NOT NULL,
  `date` datetime NOT NULL,
  `cash` decimal(10,2) DEFAULT '0.00',
  `credit` decimal(10,2) DEFAULT '0.00',
  `balance` decimal(10,2) DEFAULT '0.00',
  `payment_type` varchar(20) DEFAULT NULL,
  `payment_status` enum('partially_paid','completed','uncredit','incomplete') NOT NULL DEFAULT 'incomplete'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payment_records`
--

INSERT INTO `payment_records` (`id`, `job_sheet_id`, `job_sheet_name`, `date`, `cash`, `credit`, `balance`, `payment_type`, `payment_status`) VALUES
(1, 2, '', '2025-04-12 23:28:35', 20.00, 0.00, 30.00, 'cash', 'partially_paid'),
(2, 2, '', '2025-04-12 23:29:03', 10.00, 0.00, 20.00, 'cash', 'partially_paid'),
(3, 2, '', '2025-04-12 23:29:21', 20.00, 0.00, 0.00, 'cash', 'completed'),
(4, 3, 'Sending people to mars', '2025-04-14 11:13:15', 0.00, 0.00, 0.00, 'cash', 'completed');

-- --------------------------------------------------------

--
-- Table structure for table `pricing_table`
--

CREATE TABLE `pricing_table` (
  `category` varchar(50) NOT NULL,
  `member_first` int NOT NULL,
  `member_next` int NOT NULL,
  `non_member_first` int NOT NULL,
  `non_member_next` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pricing_table`
--

INSERT INTO `pricing_table` (`category`, `member_first`, `member_next`, `non_member_first`, `non_member_next`) VALUES
('DC', 2300, 400, 2500, 500),
('DD', 1800, 350, 1900, 400),
('RYOBI', 400, 100, 400, 150),
('RYOBI_COLOR', 500, 150, 500, 200),
('SDD', 2300, 400, 2500, 500),
('Web', 1300, 150, 1450, 200);

-- --------------------------------------------------------

--
-- Table structure for table `progress_stages`
--

CREATE TABLE `progress_stages` (
  `id` int NOT NULL,
  `stage_name` varchar(50) NOT NULL,
  `stage_order` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `progress_stages`
--

INSERT INTO `progress_stages` (`id`, `stage_name`, `stage_order`) VALUES
(1, 'Draft', 1),
(2, 'Approved', 2),
(3, 'Finalized', 3),
(4, 'Sent to CTP', 4),
(5, 'Completed CTP', 5),
(6, 'Sent to Multicolour', 6),
(7, 'Completed Multicolour', 7),
(8, 'Sent to Digital', 8),
(9, 'Completed Digital', 9),
(10, 'Sent to Delivery', 10),
(11, 'Dispatched', 11),
(12, 'Completed', 14),
(13, 'Accounts', 12);

-- --------------------------------------------------------

--
-- Table structure for table `sales_prices`
--

CREATE TABLE `sales_prices` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `quantity_per_unit` int NOT NULL,
  `unit_type` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sales_prices`
--

INSERT INTO `sales_prices` (`id`, `item_id`, `selling_price`, `quantity_per_unit`, `unit_type`, `created_at`, `updated_at`) VALUES
(1, 1, 10.00, 1000, 'Kilogram(KG)', '2025-04-12 12:51:05', '2025-04-12 12:51:05'),
(2, 4, 2.00, 1, 'Kilogram(KG)', '2025-04-15 13:15:38', '2025-04-15 13:15:38'),
(3, 4, 3.00, 1, 'Kilogram(KG)', '2025-04-15 13:24:11', '2025-04-15 13:24:11');

-- --------------------------------------------------------

--
-- Table structure for table `upi_settings`
--

CREATE TABLE `upi_settings` (
  `id` int NOT NULL,
  `upi_id` varchar(255) NOT NULL,
  `paper_charges_upi_id` varchar(255) DEFAULT NULL,
  `payee_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `upi_settings`
--

INSERT INTO `upi_settings` (`id`, `upi_id`, `paper_charges_upi_id`, `payee_name`, `created_at`) VALUES
(1, 'bharatpe.90066372269@fbpe', 'q728884354@ybl', 'SSK', '2025-04-12 12:42:39');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` enum('admin','super_admin','reception','ctp','accounts','multicolour','delivery','dispatch','digital') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `created_at`, `updated_at`, `active`) VALUES
(1, 'admin', 'admin', NULL, 'admin', '2025-04-12 12:42:39', '2025-04-12 12:42:39', 1),
(2, 'superadmin', 'superadmin', NULL, 'super_admin', '2025-04-12 12:42:39', '2025-04-12 12:42:39', 1),
(3, 'reception', 'reception', NULL, 'reception', '2025-04-12 12:42:39', '2025-04-12 12:42:39', 1),
(4, 'ctp', 'ctp', NULL, 'ctp', '2025-04-12 12:42:39', '2025-04-12 12:42:39', 1),
(5, 'accounts', 'accounts', NULL, 'accounts', '2025-04-12 12:42:39', '2025-04-12 12:42:39', 1),
(6, 'multicolour', 'multicolour', NULL, 'multicolour', '2025-04-12 12:42:39', '2025-04-12 12:42:39', 1),
(7, 'delivery', 'delivery', NULL, 'delivery', '2025-04-12 12:42:39', '2025-04-12 12:42:39', 1),
(8, 'dispatch', 'dispatch', NULL, 'dispatch', '2025-04-12 12:42:39', '2025-04-12 12:42:39', 1),
(9, 'digital', 'digital', NULL, 'digital', '2025-04-12 12:42:39', '2025-04-12 12:42:39', 1);

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int NOT NULL,
  `vendor_name` varchar(255) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `gst_number` varchar(50) DEFAULT NULL,
  `hsn_number` varchar(50) DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `date_of_supply` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `vendor_name`, `phone_number`, `email`, `gst_number`, `hsn_number`, `invoice_number`, `date_of_supply`) VALUES
(1, 'Aman', '2222222222', 'aman@gmail.com', '22K1A5544224', '1234', '', '2025-04-01');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ctp`
--
ALTER TABLE `ctp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_sheet_id` (`job_sheet_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_name` (`customer_name`);

--
-- Indexes for table `customer_jobs`
--
ALTER TABLE `customer_jobs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dispatch_jobs`
--
ALTER TABLE `dispatch_jobs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_number` (`invoice_number`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subcategory_id` (`subcategory_id`);

--
-- Indexes for table `inventory_items_copy`
--
ALTER TABLE `inventory_items_copy`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_subcategories`
--
ALTER TABLE `inventory_subcategories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `invoice`
--
ALTER TABLE `invoice`
  ADD PRIMARY KEY (`invoice_number`);

--
-- Indexes for table `jobsheet_progress_history`
--
ALTER TABLE `jobsheet_progress_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_sheet_id` (`job_sheet_id`);

--
-- Indexes for table `job_sheets`
--
ALTER TABLE `job_sheets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_records`
--
ALTER TABLE `payment_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_sheet_id` (`job_sheet_id`);

--
-- Indexes for table `pricing_table`
--
ALTER TABLE `pricing_table`
  ADD PRIMARY KEY (`category`);

--
-- Indexes for table `progress_stages`
--
ALTER TABLE `progress_stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stage_name` (`stage_name`);

--
-- Indexes for table `sales_prices`
--
ALTER TABLE `sales_prices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `upi_settings`
--
ALTER TABLE `upi_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ctp`
--
ALTER TABLE `ctp`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customer_jobs`
--
ALTER TABLE `customer_jobs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `inventory_items_copy`
--
ALTER TABLE `inventory_items_copy`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `inventory_subcategories`
--
ALTER TABLE `inventory_subcategories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `invoice`
--
ALTER TABLE `invoice`
  MODIFY `invoice_number` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `jobsheet_progress_history`
--
ALTER TABLE `jobsheet_progress_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `job_sheets`
--
ALTER TABLE `job_sheets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payment_records`
--
ALTER TABLE `payment_records`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `progress_stages`
--
ALTER TABLE `progress_stages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `sales_prices`
--
ALTER TABLE `sales_prices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `upi_settings`
--
ALTER TABLE `upi_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ctp`
--
ALTER TABLE `ctp`
  ADD CONSTRAINT `ctp_ibfk_1` FOREIGN KEY (`job_sheet_id`) REFERENCES `job_sheets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`invoice_number`) REFERENCES `invoice` (`invoice_number`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `inventory_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`subcategory_id`) REFERENCES `inventory_subcategories` (`id`);

--
-- Constraints for table `inventory_subcategories`
--
ALTER TABLE `inventory_subcategories`
  ADD CONSTRAINT `inventory_subcategories_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`);

--
-- Constraints for table `jobsheet_progress_history`
--
ALTER TABLE `jobsheet_progress_history`
  ADD CONSTRAINT `jobsheet_progress_history_ibfk_1` FOREIGN KEY (`job_sheet_id`) REFERENCES `job_sheets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_records`
--
ALTER TABLE `payment_records`
  ADD CONSTRAINT `payment_records_ibfk_1` FOREIGN KEY (`job_sheet_id`) REFERENCES `job_sheets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_prices`
--
ALTER TABLE `sales_prices`
  ADD CONSTRAINT `sales_prices_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
