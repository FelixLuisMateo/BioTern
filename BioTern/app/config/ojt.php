<?php

return [
    'internal_ojt_hours' => env('INTERNAL_OJT_HOURS', 300),
    'external_ojt_hours' => env('EXTERNAL_OJT_HOURS', 300),
    'total_ojt_hours' => env('TOTAL_OJT_HOURS', 600),
    
    'daily_work_hours' => 8,
    'minimum_daily_hours' => 4,
    
    'internship_types' => [
        'internal' => 'Internal OJT',
        'external' => 'External OJT',
    ],
    
    'statuses' => [
        'pending' => 'Pending',
        'ongoing' => 'Ongoing',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'dtr_statuses' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],
];