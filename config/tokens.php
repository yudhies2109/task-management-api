<?php

return [
    // Masa berlaku refresh token dalam hari.
    'refresh_ttl_days' => (int) env('REFRESH_TOKEN_TTL_DAYS', 7),

    // Origin frontend yang boleh menggunakan endpoint berbasis cookie.
    'frontend_origin' => env('FRONTEND_URL', 'http://localhost:3000'),
];