<?php

return [
    'partner_id'   => (int) env('SHOPEE_PARTNER_ID'),
    'partner_key'  => env('SHOPEE_PARTNER_KEY'),
    'shop_id'      => env('SHOPEE_SHOP_ID'),
    'sandbox'      => env('SHOPEE_SANDBOX', true),
    'use_laraditz' => env('SHOPEE_USE_LARADITZ', true),
    'redirect_url' => env('SHOPEE_REDIRECT_URL', env('SHOPEE_REDIRECT_URI', null)),

    // Hosts (apenas fallback para cliente custom)
    'host_sandbox' => 'https://partner.test-stable.shopeemobile.com',
    'host_live'    => 'https://partner.shopeemobile.com',
];
