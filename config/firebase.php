<?php

return [
    'project_id' => env('FIREBASE_PROJECT_ID', 'skillflowapp-3c4f7'),
    'certificates_url' => env(
        'FIREBASE_CERTIFICATES_URL',
        'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com'
    ),
];
