<?php

$partnerId  = 1230004;
$partnerKey = '72654256536d51704d63636f76524668476e4b6c516a427876735163647a'; // sem prefixo shpk
$path       = '/api/v2/shop/auth_partner';
$timestamp  = time();

$baseString = $partnerId . $path . $timestamp;
$sign       = hash_hmac('sha256', $baseString, $partnerKey);

$url = "https://partner.test-stable.shopeemobile.com{$path}"
     . "?partner_id={$partnerId}&timestamp={$timestamp}&sign={$sign}"
     . "&redirect=https://www.hesmoveis.com.br/shopee/callback";

echo "Base string : {$baseString}\n";
echo "Sign        : {$sign}\n";
echo "URL         : {$url}\n";
