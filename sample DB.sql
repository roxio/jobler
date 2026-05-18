-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 23, 2026 at 08:36 AM
-- Wersja serwera: 10.4.32-MariaDB
-- Wersja PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `jobler`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `admin_login_history`
--

CREATE TABLE `admin_login_history` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_login_history`
--

INSERT INTO `admin_login_history` (`id`, `admin_id`, `ip_address`, `login_time`) VALUES
(26, 4, '127.0.0.1', '2025-09-09 06:35:37'),
(37, 4, '127.0.0.1', '2025-09-09 12:55:04'),
(38, 4, '127.0.0.1', '2025-09-09 12:55:08'),
(39, 4, '127.0.0.1', '2025-09-11 20:20:48'),
(40, 4, '127.0.0.1', '2026-04-23 05:22:03');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `admin_messages`
--

CREATE TABLE `admin_messages` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('notification','information','warning','promotion') DEFAULT 'notification',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_messages`
--

INSERT INTO `admin_messages` (`id`, `admin_id`, `user_id`, `subject`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 4, 6, 'test', 'test', 'notification', 0, '2025-09-08 20:54:26');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `parent_id`) VALUES
(1, '1 master', NULL),
(2, 'slave 1', 1),
(3, 'slave 2', 1),
(4, 'khhjk', NULL),
(7, '123', 1),
(8, 'test', NULL),
(9, 'nadrzedna', NULL),
(10, 'pod', 9),
(11, 'pod', 4);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `conversation_reports`
--

CREATE TABLE `conversation_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` varchar(100) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `reporter_id` int(11) NOT NULL,
  `reported_user_id` int(11) DEFAULT NULL,
  `message_id` int(11) DEFAULT NULL,
  `report_type` varchar(30) NOT NULL DEFAULT 'conversation',
  `reason` text DEFAULT NULL,
  `conversation_snapshot` mediumtext DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_conversation_reports_conversation` (`conversation_id`),
  KEY `idx_conversation_reports_status` (`status`),
  KEY `idx_conversation_reports_message` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` enum('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `points_required` int(11) NOT NULL DEFAULT 1,
  `category_id` int(11) DEFAULT NULL,
  `executor_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `user_id`, `title`, `description`, `status`, `created_at`, `updated_at`, `points_required`, `category_id`) VALUES
(1, 6, 'Potrzebuję pomocy przy remoncie mieszkania 12', 'Szukam wykonawcy do remontu w moim mieszkaniu. 1', 'open', '2025-02-16 09:00:18', '2025-02-20 10:55:23', 1, NULL),
(2, 6, 'Szukam specjalisty od SEO', 'Chciałbym poprawić widoczność mojej strony w wyszukiwarkach.', 'open', '2025-02-16 09:00:18', '2025-02-20 10:55:26', 1, NULL),
(11, 6, 'ogloszenie w kategorii', 'pierwsze w kategorii', 'open', '2025-03-03 20:10:44', '2026-04-23 05:50:28', 1, 1),
(12, 6, 'test admina tworzenie', 'test admina tworzenie tresc\r\nążśćńłóęź', 'open', '2026-04-23 05:54:11', '2026-04-23 06:02:48', 1, 1);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `job_change_history`
--

CREATE TABLE `job_change_history` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `change_description` text NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_change_history`
--

INSERT INTO `job_change_history` (`id`, `job_id`, `admin_id`, `change_description`, `changed_at`) VALUES
(1, 11, 4, 'Punkty: 1 → 3; Status:  → open', '2026-04-23 05:49:47'),
(2, 11, 4, 'Punkty: 3 → 1', '2026-04-23 05:50:21'),
(3, 11, 4, 'Dodano zdjęcie: job_69e9b3244cc80.jpg', '2026-04-23 05:50:28'),
(4, 12, 4, 'Zlecenie utworzone przez administratora. Status: open, Punkty: 1. Dodano 1 zdjęcie(a).', '2026-04-23 05:54:13'),
(5, 12, 4, 'Właściciel zmieniony (user_id: 4 → 6)', '2026-04-23 06:02:48');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `job_images`
--

CREATE TABLE `job_images` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_images`
--

INSERT INTO `job_images` (`id`, `job_id`, `filename`, `created_at`) VALUES
(1, 11, 'job_69e9b3244cc80.jpg', '2026-04-23 05:50:28'),
(2, 12, 'job_69e9b405b0b1e.jpg', '2026-04-23 05:54:13');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `job_reports`
--

CREATE TABLE `job_reports` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `activity_type` varchar(255) NOT NULL,
  `details` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_status` tinyint(1) DEFAULT 0,
  `conversation_id` varchar(100) NOT NULL,
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `participant_note` text DEFAULT NULL,
  `moderated_at` timestamp NULL DEFAULT NULL,
  `moderated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `job_id`, `sender_id`, `receiver_id`, `content`, `message`, `created_at`, `read_status`, `conversation_id`) VALUES
(28, 1, 6, 5, 'test 2', '', '2025-02-22 20:25:46', 0, 5),
(29, 1, 5, 6, 'odp 3', '', '2025-02-22 20:26:13', 0, 5),
(30, 2, 6, 5, 'test', 'test', '2026-04-23 06:20:59', 0, 5),
(31, 2, 6, 5, 'test', 'test', '2026-04-23 06:21:02', 0, 5),
(32, 1, 5, 6, 'test', 'test', '2026-04-23 06:22:01', 0, 5),
(33, 2, 6, 5, 'tets', 'tets', '2026-04-23 06:32:39', 0, 2),
(34, 1, 6, 5, 'test', 'test', '2026-04-23 06:32:50', 0, 1);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `newsletter_subscriptions`
--

CREATE TABLE `newsletter_subscriptions` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `payment_reports`
--

CREATE TABLE `payment_reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `details` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('unread','in_progress','resolved') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `responses`
--

CREATE TABLE `responses` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `executor_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `proposed_price` decimal(10,2) DEFAULT NULL,
  `scope` text DEFAULT NULL,
  `points_reserved` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `accepted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `responses`
--

INSERT INTO `responses` (`id`, `job_id`, `executor_id`, `message`, `created_at`) VALUES
(21, 1, 5, 'odp1', '2025-02-22 20:25:33'),
(22, 2, 5, 'testttt', '2025-02-22 22:32:17'),
(23, 11, 5, 'test', '2026-04-23 06:21:43'),
(24, 12, 5, 'test', '2026-04-23 06:21:50');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `settings_log`
--

CREATE TABLE `settings_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `change_description` text NOT NULL,
  `timestamp` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings_log`
--

INSERT INTO `settings_log` (`id`, `user_id`, `change_description`, `timestamp`) VALUES
(5, 4, 'Zaktualizowane ustawienia SMTP', '2025-02-25 22:35:06'),
(6, 4, 'Zaktualizowane ustawienia SMTP', '2025-09-09 09:21:04'),
(7, 4, 'Zaktualizowane ustawienia strony i SMTP', '2025-09-09 11:43:17'),
(8, 4, 'Zaktualizowane ustawienia strony i SMTP', '2025-09-09 12:28:38');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `logo` varchar(255) NOT NULL,
  `categories` text DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` text DEFAULT NULL,
  `max_ads` int(11) DEFAULT 10,
  `promotion_fee` decimal(10,2) DEFAULT 10.00,
  `facebook_url` varchar(255) DEFAULT NULL,
  `twitter_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `contact_address` text DEFAULT NULL,
  `business_hours` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `allow_registration` tinyint(1) NOT NULL DEFAULT 0,
  `smtp_server` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT NULL,
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `default_language` varchar(10) NOT NULL DEFAULT 'pl',
  `layout_variant` varchar(50) NOT NULL DEFAULT 'classic',
  `company_name` varchar(255) DEFAULT NULL,
  `company_tax_id` varchar(50) DEFAULT NULL,
  `company_addresses` text DEFAULT NULL,
  `company_emails` text DEFAULT NULL,
  `company_phones` text DEFAULT NULL,
  `favicon` varchar(255) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `maintenance_mode` tinyint(1) NOT NULL DEFAULT 0,
  `maintenance_message` text DEFAULT NULL,
  `email_templates` mediumtext DEFAULT NULL,
  `sitemap_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sitemap_last_generated` datetime DEFAULT NULL,
  `last_system_backup` varchar(255) DEFAULT NULL,
  `last_database_backup` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `title`, `logo`, `categories`, `meta_description`, `meta_keywords`, `max_ads`, `promotion_fee`, `facebook_url`, `twitter_url`, `instagram_url`, `linkedin_url`, `contact_email`, `contact_phone`, `contact_address`, `business_hours`, `created_at`, `updated_at`, `allow_registration`, `smtp_server`, `smtp_port`, `smtp_username`, `smtp_password`, `default_language`, `layout_variant`, `company_name`, `company_tax_id`, `company_addresses`, `company_emails`, `company_phones`, `favicon`, `meta_title`, `maintenance_mode`, `maintenance_message`, `email_templates`, `sitemap_enabled`, `sitemap_last_generated`, `last_system_backup`, `last_database_backup`) VALUES
(1, 'test', '68bff6b54a5ca.png', '', '', '', 10, 10.00, 'http://testfb.pl', 'http://testfb.pl', 'http://testfb.pl', 'http://testfb.pl', 'info@jobler.pl', '+48 123 456 789', 'ul. Przykładowa 123, 00-000 Warszawa', 'Pon-Pt: 8:00-18:00', '2025-02-17 21:58:18', '2025-09-09 10:28:38', 0, 'smtp.example.com', 25, 'user', 'password', 'pl', 'classic', '', '', '[]', '[]', '[]', '', '', 0, '', '{}', 1, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `site_stats`
--

CREATE TABLE `site_stats` (
  `id` int(11) NOT NULL,
  `views` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `log_level` varchar(50) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `error_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('payment','withdrawal','refund') NOT NULL DEFAULT 'payment',
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `transaction_history`
--

CREATE TABLE `transaction_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','executor','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email_verified_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `registration_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `phone` varchar(15) DEFAULT NULL,
  `account_balance` int(11) NOT NULL DEFAULT 0,
  `avatar` varchar(255) DEFAULT NULL,
  `need_change` tinyint(1) NOT NULL DEFAULT 0,
  `newsletter_subscription` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `role`, `created_at`, `email_verified_at`, `last_login`, `last_activity`, `name`, `username`, `updated_at`, `registration_ip`, `user_agent`, `last_login_ip`, `status`, `phone`, `account_balance`, `avatar`, `need_change`, `newsletter_subscription`) VALUES
(4, 'admin@admin.admin', '$2y$10$7Akbgh6LTH745rdTsDbS4u2hVE390NiBFQ6zeC/3HuIAEIKI2M.DW', 'admin', '2025-01-01 10:00:29', NULL, '2026-04-23 08:02:19', NULL, 'admin1', 'admin2', '2026-04-23 06:02:19', NULL, NULL, '127.0.0.1', 'active', NULL, 12, NULL, 0, 1),
(5, 'executor@executor.executor', '$2y$10$7Akbgh6LTH745rdTsDbS4u2hVE390NiBFQ6zeC/3HuIAEIKI2M.DW', 'executor', '2025-02-16 10:06:12', NULL, '2026-04-23 08:21:16', NULL, 'exe1', 'exe2', '2026-04-23 06:21:16', NULL, NULL, '127.0.0.1', 'active', NULL, 12, NULL, 0, 0),
(6, 'user@user.user', '$2y$10$r2WL/H9KjsS9W.JG.q71bOAc90kbKEX.fa40LM7GpC9qB8wg8MJEi', 'user', '2025-02-16 13:19:36', NULL, '2026-04-23 08:22:15', NULL, 'user1', 'user2', '2026-04-23 06:22:15', NULL, NULL, '127.0.0.1', 'active', NULL, 12, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `user_activities`
--

CREATE TABLE `user_activities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `user_activity_reports`
--

CREATE TABLE `user_activity_reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(255) NOT NULL,
  `details` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_activity_reports`
--

INSERT INTO `user_activity_reports` (`id`, `user_id`, `activity_type`, `details`, `timestamp`) VALUES
(4, 4, 'login', 'test', '2025-09-07 19:53:54');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `user_login_history`
--

CREATE TABLE `user_login_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `login_time` datetime DEFAULT NULL,
  `success` tinyint(1) DEFAULT 1,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_login_history`
--

INSERT INTO `user_login_history` (`id`, `user_id`, `ip_address`, `login_time`, `success`, `user_agent`, `created_at`) VALUES
(1, 6, '127.0.0.1', '2025-09-12 12:33:17', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 10:33:17'),
(2, 6, '127.0.0.1', '2025-09-12 12:33:35', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 10:33:35'),
(4, 6, '127.0.0.1', '2025-09-12 12:36:49', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 10:36:49'),
(5, 6, '127.0.0.1', '2025-09-12 12:36:54', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 10:36:54'),
(6, 4, '127.0.0.1', '2025-09-13 19:59:25', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:142.0) Gecko/20100101 Firefox/142.0', '2025-09-13 17:59:25'),
(7, 4, '127.0.0.1', '2026-04-20 21:50:14', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0', '2026-04-20 19:50:14'),
(8, 4, '127.0.0.1', '2026-04-23 07:20:56', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-23 05:20:56'),
(10, 4, '127.0.0.1', '2026-04-23 07:51:41', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-23 05:51:41'),
(11, 6, '127.0.0.1', '2026-04-23 07:55:25', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-23 05:55:25'),
(13, NULL, '127.0.0.1', '2026-04-23 08:00:40', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-23 06:00:40'),
(14, 6, '127.0.0.1', '2026-04-23 08:00:51', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-23 06:00:51'),
(15, NULL, '127.0.0.1', '2026-04-23 08:01:03', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-23 06:01:03'),
(16, 4, '127.0.0.1', '2026-04-23 08:02:21', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-23 06:02:21'),
(17, 6, '127.0.0.1', '2026-04-23 08:03:04', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-23 06:03:04'),
(18, 5, '127.0.0.1', '2026-04-23 08:21:16', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-23 06:21:16'),
(19, 6, '127.0.0.1', '2026-04-23 08:22:15', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-23 06:22:15');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_type` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `admin_login_history`
--
ALTER TABLE `admin_login_history`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `admin_messages`
--
ALTER TABLE `admin_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indeksy dla tabeli `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indeksy dla tabeli `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeksy dla tabeli `job_change_history`
--
ALTER TABLE `job_change_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indeksy dla tabeli `job_images`
--
ALTER TABLE `job_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indeksy dla tabeli `job_reports`
--
ALTER TABLE `job_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indeksy dla tabeli `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indeksy dla tabeli `newsletter_subscriptions`
--
ALTER TABLE `newsletter_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeksy dla tabeli `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `payment_reports`
--
ALTER TABLE `payment_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeksy dla tabeli `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeksy dla tabeli `responses`
--
ALTER TABLE `responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `executor_id` (`executor_id`);

--
-- Indeksy dla tabeli `settings_log`
--
ALTER TABLE `settings_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeksy dla tabeli `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `site_stats`
--
ALTER TABLE `site_stats`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_transactions_user` (`user_id`);

--
-- Indeksy dla tabeli `transaction_history`
--
ALTER TABLE `transaction_history`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeksy dla tabeli `user_activities`
--
ALTER TABLE `user_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeksy dla tabeli `user_activity_reports`
--
ALTER TABLE `user_activity_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeksy dla tabeli `user_login_history`
--
ALTER TABLE `user_login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_login_history_user_id` (`user_id`);

--
-- Indeksy dla tabeli `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_login_history`
--
ALTER TABLE `admin_login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `admin_messages`
--
ALTER TABLE `admin_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `job_change_history`
--
ALTER TABLE `job_change_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `job_images`
--
ALTER TABLE `job_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `job_reports`
--
ALTER TABLE `job_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `newsletter_subscriptions`
--
ALTER TABLE `newsletter_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_reports`
--
ALTER TABLE `payment_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `responses`
--
ALTER TABLE `responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `settings_log`
--
ALTER TABLE `settings_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `site_stats`
--
ALTER TABLE `site_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaction_history`
--
ALTER TABLE `transaction_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user_activities`
--
ALTER TABLE `user_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_activity_reports`
--
ALTER TABLE `user_activity_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_login_history`
--
ALTER TABLE `user_login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_messages`
--
ALTER TABLE `admin_messages`
  ADD CONSTRAINT `admin_messages_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `job_change_history`
--
ALTER TABLE `job_change_history`
  ADD CONSTRAINT `jch_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jch_job_fk` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_images`
--
ALTER TABLE `job_images`
  ADD CONSTRAINT `job_images_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_reports`
--
ALTER TABLE `job_reports`
  ADD CONSTRAINT `job_reports_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `newsletter_subscriptions`
--
ALTER TABLE `newsletter_subscriptions`
  ADD CONSTRAINT `newsletter_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payment_reports`
--
ALTER TABLE `payment_reports`
  ADD CONSTRAINT `payment_reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `responses`
--
ALTER TABLE `responses`
  ADD CONSTRAINT `responses_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`),
  ADD CONSTRAINT `responses_ibfk_2` FOREIGN KEY (`executor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `settings_log`
--
ALTER TABLE `settings_log`
  ADD CONSTRAINT `settings_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_activities`
--
ALTER TABLE `user_activities`
  ADD CONSTRAINT `user_activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_activity_reports`
--
ALTER TABLE `user_activity_reports`
  ADD CONSTRAINT `user_activity_reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_login_history`
--
ALTER TABLE `user_login_history`
  ADD CONSTRAINT `fk_user_login_history_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
