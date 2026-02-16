<?php

namespace CallMeLater\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \CallMeLater\Laravel\Builders\HttpActionBuilder http(string $url)
 * @method static \CallMeLater\Laravel\Builders\ReminderBuilder reminder(string $name)
 * @method static array get(string $id)
 * @method static array list(array $filters = [])
 * @method static array cancel(string $id)
 * @method static void verifySignature(\Illuminate\Http\Request $request)
 * @method static bool isValidSignature(\Illuminate\Http\Request $request)
 * @method static \CallMeLater\Laravel\WebhookHandler webhooks()
 *
 * @see \CallMeLater\Laravel\CallMeLater
 */
class CallMeLater extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CallMeLater\Laravel\CallMeLater::class;
    }
}
