<?php

namespace CallMeLater\Laravel\Tests\Unit;

use CallMeLater\Laravel\CallMeLater;
use CallMeLater\Laravel\Events\ActionExecuted;
use CallMeLater\Laravel\Events\ActionFailed;
use CallMeLater\Laravel\Events\ReminderResponded;
use CallMeLater\Laravel\Exceptions\SignatureVerificationException;
use CallMeLater\Laravel\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

class WebhookHandlerTest extends TestCase
{
    protected CallMeLater $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = app(CallMeLater::class);
    }

    public function test_valid_signature_passes(): void
    {
        $payload = json_encode(['event' => 'action.executed', 'action_id' => 'act_1']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'test_webhook_secret');

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_CALLMELATER_SIGNATURE' => $signature,
        ], $payload);

        $this->client->verifySignature($request);
        $this->assertTrue(true);
    }

    public function test_invalid_signature_throws(): void
    {
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_CALLMELATER_SIGNATURE' => 'sha256=bad_signature',
        ], '{"event":"test"}');

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        $this->client->verifySignature($request);
    }

    public function test_missing_signature_throws(): void
    {
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{"event":"test"}');

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Missing webhook signature header');

        $this->client->verifySignature($request);
    }

    public function test_webhook_handler_dispatches_executed_event(): void
    {
        Event::fake();

        $request = Request::create('/webhook', 'POST', [
            'event' => 'action.executed',
            'action_id' => 'act_1',
            'action_name' => 'Test Action',
            'execution' => ['status' => 'success'],
        ]);

        $result = $this->client->webhooks()->skipVerification()->handle($request);

        $this->assertEquals('action.executed', $result['event']);
        $this->assertEquals('act_1', $result['action_id']);

        Event::assertDispatched(ActionExecuted::class, function ($event) {
            return $event->actionId === 'act_1';
        });
    }

    public function test_webhook_handler_dispatches_failed_event(): void
    {
        Event::fake();

        $request = Request::create('/webhook', 'POST', [
            'event' => 'action.failed',
            'action_id' => 'act_2',
            'action_name' => 'Failed Action',
            'failure' => ['reason' => 'timeout'],
        ]);

        $this->client->webhooks()->skipVerification()->handle($request);

        Event::assertDispatched(ActionFailed::class);
    }

    public function test_webhook_handler_without_events(): void
    {
        Event::fake();

        $request = Request::create('/webhook', 'POST', [
            'event' => 'reminder.responded',
            'action_id' => 'act_3',
        ]);

        $result = $this->client->webhooks()
            ->skipVerification()
            ->withoutEvents()
            ->handle($request);

        $this->assertEquals('reminder.responded', $result['event']);
        Event::assertNotDispatched(ReminderResponded::class);
    }

    public function test_is_valid_signature_returns_boolean(): void
    {
        $payload = json_encode(['event' => 'test']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'test_webhook_secret');

        $validRequest = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_CALLMELATER_SIGNATURE' => $signature,
        ], $payload);

        $invalidRequest = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_CALLMELATER_SIGNATURE' => 'sha256=bad',
        ], $payload);

        $this->assertTrue($this->client->isValidSignature($validRequest));
        $this->assertFalse($this->client->isValidSignature($invalidRequest));
    }
}
