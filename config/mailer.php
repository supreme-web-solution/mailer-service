<?php

return [
    'queue' => env('MAILER_QUEUE', 'mailers'),
    'chunk_size' => (int) env('MAILER_CHUNK_SIZE', 200),
    'resend' => [
        'max_retries' => (int) env('MAILER_RESEND_MAX_RETRIES', 3),
        'webhook_secret' => env('RESEND_WEBHOOK_SECRET'),
    ],
];
