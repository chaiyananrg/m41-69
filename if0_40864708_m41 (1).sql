-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql300.infinityfree.com
-- Generation Time: Jun 19, 2026 at 09:16 AM
-- Server version: 11.4.12-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_40864708_m41`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_albums`
--

CREATE TABLE `activity_albums` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `album_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_admin_shared` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_albums`
--

INSERT INTO `activity_albums` (`id`, `user_id`, `album_name`, `category`, `description`, `is_admin_shared`, `created_at`, `updated_at`) VALUES
(3, '17099', 'วันเฉลิมพระชนมพรรษา สมเด็จพระนางเจ้าสุทิดา พัชรสุธาพิมลลักษณ พระบรมราชินี 2 มิถุนายน 2569', 'กิจกรรม', NULL, 1, '2026-06-06 10:11:19', '2026-06-06 10:11:19'),
(4, '17099', 'ทำพานไหว้ครู ม.4/1 2569', 'กิจกรรมโรงเรียน', NULL, 1, '2026-06-10 02:30:07', '2026-06-10 02:42:05'),
(6, '17099', 'อัลบั้มส่วนกลาง', 'กิจกรรมส่วนกลาง', NULL, 1, '2026-06-11 18:45:35', '2026-06-11 18:45:35');

-- --------------------------------------------------------

--
-- Table structure for table `activity_images`
--

CREATE TABLE `activity_images` (
  `id` int(11) NOT NULL,
  `album_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_images`
--

INSERT INTO `activity_images` (`id`, `album_id`, `file_path`, `uploaded_at`, `sort_order`) VALUES
(4, 3, 'adm_act_1780765879_6a2454b7814ef.webp', '2026-06-06 10:11:20', 0),
(5, 3, 'adm_act_1780765887_6a2454bfec0df.webp', '2026-06-06 10:11:32', 0),
(6, 4, 'adm_act_1781083812_6a292ea4667fd.webp', '2026-06-10 02:30:12', 0),
(7, 4, 'adm_act_1781083817_6a292ea937951.webp', '2026-06-10 02:30:19', 0),
(8, 4, 'adm_act_1781083826_6a292eb23df5d.webp', '2026-06-10 02:30:29', 0),
(9, 4, 'adm_act_1781083839_6a292ebf8b94b.webp', '2026-06-10 02:30:42', 0),
(10, 4, 'adm_act_1781083855_6a292ecf6f08d.webp', '2026-06-10 02:30:58', 0),
(11, 4, 'adm_act_1781083868_6a292edceecc8.webp', '2026-06-10 02:31:11', 0),
(12, 4, 'adm_act_1781083878_6a292ee6350a1.webp', '2026-06-10 02:31:21', 0),
(13, 4, 'adm_act_1781083886_6a292eee4fec3.webp', '2026-06-10 02:31:29', 0),
(14, 4, 'adm_act_1781083893_6a292ef5e61e8.webp', '2026-06-10 02:31:36', 0),
(15, 4, 'adm_act_1781083908_6a292f04b3e93.webp', '2026-06-10 02:31:51', 0),
(16, 4, 'adm_act_1781083925_6a292f15b60f0.webp', '2026-06-10 02:32:08', 0),
(17, 4, 'adm_act_1781083931_6a292f1b70c20.webp', '2026-06-10 02:32:11', 0),
(43, 6, 'adm_act_1781228737_6a2b64c13a1eb.webp', '2026-06-11 18:45:37', 0),
(44, 6, 'adm_act_1781228740_6a2b64c4c05d3.webp', '2026-06-11 18:45:41', 0),
(45, 6, 'adm_act_1781228743_6a2b64c7516d7.webp', '2026-06-11 18:45:43', 0),
(46, 6, 'adm_act_1781228745_6a2b64c9f1555.webp', '2026-06-11 18:45:46', 0),
(47, 6, 'adm_act_1781228748_6a2b64cc98abd.webp', '2026-06-11 18:45:49', 0),
(48, 6, 'adm_act_1781228751_6a2b64cf2e43b.webp', '2026-06-11 18:45:51', 0),
(49, 6, 'adm_act_1781228754_6a2b64d22fd14.webp', '2026-06-11 18:45:54', 0),
(50, 6, 'adm_act_1781228756_6a2b64d4e0d99.webp', '2026-06-11 18:45:57', 0),
(51, 6, 'adm_act_1781228759_6a2b64d78a0f0.webp', '2026-06-11 18:46:00', 0),
(52, 6, 'adm_act_1781228762_6a2b64da3ac7f.webp', '2026-06-11 18:46:02', 0),
(53, 6, 'adm_act_1781228764_6a2b64dca66f4.webp', '2026-06-11 18:46:05', 0),
(54, 6, 'adm_act_1781228767_6a2b64df5483b.webp', '2026-06-11 18:46:07', 0),
(55, 6, 'adm_act_1781228770_6a2b64e27400b.webp', '2026-06-11 18:46:10', 0),
(56, 6, 'adm_act_1781228773_6a2b64e5a8913.webp', '2026-06-11 18:46:14', 0),
(57, 6, 'adm_act_1781228778_6a2b64ea67e7d.webp', '2026-06-11 18:46:18', 0),
(58, 6, 'adm_act_1781228781_6a2b64ed5ab2c.webp', '2026-06-11 18:46:21', 0),
(59, 6, 'adm_act_1781228784_6a2b64f01303b.webp', '2026-06-11 18:46:24', 0),
(60, 6, 'adm_act_1781228786_6a2b64f27cbd2.webp', '2026-06-11 18:46:27', 0),
(61, 6, 'adm_act_1781228788_6a2b64f4e678e.webp', '2026-06-11 18:46:29', 0),
(62, 6, 'adm_act_1781228791_6a2b64f7b57a7.webp', '2026-06-11 18:46:32', 0),
(63, 6, 'adm_act_1781228794_6a2b64fa6018a.webp', '2026-06-11 18:46:34', 0),
(64, 6, 'adm_act_1781228796_6a2b64fcae09f.webp', '2026-06-11 18:46:37', 0),
(65, 6, 'adm_act_1781229216_6a2b66a0399e5.webp', '2026-06-11 18:53:38', 0);

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `name`) VALUES
(1, 'chaiyanan.rg', '$2y$10$5fB4hsmMoPpHgtioFwpXnO0qxIxO8amPj5uKt/L9LXaJ2ZwAcCXXC', 'ผู้ดูแลระบบสูงสุด');

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `activity_topic` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `is_admin_shared` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`id`, `user_id`, `title`, `category`, `activity_topic`, `description`, `file_path`, `is_admin_shared`, `created_at`, `updated_at`) VALUES
(21, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764511_6a244f5fbfe24.jpg', 1, '2026-06-06 09:48:32', '2026-06-06 09:48:32'),
(22, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764512_6a244f60ded82.jpg', 1, '2026-06-06 09:48:33', '2026-06-06 09:48:33'),
(23, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764514_6a244f62f2d83.jpg', 1, '2026-06-06 09:48:35', '2026-06-06 09:48:35'),
(24, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764516_6a244f64e039a.jpg', 1, '2026-06-06 09:48:37', '2026-06-06 09:48:37'),
(25, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764518_6a244f66ec496.jpg', 1, '2026-06-06 09:48:39', '2026-06-06 09:48:39'),
(26, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764520_6a244f68ebf2f.jpg', 1, '2026-06-06 09:48:41', '2026-06-06 09:48:41'),
(27, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764523_6a244f6b21f30.jpg', 1, '2026-06-06 09:48:43', '2026-06-06 09:48:43'),
(28, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764524_6a244f6c741dd.jpg', 1, '2026-06-06 09:48:44', '2026-06-06 09:48:44'),
(29, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764527_6a244f6f01b0b.jpg', 1, '2026-06-06 09:48:47', '2026-06-06 09:48:47'),
(30, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764528_6a244f70792a5.jpg', 1, '2026-06-06 09:48:48', '2026-06-06 09:48:48'),
(31, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764530_6a244f7271b48.jpg', 1, '2026-06-06 09:48:50', '2026-06-06 09:48:50'),
(32, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764532_6a244f74d34bb.jpg', 1, '2026-06-06 09:48:53', '2026-06-06 09:48:53'),
(33, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764535_6a244f770f6bf.jpg', 1, '2026-06-06 09:48:55', '2026-06-06 09:48:55'),
(34, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764537_6a244f793f4ed.jpg', 1, '2026-06-06 09:48:57', '2026-06-06 09:48:57'),
(35, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764539_6a244f7b71bbb.jpg', 1, '2026-06-06 09:48:59', '2026-06-06 09:48:59'),
(36, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764541_6a244f7dbb4f4.jpg', 1, '2026-06-06 09:49:01', '2026-06-06 09:49:01'),
(37, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764543_6a244f7ff330e.jpg', 1, '2026-06-06 09:49:04', '2026-06-06 09:49:04'),
(38, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764548_6a244f84d032e.jpg', 1, '2026-06-06 09:49:09', '2026-06-06 09:49:09'),
(39, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764551_6a244f87e876c.jpg', 1, '2026-06-06 09:49:12', '2026-06-06 09:49:12'),
(40, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764554_6a244f8a1fe5d.jpg', 1, '2026-06-06 09:49:14', '2026-06-06 09:49:14'),
(41, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764555_6a244f8bbb8e7.jpg', 1, '2026-06-06 09:49:15', '2026-06-06 09:49:15'),
(42, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764557_6a244f8d1b6a5.jpg', 1, '2026-06-06 09:49:17', '2026-06-06 09:49:17'),
(43, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764559_6a244f8f0e2a2.jpg', 1, '2026-06-06 09:49:19', '2026-06-06 09:49:19'),
(44, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764561_6a244f912e98a.jpg', 1, '2026-06-06 09:49:21', '2026-06-06 09:49:21'),
(45, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764563_6a244f9359b7f.jpg', 1, '2026-06-06 09:49:23', '2026-06-06 09:49:23'),
(46, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764565_6a244f95aabe3.jpg', 1, '2026-06-06 09:49:25', '2026-06-06 09:49:25'),
(47, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764567_6a244f97c572d.jpg', 1, '2026-06-06 09:49:28', '2026-06-06 09:49:28'),
(48, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764569_6a244f999e05d.jpg', 1, '2026-06-06 09:49:29', '2026-06-06 09:49:29'),
(49, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764571_6a244f9b5d5f9.jpg', 1, '2026-06-06 09:49:31', '2026-06-06 09:49:31'),
(50, '17099', 'ต้นกล้าจามจุรี ม.4/1', 'ประกาศ', 'ทุกคน', NULL, 'admin_cert_1780764571_6a244f9bdcf8f.jpg', 1, '2026-06-06 09:49:32', '2026-06-06 09:49:32'),
(53, 'STU001', 'เริ่ด', 'academic', '', '', 'cert_1780770208_6a2465a0b54ce.jpg', 0, '2026-06-06 11:23:29', '2026-06-06 11:23:29');

-- --------------------------------------------------------

--
-- Table structure for table `class_finance`
--

CREATE TABLE `class_finance` (
  `id` int(11) NOT NULL,
  `officer_id` int(11) NOT NULL,
  `tx_type` enum('in','out') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_finance`
--

INSERT INTO `class_finance` (`id`, `officer_id`, `tx_type`, `amount`, `detail`, `created_at`) VALUES
(1, 2, 'in', '580.00', 'chaiyanan.rg', '2026-05-29 00:54:04'),
(2, 4, 'in', '20.00', 'ทดสอบ', '2026-05-29 10:23:42'),
(3, 4, 'in', '20.00', '-', '2026-05-29 10:28:48'),
(4, 4, 'out', '1000.00', '-', '2026-05-29 10:30:15'),
(5, 4, 'out', '1000.00', '-', '2026-05-29 10:30:22');

-- --------------------------------------------------------

--
-- Table structure for table `line_users`
--

CREATE TABLE `line_users` (
  `id` int(11) NOT NULL,
  `line_id` varchar(100) NOT NULL,
  `type` enum('user','group','room') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `line_users`
--

INSERT INTO `line_users` (`id`, `line_id`, `type`, `created_at`) VALUES
(1, 'Cf2ffdb73a2e14af3c959791f2261575a', 'group', '2026-05-17 13:10:53');

-- --------------------------------------------------------

--
-- Table structure for table `physics_permissions`
--

CREATE TABLE `physics_permissions` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `admin_level` int(11) DEFAULT 1,
  `allowed_pages` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `room_officers`
--

CREATE TABLE `room_officers` (
  `id` int(11) NOT NULL,
  `officer_name` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL,
  `barcode` varchar(50) NOT NULL,
  `dob` varchar(20) DEFAULT NULL,
  `profile_img` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_officers`
--

INSERT INTO `room_officers` (`id`, `officer_name`, `role`, `barcode`, `dob`, `profile_img`) VALUES
(1, 'นางสาวฤนท์ลยา เร็วชัย', 'หัวหน้าห้อง', '12677', '01/12/2553', '../upload/profile_1_1780042130.jpg'),
(2, 'นายกิตติกวิน สมณะ', 'รองหัวหน้าห้อง', '12638', '25/04/2554', '../upload/profile_2_1780042150.jpg'),
(3, 'นางสาววริศรา โลนุชิต', 'เหรัญญิก', '12676', '21/07/2553', '../upload/profile_3_1780042150.jpg'),
(4, 'นายชัยนันท์  ฤทธิสิงห์', 'เจ้าหน้าที่ัแอดมิน', '17099', '03/04/2554', 'uploads/profile_4_1780042883.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `site_name` varchar(255) NOT NULL,
  `site_logo` varchar(255) NOT NULL,
  `line_token` text DEFAULT NULL,
  `line_secret` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `site_name`, `site_logo`, `line_token`, `line_secret`) VALUES
(1, 'ม.4/1 โครงการห้องเรียนพิเศษ วิทย์-คณิต', 'logo_1778952365.png', 'Ps/FVa4ZBpeapAvSrW/MZMDHt2GHEL3ZZXpOltdUC87YM+s2vKhB0P8a8Wi6SJn9zk5QEeD3TPI8WhVTf2TfKusVrQfqS92+YDp7TFg/ungaAeuwPePEJrM0Vzvvf23jG0fFqrQAANUY3QZsZJSj5QdB04t89/1O/w1cDnyilFU=', 'eac11be8b0dbe149632ca5358a42cea3');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `no` int(11) NOT NULL COMMENT 'เลขที่',
  `student_id` varchar(20) NOT NULL COMMENT 'เลขประจำตัวนักเรียน',
  `national_id` varchar(13) DEFAULT NULL COMMENT 'เลขประจำตัวประชาชน',
  `fullname` varchar(150) NOT NULL COMMENT 'ชื่อ-สกุล',
  `birthdate` date DEFAULT NULL COMMENT 'วันเดือนปีเกิด YYYY-MM-DD',
  `profile_img` varchar(255) DEFAULT NULL COMMENT 'ชื่อไฟล์รูปโปรไฟล์',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `role` enum('User','Admin') DEFAULT 'User'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `no`, `student_id`, `national_id`, `fullname`, `birthdate`, `profile_img`, `created_at`, `role`) VALUES
(1, 1, '14052', '1309903877086', 'นายรัชต์พล ศรีรัตนกูล', '2011-04-16', NULL, '2026-06-06 12:29:05', 'User'),
(2, 2, '15175', '1309903882527', 'นายเหมพงษ์ ยศสันเทียะ', '2011-05-05', NULL, '2026-06-06 12:29:05', 'User'),
(3, 3, '12638', '1309903879500', 'นายกิตติกวิน สมณะ', '2011-04-25', NULL, '2026-06-06 12:29:05', 'User'),
(4, 4, '17099', '1308200128046', 'นายชัยนันท์ ฤทธิสิงห์', '2011-04-03', NULL, '2026-06-06 12:29:05', 'Admin'),
(5, 5, '13611', '1309903872386', 'นายทยากร ระวีพรมราช', NULL, NULL, '2026-06-06 12:29:05', 'User'),
(6, 6, '14068', '1100801682646', 'นายทาริค ฟอสเตอร์', '2010-09-07', NULL, '2026-06-06 12:29:05', 'User'),
(7, 7, '13610', '1103704586673', 'นายภูพิชิต ภูขุนทด', '2010-10-21', NULL, '2026-06-06 12:29:05', 'User'),
(8, 8, '12640', '1309903845966', 'นายเมธพนธ์ ขวางกระโทก', '2010-12-24', NULL, '2026-06-06 12:29:05', 'User'),
(9, 9, '17100', '1308200121769', 'นายวรากร เติมพันธ์', '2010-08-24', NULL, '2026-06-06 12:29:05', 'User'),
(10, 10, '19067', '1209702593308', 'นายอัศวิน ชื่นนอก', NULL, NULL, '2026-06-06 12:29:05', 'User'),
(11, 11, '12648', '1308200129093', 'นางสาววริศรา แปวขุนทด', '2011-05-11', NULL, '2026-06-06 12:29:05', 'User'),
(12, 12, '12650', '1308200124601', 'นางสาวปุณยาพร สมบูรณ์', '2010-11-23', NULL, '2026-06-06 12:29:05', 'User'),
(13, 13, '12653', '1309903786196', 'นางสาววิสุทธิโฉม มุ่งพาณิชย์', '2010-05-27', NULL, '2026-06-06 12:29:05', 'User'),
(14, 14, '12671', '1308200122129', 'นางสาวณัฐกมล หงษ์ลอย', '2010-09-03', NULL, '2026-06-06 12:29:05', 'User'),
(15, 15, '12676', '1100401516510', 'นางสาววริศรา โลนุชิต', '2010-07-21', NULL, '2026-06-06 12:29:05', 'User'),
(16, 16, '12677', '1309903839532', 'นางสาวฤนท์ลยา เร็วชัย', '2010-12-01', NULL, '2026-06-06 12:29:05', 'User'),
(17, 17, '12696', '1309903858219', 'นางสาวภูริชญา ชำพลกรัง', NULL, NULL, '2026-06-06 12:29:05', 'User'),
(18, 18, '13596', '1308200125314', 'นางสาวสิริกานดา ไม้สันเทียะ', '2010-12-17', NULL, '2026-06-06 12:29:05', 'User'),
(19, 19, '13606', '1101100301604', 'นางสาวฐิติรัตน์ คำสระ', '2010-09-19', NULL, '2026-06-06 12:29:05', 'User'),
(20, 20, '15938', '1308200119721', 'นางสาวณัฏฐศรัณยุ์ นนท์ขุนทด', NULL, NULL, '2026-06-06 12:29:05', 'User'),
(21, 21, '15939', '1309903888657', 'นางสาวธนาพรพรหม พรมดี', '2011-05-22', NULL, '2026-06-06 12:29:05', 'User'),
(22, 22, '17101', '1306200101871', 'นางสาวชนิกานต์ เชิดชู', '2010-07-01', NULL, '2026-06-06 12:29:05', 'User'),
(23, 23, '17102', '1308200123893', 'นางสาวชุติกาญจน์ กูกขุนทด', '2010-10-30', NULL, '2026-06-06 12:29:05', 'User'),
(24, 24, '17105', '1308200125284', 'นางสาวณิชนันท์ เติมกระโทก', '2010-12-15', NULL, '2026-06-06 12:29:05', 'User'),
(25, 25, '17107', '1308200118876', 'นางสาวพลอยชมพู มุ่งพันกลาง', '2010-05-21', NULL, '2026-06-06 12:29:05', 'User'),
(26, 26, '17108', '1308200120851', 'นางสาวภัณฑิรา ป้อมคำ', '2010-07-28', NULL, '2026-06-06 12:29:05', 'User'),
(27, 27, '17213', '1306600065171', 'นางสาวจิรัชยา แพบขุนทด', NULL, NULL, '2026-06-06 12:29:05', 'User'),
(28, 28, '17221', '1308200128283', 'นางสาวพิมพ์นภา ผลพิมาย', NULL, NULL, '2026-06-06 12:29:05', 'User'),
(29, 29, '19068', '1209000536347', 'นางสาวณิชา พูนพิพัฒน์', NULL, NULL, '2026-06-06 12:29:05', 'User'),
(30, 30, '19069', '1309903776433', 'นางสาวประภาวดี บุรีขันธ์', NULL, NULL, '2026-06-06 12:29:05', 'User');

-- --------------------------------------------------------

--
-- Table structure for table `student_profiles`
--

CREATE TABLE `student_profiles` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_profiles`
--

INSERT INTO `student_profiles` (`id`, `student_id`, `avatar`, `uploaded_at`, `updated_at`) VALUES
(1, '17099', 'avatar_17099_1780758022.jpg', '2026-06-06 08:00:22', '2026-06-06 08:00:22'),
(2, '12638', 'avatar_12638_1780895049.jpeg', '2026-06-07 22:04:09', '2026-06-07 22:04:09');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `teacher_id` varchar(20) NOT NULL COMMENT 'รหัสประจำตัวครู',
  `username` varchar(50) NOT NULL COMMENT 'ชื่อผู้ใช้สำหรับเข้าสู่ระบบ',
  `password` varchar(255) NOT NULL COMMENT 'รหัสผ่าน (แฮชความปลอดภัย)',
  `fullname` varchar(150) NOT NULL COMMENT 'ชื่อ-นามสกุลคุณครู',
  `subject` varchar(100) DEFAULT 'ฟิสิกส์',
  `national_id` varchar(13) DEFAULT NULL COMMENT 'เลขประจำตัวประชาชน',
  `profile_img` varchar(255) DEFAULT NULL COMMENT 'ชื่อไฟล์รูปโปรไฟล์',
  `role` varchar(50) DEFAULT 'Teacher' COMMENT 'บทบาทในระบบ',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `teacher_id`, `username`, `password`, `fullname`, `subject`, `national_id`, `profile_img`, `role`, `created_at`, `updated_at`) VALUES
(1, 'T001', 'wachiradon.s', '$2y$10$lpR8fP7ty7FFrI7S9ZkYVe5VvD9w/797IV8XWpREpWvB2I9p6vSVO', 'นายวชิรดล แสงกุล', 'ฟิสิกส์', NULL, NULL, 'Teacher', '2026-06-19 12:18:49', '2026-06-19 12:18:49'),
(2, 'T002', 'test.teacher', '$2y$10$lpR8fP7ty7FFrI7S9ZkYVe5VvD9w/797IV8XWpREpWvB2I9p6vSVO', 'ครูทดสอบ ระบบ', 'ฟิสิกส์', NULL, NULL, 'Teacher', '2026-06-19 12:57:12', '2026-06-19 12:57:12');

-- --------------------------------------------------------

--
-- Table structure for table `uniforms`
--

CREATE TABLE `uniforms` (
  `id` int(11) NOT NULL,
  `day_name` varchar(20) NOT NULL,
  `uniform_detail` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `uniforms`
--

INSERT INTO `uniforms` (`id`, `day_name`, `uniform_detail`) VALUES
(1, 'Monday', 'นักเรียน'),
(2, 'Tuesday', 'พละ'),
(3, 'Wednesday', 'นักเรียน'),
(4, 'Thursday', 'นักเรียน'),
(5, 'Friday', 'พละ');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_albums`
--
ALTER TABLE `activity_albums`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `activity_images`
--
ALTER TABLE `activity_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `album_id` (`album_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `class_finance`
--
ALTER TABLE `class_finance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `line_users`
--
ALTER TABLE `line_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `line_id` (`line_id`);

--
-- Indexes for table `physics_permissions`
--
ALTER TABLE `physics_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `room_officers`
--
ALTER TABLE `room_officers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teacher_id` (`teacher_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `uniforms`
--
ALTER TABLE `uniforms`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_albums`
--
ALTER TABLE `activity_albums`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `activity_images`
--
ALTER TABLE `activity_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `class_finance`
--
ALTER TABLE `class_finance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `line_users`
--
ALTER TABLE `line_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `physics_permissions`
--
ALTER TABLE `physics_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `room_officers`
--
ALTER TABLE `room_officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `student_profiles`
--
ALTER TABLE `student_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `uniforms`
--
ALTER TABLE `uniforms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_images`
--
ALTER TABLE `activity_images`
  ADD CONSTRAINT `activity_images_ibfk_1` FOREIGN KEY (`album_id`) REFERENCES `activity_albums` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
