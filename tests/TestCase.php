<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $testingEnvironment = [
            'APP_ENV' => 'testing',
            'APP_CONFIG_CACHE' => dirname(__DIR__).'/bootstrap/cache/testing-config.php',
            'APP_ROUTES_CACHE' => dirname(__DIR__).'/bootstrap/cache/testing-routes.php',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ];

        foreach ($testingEnvironment as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $app = parent::createApplication();

        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Las pruebas solo pueden ejecutarse con SQLite en memoria.');
        }

        return $app;
    }
}
