<?php

return [
    'accounts' => [
        'primary' => [
            'name' => 'Mobilia Decor',
            'client_id' => env('BLING_PRIMARY_CLIENT_ID'),
            'client_secret' => env('BLING_PRIMARY_CLIENT_SECRET'),
            'cnpj_id' => env('BLING_PRIMARY_CNPJ_ID', 1),
        ],
        'secondary' => [
            'name' => 'HES Móveis',
            'client_id' => env('BLING_SECONDARY_CLIENT_ID'),
            'client_secret' => env('BLING_SECONDARY_CLIENT_SECRET'),
            'cnpj_id' => env('BLING_SECONDARY_CNPJ_ID', 2),
        ],
    ],

    'api_base'        => 'https://api.bling.com.br/Api/v3',
    'oauth_authorize' => 'https://www.bling.com.br/Api/v3/oauth/authorize',
    'oauth_token'     => 'https://www.bling.com.br/Api/v3/oauth/token',

    'scopes' => 'orders:read orders:write products:read products:write estoques:read estoques:write nfe:read nfe:write',
];
