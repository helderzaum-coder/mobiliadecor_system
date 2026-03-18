<?php

return [
    'accounts' => [
        'primary' => [
            'name' => 'Mobilia Decor',
            'client_id' => env('ML_PRIMARY_CLIENT_ID'),
            'client_secret' => env('ML_PRIMARY_CLIENT_SECRET'),
            'redirect_uri' => env('ML_PRIMARY_REDIRECT_URI', 'http://localhost/ml/callback'),
            'user_id' => env('ML_PRIMARY_USER_ID'),
        ],
        'secondary' => [
            'name' => 'HES Móveis',
            'client_id' => env('ML_SECONDARY_CLIENT_ID'),
            'client_secret' => env('ML_SECONDARY_CLIENT_SECRET'),
            'redirect_uri' => env('ML_SECONDARY_REDIRECT_URI', 'http://localhost/ml/callback'),
            'user_id' => env('ML_SECONDARY_USER_ID'),
        ],
    ],

    'api_base' => 'https://api.mercadolibre.com',
    'oauth_authorize' => 'https://auth.mercadolivre.com.br/authorization',
    'oauth_token' => 'https://api.mercadolibre.com/oauth/token',
];
