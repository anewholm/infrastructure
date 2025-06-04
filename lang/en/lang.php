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
            'multi_max_items' => 'Max items in multi-displays',
            'enable_websockets' => 'Enable WebSockets'
        ],
        'phpinfo' => [
            'menu_label' => 'PHP Info',
            'menu_description' => 'Server information',
        ],
        'infrastructure' => 'Infrastructure',
        'seeding_functions' => 'Seeding functions',
    ],
    'models' => [
        'general' => [
            'id' => 'ID',
            'name' => 'Name',
            'noname' => 'no name',
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
            'create_and_add_new' => 'Create and add New',
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
            'view_all' => 'View all',
            'row_changes_saved' => 'Row changes saved',
            'no_changes' => 'No changes found',
            'advanced' => 'Advanced',
            'dateformats' => [
                'day' => 'Day',
                'weekinyear' => 'Week In Year',
                'month' => 'Month',
                'year' => 'Year',
                'timezone' => 'Timezone',
            ],        
            'children' => 'Children',
            'parent' => 'Parent',
        ],
        'server' => [
            'label' => 'Server',
            'label_plural' => 'Servers',
            'response' => 'Response',
            'replicated' => 'Replicated',
            'replicated_source' => 'Replication Source'
        ],
        'export' => [
            'import'      => 'Import',
            'export'      => 'Export',
            'document_template' => 'Document template',
            'batch_print' => 'Batch Print',
            'conditions'  => 'Custom Conditions',
            'conditions_comment' => 'For example, score > 90 (optional)',
            'prepend_uniqid' => 'Prepend a unique ID to the PDF filenames',
            'output_mode' => 'Output mode',
            'compression' => 'Compression',
            'export_output_format' => '1. Export Output Format',
            'select_models' => '2. Select data',
        ]
    ],
    'errors' => [
        'sql' => [
            '23505' => 'Data :constraint is not unique',
            '23502' => 'The :column field is required.',
            '23514' => 'Check :check failed',
        ],
    ],
    'helpblock' => [
        'view_add' => 'View / Add'
    ]
];