<?php

namespace Dagemawi\RelayHub\Tests;

use Dagemawi\RelayHub\RelayHubServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [RelayHubServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('relayhub.route_prefix', 'api/relayhub');
        $app['config']->set('relayhub.inbound_secret', 'inbound-test-secret');
        $app['config']->set('relayhub.outbound.url', 'https://partner.example/webhooks');
        $app['config']->set('relayhub.outbound.secret', 'outbound-test-secret');
        $app['config']->set('relayhub.outbound.timeout', 10);
        $app['config']->set('relayhub.outbound.connect_timeout', 3);
        $app['config']->set('relayhub.outbound.max_attempts', 5);
        $app['config']->set('relayhub.outbound.backoff_seconds', [10, 30, 120, 300]);
        $app['config']->set('relayhub.queue', 'integrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--database' => 'testing',
            '--force' => true,
        ])->run();
    }
}
