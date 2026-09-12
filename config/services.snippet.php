<?php
// Merge into config/services.php return array:
return [
    'datasika' => [
        'base_url' => env('DATASIKA_BASE_URL', 'https://nrsfvhztpzwkadwciizp.supabase.co/functions/v1'),
        'api_key' => env('DATASIKA_API_KEY', 'mock'),
    ],
    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
    ],
];
