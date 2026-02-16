<?php

namespace CallMeLater\Laravel;

use CallMeLater\Laravel\Commands\CancelActionCommand;
use CallMeLater\Laravel\Commands\ListActionsCommand;
use CallMeLater\Laravel\Exceptions\ConfigurationException;
use Illuminate\Support\ServiceProvider;

class CallMeLaterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/callmelater.php', 'callmelater');

        $this->app->singleton(CallMeLater::class, function ($app) {
            $config = $app['config']['callmelater'];

            if (empty($config['api_token'])) {
                throw new ConfigurationException(
                    'CallMeLater API token not configured. Set CALLMELATER_API_TOKEN in your .env file.'
                );
            }

            return new CallMeLater(
                apiToken: $config['api_token'],
                apiUrl: $config['api_url'] ?? 'https://callmelater.io',
                webhookSecret: $config['webhook_secret'] ?? null,
                timezone: $config['timezone'] ?? config('app.timezone'),
                retryConfig: $config['retry'] ?? [],
            );
        });

        $this->app->alias(CallMeLater::class, 'callmelater');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/callmelater.php' => config_path('callmelater.php'),
            ], 'callmelater-config');

            $this->commands([
                ListActionsCommand::class,
                CancelActionCommand::class,
            ]);
        }
    }
}
