<?php

declare(strict_types=1);

use Pimcore\Bootstrap;

// define project root which will be used throughout the bootstrapping process
define('PIMCORE_PROJECT_ROOT', realpath(__DIR__ . '/..'));

// set the used pimcore/symfony environment
putenv('PIMCORE_ENVIRONMENT=test');

require_once realpath(__DIR__ . '/..') . '/vendor/autoload.php';

file_put_contents(dirname(__DIR__) . '/.env', 'APP_ENV=test');

Bootstrap::setProjectRoot();
Bootstrap::bootstrap();

unlink(dirname(__DIR__) . '/.env');
