-- UnitHR Database Schema (MySQL)
-- All tables use the "uhr_" prefix (defined as a session variable in config.php).
-- Every table has basic initial columns (id, rguid) and basic tail audit columns.
-- Records are never hard-deleted; the "omitted" column (default 0) soft-removes them.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------------
-- 1. appmodules
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_appmodules` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `modulename` VARCHAR(100) NOT NULL,
  `modulenamear` VARCHAR(100) NOT NULL,
  `modulefolder` VARCHAR(100) NOT NULL,
  `menu_order` INT NOT NULL DEFAULT 0,
  `description` TEXT,
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 2. deptypes
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_deptypes` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `deptypename` VARCHAR(255) NOT NULL,
  `deptypenamear` VARCHAR(255) NOT NULL,
  `deptypeparentid` BIGINT,
  `deptypeparentname` VARCHAR(255),
  `deptypeparentnamear` VARCHAR(255),
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 3. depstructure
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_depstructure` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `depname` VARCHAR(200) NOT NULL,
  `depnamear` VARCHAR(200) NOT NULL,
  `depparentid` BIGINT,
  `depparentname` VARCHAR(200),
  `depparentnamear` VARCHAR(200),
  `deptypeid` BIGINT,
  `deptype` VARCHAR(255),
  `deptypear` VARCHAR(255),
  `managerid` BIGINT,
  `managername` VARCHAR(255),
  `managernamear` VARCHAR(255),
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 4. users
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_users` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(500) NOT NULL,
  `departmentid` BIGINT,
  `departmentname` VARCHAR(200),
  `departmentnamear` VARCHAR(200),
  `permissionid` BIGINT,
  `lastlogin` DATETIME,
  `otp_secret` VARCHAR(200),
  `otp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `is_superadmin` TINYINT(1) NOT NULL DEFAULT 0,
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 5. permissions
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_permissions` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `modulename` VARCHAR(50) NOT NULL,
  `modulenamear` VARCHAR(50) NOT NULL,
  `isadmin` TINYINT(1) NOT NULL DEFAULT 0,
  `canadd` TINYINT(1) NOT NULL DEFAULT 0,
  `canedit` TINYINT(1) NOT NULL DEFAULT 0,
  `candel` TINYINT(1) NOT NULL DEFAULT 0,
  `ishradmin` TINYINT(1) NOT NULL DEFAULT 0,
  `ishruser` TINYINT(1) NOT NULL DEFAULT 0,
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 6. accesslog
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_accesslog` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `username` VARCHAR(200),
  `ip` VARCHAR(45),
  `useragent` TEXT,
  `login_time` DATETIME,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `reason` VARCHAR(255),
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 7. employeesgroup
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_employeesgroup` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `empgroupname` VARCHAR(100) NOT NULL,
  `empgroupnamear` VARCHAR(100) NOT NULL,
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 8. reqexam
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_reqexam` (
  `id` TINYINT NOT NULL,
  `label` VARCHAR(10) NOT NULL,
  `labelar` VARCHAR(10) NOT NULL,
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default values (0=No/لا, 1=Yes/YES)
INSERT IGNORE INTO `uhr_reqexam` (`id`, `label`, `labelar`, `omitted`) VALUES (0, 'No', 'لا', 0);
INSERT IGNORE INTO `uhr_reqexam` (`id`, `label`, `labelar`, `omitted`) VALUES (1, 'Yes', 'نعم', 0);

-- -------------------------------------------------------------------------
-- Default SuperAdmin account (password: admin123)
-- SuperAdmin bypasses all permission checks, so no permissionid needed.
-- -------------------------------------------------------------------------
INSERT IGNORE INTO `uhr_users` (`rguid`, `username`, `email`, `password`, `departmentid`, `departmentname`, `departmentnamear`, `permissionid`, `lastlogin`, `otp_secret`, `otp_enabled`, `is_admin`, `is_superadmin`, `addedbyid`, `addedby`, `addedbyname`, `addedbynamear`, `lastedit`, `editedbyid`, `editedby`, `editedbyname`, `editedbynamear`, `omitted`) VALUES
('a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6', 'admin', 'admin@unithr.local', '$2y$12$w8Mw5pOKhWEWjJFv5PACH.Gz..Vkk6wJQkBvJh.H8AWD.V0YCAKWK', NULL, 'System', 'نظام', NULL, NULL, NULL, 0, 1, 1, 1, 'System', 'System', 'نظام', NOW(), 1, 'System', 'System', 'نظام', 0);
-- -------------------------------------------------------------------------
-- 9. employeestypes
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_employeestypes` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `emptypename` VARCHAR(50) NOT NULL,
  `emptypenamear` VARCHAR(50) NOT NULL,
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 9. leavestypes
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_leavestypes` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `leavetypename` VARCHAR(100) NOT NULL,
  `leavetypenamear` VARCHAR(100) NOT NULL,
  `tpel` BIGINT NOT NULL DEFAULT 0,
  `tpey` BIGINT NOT NULL DEFAULT 0,
  `incwe` TINYINT(1) NOT NULL DEFAULT 0,
  `incholidays` TINYINT(1) NOT NULL DEFAULT 0,
  `transferable` TINYINT(1) NOT NULL DEFAULT 0,
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 10. empdegrees
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_empdegrees` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `degreename` VARCHAR(50) NOT NULL,
  `degreenamear` VARCHAR(50) NOT NULL,
  `interimperiod` BIGINT NOT NULL DEFAULT 0,
  `reqexam` TINYINT(1) NOT NULL DEFAULT 0,
  `emptypeid` BIGINT,
  `emptypename` VARCHAR(50),
  `emptypenamear` VARCHAR(50),
  `employeesgroupid` BIGINT,
  `empgroupname` VARCHAR(100),
  `empgroupnamear` VARCHAR(100),
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 11. empstatus
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_empstatus` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `empstatusname` VARCHAR(100) NOT NULL,
  `empstatusnamear` VARCHAR(100) NOT NULL,
  `ispayable` TINYINT(1) NOT NULL DEFAULT 1,
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 12. retireplan
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_retireplan` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `yearofbirth` INT NOT NULL,
  `serviceyears` INT NOT NULL,
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 13. fiscalyears
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_fiscalyears` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `fstartdate` DATE NOT NULL,
  `fenddate` DATE NOT NULL,
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 14. employeesmain
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_employeesmain` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `empfullname` VARCHAR(255) NOT NULL,
  `empfullnamear` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255),
  `nid` VARCHAR(50) NOT NULL UNIQUE,
  `insuranceid` VARCHAR(50),
  `dop` DATE,
  `dor` DATE,
  `empgender` VARCHAR(10),
  `depid` BIGINT,
  `depname` VARCHAR(200),
  `depnamear` VARCHAR(200),
  `biometricid` VARCHAR(100),
  `empstatusid` BIGINT,
  `empstatusname` VARCHAR(100),
  `empstatusnamear` VARCHAR(100),
  `currentdegreeid` BIGINT,
  `currentdegreename` VARCHAR(50),
  `currentdegreenamear` VARCHAR(50),
  `emptypeid` BIGINT,
  `emptypename` VARCHAR(50),
  `emptypenamear` VARCHAR(50),
  `empgroup` BIGINT,
  `empgroupname` VARCHAR(100),
  `empgroupnamear` VARCHAR(100),
  `empsuperiorid` BIGINT,
  `empsuperiorname` VARCHAR(255),
  `empsuperiornamear` VARCHAR(255),
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 15. leavesbalances
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_leavesbalances` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `empid` BIGINT NOT NULL,
  `empfullname` VARCHAR(255),
  `empfullnamear` VARCHAR(255),
  `fiscalyearid` BIGINT NOT NULL,
  `fstartdate` DATE,
  `fenddate` DATE,
  `leavetypeid` BIGINT,
  `leavetypename` VARCHAR(100),
  `leavetypenamear` VARCHAR(100),
  `carryoverbalance` INT NOT NULL DEFAULT 0,
  `initialbalance` INT NOT NULL DEFAULT 0,
  `usedbalance` INT NOT NULL DEFAULT 0,
  `remainingbalance` INT NOT NULL DEFAULT 0,
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 16. empleaves
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_empleaves` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `rguid` VARCHAR(200) NOT NULL UNIQUE,
  `empid` BIGINT NOT NULL,
  `empfullname` VARCHAR(255),
  `empfullnamear` VARCHAR(255),
  `leavetypeid` BIGINT NOT NULL,
  `leavetypename` VARCHAR(100),
  `leavetypenamear` VARCHAR(100),
  `startfrom` DATE NOT NULL,
  `endon` DATE NOT NULL,
  `balancebefore` INT NOT NULL DEFAULT 0,
  `balanceafter` INT NOT NULL DEFAULT 0,
  `isapproved` TINYINT(1) NOT NULL DEFAULT 0,
  `rejectedbyid` BIGINT,
  `rejectedby` VARCHAR(200),
  `rejectedbyname` VARCHAR(200),
  `rejectedbynamear` VARCHAR(200),
  `rejectedat` DATETIME,
  `empsuperiorid` BIGINT,
  `empsuperiorname` VARCHAR(255),
  `empsuperiornamear` VARCHAR(255),
  `dtadded` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedbyid` BIGINT,
  `addedby` VARCHAR(200),
  `addedbyname` VARCHAR(200),
  `addedbynamear` VARCHAR(200),
  `lastedit` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `editedbyid` BIGINT,
  `editedby` VARCHAR(50),
  `editedbyname` VARCHAR(200),
  `editedbynamear` VARCHAR(200),
  `omitted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Audit shadow log (generic shadow table for every edited record)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `uhr_shadow_log` (
  `shadow_id` BIGINT NOT NULL AUTO_INCREMENT,
  `table_name` VARCHAR(100) NOT NULL,
  `original_id` BIGINT NOT NULL,
  `action` VARCHAR(20) NOT NULL,
  `changedat` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `changedbyid` BIGINT,
  `changedby` VARCHAR(200),
  `changedbyname` VARCHAR(200),
  `changedbynamear` VARCHAR(200),
  `snapshot` LONGTEXT,
  PRIMARY KEY (`shadow_id`),
  KEY `idx_table_orig` (`table_name`, `original_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
