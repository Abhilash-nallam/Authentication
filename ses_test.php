<?php

require __DIR__ . '/bootstrap.php';

$ses = new Aws\SesV2\SesV2Client([
    'version' => 'latest',
    'region' => OtpAuth\Config::env('AWS_REGION'),
    'credentials' => [
        'key' => OtpAuth\Config::env('AWS_ACCESS_KEY_ID'),
        'secret' => OtpAuth\Config::env('AWS_SECRET_ACCESS_KEY'),
    ],
]);

$result = $ses->sendEmail([
    'FromEmailAddress' => 'abhilashnallam1@gmail.com',
    'Destination' => [
        'ToAddresses' => [
            'abhilashnallam1@gmail.com',
        ],
    ],
    'Content' => [
        'Simple' => [
            'Subject' => [
                'Data' => 'OTP-Auth SES Real Test',
            ],
            'Body' => [
                'Text' => [
                    'Data' => 'OTP-Auth real SES email test.',
                ],
            ],
        ],
    ],
]);

echo "EMAIL SENT", PHP_EOL;
echo "Message ID: ", $result['MessageId'], PHP_EOL;