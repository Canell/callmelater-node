<?php

namespace CallMeLater\Laravel\Tests\Unit\Builders;

use CallMeLater\Laravel\CallMeLater;
use CallMeLater\Laravel\Exceptions\CallMeLaterException;
use CallMeLater\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class ReminderBuilderTest extends TestCase
{
    protected CallMeLater $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = app(CallMeLater::class);
    }

    public function test_base_payload_structure(): void
    {
        $payload = $this->client->reminder('Test Reminder')
            ->to('user@example.com')
            ->message('Please approve')
            ->inHours(1)
            ->toArray();

        $this->assertEquals('gated', $payload['mode']);
        $this->assertEquals('Test Reminder', $payload['name']);
        $this->assertEquals(['email:user@example.com'], $payload['gate']['recipients']);
        $this->assertEquals('Please approve', $payload['gate']['message']);
    }

    public function test_throws_without_recipients(): void
    {
        $this->expectException(CallMeLaterException::class);
        $this->expectExceptionMessage('At least one recipient is required');

        $this->client->reminder('Test')->toArray();
    }

    public function test_multiple_recipient_types(): void
    {
        $payload = $this->client->reminder('Test')
            ->to('a@example.com')
            ->toMany(['b@example.com', 'c@example.com'])
            ->toPhone('+15551234567')
            ->toChannel('some-uuid')
            ->toRecipient('user:123:email')
            ->inHours(1)
            ->toArray();

        $expected = [
            'email:a@example.com',
            'email:b@example.com',
            'email:c@example.com',
            'phone:+15551234567',
            'channel:some-uuid',
            'user:123:email',
        ];
        $this->assertEquals($expected, $payload['gate']['recipients']);
    }

    public function test_buttons_and_gate_options(): void
    {
        $payload = $this->client->reminder('Test')
            ->to('a@example.com')
            ->buttons('Approve', 'Reject')
            ->allowSnooze(3)
            ->requireAll()
            ->expiresInDays(14)
            ->inHours(1)
            ->toArray();

        $this->assertEquals('Approve', $payload['gate']['confirm_text']);
        $this->assertEquals('Reject', $payload['gate']['decline_text']);
        $this->assertEquals(3, $payload['gate']['max_snoozes']);
        $this->assertEquals('all_required', $payload['gate']['confirmation_mode']);
        $this->assertEquals(14, $payload['gate']['token_expiry_days']);
    }

    public function test_no_snooze(): void
    {
        $payload = $this->client->reminder('Test')
            ->to('a@example.com')
            ->noSnooze()
            ->inHours(1)
            ->toArray();

        $this->assertEquals(0, $payload['gate']['max_snoozes']);
    }

    public function test_escalation(): void
    {
        $payload = $this->client->reminder('Test')
            ->to('a@example.com')
            ->escalateTo(['backup@example.com', 'email:other@example.com'], afterHours: 48)
            ->inHours(1)
            ->toArray();

        $this->assertEquals([
            'contacts' => ['email:backup@example.com', 'email:other@example.com'],
            'after_hours' => 48,
        ], $payload['gate']['escalation']);
    }

    public function test_attachments(): void
    {
        $payload = $this->client->reminder('Test')
            ->to('a@example.com')
            ->attach('https://example.com/file.pdf', 'Report.pdf')
            ->attach('https://example.com/other.pdf')
            ->inHours(1)
            ->toArray();

        $this->assertCount(2, $payload['gate']['attachments']);
        $this->assertEquals('Report.pdf', $payload['gate']['attachments'][0]['name']);
        $this->assertArrayNotHasKey('name', $payload['gate']['attachments'][1]);
    }

    public function test_intent_formats(): void
    {
        $payload = $this->client->reminder('Test')->to('a@ex.com')->at('next_monday')->toArray();
        $this->assertEquals('next_monday', $payload['intent']['preset']);

        $payload = $this->client->reminder('Test')->to('a@ex.com')->inDays(3)->toArray();
        $this->assertEquals('3 days', $payload['intent']['delay']);

        $payload = $this->client->reminder('Test')->to('a@ex.com')->at('2026-06-15 09:00')->toArray();
        $this->assertEquals('2026-06-15 09:00', $payload['intent']['at']);
    }

    public function test_send_calls_api(): void
    {
        Http::fake([
            'callmelater.test/api/v1/actions' => Http::response([
                'data' => ['id' => 'act_456', 'name' => 'Reminder'],
            ], 200),
        ]);

        $result = $this->client->reminder('Test')
            ->to('a@example.com')
            ->message('Test message')
            ->inHours(1)
            ->send();

        $this->assertEquals('act_456', $result['id']);
    }
}
