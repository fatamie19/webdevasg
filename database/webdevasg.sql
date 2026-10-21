-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 21, 2026 at 02:36 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `webdevbackup`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(1, 'Books'),
(3, 'Clothing'),
(2, 'Electronics'),
(4, 'Others');

-- --------------------------------------------------------

--
-- Table structure for table `listings`
--

CREATE TABLE `listings` (
  `listing_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `item_condition` varchar(30) NOT NULL,
  `description` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `listing_status` enum('Available','Reserved','Swapped') NOT NULL DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `listings`
--

INSERT INTO `listings` (`listing_id`, `user_id`, `category_id`, `title`, `item_condition`, `description`, `image_path`, `created_at`, `listing_status`) VALUES
(29, 2, 3, 'Blue Denim Jacket', 'Good', '', 'assets/images/listings/62f4dd556049e4c0ccd1b5e5076a51e1.jpg', '2026-10-20 23:49:57', 'Reserved'),
(30, 2, 4, 'Ceramic Mug', 'Like new', '', 'assets/images/listings/075a94d96851e1968206fcbbf1ba8c89.jpg', '2026-10-20 23:50:18', 'Swapped'),
(31, 2, 2, 'Wireless earbuds', 'Good', '', 'assets/images/listings/9c552cc60ef9d84edad5eaebaf2d795b.jpg', '2026-10-20 23:51:03', 'Available'),
(32, 9, 2, 'Wireless mouse', 'Like new', '', 'assets/images/listings/25f4da818d1103084c12319a91be8b64.jpg', '2026-10-20 23:51:40', 'Reserved'),
(33, 9, 2, 'Calculator', 'Good', '', 'assets/images/listings/9a4f578392ad97b55953f147fcf56a82.jpg', '2026-10-20 23:52:44', 'Available'),
(34, 9, 4, 'Backpack', 'Good', '', 'assets/images/listings/d54164f9e77af7382cf3636184a38ccf.jpg', '2026-10-20 23:53:21', 'Swapped'),
(35, 10, 1, 'Introduction to Programming Book', 'Good', '', 'assets/images/listings/52707a1f862543c5919cfadf04c88edb.jpg', '2026-10-20 23:54:42', 'Available'),
(36, 10, 4, 'Water bottle', 'Like new', '', 'assets/images/listings/b3984f52587ff06de91348ee86bdcc99.jpg', '2026-10-20 23:56:15', 'Available'),
(37, 10, 4, 'Desk lamp', 'Good', '', 'assets/images/listings/4d98b6b7bd7ed3a17e5f7256ed0c2886.jpg', '2026-10-20 23:57:50', 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `swap_requests`
--

CREATE TABLE `swap_requests` (
  `request_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `requested_listing_id` int(11) NOT NULL,
  `offered_listing_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `request_date` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `swap_requests`
--

INSERT INTO `swap_requests` (`request_id`, `requester_id`, `requested_listing_id`, `offered_listing_id`, `message`, `status`, `request_date`, `completed_at`) VALUES
(14, 10, 32, 35, '', 'Rejected', '2026-10-20 23:58:35', NULL),
(15, 9, 29, 32, '', 'Accepted', '2026-10-20 23:58:57', NULL),
(16, 9, 30, 34, '', 'Completed', '2026-10-20 23:59:15', NULL),
(17, 2, 35, 29, '', 'Rejected', '2026-10-20 23:59:39', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `student_id` varchar(30) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `student_id`, `email`, `password`, `role`) VALUES
(1, 'admin', 'admin', 'admin@example.com', '$2y$10$7cWjZRTNiDhSitELdgzfS.qwSisPmCgOUGhROqrgDqhk7T7Kim5WG', 'admin'),
(2, 'Aisha Rahman', 'STU001', 'aisha@gmail.com', '$2y$10$CjAQZHj4ylCrM8j70t2vnOIsKw3lN3Q362ALRVTJ3yqLr/oX7RlMO', 'user'),
(9, 'Daniel Lim', 'STU002', 'daniel@gmail.com', '$2y$10$m5LQhMd/N.5WmQw7Ki1ybuvvvK2WnLNNgFJzMGHdI0/eLj6An/qoa', 'user'),
(10, 'Nur Iman', 'STU003', 'iman@gmail.com', '$2y$10$hxlaL1OCya.ljq4sFuJuzO1UfyTUiInVboC9vqysfVHDlMw9TH8K6', 'user');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `categories_name` (`category_name`);

--
-- Indexes for table `listings`
--
ALTER TABLE `listings`
  ADD PRIMARY KEY (`listing_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `listings_ibfk_2` (`category_id`);

--
-- Indexes for table `swap_requests`
--
ALTER TABLE `swap_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `requester_id` (`requester_id`),
  ADD KEY `requested_listing_id` (`requested_listing_id`),
  ADD KEY `offered_listing_id` (`offered_listing_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `listings`
--
ALTER TABLE `listings`
  MODIFY `listing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `swap_requests`
--
ALTER TABLE `swap_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `listings`
--
ALTER TABLE `listings`
  ADD CONSTRAINT `listings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `listings_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Constraints for table `swap_requests`
--
ALTER TABLE `swap_requests`
  ADD CONSTRAINT `swap_requests_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `swap_requests_ibfk_2` FOREIGN KEY (`requested_listing_id`) REFERENCES `listings` (`listing_id`),
  ADD CONSTRAINT `swap_requests_ibfk_3` FOREIGN KEY (`offered_listing_id`) REFERENCES `listings` (`listing_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
