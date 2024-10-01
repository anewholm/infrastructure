<?php

return [
    'module' => [
        'name' => 'Acorn',
        'setup' => 'Setup',
        'reports' => 'Reports',
        'replication_debug' => 'Replication Debug',
        'trigger_http_call_response' => 'Trigger HTTP call response',
    ],
    'models' => [
        'general' => [
            'id' => 'ID',
            'name' => 'Name',
            'type' => 'Type',
            'image' => 'Image',
            'select' => 'Select',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',

            'qrcode_name' => 'QR Code name',
            'scan_qrcode' => 'Scan A QRCode',
            'find_by_qrcode' => 'Find by QRCode',
            'save_and_print' => 'Save and Print',
            'correct_and_print' => 'Save correction and print',
            'print' => 'Print',

            'backend_user_group' => 'Group',
            'backend_user' => 'Person',
            'from_backend_user_group' => 'From Group',

            'or' => 'Or',
            'centre_id' => 'Centre ID',
            'leaf_id' => 'Leaf ID',
        ],
        'server' => [
            'label' => 'Server',
            'label_plural' => 'Servers',
            'response' => 'Response',
            'replicated' => 'Replicated',
            'replicated_source' => 'Replication Source',
        ],
    ],
];
