<?php

$cloudinaryUrl = env('CLOUDINARY_URL');

if (!is_string($cloudinaryUrl) || trim($cloudinaryUrl) === '') {
    $cloudinaryKey = (string) env('CLOUDINARY_KEY', '');
    $cloudinarySecret = (string) env('CLOUDINARY_SECRET', '');
    $cloudinaryCloudName = (string) env('CLOUDINARY_CLOUD_NAME', '');

    if ($cloudinaryKey !== '' && $cloudinarySecret !== '' && $cloudinaryCloudName !== '') {
        $cloudinaryUrl = sprintf(
            'cloudinary://%s:%s@%s',
            $cloudinaryKey,
            $cloudinarySecret,
            $cloudinaryCloudName
        );
    }
}

return [
    'notification_url' => env('CLOUDINARY_NOTIFICATION_URL'),
    'cloud_url' => $cloudinaryUrl,
    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
    'upload_route' => env('CLOUDINARY_UPLOAD_ROUTE'),
    'upload_action' => env('CLOUDINARY_UPLOAD_ACTION'),
];
