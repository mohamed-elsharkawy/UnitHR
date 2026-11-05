-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 06, 2026 at 12:17 AM
-- Server version: 9.1.0
-- PHP Version: 8.4.0

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `unithr`
--
CREATE DATABASE IF NOT EXISTS `unithr` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `unithr`;

-- --------------------------------------------------------

--
-- Table structure for table `uhr_accesslog`
--

DROP TABLE IF EXISTS `uhr_accesslog`;
CREATE TABLE IF NOT EXISTS `uhr_accesslog` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `useragent` text COLLATE utf8mb4_unicode_ci,
  `login_time` datetime DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_accesslog`
--

TRUNCATE TABLE `uhr_accesslog`;
--
-- Dumping data for table `uhr_accesslog`
--

INSERT INTO `uhr_accesslog` (`id`, `rguid`, `username`, `ip`, `useragent`, `login_time`, `success`, `reason`, `dtadded`, `addedbyid`, `addedby`, `addedbyname`, `addedbynamear`, `lastedit`, `editedbyid`, `editedby`, `editedbyname`, `editedbynamear`, `omitted`) VALUES
(1, 'aae592383f2717fd6cfe0cb75ae75a6f', '27106010102098', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-11-05 23:04:11', 1, 'Successful login', '2026-11-05 21:04:11', 2, '27106010102098', 'admin', 'admin', '2026-11-05 21:04:11', 2, '27106010102098', 'admin', 'admin', 0),
(2, '56afaf4cd17511a48e9245a2527b5742', '27106010102098', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-11-05 23:15:12', 1, 'Logout', '2026-11-05 21:15:12', 2, '27106010102098', 'admin', 'admin', '2026-11-05 21:15:12', 2, '27106010102098', 'admin', 'admin', 0),
(3, '979e80331d88b3ce8e76372026c37234', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-11-05 23:19:42', 1, 'Logout', '2026-11-05 21:19:42', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:19:42', 1, 'admin', 'نظام', 'نظام', 0),
(4, '4431be6cef9eccd573a1387ba4933a59', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-11-05 23:20:11', 1, 'Successful login', '2026-11-05 21:20:11', 1, 'admin', 'System', 'نظام', '2026-11-05 21:20:11', 1, 'admin', 'System', 'نظام', 0),
(5, 'fa3e5924a26b7383b969ddb64938d1ed', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-11-06 00:16:03', 1, 'Logout', '2026-11-05 22:16:03', 1, 'admin', 'نظام', 'نظام', '2026-11-05 22:16:03', 1, 'admin', 'نظام', 'نظام', 0);

-- --------------------------------------------------------

--
-- Table structure for table `uhr_appmodules`
--

DROP TABLE IF EXISTS `uhr_appmodules`;
CREATE TABLE IF NOT EXISTS `uhr_appmodules` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modulename` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modulenamear` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modulefolder` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `menu_order` int NOT NULL DEFAULT '0',
  `description` text COLLATE utf8mb4_unicode_ci,
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_appmodules`
--

TRUNCATE TABLE `uhr_appmodules`;
-- --------------------------------------------------------

--
-- Table structure for table `uhr_depstructure`
--

DROP TABLE IF EXISTS `uhr_depstructure`;
CREATE TABLE IF NOT EXISTS `uhr_depstructure` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `depname` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `depnamear` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `depparentid` bigint DEFAULT NULL,
  `depparentname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `depparentnamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deptypeid` bigint DEFAULT NULL,
  `deptype` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deptypear` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `managerid` bigint DEFAULT NULL,
  `managername` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `managernamear` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_depstructure`
--

TRUNCATE TABLE `uhr_depstructure`;
-- --------------------------------------------------------

--
-- Table structure for table `uhr_deptypes`
--

DROP TABLE IF EXISTS `uhr_deptypes`;
CREATE TABLE IF NOT EXISTS `uhr_deptypes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deptypename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deptypenamear` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deptypeparentid` bigint DEFAULT NULL,
  `deptypeparentname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deptypeparentnamear` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_deptypes`
--

TRUNCATE TABLE `uhr_deptypes`;
--
-- Dumping data for table `uhr_deptypes`
--

INSERT INTO `uhr_deptypes` (`id`, `rguid`, `deptypename`, `deptypenamear`, `deptypeparentid`, `deptypeparentname`, `deptypeparentnamear`, `dtadded`, `addedbyid`, `addedby`, `addedbyname`, `addedbynamear`, `lastedit`, `editedbyid`, `editedby`, `editedbyname`, `editedbynamear`, `omitted`) VALUES
(1, 'b63ff165bfd76e32b1b2b7248c73ba5b', 'Administrative', 'إداري', NULL, NULL, NULL, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(2, '3642fc59be2ccd3fbfcd4ac7f16de46f', 'Specialized Staff', 'متخصصين', NULL, NULL, NULL, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(3, '2beb4a82a02da0cefb147687e0435588', 'Scientific', 'علمي', NULL, NULL, NULL, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0);

-- --------------------------------------------------------

--
-- Table structure for table `uhr_empdegrees`
--

DROP TABLE IF EXISTS `uhr_empdegrees`;
CREATE TABLE IF NOT EXISTS `uhr_empdegrees` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `degreename` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `degreenamear` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `interimperiod` bigint NOT NULL DEFAULT '0',
  `nextdegreeid` bigint DEFAULT NULL,
  `nextdegreename` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nextdegreenamear` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reqexam` tinyint(1) NOT NULL DEFAULT '0',
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_empdegrees`
--

TRUNCATE TABLE `uhr_empdegrees`;
--
-- Dumping data for table `uhr_empdegrees`
--

INSERT INTO `uhr_empdegrees` (`id`, `rguid`, `degreename`, `degreenamear`, `interimperiod`, `nextdegreeid`, `nextdegreename`, `nextdegreenamear`, `reqexam`, `dtadded`, `addedbyid`, `addedby`, `addedbyname`, `addedbynamear`, `lastedit`, `editedbyid`, `editedby`, `editedbyname`, `editedbynamear`, `omitted`) VALUES
(1, '76f9790f5f60c9948044cb36f0ff84ae', 'Sixth Degree', 'الدرجة السادسة', 3, 2, 'Fifth Degree', 'الدرجة الخامسة', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(2, 'ba009a8c8dd763d96ee0cd56800f18d6', 'Fifth Degree', 'الدرجة الخامسة', 3, 3, 'Fourth Degree', 'الدرجة الرابعة', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(3, '32934e3a4ace6fc0c304c2ecdcb7e39d', 'Fourth Degree', 'الدرجة الرابعة', 3, 4, 'Third Degree C', 'الدرجة الثالثة ج', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(4, '715b0499d287827c80a438a86f48926a', 'Third Degree C', 'الدرجة الثالثة ج', 3, 5, 'Third Degree B', 'الدرجة الثالثة ب', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(5, '6b4c7cad219d48756b4b4abdad6787e7', 'Third Degree B', 'الدرجة الثالثة ب', 3, 6, 'Third Degree A', 'الدرجة الثالثة أ', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(6, 'bac64ce78cfb8b3d7ab24bc012422f69', 'Third Degree A', 'الدرجة الثالثة أ', 3, 7, 'Second Degree B', 'الدرجة الثانية ب', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(7, '3bdd9acad84e693076b8c95c4aabf084', 'Second Degree B', 'الدرجة الثانية ب', 3, 8, 'Second Degree A', 'الدرجة الثانية أ', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(8, '2b8669a158b79ba03fb90717f67b181b', 'Second Degree A', 'الدرجة الثانية أ', 3, 9, 'First Degree B', 'الدرجة الأولى ب', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(9, 'fa01c0835fe0299f57bc28419505d027', 'First Degree B', 'الدرجة الأولى ب', 3, 10, 'First Degree A', 'الدرجة الأولى أ', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(10, '2efe0bf19d98a613e123c7de757d546a', 'First Degree A', 'الدرجة الأولى أ', 0, 11, 'Manager', 'مدير عام', 1, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(11, '683625ea4c34613c7eb21f047b18c362', 'Manager', 'مدير عام', 0, 12, 'Director', 'رئيس إدارة مركزية', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(12, 'ba1cd967ed0e3cea75df1a7d74971cb8', 'Director', 'رئيس إدارة مركزية', 0, 13, 'Head of Sector', 'رئيس قطاع', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(13, 'f7ef67f9d9e91ddff0d5014af8c5ae31', 'Secretary-General', 'أمين عام', 0, 14, 'President of INP', 'رئيس المعهد', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 21:21:14', 1, 'admin', 'نظام', 'نظام', 0),
(14, '20635f21ef838c6c7c7370ed347f80e7', 'President of INP', 'رئيس المعهد', 0, NULL, NULL, NULL, 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 21:21:07', 1, 'admin', 'نظام', 'نظام', 0);

-- --------------------------------------------------------

--
-- Table structure for table `uhr_empleaves`
--

DROP TABLE IF EXISTS `uhr_empleaves`;
CREATE TABLE IF NOT EXISTS `uhr_empleaves` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `empid` bigint NOT NULL,
  `empfullname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empfullnamear` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `leavetypeid` bigint NOT NULL,
  `leavetypename` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `leavetypenamear` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `startfrom` date NOT NULL,
  `endon` date NOT NULL,
  `balancebefore` int NOT NULL DEFAULT '0',
  `balanceafter` int NOT NULL DEFAULT '0',
  `isapproved` tinyint(1) NOT NULL DEFAULT '0',
  `rejectedbyid` bigint DEFAULT NULL,
  `rejectedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejectedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejectedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejectedat` datetime DEFAULT NULL,
  `empsuperiorid` bigint DEFAULT NULL,
  `empsuperiorname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empsuperiornamear` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_empleaves`
--

TRUNCATE TABLE `uhr_empleaves`;
-- --------------------------------------------------------

--
-- Table structure for table `uhr_employeesgroup`
--

DROP TABLE IF EXISTS `uhr_employeesgroup`;
CREATE TABLE IF NOT EXISTS `uhr_employeesgroup` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `empgroupname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `empgroupnamear` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_employeesgroup`
--

TRUNCATE TABLE `uhr_employeesgroup`;
--
-- Dumping data for table `uhr_employeesgroup`
--

INSERT INTO `uhr_employeesgroup` (`id`, `rguid`, `empgroupname`, `empgroupnamear`, `dtadded`, `addedbyid`, `addedby`, `addedbyname`, `addedbynamear`, `lastedit`, `editedbyid`, `editedby`, `editedbyname`, `editedbynamear`, `omitted`) VALUES
(1, '9349e12f5d4efd751f536f52dae80060', 'Administrative Staff', 'اداريين', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:19:04', 1, 'admin', 'admin', 'admin', 0),
(2, 'a4c722b742b77bf04d93dccba8287fb2', 'Scientific Staff', 'علميين', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(3, '3554995c4711a5f375f38d1f5c6ce802', 'Specialized Staff', 'متخصصين', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0);

-- --------------------------------------------------------

--
-- Table structure for table `uhr_employeesmain`
--

DROP TABLE IF EXISTS `uhr_employeesmain`;
CREATE TABLE IF NOT EXISTS `uhr_employeesmain` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `empfullname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `empfullnamear` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nid` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `insuranceid` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dop` date DEFAULT NULL,
  `dor` date DEFAULT NULL,
  `empgender` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `depid` bigint DEFAULT NULL,
  `depname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `depnamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `biometricid` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empstatusid` bigint DEFAULT NULL,
  `empstatusname` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empstatusnamear` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currentdegreeid` bigint DEFAULT NULL,
  `currentdegreename` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currentdegreenamear` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `degreeacquireddate` date DEFAULT NULL,
  `senioritydate` date DEFAULT NULL,
  `jobgroupid` bigint DEFAULT NULL,
  `jobgroupname` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jobgroupnamear` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emptypeid` bigint DEFAULT NULL,
  `emptypename` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emptypenamear` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empgroup` bigint DEFAULT NULL,
  `empgroupname` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empgroupnamear` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empsuperiorid` bigint DEFAULT NULL,
  `empsuperiorname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empsuperiornamear` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`),
  UNIQUE KEY `nid` (`nid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_employeesmain`
--

TRUNCATE TABLE `uhr_employeesmain`;
--
-- Dumping data for table `uhr_employeesmain`
--

INSERT INTO `uhr_employeesmain` (`id`, `rguid`, `empfullname`, `empfullnamear`, `email`, `nid`, `insuranceid`, `dop`, `dor`, `empgender`, `depid`, `depname`, `depnamear`, `biometricid`, `empstatusid`, `empstatusname`, `empstatusnamear`, `currentdegreeid`, `currentdegreename`, `currentdegreenamear`, `degreeacquireddate`, `senioritydate`, `jobgroupid`, `jobgroupname`, `jobgroupnamear`, `emptypeid`, `emptypename`, `emptypenamear`, `empgroup`, `empgroupname`, `empgroupnamear`, `empsuperiorid`, `empsuperiorname`, `empsuperiornamear`, `dtadded`, `addedbyid`, `addedby`, `addedbyname`, `addedbynamear`, `lastedit`, `editedbyid`, `editedby`, `editedbyname`, `editedbynamear`, `omitted`) VALUES
(1, '0dbf9117e16b6c5e67b535d7577af39a', 'Ashraf Al-Arabi', 'اشرف العربى', 'president@inp.edu.eg', '27106010102098', '1455', '1971-06-01', '2032-05-31', 'Male', NULL, '', '', '10001', 1, 'On Job', 'على رأس العمل', 14, 'President of INP', 'رئيس المعهد', '2021-09-01', '2021-06-01', 1, 'Specialized Positions (Higher Education)', 'الوظائف التخصصية (شهادة جامعية)', 1, 'Appointed Employee', 'موظف معين', 1, 'Administrative Staff', 'اداريين', NULL, '', '', '2026-11-05 21:03:54', 1, 'admin', 'admin', 'admin', '2026-11-05 21:25:32', 1, 'admin', 'نظام', 'نظام', 0);

-- --------------------------------------------------------

--
-- Table structure for table `uhr_employeestypes`
--

DROP TABLE IF EXISTS `uhr_employeestypes`;
CREATE TABLE IF NOT EXISTS `uhr_employeestypes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `emptypename` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `emptypenamear` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_employeestypes`
--

TRUNCATE TABLE `uhr_employeestypes`;
--
-- Dumping data for table `uhr_employeestypes`
--

INSERT INTO `uhr_employeestypes` (`id`, `rguid`, `emptypename`, `emptypenamear`, `dtadded`, `addedbyid`, `addedby`, `addedbyname`, `addedbynamear`, `lastedit`, `editedbyid`, `editedby`, `editedbyname`, `editedbynamear`, `omitted`) VALUES
(1, '7deeaf4f7033e92490e763ac53bfce2b', 'Appointed Employee', 'موظف معين', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(2, 'a158094dbf9f13a2329cc1306afdcad8', 'Seconded Employee from outside', 'موظف منتدب من جهة', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(3, '73cb2f50060371c86d51b77d250e08dc', 'Seconded Employee to us', 'موظف معار إلى جهة', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(4, '51422968075e129a2bb029763e19565b', 'Resigned', 'مستقيل', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(5, 'd7cbf4585a587f08bb3920975a4f454a', 'Let Go / Dismissed', 'مفصول', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(6, '28b98e8f7b82b6506a64e82ff29ed739', 'On a Leave', 'في أجازة', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(7, 'd1b3ce303d7b12b30f0e345a07a7ad82', 'Loaned employee from outside', 'موظف معار من جهة', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(8, '553c514b78dae38a66d169e2f729819e', 'Loaned employee to us', 'موظف معار إلى جهة', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(9, '6e2bd25921bcc16d5aac82be43c00e49', 'Contract-based employee', 'موظف بتعاقد', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0);

-- --------------------------------------------------------

--
-- Table structure for table `uhr_empstatus`
--

DROP TABLE IF EXISTS `uhr_empstatus`;
CREATE TABLE IF NOT EXISTS `uhr_empstatus` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `empstatusname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `empstatusnamear` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ispayable` tinyint(1) NOT NULL DEFAULT '1',
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_empstatus`
--

TRUNCATE TABLE `uhr_empstatus`;
--
-- Dumping data for table `uhr_empstatus`
--

INSERT INTO `uhr_empstatus` (`id`, `rguid`, `empstatusname`, `empstatusnamear`, `ispayable`, `dtadded`, `addedbyid`, `addedby`, `addedbyname`, `addedbynamear`, `lastedit`, `editedbyid`, `editedby`, `editedbyname`, `editedbynamear`, `omitted`) VALUES
(1, 'aba3ab94c2de79496194cc7831c6771a', 'On Job', 'على رأس العمل', 1, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(2, '0245eb296aa50eb338eb9cfbfd8fa190', 'Leave', 'أجازة', 1, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(3, 'c7ae01fad4d7406eec2ab0fe5b9c5189', 'Seconded to another organization', 'منتدب إلى جهة', 1, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(4, '0514e9b80e8c0fb89118d2e642b701f0', 'Loaned to an organization', 'معار إلى جهة', 1, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(5, '785293ffe94e232091abe06d7f1e8fd7', 'Pension', 'معاش', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(6, '2014caf0ef4b788242a2a4f550cb849f', 'Dismissed', 'مفصول', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(7, '8e4ac17760b422e14e011e5488e52b1b', 'Exempted under Law 167', 'عجز طبي بقانون ١٦٧', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(8, 'ab13501b4548d92e33fdfa635edeb26d', 'Assignment (temporary delegation of duties)', 'تكليف', 1, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(9, 'a5f0e39b7623f99aef0cd197e776d083', 'Called up for military service', 'مستدعى للتجنيد', 1, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(10, '1597cc0f60305bc1869a4ecc932bead8', 'Suspended', 'موقوف', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(11, '5a8588ea3da0384f4fe78b93d9469d9a', 'Deceased', 'وفاه', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(12, '54eecd6f5e23c17abcf4853fdf7211e0', 'Stateless', 'فاقد الجنسية', 0, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0);

-- --------------------------------------------------------

--
-- Table structure for table `uhr_fiscalyears`
--

DROP TABLE IF EXISTS `uhr_fiscalyears`;
CREATE TABLE IF NOT EXISTS `uhr_fiscalyears` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fstartdate` date NOT NULL,
  `fenddate` date NOT NULL,
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_fiscalyears`
--

TRUNCATE TABLE `uhr_fiscalyears`;
--
-- Dumping data for table `uhr_fiscalyears`
--

INSERT INTO `uhr_fiscalyears` (`id`, `rguid`, `fstartdate`, `fenddate`, `dtadded`, `addedbyid`, `addedby`, `addedbyname`, `addedbynamear`, `lastedit`, `editedbyid`, `editedby`, `editedbyname`, `editedbynamear`, `omitted`) VALUES
(1, '80b5ed06a1f79ff29c1756a406425309', '2024-01-01', '2024-12-31', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(2, '0600a61cf868a18e2ff1fc0b2c93c32d', '2025-01-01', '2025-12-31', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(3, '338e5e4b4564b12b0f2f6f2eb0b2529a', '2026-01-01', '2026-12-01', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(4, 'ec59008d117bb044506142bb5ac13a04', '2023-01-01', '2023-12-31', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(5, 'e021919a55059b7f00ff5a834af05a5e', '2028-01-01', '2028-12-31', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(6, 'f6c9299a30c7bdefaf65eca8ea35e4ca', '2029-01-01', '2029-12-31', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(7, '9a6b1cec5985324bc41eb906b31e14c1', '2030-01-01', '2030-12-31', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0),
(8, '415b829031a01777e389d9ad00e4bd59', '2027-01-01', '2027-12-31', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', 0);

-- --------------------------------------------------------

--
-- Table structure for table `uhr_jobgroups`
--

DROP TABLE IF EXISTS `uhr_jobgroups`;
CREATE TABLE IF NOT EXISTS `uhr_jobgroups` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jobgroupname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jobgroupnamear` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jobgroupcode` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `descriptionar` text COLLATE utf8mb4_unicode_ci,
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_jobgroups`
--

TRUNCATE TABLE `uhr_jobgroups`;
--
-- Dumping data for table `uhr_jobgroups`
--

INSERT INTO `uhr_jobgroups` (`id`, `rguid`, `jobgroupname`, `jobgroupnamear`, `jobgroupcode`, `description`, `descriptionar`, `dtadded`, `addedbyid`, `addedby`, `addedbyname`, `addedbynamear`, `lastedit`, `editedbyid`, `editedby`, `editedbyname`, `editedbynamear`, `omitted`) VALUES
(1, '058f67794705164495667100d38f8dbb', 'Specialized Positions (Higher Education)', 'الوظائف التخصصية (شهادة جامعية)', 'G1', 'This group includes graduates who hold a higher education qualification (a bachelor’s degree or its equivalent).', 'تضم هذه المجموعة الخريجين الحاصلين على مؤهل عالٍ (بكالوريوس أو ليسانس أو ما يعادله).', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 22:06:50', 1, 'admin', 'نظام', 'نظام', 0),
(2, 'af5bae65bad33f635effda1bc475d591', 'Technical & Clerical (Diploma)', 'الوظائف الفنية والكتابية (دبلوم)', 'G2', 'It includes holders of technical diplomas or certificates from two-year post-secondary institutes (intermediate-level qualifications).', 'تضم الحاصلين على دبلومات فنية أو معاهد سنتين بعد الثانوية العامة (فوق متوسط).', '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 22:07:18', 1, 'admin', 'نظام', 'نظام', 0);

-- --------------------------------------------------------

--
-- Table structure for table `uhr_leavesbalances`
--

DROP TABLE IF EXISTS `uhr_leavesbalances`;
CREATE TABLE IF NOT EXISTS `uhr_leavesbalances` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `empid` bigint NOT NULL,
  `empfullname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empfullnamear` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fiscalyearid` bigint NOT NULL,
  `fstartdate` date DEFAULT NULL,
  `fenddate` date DEFAULT NULL,
  `leavetypeid` bigint DEFAULT NULL,
  `leavetypename` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `leavetypenamear` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carryoverbalance` int NOT NULL DEFAULT '0',
  `initialbalance` int NOT NULL DEFAULT '0',
  `usedbalance` int NOT NULL DEFAULT '0',
  `remainingbalance` int NOT NULL DEFAULT '0',
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_leavesbalances`
--

TRUNCATE TABLE `uhr_leavesbalances`;
-- --------------------------------------------------------

--
-- Table structure for table `uhr_leavestypes`
--

DROP TABLE IF EXISTS `uhr_leavestypes`;
CREATE TABLE IF NOT EXISTS `uhr_leavestypes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `leavetypename` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `leavetypenamear` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tpel` bigint NOT NULL DEFAULT '0',
  `tpey` bigint NOT NULL DEFAULT '0',
  `incwe` tinyint(1) NOT NULL DEFAULT '0',
  `incholidays` tinyint(1) NOT NULL DEFAULT '0',
  `transferable` tinyint(1) NOT NULL DEFAULT '0',
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_leavestypes`
--

TRUNCATE TABLE `uhr_leavestypes`;
--
-- Dumping data for table `uhr_leavestypes`
--

INSERT INTO `uhr_leavestypes` (`id`, `rguid`, `leavetypename`, `leavetypenamear`, `tpel`, `tpey`, `incwe`, `incholidays`, `transferable`, `dtadded`, `addedbyid`, `addedby`, `addedbyname`, `addedbynamear`, `lastedit`, `editedbyid`, `editedby`, `editedbyname`, `editedbynamear`, `omitted`) VALUES
(1, 'd463d9028d166816f9c73f092898908b', 'Recurring Leave', 'اجازة اعتيادية', 0, 0, 0, 0, 1, '2026-11-05 21:27:34', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:27:34', 1, 'admin', 'نظام', 'نظام', 0),
(2, '8d31cce1a68fd7a8235550cc78538035', 'Casual leave', 'اجازة عارضة', 0, 0, 0, 0, 0, '2026-11-05 21:28:21', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:28:21', 1, 'admin', 'نظام', 'نظام', 0),
(3, '0da5fdfe033682e52c4f1fa8f0c075a0', 'Sick Leave', 'اجازة مرضية', 0, 0, 1, 1, 1, '2026-11-05 21:28:58', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:28:58', 1, 'admin', 'نظام', 'نظام', 0);

-- --------------------------------------------------------

--
-- Table structure for table `uhr_permissions`
--

DROP TABLE IF EXISTS `uhr_permissions`;
CREATE TABLE IF NOT EXISTS `uhr_permissions` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modulename` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modulenamear` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isadmin` tinyint(1) NOT NULL DEFAULT '0',
  `canadd` tinyint(1) NOT NULL DEFAULT '0',
  `canedit` tinyint(1) NOT NULL DEFAULT '0',
  `candel` tinyint(1) NOT NULL DEFAULT '0',
  `ishradmin` tinyint(1) NOT NULL DEFAULT '0',
  `ishruser` tinyint(1) NOT NULL DEFAULT '0',
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_permissions`
--

TRUNCATE TABLE `uhr_permissions`;
-- --------------------------------------------------------

--
-- Table structure for table `uhr_reqexam`
--

DROP TABLE IF EXISTS `uhr_reqexam`;
CREATE TABLE IF NOT EXISTS `uhr_reqexam` (
  `id` tinyint NOT NULL,
  `label` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `labelar` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_reqexam`
--

TRUNCATE TABLE `uhr_reqexam`;
--
-- Dumping data for table `uhr_reqexam`
--

INSERT INTO `uhr_reqexam` (`id`, `label`, `labelar`, `omitted`) VALUES
(0, 'No', 'لا', 0),
(1, 'Yes', 'نعم', 0);

-- --------------------------------------------------------

--
-- Table structure for table `uhr_retireplan`
--

DROP TABLE IF EXISTS `uhr_retireplan`;
CREATE TABLE IF NOT EXISTS `uhr_retireplan` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `yearofbirth` int NOT NULL,
  `serviceyears` int NOT NULL,
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_retireplan`
--

TRUNCATE TABLE `uhr_retireplan`;
--
-- Dumping data for table `uhr_retireplan`
--

INSERT INTO `uhr_retireplan` (`id`, `rguid`, `yearofbirth`, `serviceyears`, `dtadded`, `addedbyid`, `addedby`, `addedbyname`, `addedbynamear`, `lastedit`, `editedbyid`, `editedby`, `editedbyname`, `editedbynamear`, `omitted`) VALUES
(1, 'cf84ed49034046de04143151a3a6c9ec', 1960, 60, '2026-11-05 21:00:46', 1, 'admin', 'admin', 'admin', '2026-11-05 21:00:46', 1, 'admin', 'admin', 'admin', 0),
(2, '4cb876cdfb62a308e7be43daa6e366c1', 1961, 60, '2026-11-05 21:00:55', 1, 'admin', 'admin', 'admin', '2026-11-05 21:00:55', 1, 'admin', 'admin', 'admin', 0),
(3, 'cabf5e6d3ec8acaa1b62e73eec4eb1b0', 1962, 60, '2026-11-05 21:01:03', 1, 'admin', 'admin', 'admin', '2026-11-05 21:01:03', 1, 'admin', 'admin', 'admin', 0),
(4, 'ebd4b2fdc2691bab64bc84bb19f665f1', 1963, 60, '2026-11-05 21:01:09', 1, 'admin', 'admin', 'admin', '2026-11-05 21:01:09', 1, 'admin', 'admin', 'admin', 0),
(5, '2895b81d07d092004aeba2b5eb2543bf', 1964, 60, '2026-11-05 21:01:15', 1, 'admin', 'admin', 'admin', '2026-11-05 21:01:15', 1, 'admin', 'admin', 'admin', 0),
(6, 'c8970a45362b066dd59fdf94b279e677', 1965, 60, '2026-11-05 21:01:21', 1, 'admin', 'admin', 'admin', '2026-11-05 21:01:21', 1, 'admin', 'admin', 'admin', 0),
(7, 'e678908688d2719dd46c27eb78fdfb59', 1966, 60, '2026-11-05 21:01:27', 1, 'admin', 'admin', 'admin', '2026-11-05 21:01:27', 1, 'admin', 'admin', 'admin', 0),
(8, 'f6ee221d0b6a42edfb88fd8974f062dc', 1967, 60, '2026-11-05 21:01:35', 1, 'admin', 'admin', 'admin', '2026-11-05 21:01:35', 1, 'admin', 'admin', 'admin', 0),
(9, '8f7b62a4f5d132032ba746280c727949', 1968, 60, '2026-11-05 21:01:44', 1, 'admin', 'admin', 'admin', '2026-11-05 21:01:44', 1, 'admin', 'admin', 'admin', 0),
(10, 'e866035090f102b12fe22b04feedccdc', 1969, 60, '2026-11-05 21:01:53', 1, 'admin', 'admin', 'admin', '2026-11-05 21:01:53', 1, 'admin', 'admin', 'admin', 0),
(11, '8375431c7034421bb8a98bd4d89e674b', 1970, 60, '2026-11-05 21:01:59', 1, 'admin', 'admin', 'admin', '2026-11-05 21:01:59', 1, 'admin', 'admin', 'admin', 0),
(12, 'c119516d0a96597ff12fdce84f7704ea', 1971, 61, '2026-11-05 21:02:04', 1, 'admin', 'admin', 'admin', '2026-11-05 21:02:04', 1, 'admin', 'admin', 'admin', 0),
(13, '8e87e5d8cc809d161c2034a816940198', 1972, 61, '2026-11-05 21:02:10', 1, 'admin', 'admin', 'admin', '2026-11-05 21:02:10', 1, 'admin', 'admin', 'admin', 0),
(14, 'a1855eb761b5bd7c70517c53f962e930', 1973, 62, '2026-11-05 21:02:15', 1, 'admin', 'admin', 'admin', '2026-11-05 21:02:15', 1, 'admin', 'admin', 'admin', 0),
(15, 'ab4908e47b663ca5c045dbe639af220c', 1974, 62, '2026-11-05 21:02:22', 1, 'admin', 'admin', 'admin', '2026-11-05 21:02:29', 1, 'admin', 'admin', 'admin', 0),
(16, 'e7f45b569dcbb1cf3197458a32335ff5', 1975, 63, '2026-11-05 21:21:39', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:21:46', 1, 'admin', 'نظام', 'نظام', 0),
(17, 'd0111c3e64c6ae936cbd4963748314fa', 1976, 63, '2026-11-05 21:21:53', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:21:53', 1, 'admin', 'نظام', 'نظام', 0),
(18, '457bec5bbf45ff8d91b5737b28da9297', 1977, 64, '2026-11-05 21:22:06', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:22:06', 1, 'admin', 'نظام', 'نظام', 0),
(19, '56da326da8ddce49c69c9b7645987df1', 1978, 64, '2026-11-05 21:22:14', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:22:14', 1, 'admin', 'نظام', 'نظام', 0),
(20, 'a297535fc4c52761f6e0d2fccaeff0c6', 1979, 64, '2026-11-05 21:22:20', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:22:29', 1, 'admin', 'نظام', 'نظام', 0),
(21, 'd4243384ffde6066e055f316c6c92efe', 1980, 65, '2026-11-05 21:22:48', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:22:48', 1, 'admin', 'نظام', 'نظام', 0),
(22, '0a48997e10fc1ae2f10d6ad1e0d4dcdf', 1981, 65, '2026-11-05 21:22:54', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:22:54', 1, 'admin', 'نظام', 'نظام', 0),
(23, 'a1a3c7800ea3e41bb743ec9212e9d077', 1982, 65, '2026-11-05 21:23:00', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:23:00', 1, 'admin', 'نظام', 'نظام', 0),
(24, '5a67d6d81c6784035d06dd209ffe76e1', 1983, 65, '2026-11-05 21:23:07', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:23:07', 1, 'admin', 'نظام', 'نظام', 0),
(25, 'cb2ac8c9c32b880dca87a213a3968ce8', 1984, 65, '2026-11-05 21:23:14', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:23:14', 1, 'admin', 'نظام', 'نظام', 0),
(26, '6f69b9907119f56cfd3eed0d70f74737', 1985, 65, '2026-11-05 21:23:19', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:23:27', 1, 'admin', 'نظام', 'نظام', 0),
(27, '6cb6792731f96a9bbb1c64a691316c06', 1986, 65, '2026-11-05 21:23:31', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:23:31', 1, 'admin', 'نظام', 'نظام', 0),
(28, '900b30ed2db4ee17a269407d99ccc2e1', 1987, 65, '2026-11-05 21:23:38', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:23:38', 1, 'admin', 'نظام', 'نظام', 0),
(29, 'd3268980bb03a0af9692b19a79743ded', 1988, 65, '2026-11-05 21:23:43', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:23:43', 1, 'admin', 'نظام', 'نظام', 0),
(30, 'eefef4ad0be878c938969a5570aa28f3', 1989, 65, '2026-11-05 21:23:48', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:23:48', 1, 'admin', 'نظام', 'نظام', 0),
(31, '5721c61e9a0e3de945ebc9f31c33c95c', 1990, 65, '2026-11-05 21:23:54', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:23:54', 1, 'admin', 'نظام', 'نظام', 0),
(32, '89ffb4e75c4a3e76dbfbec056dd17f72', 1991, 65, '2026-11-05 21:23:58', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:23:58', 1, 'admin', 'نظام', 'نظام', 0),
(33, 'e19a4b556c611db733b974fc423b37b0', 1992, 65, '2026-11-05 21:24:02', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:24:02', 1, 'admin', 'نظام', 'نظام', 0),
(34, '328c626a71ce2d273c82a02cb74b3f51', 1993, 65, '2026-11-05 21:24:08', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:24:08', 1, 'admin', 'نظام', 'نظام', 0),
(35, '4a7b45357540ab795d4a11776247ea74', 1994, 65, '2026-11-05 21:24:16', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:24:16', 1, 'admin', 'نظام', 'نظام', 0),
(36, 'b84ae5577306694fe0529d8859935ee2', 1959, 60, '2026-11-05 21:24:22', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:24:22', 1, 'admin', 'نظام', 'نظام', 0),
(37, '7b9ba2a7172a40411a3c0b960d33d248', 1958, 60, '2026-11-05 21:24:29', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:24:29', 1, 'admin', 'نظام', 'نظام', 0),
(38, '01c96fc0d3507410086a40e38942634f', 1957, 60, '2026-11-05 21:24:34', 1, 'admin', 'نظام', 'نظام', '2026-11-05 21:24:48', 1, 'admin', 'نظام', 'نظام', 0);

-- --------------------------------------------------------

--
-- Table structure for table `uhr_shadow_log`
--

DROP TABLE IF EXISTS `uhr_shadow_log`;
CREATE TABLE IF NOT EXISTS `uhr_shadow_log` (
  `shadow_id` bigint NOT NULL AUTO_INCREMENT,
  `table_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_id` bigint NOT NULL,
  `action` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changedat` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `changedbyid` bigint DEFAULT NULL,
  `changedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snapshot` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`shadow_id`),
  KEY `idx_table_orig` (`table_name`,`original_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_shadow_log`
--

TRUNCATE TABLE `uhr_shadow_log`;
--
-- Dumping data for table `uhr_shadow_log`
--

INSERT INTO `uhr_shadow_log` (`shadow_id`, `table_name`, `original_id`, `action`, `changedat`, `changedbyid`, `changedby`, `changedbyname`, `changedbynamear`, `snapshot`) VALUES
(1, 'employeesgroup', 1, 'update', '2026-11-05 20:19:04', 1, 'admin', 'admin', 'admin', '{\"id\":1,\"rguid\":\"9349e12f5d4efd751f536f52dae80060\",\"empgroupname\":\"Administrative Staff\",\"empgroupnamear\":\"intérn\",\"dtadded\":\"2026-11-05 22:09:40\",\"addedbyid\":1,\"addedby\":\"System\",\"addedbyname\":\"System\",\"addedbynamear\":\"نظام\",\"lastedit\":\"2026-11-05 22:09:40\",\"editedbyid\":1,\"editedby\":\"System\",\"editedbyname\":\"System\",\"editedbynamear\":\"نظام\",\"omitted\":0}'),
(2, 'retireplan', 15, 'update', '2026-11-05 21:02:29', 1, 'admin', 'admin', 'admin', '{\"id\":15,\"rguid\":\"ab4908e47b663ca5c045dbe639af220c\",\"yearofbirth\":1974,\"serviceyears\":63,\"dtadded\":\"2026-11-05 23:02:22\",\"addedbyid\":1,\"addedby\":\"admin\",\"addedbyname\":\"admin\",\"addedbynamear\":\"admin\",\"lastedit\":\"2026-11-05 23:02:22\",\"editedbyid\":1,\"editedby\":\"admin\",\"editedbyname\":\"admin\",\"editedbynamear\":\"admin\",\"omitted\":0}'),
(3, 'empdegrees', 13, 'update', '2026-11-05 21:20:54', 1, 'admin', 'نظام', 'نظام', '{\"id\":13,\"rguid\":\"f7ef67f9d9e91ddff0d5014af8c5ae31\",\"degreename\":\"Head of Sector\",\"degreenamear\":\"رئيس قطاع\",\"interimperiod\":0,\"nextdegreeid\":14,\"nextdegreename\":\"Consultant\",\"nextdegreenamear\":\"مستشار\",\"reqexam\":0,\"dtadded\":\"2026-11-05 22:09:40\",\"addedbyid\":1,\"addedby\":\"System\",\"addedbyname\":\"System\",\"addedbynamear\":\"نظام\",\"lastedit\":\"2026-11-05 22:09:40\",\"editedbyid\":1,\"editedby\":\"System\",\"editedbyname\":\"System\",\"editedbynamear\":\"نظام\",\"omitted\":0}'),
(4, 'empdegrees', 14, 'update', '2026-11-05 21:21:07', 1, 'admin', 'نظام', 'نظام', '{\"id\":14,\"rguid\":\"20635f21ef838c6c7c7370ed347f80e7\",\"degreename\":\"Consultant\",\"degreenamear\":\"مستشار\",\"interimperiod\":0,\"nextdegreeid\":null,\"nextdegreename\":null,\"nextdegreenamear\":null,\"reqexam\":0,\"dtadded\":\"2026-11-05 22:09:40\",\"addedbyid\":1,\"addedby\":\"System\",\"addedbyname\":\"System\",\"addedbynamear\":\"نظام\",\"lastedit\":\"2026-11-05 22:09:40\",\"editedbyid\":1,\"editedby\":\"System\",\"editedbyname\":\"System\",\"editedbynamear\":\"نظام\",\"omitted\":0}'),
(5, 'empdegrees', 13, 'update', '2026-11-05 21:21:14', 1, 'admin', 'نظام', 'نظام', '{\"id\":13,\"rguid\":\"f7ef67f9d9e91ddff0d5014af8c5ae31\",\"degreename\":\"Secretary-General\",\"degreenamear\":\"أمين عام\",\"interimperiod\":0,\"nextdegreeid\":14,\"nextdegreename\":\"Consultant\",\"nextdegreenamear\":\"مستشار\",\"reqexam\":0,\"dtadded\":\"2026-11-05 22:09:40\",\"addedbyid\":1,\"addedby\":\"System\",\"addedbyname\":\"System\",\"addedbynamear\":\"نظام\",\"lastedit\":\"2026-11-05 23:20:54\",\"editedbyid\":1,\"editedby\":\"admin\",\"editedbyname\":\"نظام\",\"editedbynamear\":\"نظام\",\"omitted\":0}'),
(6, 'retireplan', 16, 'update', '2026-11-05 21:21:46', 1, 'admin', 'نظام', 'نظام', '{\"id\":16,\"rguid\":\"e7f45b569dcbb1cf3197458a32335ff5\",\"yearofbirth\":1975,\"serviceyears\":64,\"dtadded\":\"2026-11-05 23:21:39\",\"addedbyid\":1,\"addedby\":\"admin\",\"addedbyname\":\"نظام\",\"addedbynamear\":\"نظام\",\"lastedit\":\"2026-11-05 23:21:39\",\"editedbyid\":1,\"editedby\":\"admin\",\"editedbyname\":\"نظام\",\"editedbynamear\":\"نظام\",\"omitted\":0}'),
(7, 'retireplan', 20, 'update', '2026-11-05 21:22:29', 1, 'admin', 'نظام', 'نظام', '{\"id\":20,\"rguid\":\"a297535fc4c52761f6e0d2fccaeff0c6\",\"yearofbirth\":1975,\"serviceyears\":64,\"dtadded\":\"2026-11-05 23:22:20\",\"addedbyid\":1,\"addedby\":\"admin\",\"addedbyname\":\"نظام\",\"addedbynamear\":\"نظام\",\"lastedit\":\"2026-11-05 23:22:20\",\"editedbyid\":1,\"editedby\":\"admin\",\"editedbyname\":\"نظام\",\"editedbynamear\":\"نظام\",\"omitted\":0}'),
(8, 'retireplan', 26, 'update', '2026-11-05 21:23:27', 1, 'admin', 'نظام', 'نظام', '{\"id\":26,\"rguid\":\"6f69b9907119f56cfd3eed0d70f74737\",\"yearofbirth\":1986,\"serviceyears\":65,\"dtadded\":\"2026-11-05 23:23:19\",\"addedbyid\":1,\"addedby\":\"admin\",\"addedbyname\":\"نظام\",\"addedbynamear\":\"نظام\",\"lastedit\":\"2026-11-05 23:23:19\",\"editedbyid\":1,\"editedby\":\"admin\",\"editedbyname\":\"نظام\",\"editedbynamear\":\"نظام\",\"omitted\":0}'),
(9, 'retireplan', 38, 'update', '2026-11-05 21:24:48', 1, 'admin', 'نظام', 'نظام', '{\"id\":38,\"rguid\":\"01c96fc0d3507410086a40e38942634f\",\"yearofbirth\":1957,\"serviceyears\":65,\"dtadded\":\"2026-11-05 23:24:34\",\"addedbyid\":1,\"addedby\":\"admin\",\"addedbyname\":\"نظام\",\"addedbynamear\":\"نظام\",\"lastedit\":\"2026-11-05 23:24:34\",\"editedbyid\":1,\"editedby\":\"admin\",\"editedbyname\":\"نظام\",\"editedbynamear\":\"نظام\",\"omitted\":0}'),
(10, 'employeesmain', 1, 'update', '2026-11-05 21:25:32', 1, 'admin', 'نظام', 'نظام', '{\"id\":1,\"rguid\":\"0dbf9117e16b6c5e67b535d7577af39a\",\"empfullname\":\"Ashraf Al-Arabi\",\"empfullnamear\":\"اشرف العربى\",\"email\":\"president@inp.edu.eg\",\"nid\":\"27106010102098\",\"insuranceid\":\"1455\",\"dop\":\"1971-06-01\",\"dor\":\"2032-05-31\",\"empgender\":\"Male\",\"depid\":null,\"depname\":\"\",\"depnamear\":\"\",\"biometricid\":\"10001\",\"empstatusid\":1,\"empstatusname\":\"On Job\",\"empstatusnamear\":\"على رأس العمل\",\"currentdegreeid\":14,\"currentdegreename\":\"Consultant\",\"currentdegreenamear\":\"مستشار\",\"degreeacquireddate\":null,\"senioritydate\":null,\"jobgroupid\":1,\"jobgroupname\":\"Specialized Positions (Higher Education)\",\"jobgroupnamear\":\"الوظائف التخصصية (شهادة جامعية)\",\"emptypeid\":1,\"emptypename\":\"Appointed Employee\",\"emptypenamear\":\"موظف معين\",\"empgroup\":1,\"empgroupname\":\"Administrative Staff\",\"empgroupnamear\":\"اداريين\",\"empsuperiorid\":null,\"empsuperiorname\":\"\",\"empsuperiornamear\":\"\",\"dtadded\":\"2026-11-05 23:03:54\",\"addedbyid\":1,\"addedby\":\"admin\",\"addedbyname\":\"admin\",\"addedbynamear\":\"admin\",\"lastedit\":\"2026-11-05 23:03:54\",\"editedbyid\":1,\"editedby\":\"admin\",\"editedbyname\":\"admin\",\"editedbynamear\":\"admin\",\"omitted\":0}'),
(11, 'jobgroups', 1, 'update', '2026-11-05 22:06:50', 1, 'admin', 'نظام', 'نظام', '{\"id\":1,\"rguid\":\"058f67794705164495667100d38f8dbb\",\"jobgroupname\":\"Specialized Positions (Higher Education)\",\"jobgroupnamear\":\"الوظائف التخصصية (شهادة جامعية)\",\"jobgroupcode\":\"G1\",\"description\":null,\"descriptionar\":null,\"dtadded\":\"2026-11-05 22:09:40\",\"addedbyid\":1,\"addedby\":\"System\",\"addedbyname\":\"System\",\"addedbynamear\":\"نظام\",\"lastedit\":\"2026-11-05 22:09:40\",\"editedbyid\":1,\"editedby\":\"System\",\"editedbyname\":\"System\",\"editedbynamear\":\"نظام\",\"omitted\":0}'),
(12, 'jobgroups', 2, 'update', '2026-11-05 22:07:18', 1, 'admin', 'نظام', 'نظام', '{\"id\":2,\"rguid\":\"af5bae65bad33f635effda1bc475d591\",\"jobgroupname\":\"Technical & Clerical (Diploma)\",\"jobgroupnamear\":\"الوظائف الفنية والكتابية (دبلوم)\",\"jobgroupcode\":\"G2\",\"description\":null,\"descriptionar\":null,\"dtadded\":\"2026-11-05 22:09:40\",\"addedbyid\":1,\"addedby\":\"System\",\"addedbyname\":\"System\",\"addedbynamear\":\"نظام\",\"lastedit\":\"2026-11-05 22:09:40\",\"editedbyid\":1,\"editedby\":\"System\",\"editedbyname\":\"System\",\"editedbynamear\":\"نظام\",\"omitted\":0}');

-- --------------------------------------------------------

--
-- Table structure for table `uhr_users`
--

DROP TABLE IF EXISTS `uhr_users`;
CREATE TABLE IF NOT EXISTS `uhr_users` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rguid` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `departmentid` bigint DEFAULT NULL,
  `departmentname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `departmentnamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permissionid` bigint DEFAULT NULL,
  `lastlogin` datetime DEFAULT NULL,
  `otp_secret` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otp_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `is_superadmin` tinyint(1) NOT NULL DEFAULT '0',
  `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` bigint DEFAULT NULL,
  `addedby` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` bigint DEFAULT NULL,
  `editedby` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbyname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `editedbynamear` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rguid` (`rguid`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `uhr_users`
--

TRUNCATE TABLE `uhr_users`;
--
-- Dumping data for table `uhr_users`
--

INSERT INTO `uhr_users` (`id`, `rguid`, `username`, `email`, `password`, `departmentid`, `departmentname`, `departmentnamear`, `permissionid`, `lastlogin`, `otp_secret`, `otp_enabled`, `is_admin`, `is_superadmin`, `dtadded`, `addedbyid`, `addedby`, `addedbyname`, `addedbynamear`, `lastedit`, `editedbyid`, `editedby`, `editedbyname`, `editedbynamear`, `omitted`) VALUES
(1, 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6', 'admin', 'admin@unithr.local', '$2y$12$w8Mw5pOKhWEWjJFv5PACH.Gz..Vkk6wJQkBvJh.H8AWD.V0YCAKWK', NULL, 'System', 'نظام', NULL, '2026-11-05 23:20:11', 'D3ET3J7YGSSZJMB2', 1, 1, 1, '2026-11-05 20:09:40', 1, 'System', 'System', 'نظام', '2026-11-05 21:20:11', 1, 'System', 'System', 'نظام', 0),
(2, '8f90f10bc90661bd90916991b7adfe07', '27106010102098', 'president@inp.edu.eg', '$2y$12$vs2Qxs8nQ.PPZ39WkMXl3OvafUC3Swyx8XqOmEy3zGbo/.XbcZ4JC', NULL, NULL, NULL, NULL, '2026-11-05 23:04:11', NULL, 0, 0, 0, '2026-11-05 21:03:54', 1, 'admin', 'admin', 'admin', '2026-11-05 21:04:11', 1, 'admin', 'admin', 'admin', 0);
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
