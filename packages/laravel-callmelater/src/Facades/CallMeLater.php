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
 * @method static \CallMeLater\Laravel\Builders\ChainBuilder chain(string $name)
 * @method static array sendChain(array $payload)
 * @method static array getChain(string $id)
 * @method static array listChains(array $filters = [])
 * @method static array cancelChain(string $id)
 * @method static \CallMeLater\Laravel\Builders\TemplateBuilder template(string $name)
 * @method static array sendTemplate(array $payload)
 * @method static array updateTemplate(string $id, array $payload)
 * @method static array getTemplate(string $id)
 * @method static array listTemplates(array $filters = [])
 * @method static array deleteTemplate(string $id)
 * @method static array regenerateTemplateToken(string $id)
 * @method static array toggleTemplate(string $id)
 * @method static array templateLimits()
 * @method static array trigger(string $token, array $params = [])
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
