<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/.env.test')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env.test');
}

$_SERVER['APP_ENV'] = $_SERVER['APP_ENV'] ?? 'test';
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'];
$_SERVER['DATABASE_URL'] = $_SERVER['DATABASE_URL'] ?? 'sqlite:///' . dirname(__DIR__) . '/var/data_test.db';
$_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'];

$kernel = new Kernel('test', true);
$kernel->boot();
$application = new Application($kernel);
$application->setAutoExit(false);
$application->run(
    new ArrayInput([
        'command' => 'doctrine:migrations:migrate',
        '--no-interaction' => true,
    ]),
    new NullOutput()
);
$kernel->shutdown();
