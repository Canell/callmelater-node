<?php

namespace CallMeLater\Laravel\Tests;

use CallMeLater\Laravel\CallMeLaterServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [CallMeLaterServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'CallMeLater' => \CallMeLater\Laravel\Facades\CallMeLater::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('callmelater.api_token', 'sk_test_fake_token');
        $app['config']->set('callmelater.api_url', 'https://callmelater.test');
        $app['config']->set('callmelater.webhook_secret', 'test_webhook_secret');
        $app['config']->set('callmelater.timezone', 'America/New_York');
    }
}
