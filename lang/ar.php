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

// ============================================================
// PAYROLL
// ============================================================
$lang['payroll'] = 'الإستحقاقات';
$lang['payroll_module_title'] = 'الإستحقاقات';
$lang['payroll_subtitle'] = 'إستحقاقات وخصومات قابلة للتعديل، مع ضريبة الدخل المصرية والتأمينات الاجتماعية.';
$lang['payroll_dashboard'] = 'لوحة الإستحقاقات';
$lang['payroll_dashboard_subtitle'] = 'نظرة عامة على إعداد الإستحقاقات والقواعد القانونية والمسيرات الأخيرة.';
$lang['payroll_types'] = 'أنواع الإستحقاقات والخصومات';
$lang['payroll_type'] = 'نوع الإستحقاق / الخصم';
$lang['payroll_payability'] = 'استحقاق';
$lang['payroll_payabilities'] = 'الإستحقاقات';
$lang['payroll_deduction'] = 'خصم';
$lang['payroll_deductions'] = 'الخصومات';
$lang['payroll_employees'] = 'إعداد رواتب الموظفين';
$lang['payroll_employee_setup'] = 'إعداد رواتب الموظفين';
$lang['payroll_import'] = 'استيراد CSV';
$lang['payroll_import_basic'] = 'استيراد الرواتب الأساسية';
$lang['payroll_import_values'] = 'استيراد الخصومات / الإستحقاقات';
$lang['payroll_runs'] = 'مسيرات الرواتب';
$lang['payroll_run'] = 'مسير الرواتب';
$lang['payroll_tax'] = 'ضريبة الدخل';
$lang['payroll_insurance'] = 'التأمينات الاجتماعية';
$lang['payroll_year_end'] = 'التسوية السنوية';
$lang['payroll_settings'] = 'إعدادات الإستحقاقات';
$lang['payroll_new_run'] = 'مسير رواتب جديد';
$lang['payroll_create_run'] = 'إنشاء مسير رواتب';
$lang['payroll_manage_types'] = 'إدارة الأنواع';
$lang['payroll_import_csv'] = 'استيراد CSV';
$lang['payroll_quick_actions'] = 'إجراءات سريعة';
$lang['payroll_active_types'] = 'الأنواع النشطة';
$lang['payroll_payability_types'] = 'أنواع الإستحقاقات';
$lang['payroll_deduction_types'] = 'أنواع الخصومات';
$lang['payroll_employees_with_salary'] = 'الموظفون الذين لهم راتب أساسي';
$lang['payroll_latest_run'] = 'أحدث مسير';
$lang['payroll_ytd_gross'] = 'إجمالي الإستحقاقات حتى تاريخه';
$lang['payroll_ytd_tax'] = 'الضريبة حتى تاريخه';
$lang['payroll_ytd_net'] = 'الصافي حتى تاريخه';
$lang['payroll_current_tax_year'] = 'السنة الضريبية الحالية';
$lang['payroll_current_insurance_year'] = 'سنة التأمين الحالية';
$lang['payroll_personal_exemption'] = 'الإعفاء الشخصي';
$lang['payroll_insurance_max_base'] = 'الحد الأقصى للأجر التأميني';
$lang['payroll_recent_runs'] = 'مسيرات الرواتب الأخيرة';
$lang['payroll_no_runs_yet'] = 'لا توجد مسيرات رواتب بعد.';
$lang['payroll_no_final_runs_year'] = 'لا توجد مسيرات رواتب معتمدة لهذه السنة.';
$lang['payroll_year_end_short'] = 'التسوية السنوية';
$lang['payroll_new_type'] = 'نوع استحقاق / خصم جديد';
$lang['payroll_edit_type'] = 'تعديل نوع الاستحقاق / الخصم';
$lang['payroll_type_details'] = 'تفاصيل النوع';
$lang['payroll_calculation_method'] = 'طريقة الحساب';
$lang['payroll_calc_method'] = 'طريقة الحساب';
$lang['payroll_method_fixed'] = 'مبلغ ثابت';
$lang['payroll_method_manual'] = 'مبلغ يدوي / مستورد';
$lang['payroll_method_percent_basic'] = 'نسبة من الراتب الأساسي';
$lang['payroll_method_percent_gross'] = 'نسبة من إجمالي الإستحقاقات';
$lang['payroll_method_percent_type'] = 'نسبة من نوع آخر';
$lang['payroll_method_degree'] = 'مبلغ حسب درجة الموظف';
$lang['payroll_method_seniority'] = 'مبلغ حسب الأقدمية';
$lang['payroll_method_tax'] = 'ضريبة دخل تلقائية';
$lang['payroll_method_insurance'] = 'تأمينات اجتماعية تلقائية';
$lang['payroll_rate_percent'] = 'النسبة (%)';
$lang['payroll_fixed_amount'] = 'المبلغ الثابت';
$lang['payroll_base_type'] = 'أساس الحساب';
$lang['payroll_base_total_payabilities'] = 'إجمالي الإستحقاقات';
$lang['payroll_max_amount'] = 'الحد الأقصى للمبلغ';
$lang['payroll_min_amount'] = 'الحد الأدنى للمبلغ';
$lang['payroll_before_tax'] = 'قبل الضريبة';
$lang['payroll_after_tax'] = 'بعد الضريبة';
$lang['payroll_before_insurance'] = 'قبل التأمينات';
$lang['payroll_after_insurance'] = 'بعد التأمينات';
$lang['payroll_flags_hint'] = 'قبل الضريبة يعني أن المبلغ يُدرج في الوعاء الخاضع للضريبة (والخصومات تخفضه). وقبل التأمينات يعني أن المبلغ يُدرج في قاعدة التأمين.';
$lang['payroll_sort_order'] = 'ترتيب العرض';
$lang['payroll_system_type'] = 'نوع نظامي';
$lang['payroll_basic_type'] = 'نوع الراتب الأساسي';
$lang['payroll_degree_amounts'] = 'المبالغ حسب درجة الموظف';
$lang['payroll_degree_amounts_hint'] = 'تُستخدم هذه المبالغ عندما تكون طريقة الحساب "مبلغ حسب درجة الموظف". اتركها فارغة لاستخدام صفر.';
$lang['payroll_type_added'] = 'تمت إضافة نوع الاستحقاق / الخصم.';
$lang['payroll_type_updated'] = 'تم تحديث نوع الاستحقاق / الخصم.';
$lang['payroll_type_enabled_msg'] = 'تم تفعيل النوع.';
$lang['payroll_type_disabled_msg'] = 'تم تعطيل النوع.';
$lang['payroll_basic_cannot_disable'] = 'لا يمكن تعطيل نوع الراتب الأساسي.';
$lang['payroll_code_invalid'] = 'يجب أن يتكون الكود من 2 إلى 50 حرفاً باستخدام الحروف والأرقام والشرطة أو الشرطة السفلية.';
$lang['payroll_code_exists'] = 'كود النوع موجود بالفعل.';
$lang['payroll_type_not_found'] = 'نوع الاستحقاق / الخصم غير موجود.';
$lang['payroll_method_hint_fixed'] = 'مبلغ شهري ثابت محدد على النوع.';
$lang['payroll_method_hint_manual'] = 'لا يوجد مبلغ تلقائي. أدخل قيمة مخصصة للموظف أو استورد القيمة من CSV.';
$lang['payroll_method_hint_percent_basic'] = 'يُحسب كنسبة من راتب الموظف الأساسي.';
$lang['payroll_method_hint_percent_gross'] = 'يُحسب كنسبة من إجمالي الإستحقاقات في الفترة.';
$lang['payroll_method_hint_percent_type'] = 'يُحسب كنسبة من النوع الأساسي المحدد. وإذا لم يُحدد أساس، يُستخدم إجمالي الإستحقاقات.';
$lang['payroll_method_hint_degree'] = 'يُؤخذ المبلغ من جدول الدرجات أدناه حسب درجة الموظف الحالية.';
$lang['payroll_method_hint_seniority'] = 'عند وجود نسبة: الراتب الأساسي × النسبة × سنوات الخدمة الكاملة. وإلا: المبلغ الثابت × سنوات الخدمة.';
$lang['payroll_method_hint_tax'] = 'يُحسب من شرائح السنة الضريبية والوعاء الخاضع للضريبة للموظف.';
$lang['payroll_method_hint_insurance'] = 'يُحسب من قاعدة التأمين السنوية وقاعدة الأجر التأميني للموظف.';
$lang['payroll_before_tax_hint'] = 'يُدرج في الوعاء الخاضع للضريبة؛ والخصم يخفض الدخل الخاضع للضريبة.';
$lang['payroll_before_insurance_hint'] = 'يُدرج في قاعدة التأمين؛ والخصم يخفض الأجر التأميني.';
$lang['payroll_assigned_employees'] = 'الموظفون المسندون';
$lang['payroll_default_value'] = 'القيمة الافتراضية';
$lang['payroll_enable'] = 'تفعيل';
$lang['payroll_disable'] = 'تعطيل';
$lang['payroll_tax_years'] = 'سنوات وشرائح ضريبة الدخل';
$lang['payroll_tax_year'] = 'السنة الضريبية';
$lang['payroll_tax_year_details'] = 'تفاصيل السنة الضريبية';
$lang['payroll_tax_brackets'] = 'شرائح الضريبة';
$lang['payroll_tax_schedule'] = 'الجدول الضريبي';
$lang['payroll_schedule'] = 'الجدول';
$lang['payroll_schedule_min'] = 'الجدول من';
$lang['payroll_schedule_max'] = 'الجدول إلى';
$lang['payroll_bracket_order'] = 'ترتيب الشريحة';
$lang['payroll_from_amount'] = 'الشريحة من';
$lang['payroll_to_amount'] = 'الشريحة إلى';
$lang['payroll_band_rate'] = 'النسبة (%)';
$lang['payroll_add_band'] = 'إضافة شريحة';
$lang['payroll_remove_band'] = 'حذف';
$lang['payroll_round_to'] = 'التقريب لأسفل إلى';
$lang['payroll_add_tax_year'] = 'إضافة سنة ضريبية';
$lang['payroll_tax_year_added'] = 'تمت إضافة السنة الضريبية مع نسخة من الشرائح السابقة.';
$lang['payroll_tax_year_exists'] = 'هذه السنة الضريبية موجودة بالفعل.';
$lang['payroll_tax_year_updated'] = 'تم تحديث السنة الضريبية.';
$lang['payroll_tax_year_enabled_msg'] = 'تم تفعيل السنة الضريبية.';
$lang['payroll_tax_year_disabled_msg'] = 'تم تعطيل السنة الضريبية.';
$lang['payroll_tax_saved'] = 'تم حفظ السنة الضريبية والشرائح.';
$lang['payroll_tax_brackets_hint'] = 'يُطبق كل جدول عندما يقع الدخل السنوي الخاضع للضريبة داخل نطاقه. والشرائح داخل الجدول تصاعدية. ترك "إلى" فارغاً يعني ما لا نهاية.';
$lang['payroll_tax_high_income_hint'] = 'يسقط القانون المصري الشرائح الأدنى عند تجاوز الدخل السنوي الخاضع للضريبة 600,000 جنيه. استخدم جدولاً مستقلاً لكل فئة؛ ويُختار الجدول الصحيح تلقائياً.';
$lang['payroll_tax_band'] = 'الشريحة';
$lang['payroll_taxable_amount'] = 'المبلغ الخاضع للضريبة';
$lang['payroll_tax_amount'] = 'قيمة الضريبة';
$lang['payroll_no_brackets'] = 'لا توجد شرائح ضريبية محددة لهذه السنة.';
$lang['payroll_tax_year_required'] = 'يجب إدخال سنة ضريبية صحيحة.';
$lang['payroll_invalid_bracket'] = 'قيم شرائح الضريبة غير صالحة.';
$lang['payroll_bracket_required'] = 'مطلوب شريحة ضريبية واحدة على الأقل.';
$lang['payroll_year_exists'] = 'هذه السنة موجودة بالفعل.';
$lang['payroll_annual_taxable'] = 'الدخل السنوي الخاضع للضريبة';
$lang['payroll_taxable_income'] = 'الدخل الخاضع للضريبة';
$lang['payroll_tax_breakdown'] = 'تفصيل الضريبة';
$lang['payroll_personal_exemption_hint'] = 'يُخصم من الدخل السنوي قبل تطبيق الشرائح.';
$lang['payroll_round_to_hint'] = 'يُقرّب الدخل السنوي الخاضع للضريبة لأسفل إلى هذه القيمة (مصر تستخدم 10 جنيهات).';
$lang['payroll_insurance_rules'] = 'قواعد التأمينات الاجتماعية';
$lang['payroll_insurance_year'] = 'سنة التأمين';
$lang['payroll_employee_rate'] = 'نسبة الموظف (%)';
$lang['payroll_employer_rate'] = 'نسبة صاحب العمل (%)';
$lang['payroll_min_insurance_base'] = 'الحد الأدنى للأجر التأميني';
$lang['payroll_max_insurance_base'] = 'الحد الأقصى للأجر التأميني';
$lang['payroll_add_insurance_year'] = 'إضافة سنة تأمين';
$lang['payroll_insurance_added'] = 'تمت إضافة سنة التأمين.';
$lang['payroll_insurance_saved'] = 'تم حفظ قاعدة التأمين.';
$lang['payroll_insurance_exists'] = 'سنة التأمين هذه موجودة بالفعل.';
$lang['payroll_insurance_enabled_msg'] = 'تم تفعيل سنة التأمين.';
$lang['payroll_insurance_disabled_msg'] = 'تم تعطيل سنة التأمين.';
$lang['payroll_insurance_rate_hint'] = 'نسب اشتراك الموظف وصاحب العمل كنسبة من الأجر التأميني.';
$lang['payroll_insurance_limit_hint'] = 'حدود الأجر التأميني الشهري. تُحسب الاشتراكات بعد تقييد القاعدة بهذه الحدود.';
$lang['payroll_total_insurance_base'] = 'إجمالي قاعدة التأمين';
$lang['payroll_employee_insurance'] = 'تأمينات الموظف';
$lang['payroll_employer_insurance'] = 'تأمينات صاحب العمل';
$lang['payroll_insurance_base'] = 'قاعدة التأمين';
$lang['payroll_insurance_contribution'] = 'اشتراك التأمينات';
$lang['payroll_employee_setup_for'] = 'إعداد رواتب الموظف: %s';
$lang['payroll_basic_salary'] = 'الراتب الأساسي';
$lang['payroll_insurance_base_override'] = 'تجاوز قاعدة التأمين';
$lang['payroll_tax_exempt'] = 'معفى من ضريبة الدخل';
$lang['payroll_insurance_exempt'] = 'معفى من التأمينات الاجتماعية';
$lang['payroll_exempt_hint'] = 'يحتفظ الموظف المعفى باستحقاقاته، لكن لا يُطبق عليه الخصم التلقائي المقابل للضريبة أو التأمينات.';
$lang['payroll_applicable_types'] = 'أنواع الإستحقاقات والخصومات المطبقة';
$lang['payroll_applies'] = 'يُطبق';
$lang['payroll_amount_override'] = 'قيمة مخصصة';
$lang['payroll_rate_override'] = 'نسبة مخصصة (%)';
$lang['payroll_effective_from'] = 'ساري من';
$lang['payroll_effective_to'] = 'ساري إلى';
$lang['payroll_auto_system_type'] = 'نوع نظامي تلقائي';
$lang['payroll_basic_always'] = 'يُطبق دائماً';
$lang['payroll_employee_saved'] = 'تم حفظ إعداد رواتب الموظف.';
$lang['payroll_employee_not_found'] = 'الموظف غير موجود.';
$lang['payroll_no_types_defined'] = 'لم يتم تعريف أنواع إستحقاقات بعد.';
$lang['payroll_assigned_types'] = 'الأنواع المسندة';
$lang['payroll_current_values'] = 'القيم المستوردة للفترة';
$lang['payroll_view_values'] = 'عرض القيم';
$lang['payroll_no_values'] = 'لا توجد قيم مستوردة لهذه الفترة.';
$lang['payroll_search_employee'] = 'ابحث بالاسم أو الرقم القومي أو الرقم التأميني...';
$lang['payroll_employee_nid'] = 'الرقم القومي';
$lang['payroll_employee_insurance_id'] = 'الرقم التأميني';
$lang['payroll_degree'] = 'الدرجة';
$lang['payroll_service_years'] = 'سنوات الخدمة';
$lang['payroll_assign_hint'] = 'حدد الأنواع التي تُطبق على هذا الموظف. تُطبق الضريبة والتأمينات تلقائياً إلا إذا كان الموظف معفياً.';
$lang['payroll_override_hint'] = 'القيمة المخصصة تستبدل المبلغ أو النسبة المحسوبة لهذا الموظف فقط. اتركها فارغة لاستخدام قاعدة النوع.';
$lang['payroll_exempt'] = 'معفى';
$lang['payroll_active'] = 'نشط';
$lang['payroll_inactive'] = 'غير نشط';
$lang['payroll_salary_not_set'] = 'الراتب الأساسي غير محدد';
$lang['payroll_type_disabled_warning'] = 'هذا النوع معطل حالياً ولن يُحتسب.';
$lang['payroll_effective_bad'] = 'تاريخ البداية يجب ألا يكون بعد تاريخ النهاية.';
$lang['payroll_import_basic_title'] = 'استيراد الرواتب الأساسية من CSV';
$lang['payroll_import_values_title'] = 'استيراد قيم الخصومات / الإستحقاقات من CSV';
$lang['payroll_import_basic_hint'] = 'يجب أن يحتوي الملف على معرّف الموظف (الرقم القومي أو التأميني أو رقم الموظف) وعمود الراتب الأساسي. ويمكن تضمين السنة والشهر اختيارياً؛ وإلا تُستخدم الفترة المحددة.';
$lang['payroll_import_values_hint'] = 'استخدم التنسيق الطويل (الموظف، الكود، المبلغ) أو التنسيق العريض (عمود لكل كود نوع). تُحفظ القيم للموظف والشهر المحددين وتستبدل المبلغ المحسوب لتلك الفترة.';
$lang['payroll_csv_file'] = 'ملف CSV';
$lang['payroll_default_period'] = 'الفترة الافتراضية';
$lang['payroll_validate_only'] = 'تحقق فقط (دون حفظ)';
$lang['payroll_create_missing_types'] = 'إنشاء أنواع الخصم غير الموجودة تلقائياً';
$lang['payroll_download_template'] = 'تنزيل القالب';
$lang['payroll_import_run'] = 'استيراد الملف';
$lang['payroll_import_result_title'] = 'نتيجة الاستيراد';
$lang['payroll_import_rows'] = 'الصفوف';
$lang['payroll_import_imported'] = 'تم استيرادها';
$lang['payroll_import_skipped'] = 'تم تجاوزها';
$lang['payroll_import_types_created'] = 'الأنواع المنشأة';
$lang['payroll_import_encoding'] = 'الترميز';
$lang['payroll_import_delimiter'] = 'الفاصل';
$lang['payroll_import_errors'] = 'الأخطاء';
$lang['payroll_import_no_errors'] = 'لا توجد أخطاء في التحقق.';
$lang['payroll_import_row'] = 'الصف';
$lang['payroll_import_error'] = 'خطأ';
$lang['payroll_import_success'] = 'اكتمل استيراد CSV: تم استيراد %d صف/صفوف.';
$lang['payroll_import_validation_success'] = 'اكتمل التحقق: %d صف/صفوف صالحة. لم يتم حفظ أي شيء.';
$lang['payroll_import_failed'] = 'تعذر استيراد ملف CSV.';
$lang['payroll_import_file_required'] = 'يرجى اختيار ملف CSV.';
$lang['payroll_import_invalid_extension'] = 'يُقبل فقط ملفات CSV والنصوص.';
$lang['payroll_import_upload_error'] = 'فشل رفع الملف. يرجى المحاولة مرة أخرى.';
$lang['payroll_import_too_large'] = 'حجم الملف يتجاوز الحد 10 ميجابايت.';
$lang['payroll_import_err_unreadable'] = 'لا يمكن قراءة ملف CSV.';
$lang['payroll_import_err_too_large'] = 'حجم ملف CSV يتجاوز الحد 10 ميجابايت.';
$lang['payroll_import_err_empty_file'] = 'ملف CSV فارغ أو لا يحتوي على صف عناوين.';
$lang['payroll_import_err_no_rows'] = 'ملف CSV لا يحتوي على صفوف بيانات.';
$lang['payroll_import_err_missing_key'] = 'ملف CSV يفتقد عمود معرّف الموظف (nid أو insuranceid أو empid).';
$lang['payroll_import_err_employee'] = 'الموظف غير موجود: %s';
$lang['payroll_import_err_employee_missing'] = 'معرّف الموظف فارغ.';
$lang['payroll_import_err_amount'] = 'مبلغ غير صالح: %s';
$lang['payroll_import_err_rate'] = 'نسبة غير صالحة: %s';
$lang['payroll_import_err_year'] = 'سنة غير صالحة: %s';
$lang['payroll_import_err_month'] = 'شهر غير صالح: %s';
$lang['payroll_import_err_negative'] = 'لا يمكن أن تكون المبالغ أو النسب سالبة.';
$lang['payroll_import_err_no_columns'] = 'لم يتم العثور على أعمدة أكواد الأنواع في ملف CSV.';
$lang['payroll_import_err_type'] = 'كود نوع غير معروف: %s';
$lang['payroll_import_err_basic_type'] = 'نوع الراتب الأساسي غير موجود. قم بتشغيل مثبّت الإستحقاقات أولاً.';
$lang['payroll_import_err_db'] = 'خطأ في قاعدة البيانات: %s';
$lang['payroll_import_created_type_note'] = 'تم إنشاؤه تلقائياً أثناء استيراد CSV.';
$lang['payroll_import_note'] = 'تم الاستيراد من ملف CSV: %s';
$lang['payroll_import_template_basic'] = 'قالب الراتب الأساسي';
$lang['payroll_import_template_wide'] = 'قالب الخصومات العريض';
$lang['payroll_import_template_long'] = 'قالب الخصومات الطويل';
$lang['payroll_import_long_example'] = 'مثال التنسيق الطويل';
$lang['payroll_import_wide_example'] = 'مثال التنسيق العريض';
$lang['payroll_import_basic_example'] = 'مثال الراتب الأساسي';
$lang['payroll_import_dry_run_notice'] = 'وضع التحقق فقط: الأرقام أدناه توضح ما سيتم استيراده، لكن لم يتم حفظ أي شيء.';
$lang['payroll_import_formats_hint'] = 'المعرّفات المقبولة: الرقم القومي، الرقم التأميني، أو رقم الموظف في قاعدة البيانات. الترميزات المقبولة: UTF-8 وWindows-1256. الفواصل المقبولة: فاصلة، فاصلة منقوطة، تبويب.';
$lang['payroll_import_stored'] = 'تم حفظ الملف الأصلي في uploads/payroll.';
$lang['payroll_period'] = 'الفترة';
$lang['payroll_run_status'] = 'حالة المسير';
$lang['payroll_draft'] = 'مسودة';
$lang['payroll_final'] = 'نهائي';
$lang['payroll_finalized'] = 'معتمد';
$lang['payroll_finalize'] = 'اعتماد';
$lang['payroll_reopen'] = 'إعادة فتح';
$lang['payroll_recalculate'] = 'إعادة الحساب';
$lang['payroll_omit_run'] = 'حذف المسير';
$lang['payroll_omit_run_confirm'] = 'حذف مسير الرواتب هذا؟ سيُخفى من القوائم ومن إجماليات التسوية السنوية.';
$lang['payroll_run_created'] = 'تم إنشاء مسير الرواتب.';
$lang['payroll_run_exists'] = 'يوجد مسير رواتب بالفعل لهذه الفترة.';
$lang['payroll_run_not_found'] = 'مسير الرواتب غير موجود.';
$lang['payroll_run_finalized_msg'] = 'تم اعتماد مسير الرواتب وقفله.';
$lang['payroll_run_reopened_msg'] = 'تمت إعادة فتح مسير الرواتب للتعديل.';
$lang['payroll_run_recalculated_msg'] = 'تمت إعادة حساب مسير الرواتب.';
$lang['payroll_run_omitted_msg'] = 'تم حذف مسير الرواتب.';
$lang['payroll_run_locked'] = 'هذا المسير معتمد ولا يمكن تعديله. أعد فتحه أولاً.';
$lang['payroll_run_draft_only'] = 'لا يمكن تعديل إلا مسيرات المسودة.';
$lang['payroll_run_no_employees'] = 'لا يوجد موظفون لديهم إعداد رواتب أو قيم مستوردة لهذه الفترة.';
$lang['payroll_run_employees'] = 'موظفو المسير';
$lang['payroll_employee_count'] = 'الموظفون';
$lang['payroll_gross_pay'] = 'إجمالي الإستحقاقات';
$lang['payroll_taxable_base'] = 'الوعاء الخاضع للضريبة';
$lang['payroll_income_tax'] = 'ضريبة الدخل';
$lang['payroll_total_deductions'] = 'إجمالي الخصومات';
$lang['payroll_net_salary'] = 'صافي الراتب';
$lang['payroll_net'] = 'الصافي';
$lang['payroll_view_payslip'] = 'قسيمة الراتب';
$lang['payroll_payslip'] = 'قسيمة الراتب';
$lang['payroll_payslip_for'] = 'قسيمة راتب %s';
$lang['payroll_run_details'] = 'تفاصيل مسير الرواتب';
$lang['payroll_run_lines'] = 'بنود المسير';
$lang['payroll_run_summary'] = 'ملخص المسير';
$lang['payroll_finalized_by'] = 'اعتمده';
$lang['payroll_finalized_at'] = 'تاريخ الاعتماد';
$lang['payroll_no_items'] = 'لا يحتوي هذا المسير على بنود موظفين.';
$lang['payroll_export_run'] = 'تصدير المسير';
$lang['payroll_export_lines'] = 'تصدير البنود';
$lang['payroll_status_draft'] = 'مسودة';
$lang['payroll_status_final'] = 'نهائي';
$lang['payroll_run_cannot_finalize'] = 'لا يمكن اعتماد مسير بدون بنود موظفين.';
$lang['payroll_run_recalc_hint'] = 'إعادة الحساب تستبدل بنود المسودة بأحدث إعداد وقواعد وقيم مستوردة.';
$lang['payroll_run_final_hint'] = 'الاعتماد يقفل المسير ويُدرجه في التسوية السنوية.';
$lang['payroll_finalize_confirm'] = 'اعتماد مسير الرواتب وقفله؟';
$lang['payroll_reopen_confirm'] = 'إعادة فتح هذا المسير المعتمد للتعديل؟';
$lang['payroll_omit_run_hint'] = 'يمكن حذف مسيرات المسودة فقط. أعد فتح المسير المعتمد أولاً.';
$lang['payroll_line_type'] = 'النوع';
$lang['payroll_line_code'] = 'الكود';
$lang['payroll_line_base'] = 'الأساس';
$lang['payroll_line_rate'] = 'النسبة';
$lang['payroll_line_amount'] = 'المبلغ';
$lang['payroll_line_taxable'] = 'خاضع للضريبة';
$lang['payroll_line_insurable'] = 'خاضع للتأمين';
$lang['payroll_line_source'] = 'المصدر';
$lang['payroll_source'] = 'المصدر';
$lang['payroll_source_import'] = 'مستورد';
$lang['payroll_source_calculated'] = 'محسوب';
$lang['payroll_source_settings'] = 'إعداد الموظف';
$lang['payroll_source_assignment'] = 'تجاوز الإسناد';
$lang['payroll_months_count'] = 'عدد الأشهر';
$lang['payroll_year_end_title'] = 'التسوية السنوية للضريبة والتأمينات';
$lang['payroll_year_end_hint'] = 'تُبنى من مسيرات الرواتب المعتمدة. تُعاد الضريبة السنوية المستحقة على إجمالي الدخل الخاضع للضريبة؛ والفرق عن الضريبة المخصومة هو فرق التسوية الضريبية للموظف.';
$lang['payroll_tax_offset'] = 'فرق التسوية الضريبية';
$lang['payroll_tax_creditor'] = 'دائن ضريبي';
$lang['payroll_tax_debtor'] = 'مدين ضريبي';
$lang['payroll_tax_settled'] = 'مُسوّى';
$lang['payroll_annual_tax_due'] = 'الضريبة السنوية المستحقة';
$lang['payroll_tax_withheld'] = 'الضريبة المخصومة';
$lang['payroll_annual_insurance_base'] = 'قاعدة التأمين السنوية';
$lang['payroll_annual_insurance'] = 'تأمينات الموظف السنوية';
$lang['payroll_total_tax_paid'] = 'إجمالي الضريبة المخصومة';
$lang['payroll_total_tax_due'] = 'إجمالي الضريبة المستحقة';
$lang['payroll_total_net_offset'] = 'صافي فرق التسوية';
$lang['payroll_creditors'] = 'الدائنون';
$lang['payroll_debtors'] = 'المدينون';
$lang['payroll_finalized_runs_only'] = 'تُدرج المسيرات المعتمدة فقط في التسوية السنوية.';
$lang['payroll_no_final_runs'] = 'لا توجد مسيرات معتمدة للسنة المختارة.';
$lang['payroll_year_end_export'] = 'تصدير التسوية السنوية';
$lang['payroll_months_paid'] = 'الأشهر المدفوعة';
$lang['payroll_annual_gross'] = 'الإجمالي السنوي';
$lang['payroll_annual_net'] = 'الصافي السنوي';
$lang['payroll_offset_positive_hint'] = 'الفرق الموجب يعني أن الموظف مدين بضريبة إضافية (مدين).';
$lang['payroll_offset_negative_hint'] = 'الفرق السالب يعني أن الموظف دفع ضريبة أكثر من المستحق (دائن).';
$lang['payroll_settings_saved'] = 'تم حفظ إعدادات الإستحقاقات.';
$lang['payroll_currency'] = 'العملة';
$lang['payroll_enable_tax'] = 'تفعيل ضريبة الدخل التلقائية';
$lang['payroll_enable_insurance'] = 'تفعيل التأمينات الاجتماعية التلقائية';
$lang['payroll_settings_hint'] = 'تتحكم هذه المفاتيح في الخصومات النظامية التلقائية. يمكن تعليم الموظفين كمعفيين بشكل فردي.';
$lang['payroll_required'] = 'هذا الحقل مطلوب.';
$lang['payroll_invalid_amount'] = 'أدخل مبلغاً صالحاً.';
$lang['payroll_invalid_rate'] = 'أدخل نسبة صالحة بين 0 و100.';
$lang['payroll_invalid_year'] = 'أدخل سنة صالحة.';
$lang['payroll_invalid_month'] = 'أدخل شهراً صالحاً.';
$lang['payroll_saved'] = 'تم الحفظ.';
$lang['payroll_all_kinds'] = 'كل الأنواع';
$lang['payroll_kind'] = 'النوع';
$lang['payroll_current_period'] = 'الفترة الحالية';
$lang['payroll_month_1'] = 'يناير';
$lang['payroll_month_2'] = 'فبراير';
$lang['payroll_month_3'] = 'مارس';
$lang['payroll_month_4'] = 'أبريل';
$lang['payroll_month_5'] = 'مايو';
$lang['payroll_month_6'] = 'يونيو';
$lang['payroll_month_7'] = 'يوليو';
$lang['payroll_month_8'] = 'أغسطس';
$lang['payroll_month_9'] = 'سبتمبر';
$lang['payroll_month_10'] = 'أكتوبر';
$lang['payroll_month_11'] = 'نوفمبر';
$lang['payroll_month_12'] = 'ديسمبر';
$lang['payroll_no_permission'] = 'ليس لديك صلاحية لتنفيذ هذا الإجراء في الإستحقاقات.';
$lang['payroll_employee_name'] = 'اسم الموظف';
$lang['payroll_national_id'] = 'الرقم القومي';
$lang['payroll_insurance_number'] = 'الرقم التأميني';
$lang['payroll_basic'] = 'الأساسي';
$lang['payroll_total'] = 'الإجمالي';
$lang['payroll_print'] = 'طباعة';
$lang['payroll_download'] = 'تنزيل';
$lang['payroll_file'] = 'الملف';
$lang['payroll_back_to_runs'] = 'العودة إلى مسيرات الرواتب';
$lang['payroll_back_to_employees'] = 'العودة إلى إعداد الموظفين';
$lang['payroll_back_to_types'] = 'العودة إلى الأنواع';
$lang['payroll_back_to_tax'] = 'العودة إلى السنوات الضريبية';
$lang['payroll_view_all'] = 'عرض الكل';
$lang['payroll_system'] = 'نظامي';
$lang['payroll_custom'] = 'مخصص';
$lang['payroll_automatic'] = 'تلقائي';
$lang['payroll_payslip_note'] = 'تم إنشاء قسيمة الراتب هذه من لقطة مسير الرواتب.';
$lang['payroll_net_pay'] = 'صافي الدفع';
$lang['payroll_employee_signature'] = 'توقيع الموظف';
$lang['payroll_authorized_signature'] = 'التوقيع المعتمد';
$lang['payroll_no_data'] = 'لا توجد بيانات.';
$lang['payroll_percent'] = '%';
$lang['payroll_egp'] = 'جنيه';
$lang['payroll_year'] = 'السنة';
$lang['payroll_month'] = 'الشهر';
$lang['payroll_status'] = 'الحالة';
$lang['payroll_notes'] = 'ملاحظات';
$lang['payroll_edit'] = 'تعديل';
$lang['payroll_view'] = 'عرض';
$lang['payroll_import_validation_errors'] = 'اكتمل التحقق مع وجود أخطاء.';
$lang['code'] = 'الكود';
$lang['notes'] = 'ملاحظات';
$lang['filter'] = 'تصفية';
$lang['total'] = 'الإجمالي';

$lang['dir'] = 'rtl';
$lang['is_rtl'] = true;
return $lang;
?>