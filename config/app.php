<?php

$config = [
    'env' => getenv('APP_ENV') ?: 'development',
    'url' => getenv('APP_URL') ?: 'http://localhost/Shoheed%20schools',
    'timezone' => 'Africa/Lagos',
    'name' => 'Shoheed Schools'
];

if ($config['env'] === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

return $config;
