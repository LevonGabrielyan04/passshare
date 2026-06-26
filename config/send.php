<?php

return [
    'max_per_user' => env('MAX_SENDS_PER_USER',15),
    'cache_ttl' => env('SEND_CACHE_TTL', 60),
    'message' => [
        'max_length' => env('MAX_MESSAGE_LENGTH', 1000),
        'encrypted_max_length' => env('ENCRYPTED_MAX_MESSAGE_LENGTH', 5372),
    ],
    'password' => [
        'min_length' => 15,
    ],
];
