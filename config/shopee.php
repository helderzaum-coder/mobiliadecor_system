<?php

return [
    'partner_id'   => (int) env('SHOPEE_PARTNER_ID'),
    'partner_key'  => env('SHOPEE_PARTNER_KEY'),
    'sandbox'      => env('SHOPEE_SANDBOX', true),
    'redirect_uri' => env('SHOPEE_REDIRECT_URI'),

    // Hosts
    'host_sandbox' => 'https://partner.test-stable.shopeemobile.com',
    'host_live'    => 'https://partner.shopeemobile.com',
];
