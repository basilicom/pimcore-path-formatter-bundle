<?php

declare(strict_types=1);

use Pimcore\Bootstrap;

// define project root which will be used throughout the bootstrapping process
define('PIMCORE_PROJECT_ROOT', realpath(__DIR__ . '/..'));

// set the used pimcore/symfony environment
putenv('PIMCORE_ENVIRONMENT=test');

require_once realpath(__DIR__ . '/..') . '/vendor/autoload.php';

Bootstrap::setProjectRoot();
Bootstrap::bootstrap();
