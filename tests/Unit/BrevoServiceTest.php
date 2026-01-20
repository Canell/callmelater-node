<?php

namespace Tests\Unit;

use App\Services\BrevoService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrevoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('brevo.enabled', true);
        Config::set('brevo.api_key', 'test_api_key');
        Config::set('brevo.sender', 'TestSender');
        Config::set('brevo.type', 'transactional');
    }

    public function test_is_enabled_returns_true_when_configured(): void
    {
        $service = new BrevoService();

        $this->assertTrue($service->isEnabled());
    }

    public function test_is_enabled_returns_false_when_disabled(): void
    {
        Config::set('brevo.enabled', false);

        $service = new BrevoService();

        $this->assertFalse($service->isEnabled());
    }

    public function test_is_enabled_returns_false_when_no_api_key(): void
    {
        Config::set('brevo.api_key', null);

        $service = new BrevoService();

        $this->assertFalse($service->isEnabled());
    }

    public function test_send_sms_returns_null_when_disabled(): void
    {
        Config::set('brevo.enabled', false);

        $service = new BrevoService();
        $result = $service->sendSms('+15551234567', 'Test message');

        $this->assertNull($result);
    }

    public function test_send_sms_returns_message_id_on_success(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => 'msg_12345'], 200),
        ]);

        $service = new BrevoService();
        $result = $service->sendSms('+15551234567', 'Test message');

        $this->assertEquals('msg_12345', $result);
    }

    public function test_send_sms_normalizes_phone_number(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => 'msg_123'], 200),
        ]);

        $service = new BrevoService();
        $service->sendSms('+1 (555) 123-4567', 'Test message');

        Http::assertSent(function ($request) {
            return $request['recipient'] === '15551234567';
        });
    }

    public function test_send_sms_returns_null_on_api_error(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['error' => 'Invalid API key'], 401),
        ]);

        $service = new BrevoService();
        $result = $service->sendSms('+15551234567', 'Test message');

        $this->assertNull($result);
    }

    public function test_send_sms_returns_null_on_exception(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(null, 500),
        ]);

        $service = new BrevoService();
        $result = $service->sendSms('+15551234567', 'Test message');

        $this->assertNull($result);
    }

    public function test_send_sms_sends_correct_payload(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => 'msg_123'], 200),
        ]);

        $service = new BrevoService();
        $service->sendSms('+15551234567', 'Test message content');

        Http::assertSent(function ($request) {
            return $request['sender'] === 'TestSender'
                && $request['recipient'] === '15551234567'
                && $request['content'] === 'Test message content'
                && $request['type'] === 'transactional';
        });
    }

    public function test_send_sms_includes_api_key_header(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => 'msg_123'], 200),
        ]);

        $service = new BrevoService();
        $service->sendSms('+15551234567', 'Test message');

        Http::assertSent(function ($request) {
            return $request->hasHeader('api-key', 'test_api_key');
        });
    }

    // ==================== REMINDER SMS ====================

    public function test_send_reminder_sms_with_message(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => 'msg_123'], 200),
        ]);

        $service = new BrevoService();
        $service->sendReminderSms(
            '+15551234567',
            'Action Name',
            'Please confirm this deployment',
            'https://example.com/r/abc123'
        );

        Http::assertSent(function ($request) {
            $content = $request['content'];

            return str_contains($content, 'Please confirm this deployment')
                && str_contains($content, 'https://example.com/r/abc123');
        });
    }

    public function test_send_reminder_sms_falls_back_to_action_name(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => 'msg_123'], 200),
        ]);

        $service = new BrevoService();
        $service->sendReminderSms(
            '+15551234567',
            'Deploy to Production',
            null, // No message
            'https://example.com/r/abc123'
        );

        Http::assertSent(function ($request) {
            return str_contains($request['content'], 'Deploy to Production');
        });
    }

    public function test_send_reminder_sms_truncates_long_message(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => 'msg_123'], 200),
        ]);

        $longMessage = str_repeat('A', 200); // Very long message

        $service = new BrevoService();
        $service->sendReminderSms(
            '+15551234567',
            'Action Name',
            $longMessage,
            'https://example.com/r/abc123'
        );

        Http::assertSent(function ($request) {
            // Total SMS should be <= 160 characters
            return strlen($request['content']) <= 160
                && str_contains($request['content'], '...');
        });
    }

    public function test_send_reminder_sms_includes_link(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => 'msg_123'], 200),
        ]);

        $service = new BrevoService();
        $service->sendReminderSms(
            '+15551234567',
            'Test Action',
            'Message',
            'https://example.com/r/abc'
        );

        Http::assertSent(function ($request) {
            // Verify the URL is included in the message
            return str_contains($request['content'], 'https://example.com/r/abc');
        });
    }
}
