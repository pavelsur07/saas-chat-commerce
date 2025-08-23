<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

$_ENV['APP_ENV'] = 'test';

// Безопасный umask для создания файлов/кэша в CI
umask(0002);

// Очистка переменных окружения, мешающих тестам
putenv('SYMFONY_DOTENV_VARS');
