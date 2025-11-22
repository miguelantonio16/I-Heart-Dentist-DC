<?php
// Get your keys from https://dashboard.paymongo.com/developers
define('PAYMONGO_SECRET_KEY', 'sk_test_LXne9VWhFHEoZEL4KR1Wytmp'); 
define('PAYMONGO_PUBLIC_KEY', 'pk_test_nRBJo8rfD32iQXkSiGRhPDqz');

function createPayMongoSession($amount, $description, $success_url, $cancel_url) {
    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
    
    // Amount must be in centavos (multiply PHP by 100)
    $amount_in_centavos = $amount * 100;

    $data = [
        'data' => [
            'attributes' => [
                'line_items' => [[
                    'currency' => 'PHP',
                    'amount' => $amount_in_centavos,
                    'description' => $description,
                    'name' => 'IHeartDentistDC Payment',
                    'quantity' => 1
                ]],
                'payment_method_types' => ['card', 'gcash', 'paymaya'],
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'description' => $description
            ]
        ]
    ];

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':')
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}
?>
