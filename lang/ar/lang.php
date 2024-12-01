<?php

return [
    'module' => [
        'name' => 'Acorn',
        'setup' => 'تثبيت',
        'reports' => 'التقارير',
        'replication_debug' => 'تصحيح أخطاء التكرار',
        'trigger_http_call_response' => 'تشغيل استجابة اتصال HTTP',
    ],
    'models' => [
        'general' => [
            'id' => 'المعرف',
            'name' => 'الأسم',
            'type' => 'النوع',
            'image' => 'الصور',
            'select' => 'إختيار',
            'created_at' => 'تم التسجيل في',
            'updated_at' => 'تم التحديث في',

            'qrcode_name' => 'رمز QR',
            'scan_qrcode' => 'مسح الرمز',
            'find_by_qrcode' => 'البحث بواسطة الرمز',
            'save_and_print' => 'حفظ وطباعة',
            'correct_and_print' => 'حفظ التصحيح وطباعته',
            'print' => 'Print',

            'backend_user_group' => 'المجموعة',
            'backend_user' => 'المستخدم',
            'from_backend_user_group' => 'من المجموعة',

            'or' => 'أو',
            'centre_id' => 'معرف المركز',
            'leaf_id' => 'معرف الفرع',
        ],
        'server' => [
            'label' => 'المخدم',
            'label_plural' => 'المخدمات',
            'response' => 'أستجابة',
            'replicated' => 'تكرر',
            'replicated_source' => 'مصدر تكرار البيانات',
        ],
    ],
    'helpblock' => [
        'view_add' => 'عرض / أضافة',
    ],
];
