<?php
// UnitHR Arabic Language File
// All UI strings in Arabic

// ============================================================
// COMMON / GENERAL
// ============================================================
$lang['app_name'] = 'UnitHR';
$lang['app_description'] = 'حل إدارة الموارد البشرية';
$lang['back'] = 'العودة';
$lang['save'] = 'حفظ';
$lang['update'] = 'تحديث';
$lang['cancel'] = 'إلغاء';
$lang['delete'] = 'حذف';
$lang['edit'] = 'تعديل';
$lang['view'] = 'عرض';
$lang['add'] = 'إضافة';
$lang['add_new'] = 'إضافة جديد';
$lang['search'] = 'بحث';
$lang['reset'] = 'إعادة تعيين';
$lang['export'] = 'تصدير';
$lang['actions'] = 'الإجراءات';
$lang['no_records'] = 'لا توجد سجلات.';
$lang['no_data'] = 'لا توجد بيانات للتصدير.';
$lang['confirm_delete'] = 'هل تريد حذف هذا السجل؟';
$lang['record_added'] = 'تمت إضافة السجل بنجاح.';
$lang['record_updated'] = 'تم تحديث السجل. تم حفظ النسخة السابقة في سجل التعديلات.';
$lang['record_deleted'] = 'تم حذف السجل.';
$lang['required_field'] = 'حقل مطلوب';
$lang['search_placeholder'] = 'بحث (عربي/إنجليزي)...';
$lang['page_title'] = 'UnitHR';
$lang['copyright'] = '© %d UnitHR - فريق UnitHR';
$lang['loading'] = 'جاري التحميل...';
$lang['submit'] = 'إرسال';
$lang['close'] = 'إغلاق';
$lang['yes'] = 'نعم';
$lang['no'] = 'لا';
$lang['none'] = 'لا يوجد';
$lang['select'] = 'اختر';
$lang['select_option'] = '-- اختر --';
$lang['none_option'] = '-- لا يوجد --';

// ============================================================
// AUTHENTICATION
// ============================================================
$lang['login'] = 'تسجيل دخول';
$lang['logout'] = 'تسجيل خروج';
$lang['username'] = 'اسم المستخدم';
$lang['username_email'] = 'اسم المستخدم / البريد الإلكتروني';
$lang['password'] = 'كلمة المرور';
$lang['email'] = 'البريد الإلكتروني';
$lang['login_title'] = 'UnitHR - حل إدارة الموارد البشرية';
$lang['employee_login'] = 'تسجيل دخول الموظفين';
$lang['admin_login'] = 'تسجيل دخول المشرفين';
$lang['enter_credentials_ess'] = 'أدخل بيانات اعتمادك للوصول إلى خدمة الموظف الذاتية.';
$lang['admins_otp_required'] = 'المشرفون ومدراء الموارد البشرية يتطلبون رمز OTP من تطبيق مصادقة.';
$lang['otp_verification'] = 'التحقق من رمز OTP';
$lang['otp_code'] = 'رمز التحقق (6 أرقام)';
$lang['otp_code_placeholder'] = '6-digit code';
$lang['enter_otp_code'] = 'أدخل رمز التحقق من تطبيق المصادقة.';
$lang['verify'] = 'تحقق';
$lang['verify_setup_otp'] = 'تفعيل OTP';
$lang['enable_login'] = 'تفعيل وتسجيل الدخول';
$lang['cancel'] = 'إلغاء';
$lang['otp_setup'] = 'إعداد المصادقة الثنائية (OTP)';
$lang['open_authenticator'] = 'افتح تطبيق المصادقة (Google / Microsoft / FreeOTP / Oracle Authenticator) واطبع الرمز التالي:';
$lang['secret_key'] = 'مفتاح السر (سري):';
$lang['scan_qr'] = 'امسح رمز QR أعلاه بتطبيق المصادقة (Google / Microsoft / FreeOTP / Oracle). يعمل بدون اتصال.';
$lang['code_changes'] = 'الرمز يتغير كل 30 ثانية.';
$lang['otp_enabled_success'] = 'تم تفعيل المصادقة (OTP) بنجاح.';
$lang['invalid_otp'] = 'رمز OTP غير صحيح. تم تسجيل خروجك. يرجى المحاولة مرة أخرى.';
$lang['otp_required'] = 'رمز OTP مطلوب للمشرفين ومدراء الموارد البشرية.';
$lang['invalid_credentials'] = 'اسم المستخدم/البريد الإلكتروني أو كلمة المرور غير صحيحة.';
$lang['enter_username_password'] = 'يرجى إدخال اسم المستخدم/البريد الإلكتروني وكلمة المرور.';
$lang['db_not_available'] = 'اتصال قاعدة البيانات غير متاح. قم بتكوين MySQL وتشغيل التثبيت.';

// ============================================================
// CHANGE PASSWORD
// ============================================================
$lang['change_password'] = 'تغيير كلمة المرور';
$lang['current_password'] = 'كلمة المرور الحالية';
$lang['new_password'] = 'كلمة المرور الجديدة';
$lang['confirm_password'] = 'تأكيد كلمة المرور الجديدة';
$lang['password_changed'] = 'تم تغيير كلمة المرور بنجاح.';
$lang['password_change_failed'] = 'فشل تغيير كلمة المرور.';
$lang['current_password_incorrect'] = 'كلمة المرور الحالية غير صحيحة.';
$lang['passwords_not_match'] = 'كلمة المرور الجديدة وتأكيدها غير متطابقتين.';
$lang['password_min_length'] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.';
$lang['all_fields_required'] = 'جميع الحقول مطلوبة.';

// ============================================================
// NAVIGATION / SIDEBAR
// ============================================================
$lang['dashboard'] = 'لوحة التحكم';
$lang['organization'] = 'التنظيم';
$lang['employees'] = 'الموظفون';
$lang['leaves'] = 'الإجازات';
$lang['users_permissions'] = 'المستخدمون والصلاحيات';
$lang['fiscal'] = 'السنة المالية';
$lang['reports'] = 'التقارير';
$lang['self_service'] = 'خدمة الموظف الذاتية';
$lang['toggle_sidebar'] = 'تبديل الشريط الجانبي';

// ============================================================
// MODULE NAMES
// ============================================================
$lang['module_home'] = 'لوحة التحكم';
$lang['module_appmodules'] = 'وحدات التطبيق';
$lang['module_deptypes'] = 'أنواع الأقسام';
$lang['module_depstructure'] = 'هيكلة الأقسام';
$lang['module_users'] = 'المستخدمون';
$lang['module_permissions'] = 'الصلاحيات';
$lang['module_accesslog'] = 'سجل الدخول';
$lang['module_employeesgroup'] = 'مجموعات الموظفين';
$lang['module_employeestypes'] = 'أنواع الموظفين';
$lang['module_empdegrees'] = 'الدرجات الوظيفية';
$lang['module_empstatus'] = 'الحالة الوظيفية';
$lang['module_retireplan'] = 'خطة التقاعد';
$lang['module_fiscalyears'] = 'السنة المالية';
$lang['module_leavestypes'] = 'أنواع الإجازات';
$lang['module_employeesmain'] = 'الموظفون الرئيسيون';
$lang['module_leavesbalances'] = 'أرصدة الإجازات';
$lang['module_empleaves'] = 'إجازات الموظفين';
$lang['module_reports'] = 'التقارير';
$lang['module_ess'] = 'خدمة الموظف الذاتية';
$lang['module_empseniority'] = 'أقدمية الموظفين';

// ============================================================
// DASHBOARD
// ============================================================
$lang['departments'] = 'الأقسام';
$lang['employees_count'] = 'الموظفون';
$lang['leaves_count'] = 'الإجازات';
$lang['users_count'] = 'المستخدمون';
$lang['leave_types_count'] = 'أنواع الإجازات';

// ============================================================
// DEPARTMENT TYPES
// ============================================================
$lang['department_type_name'] = 'اسم نوع القسم';
$lang['department_type_name_ar'] = 'اسم نوع القسم بالعربية';
$lang['parent_type'] = 'النوع الأصل';
$lang['parent_type_name'] = 'اسم النوع الأصل';
$lang['parent_type_name_ar'] = 'اسم النوع الأصل بالعربية';
$lang['none'] = 'لا يوجد';

// ============================================================
// DEPARTMENT STRUCTURE
// ============================================================
$lang['department_name'] = 'اسم القسم';
$lang['department_name_ar'] = 'اسم القسم بالعربية';
$lang['parent_department'] = 'القسم الأصلي';
$lang['department_type'] = 'نوع القسم';
$lang['manager'] = 'المدير';
$lang['department_details'] = 'تفاصيل القسم';
$lang['department_information'] = 'معلومات القسم';
$lang['sub_departments'] = 'الأقسام الفرعية';
$lang['no_sub_departments'] = 'لا توجد أقسام فرعية';
$lang['add_department'] = 'إضافة قسم';
$lang['edit_department'] = 'تعديل قسم';
$lang['omit_department'] = 'حذف القسم؟';
$lang['department_added'] = 'تم إضافة القسم.';
$lang['department_updated'] = 'تم تحديث القسم.';
$lang['department_omitted'] = 'تم حذف القسم.';
$lang['cannot_omit_dept_in_use'] = 'لا يمكن الحذف: هذا القسم مستخدم من قبل موظفين.';

// ============================================================
// USERS
// ============================================================
$lang['user_username'] = 'اسم المستخدم';
$lang['user_email'] = 'البريد الإلكتروني';
$lang['user_password'] = 'كلمة المرور';
$lang['user_department'] = 'القسم';
$lang['user_department_name'] = 'اسم القسم';
$lang['user_department_name_ar'] = 'اسم القسم بالعربية';
$lang['user_permissions_record'] = 'سجل الصلاحيات';
$lang['user_otp_secret'] = 'مفتاح سر OTP';
$lang['user_otp_enabled'] = 'تفعيل OTP';
$lang['user_is_admin'] = 'مدير تطبيق';
$lang['user_is_superadmin'] = 'مشرف عام';
$lang['reset_otp'] = 'إعادة تعيين OTP';
$lang['reset_otp_confirm'] = 'إعادة تعيين OTP لهذا المستخدم؟';
$lang['otp_reset_success'] = 'تم إعادة تعيين OTP. سيحتاج المستخدم إلى إعداد OTP عند تسجيل الدخول التالي.';

// ============================================================
// PERMISSIONS
// ============================================================
$lang['module_name'] = 'اسم الوحدة';
$lang['module_name_ar'] = 'اسم الوحدة بالعربية';
$lang['is_admin'] = 'مدير';
$lang['can_add'] = 'يمكن الإضافة';
$lang['can_edit'] = 'يمكن التعديل';
$lang['can_omit'] = 'يمكن الحذف';
$lang['is_hr_admin'] = 'مدير موارد بشرية';
$lang['is_hr_employee'] = 'موظف موارد بشرية';

// ============================================================
// ACCESS LOG
// ============================================================
$lang['access_log'] = 'سجل الدخول';
$lang['user'] = 'المستخدم';
$lang['ip_address'] = 'العنوان';
$lang['login_time'] = 'وقت الدخول';
$lang['success'] = 'ناجح';
$lang['reason'] = 'السبب';
$lang['successful_login'] = 'تسجيل دخول ناجح';
$lang['logout_reason'] = 'تسجيل خروج';

// ============================================================
// EMPLOYEE GROUPS
// ============================================================
$lang['employee_group_name'] = 'اسم مجموعة الموظفين';
$lang['employee_group_name_ar'] = 'اسم مجموعة الموظفين بالعربية';

// ============================================================
// EMPLOYEE TYPES
// ============================================================
$lang['employee_type_name'] = 'اسم نوع الموظف';
$lang['employee_type_name_ar'] = 'اسم نوع الموظف بالعربية';

// ============================================================
// EMPLOYEE DEGREES
// ============================================================
$lang['degree_name'] = 'اسم الدرجة';
$lang['degree_name_ar'] = 'اسم الدرجة بالعربية';
$lang['interim_period'] = 'فترة الانتقال';
$lang['require_exam'] = 'يتطلب اختبار';
$lang['employee_type'] = 'نوع الموظف';
$lang['employee_group'] = 'مجموعة الموظفين';

// ============================================================
// EMPLOYEE STATUS
// ============================================================
$lang['employee_status'] = 'الحالة الوظيفية';
$lang['employee_status_ar'] = 'الحالة الوظيفية بالعربية';
$lang['payable_on_payroll'] = 'قابلة للدفع على الكشوف';

// ============================================================
// RETIREMENT PLAN
// ============================================================
$lang['year_of_birth'] = 'سنة الميلاد';
$lang['service_years_to_retire'] = 'سنوات الخدمة للتقاعد';

// ============================================================
// FISCAL YEARS
// ============================================================
$lang['start_date'] = 'تاريخ البداية';
$lang['end_date'] = 'تاريخ الانتهاء';

// ============================================================
// LEAVE TYPES
// ============================================================
$lang['leave_type_name'] = 'اسم نوع الإجازة';
$lang['leave_type_name_ar'] = 'اسم نوع الإجازة بالعربية';
$lang['times_per_employee_life'] = 'عدد المرات مدى الحياة (0=غير محدود)';
$lang['times_per_year'] = 'عدد المرات في السنة (0=غير محدود)';
$lang['include_weekends'] = 'يشمل عطلات نهاية الأسبوع';
$lang['include_holidays'] = 'يشمل العطلات الرسمية';
$lang['transferable'] = 'قابلة للنقل للسنة التالية';

// ============================================================
// EMPLOYEES MAIN
// ============================================================
$lang['full_name'] = 'الاسم الكامل';
$lang['full_name_ar'] = 'الاسم الكامل بالعربية';
$lang['full_name_en'] = 'الاسم الكامل (إنجليزي)';
$lang['national_id'] = 'الرقم qual';
$lang['national_id_min'] = 'الرقم qual (14 رقم على الأقل)';
$lang['insurance_id'] = 'رقم التأمين';
$lang['date_of_birth'] = 'تاريخ الميلاد';
$lang['date_of_birth_from_nid'] = 'تاريخ الميلاد (من الرقم qual)';
$lang['retirement_date'] = 'تاريخ التقاعد';
$lang['gender'] = 'النوع';
$lang['male'] = 'ذكر';
$lang['female'] = 'أنثى';
$lang['department'] = 'القسم';
$lang['biometric_id'] = 'رقم البصمة';
$lang['job_status'] = 'الحالة الوظيفية';
$lang['job_degree'] = 'الدرجة الوظيفية';
$lang['direct_superior'] = 'المدير المباشر';
$lang['employee_details'] = 'تفاصيل الموظف';
$lang['personal_information'] = 'المعلومات الشخصية';
$lang['job_information'] = 'المعلومات الوظيفية';
$lang['login_username'] = 'اسم المستخدم (تسجيل الدخول)';
$lang['password_hint'] = 'كلمة المرور: user1234';
$lang['add_employee'] = 'إضافة موظف';
$lang['edit_employee'] = 'تعديل موظف';
$lang['employees_main'] = 'الموظفون الرئيسيون';
$lang['employee_added'] = 'تم إضافة الموظف. تم إنشاء بيانات الدخول (اسم المستخدم: %s, كلمة المرور: user1234). تم تهيئة أرصدة الإجازات لجميع الأنواع والسنوات.';
$lang['employee_updated'] = 'تم تحديث الموظف. تم تحديث الأرصدة والسجلات ذات الصلة.';
$lang['employee_omitted'] = 'تم حذف الموظف.';
$lang['cannot_omit_employee'] = 'لا يمكن الحذف: الموظف لديه أرصدة إجازات أو سجلات إجازات.';
$lang['omit_employee'] = 'حذف الموظف؟';
$lang['nid_duplicate'] = 'موظف بهذا الرقم qual موجود مسبقاً.';
$lang['insurance_duplicate'] = 'موظف بهذا رقم التأمين موجود مسبقاً.';
$lang['biometric_duplicate'] = 'موظف بهذا رقم البصمة موجود مسبقاً.';
$lang['nid_min_length'] = 'الرقم qual يجب أن يكون 14 رقم على الأقل.';
$lang['name_required'] = 'اسم الموظف (عربي وإنجليزي) مطلوب.';

// ============================================================
// LEAVE BALANCES
// ============================================================
$lang['leave_balance'] = 'رصيد إجازة';
$lang['add_leave_balance'] = 'إضافة رصيد إجازة';
$lang['edit_leave_balance'] = 'تعديل رصيد إجازة';
$lang['employee'] = 'الموظف';
$lang['leave_type'] = 'نوع الإجازة';
$lang['fiscal_year'] = 'السنة المالية';
$lang['carryover_balance'] = 'الرصيد المحول';
$lang['initial_balance'] = 'الرصيد الابتدائي';
$lang['used_balance'] = 'الرصيد المستخدم';
$lang['remaining_balance'] = 'الرصيد المتبقي';
$lang['remaining_auto'] = 'الرصيد المتبقي (يُحسب تلقائياً)';
$lang['carryover'] = 'محول';
$lang['initial'] = 'ابتدائي';
$lang['used'] = 'مستخدم';
$lang['remaining'] = 'متبقي';
$lang['leave_balances'] = 'أرصدة الإجازات';
$lang['omit_leave_balance'] = 'حذف رصيد الإجازة؟';
$lang['leave_balance_added'] = 'تم إضافة رصيد الإجازة.';
$lang['leave_balance_updated'] = 'تم تحديث رصيد الإجازة.';
$lang['leave_balance_omitted'] = 'تم حذف رصيد الإجازة.';
$lang['balance_exists'] = 'رصيد إجازة موجود مسبقاً لهذا الموظف والسنة ونوع الإجازة.';
$lang['emp_fy_required'] = 'الموظف والسنة المالية مطلوبان.';
$lang['emp_fy_lt_required'] = 'الموظف والسنة المالية ونوع الإجازة مطلوبة.';

// ============================================================
// EMPLOYEE LEAVES
// ============================================================
$lang['leave_request'] = 'طلب إجازة';
$lang['add_leave_request'] = 'إضافة طلب إجازة';
$lang['edit_leave_request'] = 'تعديل طلب إجازة';
$lang['leave_details'] = 'تفاصيل الإجازة';
$lang['leave_information'] = 'معلومات الإجازة';
$lang['start_from'] = 'تاريخ البداية';
$lang['end_on'] = 'تاريخ الانتهاء';
$lang['balance_before'] = 'الرصيد قبل';
$lang['balance_after'] = 'الرصيد بعد';
$lang['approved'] = 'موافق';
$lang['pending'] = 'قيد الانتظار';
$lang['status'] = 'الحالة';
$lang['superior'] = 'المدير';
$lang['superior_leave_approver'] = 'المدير / منتمد الإجازة';
$lang['approved_superior_hr'] = 'موافق عليه (المدير / مسؤول الموارد البشرية)';
$lang['employee_leaves'] = 'إجازات الموظفين';
$lang['omit_leave_request'] = 'حذف طلب الإجازة؟';
$lang['leave_added'] = 'تم إضافة طلب الإجازة.';
$lang['leave_updated'] = 'تم تحديث طلب الإجازة.';
$lang['leave_omitted'] = 'تم حذف طلب الإجازة.';
$lang['leave_balance_updated_msg'] = 'تم تحديث الرصيد.';
$lang['not_enough_balance'] = 'رصيد الإجازة غير كافٍ.';
$lang['cannot_span_fiscal'] = 'فترة الإجازة لا يمكن أن تمتد بين سنتين ماليتين.';
$lang['end_date_before_start'] = 'تاريخ الانتهاء لا يمكن أن يكون قبل تاريخ البداية.';
$lang['required_fields_leave'] = 'الموظف ونوع الإجازة وتاريخ البداية والانتهاء مطلوبة.';
$lang['approved_leave_locked'] = 'لا يمكن تعديل أو حذف إجازة موافق عليها.';
$lang['rejected_by'] = 'من رفض';
$lang['rejected_at'] = 'تاريخ الرفض';
$lang['leave_approved'] = 'تمت الموافقة على الإجازة.';
$lang['leave_rejected'] = 'تم رفض الإجازة.';
$lang['no_permission_approve'] = 'ليس لديك صلاحية للموافقة على هذه الإجازة.';
$lang['approve'] = 'موافقة';
$lang['reject'] = 'رفض';
$lang['rejection_reason'] = 'سبب الرفض';

// ============================================================
// ESS (EMPLOYEE SELF-SERVICE)
// ============================================================
$lang['ess_title'] = 'خدمة الموظف الذاتية';
$lang['enter_nid'] = 'أدخل الرقم qual للوصول إلى بياناتك وخدماتك.';
$lang['enter_nid_lookup'] = 'أدخل الرقم qual للبحث عن موظف.';
$lang['nid_not_found'] = 'لم يتم العثور على موظف بهذا الرقم qual.';
$lang['no_employee_record'] = 'لم يتم العثور على سجل موظف مرتبط بحسابك. يرجى التواصل مع المسؤول.';
$lang['ess_employee_details'] = 'بيانات الموظف';
$lang['ess_leave_balances'] = 'أرصدة الإجازات';
$lang['no_balances'] = 'لا توجد أرصدة';
$lang['request_leave'] = 'طلب إجازة neue';
$lang['submit_request'] = 'إرسال الطلب';
$lang['leave_history'] = 'تاريخ الإجازات';
$lang['no_leave_records'] = 'لا توجد إجازات';
$lang['pending_approvals'] = 'طلبات إجازات بانتظار الموافقة';
$lang['approve_leave_confirm'] = 'الموافقة على هذه الإجازة؟';

// ============================================================
// EXPORTS
// ============================================================
$lang['employees_report'] = 'تقرير الموظفين';
$lang['leaves_report'] = 'تقرير الإجازات';
$lang['leave_balances_report'] = 'تقرير أرصدة الإجازات';
$lang['employee_seniority'] = 'أقدمية الموظفين';
$lang['current_degree'] = 'الدرجة الحالية';
$lang['degree_acquired'] = 'تاريخ الحصول على الدرجة';
$lang['seniority_date'] = 'تاريخ الأقدمية';
$lang['interim_period'] = 'فترة الانتقال';
$lang['eligible'] = 'مؤهل';
$lang['next_degree'] = 'الدرجة التالية';
$lang['promote'] = 'ترقية';
$lang['promote_employee'] = 'ترقية موظف';
$lang['promotion_date'] = 'تاريخ الترقية';
$lang['employee_promoted'] = 'تمت ترقية الموظف بنجاح.';
$lang['invalid_data'] = 'بيانات غير صحيحة.';
$lang['years'] = 'سنوات';
$lang['na'] = 'لا يوجد';
$lang['no_vacant_degree'] = 'لا توجد درجة شاغرة.';
$lang['department_structure_report'] = 'تقرير هيكلة الأقسام';
$lang['no_data_export'] = 'لا توجد بيانات للتصدير.';
$lang['db_not_available'] = 'قاعدة البيانات غير متاحة.';

// ============================================================
// MISC
// ============================================================
$lang['back'] = 'العودة';
$lang['return'] = 'عودة';
$lang['select'] = 'اختر';
$lang['search'] = 'بحث';
$lang['reset'] = 'إعادة تعيين';
$lang['from'] = 'من';
$lang['to'] = 'إلى';
$lang['date'] = 'التاريخ';
$lang['name'] = 'الاسم';
$lang['type'] = 'النوع';
$lang['value'] = 'القيمة';
$lang['description'] = 'الوصف';
$lang['order'] = 'الترتيب';
$lang['parent'] = 'الأصل';
$lang['none'] = 'لا يوجد';
$lang['omit_record'] = 'حذف السجل؟';
$lang['records_not_deleted'] = 'السجلات لا تُحذف، بل تُعلّم كمحذوفة وتُخفى من التقارير.';
$lang['yes_omit'] = 'نعم، حذف';
$lang['department'] = 'القسم';
$lang['access_denied'] = 'تم رفض الوصول';
$lang['devtools_not_allowed'] = 'أدوات المطور غير مسموح بها في هذه الصفحة.';
$lang['return_home'] = 'العودة إلى الصفحة الرئيسية';
$lang['enable_devtools'] = 'تفعيل حماية DevTools';
$lang['disable_devtools'] = 'تعطيل حماية DevTools';
$lang['devtools_disabled_session'] = '(معطلة لهذه الجلسة)';
$lang['devtools_active'] = '(نشطة)';
$lang['logout'] = 'تسجيل خروج';

// ============================================================
// DEPARTMENT
// ============================================================
$lang['department_name'] = 'اسم القسم';
$lang['department_name_ar'] = 'اسم القسم بالعربية';
$lang['parent_department'] = 'القسم الأصلي';
$lang['department_type'] = 'نوع القسم';
$lang['manager'] = 'المدير';
$lang['department_details'] = 'تفاصيل القسم';
$lang['department_information'] = 'معلومات القسم';
$lang['sub_departments'] = 'الأقسام الفرعية';
$lang['no_sub_departments'] = 'لا توجد أقسام فرعية';
$lang['add_department'] = 'إضافة قسم';
$lang['edit_department'] = 'تعديل قسم';

// ============================================================
// STATUS MESSAGES
// ============================================================
$lang['success'] = 'نجاح';
$lang['error'] = 'خطأ';
$lang['warning'] = 'تحذير';
$lang['info'] = 'معلومات';

return $lang;
?>