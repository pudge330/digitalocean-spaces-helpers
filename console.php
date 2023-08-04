#!/usr/bin/env php
<?php
use Symfony\Component\Console\Application;

define('CONSOLE_ROOT', __DIR__);

require __DIR__ . '/vendor/autoload.php';

$key = '';
$secret = '';
$region = 'nyc3';

$application = new Application();

$application->add(new BAG\Spaces\Console\SpacesCommand(new BAG\Spaces\Client($key, $secret, $region)));

$application->run();