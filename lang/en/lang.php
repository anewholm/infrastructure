<?php return [
    'module' => [
        'name' => 'Acorn',
        'setup' => 'Setup',
        'reports' => 'Reports',
        'replication_debug' => 'Replication Debug',
        'trigger_http_call_response' => 'Trigger HTTP call response'
    ],
    'settings' => [
        'interface' => [
            'menu_label' => 'Interface',
            'menu_description' => 'Interface settings',
            'multi_max_items' => 'Max items in multi-displays'
        ],
        'reporting' => [
            'menu_label' => 'Reporting',
            'menu_description' => 'OLAP reports settings',
            'cube' => 'Cube',
            'cube_refresh' => 'Cube refresh strategy',
            'cube_refresh_interval' => 'Cube refresh interval'
        ],
        'infrastructure' => 'Infrastructure',
        'seeding_functions' => 'Seeding functions',
    ],
    'models' => [
        'general' => [
            'id' => 'ID',
            'name' => 'Name',
            'description' => 'Notes',
            'notes' => 'Notes',
            'type' => 'Type',
            'image' => 'Image',
            'select' => 'Select',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'save_and_print' => 'Save and Print',
            'correct_and_print' => 'Save correction and print',
            'print' => 'Print',
            'backend_user_group' => 'Group',
            'backend_user' => 'Person',
            'from_backend_user_group' => 'From Group',
            'or' => 'Or',
            'centre_id' => 'Centre ID',
            'leaf_id' => 'Leaf ID',
            'qrcode_name' => 'QR Code name',
            'scan_qrcode' => 'Scan A QRCode',
            'find_by_qrcode' => 'Find by QRCode',
            'find_in_list' => 'Find in list',
            'redirect' => 'Go to record',
            'form_field_complete' => 'Fill out form fields',
            'view_all' => 'View all'
        ],
        'server' => [
            'label' => 'Server',
            'label_plural' => 'Servers',
            'response' => 'Response',
            'replicated' => 'Replicated',
            'replicated_source' => 'Replication Source'
        ]
    ],
    'helpblock' => [
        'view_add' => 'View / Add'
    ]
];