<?php

return [
    // Masa berlaku refresh token dalam hari.
    'refresh_ttl_days' => (int) env('REFRESH_TOKEN_TTL_DAYS', 7),
];