<?php
/**
 * UnitHR - Payroll module installer
 *
 * Creates the payroll tables and seeds the statutory defaults (basic salary
 * type, income tax, social insurance, Egyptian tax brackets and insurance
 * limits). Safe to run repeatedly: tables use IF NOT EXISTS and seed rows are
 * inserted only when absent.
 *
 * Called by install.php for a fresh installation and by the module's setup
 * script for an existing database.
 */

if (!function_exists('payroll_install')) {
    /**
     * @return array{tables:int,settings:int,types:int,tax_years:int,brackets:int,insurance:int}
     */
    function payroll_install(PDO $pdo, string $P): array
    {
        $audit = "
            `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `addedbyid` bigint DEFAULT NULL,
            `addedby` varchar(200) DEFAULT NULL,
            `addedbyname` varchar(200) DEFAULT NULL,
            `addedbynamear` varchar(200) DEFAULT NULL,
            `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            `editedbyid` bigint DEFAULT NULL,
            `editedby` varchar(50) DEFAULT NULL,
            `editedbyname` varchar(200) DEFAULT NULL,
            `editedbynamear` varchar(200) DEFAULT NULL,
            `omitted` tinyint(1) NOT NULL DEFAULT 0";

        $tables = [
            'payroll_settings' => "CREATE TABLE IF NOT EXISTS `{$P}payroll_settings` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `rguid` varchar(200) NOT NULL,
                `setting_key` varchar(100) NOT NULL,
                `setting_value` text,
                `notes` varchar(255) DEFAULT NULL,
                {$audit},
                PRIMARY KEY (`id`),
                UNIQUE KEY `rguid` (`rguid`),
                UNIQUE KEY `setting_key` (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'payroll_types' => "CREATE TABLE IF NOT EXISTS `{$P}payroll_types` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `rguid` varchar(200) NOT NULL,
                `kind` varchar(20) NOT NULL DEFAULT 'payability',
                `code` varchar(50) NOT NULL,
                `name` varchar(150) NOT NULL,
                `name_ar` varchar(150) NOT NULL,
                `description` text,
                `description_ar` text,
                `calc_method` varchar(30) NOT NULL DEFAULT 'fixed',
                `rate` decimal(9,4) NOT NULL DEFAULT 0,
                `amount` decimal(15,2) NOT NULL DEFAULT 0,
                `base_type_id` bigint DEFAULT NULL,
                `max_amount` decimal(15,2) DEFAULT NULL,
                `min_amount` decimal(15,2) DEFAULT NULL,
                `before_tax` tinyint(1) NOT NULL DEFAULT 1,
                `before_insurance` tinyint(1) NOT NULL DEFAULT 1,
                `is_basic` tinyint(1) NOT NULL DEFAULT 0,
                `is_system` tinyint(1) NOT NULL DEFAULT 0,
                `sort_order` int NOT NULL DEFAULT 100,
                `enabled` tinyint(1) NOT NULL DEFAULT 1,
                {$audit},
                PRIMARY KEY (`id`),
                UNIQUE KEY `rguid` (`rguid`),
                UNIQUE KEY `code` (`code`),
                KEY `kind_enabled` (`kind`, `enabled`),
                KEY `base_type_id` (`base_type_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'payroll_type_degrees' => "CREATE TABLE IF NOT EXISTS `{$P}payroll_type_degrees` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `rguid` varchar(200) NOT NULL,
                `type_id` bigint NOT NULL,
                `degree_id` bigint NOT NULL,
                `amount` decimal(15,2) NOT NULL DEFAULT 0,
                `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `lastedit` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                `omitted` tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `rguid` (`rguid`),
                KEY `type_degree` (`type_id`, `degree_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'payroll_tax_years' => "CREATE TABLE IF NOT EXISTS `{$P}payroll_tax_years` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `rguid` varchar(200) NOT NULL,
                `tax_year` int NOT NULL,
                `personal_exemption` decimal(15,2) NOT NULL DEFAULT 20000,
                `round_to` decimal(10,2) NOT NULL DEFAULT 10,
                `enabled` tinyint(1) NOT NULL DEFAULT 1,
                `notes` varchar(255) DEFAULT NULL,
                {$audit},
                PRIMARY KEY (`id`),
                UNIQUE KEY `rguid` (`rguid`),
                UNIQUE KEY `tax_year` (`tax_year`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'payroll_tax_brackets' => "CREATE TABLE IF NOT EXISTS `{$P}payroll_tax_brackets` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `rguid` varchar(200) NOT NULL,
                `tax_year` int NOT NULL,
                `schedule_min` decimal(15,2) NOT NULL DEFAULT 0,
                `schedule_max` decimal(15,2) DEFAULT NULL,
                `bracket_order` int NOT NULL DEFAULT 1,
                `from_amount` decimal(15,2) NOT NULL DEFAULT 0,
                `to_amount` decimal(15,2) DEFAULT NULL,
                `rate` decimal(7,4) NOT NULL DEFAULT 0,
                `notes` varchar(255) DEFAULT NULL,
                {$audit},
                PRIMARY KEY (`id`),
                UNIQUE KEY `rguid` (`rguid`),
                KEY `year_schedule` (`tax_year`, `schedule_min`, `schedule_max`, `bracket_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'payroll_insurance_rules' => "CREATE TABLE IF NOT EXISTS `{$P}payroll_insurance_rules` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `rguid` varchar(200) NOT NULL,
                `insurance_year` int NOT NULL,
                `employee_rate` decimal(7,4) NOT NULL DEFAULT 11,
                `employer_rate` decimal(7,4) NOT NULL DEFAULT 18.75,
                `min_base` decimal(15,2) NOT NULL DEFAULT 0,
                `max_base` decimal(15,2) DEFAULT NULL,
                `enabled` tinyint(1) NOT NULL DEFAULT 1,
                `notes` varchar(255) DEFAULT NULL,
                {$audit},
                PRIMARY KEY (`id`),
                UNIQUE KEY `rguid` (`rguid`),
                UNIQUE KEY `insurance_year` (`insurance_year`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'payroll_employee_settings' => "CREATE TABLE IF NOT EXISTS `{$P}payroll_employee_settings` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `rguid` varchar(200) NOT NULL,
                `empid` bigint NOT NULL,
                `basic_salary` decimal(15,2) NOT NULL DEFAULT 0,
                `insurance_base_override` decimal(15,2) DEFAULT NULL,
                `tax_exempt` tinyint(1) NOT NULL DEFAULT 0,
                `insurance_exempt` tinyint(1) NOT NULL DEFAULT 0,
                `notes` varchar(255) DEFAULT NULL,
                {$audit},
                PRIMARY KEY (`id`),
                UNIQUE KEY `rguid` (`rguid`),
                KEY `empid` (`empid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'payroll_employee_types' => "CREATE TABLE IF NOT EXISTS `{$P}payroll_employee_types` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `rguid` varchar(200) NOT NULL,
                `empid` bigint NOT NULL,
                `type_id` bigint NOT NULL,
                `amount_override` decimal(15,2) DEFAULT NULL,
                `rate_override` decimal(9,4) DEFAULT NULL,
                `effective_from` date DEFAULT NULL,
                `effective_to` date DEFAULT NULL,
                `enabled` tinyint(1) NOT NULL DEFAULT 1,
                `notes` varchar(255) DEFAULT NULL,
                {$audit},
                PRIMARY KEY (`id`),
                UNIQUE KEY `rguid` (`rguid`),
                KEY `emp_type` (`empid`, `type_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'payroll_employee_type_values' => "CREATE TABLE IF NOT EXISTS `{$P}payroll_employee_type_values` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `rguid` varchar(200) NOT NULL,
                `empid` bigint NOT NULL,
                `type_id` bigint NOT NULL,
                `payroll_year` int NOT NULL,
                `payroll_month` tinyint NOT NULL,
                `amount` decimal(15,2) DEFAULT NULL,
                `rate` decimal(9,4) DEFAULT NULL,
                `source` varchar(50) NOT NULL DEFAULT 'csv',
                `notes` varchar(255) DEFAULT NULL,
                {$audit},
                PRIMARY KEY (`id`),
                UNIQUE KEY `rguid` (`rguid`),
                UNIQUE KEY `emp_type_period` (`empid`, `type_id`, `payroll_year`, `payroll_month`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'payroll_runs' => "CREATE TABLE IF NOT EXISTS `{$P}payroll_runs` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `rguid` varchar(200) NOT NULL,
                `payroll_year` int NOT NULL,
                `payroll_month` tinyint NOT NULL,
                `period_start` date DEFAULT NULL,
                `period_end` date DEFAULT NULL,
                `status` varchar(20) NOT NULL DEFAULT 'draft',
                `employee_count` int NOT NULL DEFAULT 0,
                `gross_total` decimal(15,2) NOT NULL DEFAULT 0,
                `taxable_total` decimal(15,2) NOT NULL DEFAULT 0,
                `tax_total` decimal(15,2) NOT NULL DEFAULT 0,
                `insurance_total` decimal(15,2) NOT NULL DEFAULT 0,
                `employer_insurance_total` decimal(15,2) NOT NULL DEFAULT 0,
                `deductions_total` decimal(15,2) NOT NULL DEFAULT 0,
                `net_total` decimal(15,2) NOT NULL DEFAULT 0,
                `finalized_at` datetime DEFAULT NULL,
                `finalizedbyid` bigint DEFAULT NULL,
                `finalizedby` varchar(200) DEFAULT NULL,
                `notes` varchar(255) DEFAULT NULL,
                {$audit},
                PRIMARY KEY (`id`),
                UNIQUE KEY `rguid` (`rguid`),
                KEY `year_month` (`payroll_year`, `payroll_month`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'payroll_run_items' => "CREATE TABLE IF NOT EXISTS `{$P}payroll_run_items` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `rguid` varchar(200) NOT NULL,
                `run_id` bigint NOT NULL,
                `empid` bigint NOT NULL,
                `empfullname` varchar(255) DEFAULT NULL,
                `empfullnamear` varchar(255) DEFAULT NULL,
                `basic_salary` decimal(15,2) NOT NULL DEFAULT 0,
                `gross_pay` decimal(15,2) NOT NULL DEFAULT 0,
                `taxable_base` decimal(15,2) NOT NULL DEFAULT 0,
                `tax_amount` decimal(15,2) NOT NULL DEFAULT 0,
                `insurance_base` decimal(15,2) NOT NULL DEFAULT 0,
                `insurance_amount` decimal(15,2) NOT NULL DEFAULT 0,
                `employer_insurance` decimal(15,2) NOT NULL DEFAULT 0,
                `deductions_total` decimal(15,2) NOT NULL DEFAULT 0,
                `net_salary` decimal(15,2) NOT NULL DEFAULT 0,
                `details_json` longtext,
                `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `omitted` tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `rguid` (`rguid`),
                KEY `run_emp` (`run_id`, `empid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'payroll_run_lines' => "CREATE TABLE IF NOT EXISTS `{$P}payroll_run_lines` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `rguid` varchar(200) NOT NULL,
                `item_id` bigint NOT NULL,
                `type_kind` varchar(20) NOT NULL,
                `type_code` varchar(50) NOT NULL,
                `name` varchar(150) DEFAULT NULL,
                `name_ar` varchar(150) DEFAULT NULL,
                `calc_method` varchar(30) DEFAULT NULL,
                `rate` decimal(9,4) DEFAULT NULL,
                `base_amount` decimal(15,2) DEFAULT NULL,
                `amount` decimal(15,2) NOT NULL DEFAULT 0,
                `taxable` tinyint(1) NOT NULL DEFAULT 1,
                `insurable` tinyint(1) NOT NULL DEFAULT 1,
                `sort_order` int NOT NULL DEFAULT 100,
                `notes` varchar(255) DEFAULT NULL,
                `dtadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `omitted` tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `rguid` (`rguid`),
                KEY `item_id` (`item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }

        $now = date('Y-m-d H:i:s');
        $actorId = 1;
        $actor = 'System';
        $actorAr = 'نظام';
        $stats = ['tables' => count($tables), 'settings' => 0, 'types' => 0, 'tax_years' => 0, 'brackets' => 0, 'insurance' => 0, 'permissions' => 0];

        // Permission row so the module can be granted to non-admin users.
        $permCheck = $pdo->prepare("SELECT id FROM `{$P}permissions` WHERE modulename='payroll' LIMIT 1");
        $permCheck->execute();
        if (!$permCheck->fetchColumn()) {
            $pdo->prepare("INSERT INTO `{$P}permissions`
                (rguid, modulename, modulenamear, isadmin, canadd, canedit, candel, ishradmin, ishruser,
                 addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
                VALUES (?, 'payroll', 'الإستحقاقات', 1, 1, 1, 1, 1, 1, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, 0)")->execute([
                bin2hex(random_bytes(16)), $actorId, $actor, $actor, $actorAr,
                $actorId, $actor, $actor, $actorAr,
            ]);
            $stats['permissions']++;
        }

        // Settings
        $settings = [
            'tax_enabled' => '1',
            'insurance_enabled' => '1',
            'currency' => 'EGP',
        ];
        $check = $pdo->prepare("SELECT COUNT(*) FROM `{$P}payroll_settings` WHERE setting_key = ?");
        $insert = $pdo->prepare("INSERT INTO `{$P}payroll_settings`
            (rguid, setting_key, setting_value, dtadded, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0)");
        foreach ($settings as $key => $value) {
            $check->execute([$key]);
            if ((int)$check->fetchColumn() === 0) {
                $insert->execute([bin2hex(random_bytes(16)), $key, $value, $now, $actorId, $actor, $actor, $actorAr, $now, $actorId, $actor, $actor, $actorAr]);
                $stats['settings']++;
            }
        }

        // System and example types
        $types = [
            [
                'kind' => 'payability', 'code' => 'BASIC', 'name' => 'Basic Salary', 'name_ar' => 'الراتب الأساسي',
                'method' => 'fixed', 'rate' => 0, 'amount' => 0, 'base' => 0, 'max' => 0, 'min' => 0,
                'before_tax' => 1, 'before_insurance' => 1, 'is_basic' => 1, 'is_system' => 1, 'sort' => 10,
                'desc' => 'The employee basic salary, entered in the payroll employee setup or imported by CSV.',
                'desc_ar' => 'الراتب الأساسي للموظف، يتم إدخاله في إعدادات الموظف أو استيراده من ملف CSV.',
            ],
            [
                'kind' => 'deduction', 'code' => 'TAX', 'name' => 'Income Tax', 'name_ar' => 'ضريبة الدخل',
                'method' => 'tax', 'rate' => 0, 'amount' => 0, 'base' => 0, 'max' => 0, 'min' => 0,
                'before_tax' => 0, 'before_insurance' => 0, 'is_basic' => 0, 'is_system' => 1, 'sort' => 900,
                'desc' => 'Progressive income tax calculated from the editable tax brackets.',
                'desc_ar' => 'ضريبة دخل تصاعدية تُحسب من شرائح الضريبة القابلة للتعديل.',
            ],
            [
                'kind' => 'deduction', 'code' => 'INSURANCE', 'name' => 'Social Insurance', 'name_ar' => 'التأمينات الاجتماعية',
                'method' => 'insurance', 'rate' => 0, 'amount' => 0, 'base' => 0, 'max' => 0, 'min' => 0,
                'before_tax' => 1, 'before_insurance' => 0, 'is_basic' => 0, 'is_system' => 1, 'sort' => 800,
                'desc' => 'Employee social insurance contribution calculated from the editable annual insurance rule.',
                'desc_ar' => 'اشتراك التأمينات الاجتماعية للموظف محسوباً من قاعدة التأمين السنوية القابلة للتعديل.',
            ],
            [
                'kind' => 'deduction', 'code' => 'MARTYRS', 'name' => "Martyrs' Fund", 'name_ar' => 'صندوق الشهداء',
                'method' => 'percent_gross', 'rate' => 0.05, 'amount' => 0, 'base' => 0, 'max' => 0, 'min' => 0,
                'before_tax' => 0, 'before_insurance' => 0, 'is_basic' => 0, 'is_system' => 0, 'sort' => 850,
                'desc' => 'Optional 0.05% contribution on gross pay. Assign it to employees who are subject to it.',
                'desc_ar' => 'مساهمة اختيارية بنسبة 0.05% من إجمالي الراتب. تُسند للموظفين الخاضعين لها.',
            ],
        ];
        $typeCheck = $pdo->prepare("SELECT id FROM `{$P}payroll_types` WHERE code = ? LIMIT 1");
        $typeInsert = $pdo->prepare("INSERT INTO `{$P}payroll_types`
            (rguid, kind, code, name, name_ar, description, description_ar, calc_method, rate, amount, base_type_id,
             max_amount, min_amount, before_tax, before_insurance, is_basic, is_system, sort_order, enabled,
             dtadded, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)");
        foreach ($types as $t) {
            $typeCheck->execute([$t['code']]);
            if ((int)$typeCheck->fetchColumn() > 0) {
                continue;
            }
            $typeInsert->execute([
                bin2hex(random_bytes(16)), $t['kind'], $t['code'], $t['name'], $t['name_ar'],
                $t['desc'], $t['desc_ar'], $t['method'], $t['rate'], $t['amount'],
                ($t['base'] ?: null), ($t['max'] ?: null), ($t['min'] ?: null),
                $t['before_tax'], $t['before_insurance'], $t['is_basic'], $t['is_system'], $t['sort'], 1,
                $now, $actorId, $actor, $actor, $actorAr, $now, $actorId, $actor, $actor, $actorAr,
            ]);
            $stats['types']++;
        }

        // Egyptian tax years and brackets (Law 91/2005 as amended by Law 7/2024)
        $taxYears = [2024, 2025, 2026, 2027];
        $yearCheck = $pdo->prepare("SELECT id FROM `{$P}payroll_tax_years` WHERE tax_year = ? LIMIT 1");
        $yearInsert = $pdo->prepare("INSERT INTO `{$P}payroll_tax_years`
            (rguid, tax_year, personal_exemption, round_to, enabled, notes, dtadded, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
            VALUES (?,?,?,?,1,?,?,?,?,?,?,?,?,?,?,?,0)");
        $bracketCount = $pdo->prepare("SELECT COUNT(*) FROM `{$P}payroll_tax_brackets` WHERE tax_year = ?");
        $bracketInsert = $pdo->prepare("INSERT INTO `{$P}payroll_tax_brackets`
            (rguid, tax_year, schedule_min, schedule_max, bracket_order, from_amount, to_amount, rate, notes, dtadded, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)");

        // Schedules model the Egyptian tier system: as annual income rises,
        // the lower bands are withdrawn (Law 7/2024).
        $schedules = [
            [0, 600000, [[0, 40000, 0], [40000, 55000, 10], [55000, 70000, 15], [70000, 200000, 20], [200000, 400000, 22.5], [400000, null, 25]]],
            [600000, 700000, [[0, 55000, 10], [55000, 70000, 15], [70000, 200000, 20], [200000, 400000, 22.5], [400000, null, 25]]],
            [700000, 800000, [[0, 70000, 15], [70000, 200000, 20], [200000, 400000, 22.5], [400000, null, 25]]],
            [800000, 900000, [[0, 200000, 20], [200000, 400000, 22.5], [400000, null, 25]]],
            [900000, 1200000, [[0, 400000, 22.5], [400000, null, 25]]],
            [1200000, null, [[0, 1200000, 25], [1200000, null, 27.5]]],
        ];
        foreach ($taxYears as $year) {
            $yearCheck->execute([$year]);
            if (!$yearCheck->fetchColumn()) {
                $yearInsert->execute([
                    bin2hex(random_bytes(16)), $year, 20000, 10,
                    'Egyptian income tax brackets (Law 91/2005, amended by Law 7/2024)',
                    $now, $actorId, $actor, $actor, $actorAr, $now, $actorId, $actor, $actor, $actorAr,
                ]);
                $stats['tax_years']++;
            }
            $bracketCount->execute([$year]);
            if ((int)$bracketCount->fetchColumn() > 0) {
                continue;
            }
            foreach ($schedules as $schedule) {
                [$schedMin, $schedMax, $bands] = $schedule;
                $order = 1;
                foreach ($bands as $band) {
                    [$from, $to, $rate] = $band;
                    $bracketInsert->execute([
                        bin2hex(random_bytes(16)), $year, $schedMin, $schedMax, $order, $from, $to, $rate,
                        $order === 1 ? 'First band in schedule' : null,
                        $now, $actorId, $actor, $actor, $actorAr, $now, $actorId, $actor, $actor, $actorAr,
                    ]);
                    $order++;
                    $stats['brackets']++;
                }
            }
        }

        // Social insurance rules (NOSI: 11% employee / 18.75% employer; annual limits)
        $rules = [
            [2024, 11, 18.75, 2000, 12600],
            [2025, 11, 18.75, 2300, 14500],
            [2026, 11, 18.75, 2700, 16700],
            [2027, 11, 18.75, 3200, 19300],
        ];
        $ruleCheck = $pdo->prepare("SELECT id FROM `{$P}payroll_insurance_rules` WHERE insurance_year = ? LIMIT 1");
        $ruleInsert = $pdo->prepare("INSERT INTO `{$P}payroll_insurance_rules`
            (rguid, insurance_year, employee_rate, employer_rate, min_base, max_base, enabled, notes, dtadded, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
            VALUES (?,?,?,?,?,?,1,?,?,?,?,?,?,?,?,?,?,?,0)");
        foreach ($rules as $r) {
            $ruleCheck->execute([$r[0]]);
            if (!$ruleCheck->fetchColumn()) {
                $ruleInsert->execute([
                    bin2hex(random_bytes(16)), $r[0], $r[1], $r[2], $r[3], $r[4],
                    'NOSI social insurance limits for ' . $r[0],
                    $now, $actorId, $actor, $actor, $actorAr, $now, $actorId, $actor, $actor, $actorAr,
                ]);
                $stats['insurance']++;
            }
        }

        return $stats;
    }
}

// Block direct web access when this file is not being included.
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit('Forbidden');
}
