<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['ADMIN_PASSWORD_HASH'] = $_ENV['ADMIN_PASSWORD_HASH'] = password_hash('test-admin-password', PASSWORD_DEFAULT);

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
