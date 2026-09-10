<?php

return [
    'default' => env('QUEUE_CONNECTION', 'sync'),
    'connections' => [
        'sync' => ['driver' => 'sync'],
        'reporting' => ['driver' => 'database', 'connection' => null, 'table' => 'reporting_jobs', 'queue' => 'default', 'retry_after' => 600, 'after_commit' => true],
    ],
    'failed' => ['driver' => 'null'],
];
