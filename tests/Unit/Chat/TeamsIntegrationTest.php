<?php

namespace Tests\Unit\Chat;

use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\Chat\TeamsIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private TeamsIntegration $integration;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = new TeamsIntegration();
        $this->user = User::factory()->create();
    }

    public function test_get_channel_returns_teams(): void
    {
        $this->assertEquals('teams', $this->integration->getChannel());
    }

    public function test_send_decision_card_posts_to_webhook(): void
    {
        Http::fake([
            'outlook.office.com/*' => Http::response('', 200),
        ]);

        $action = $this->createGatedAction([
            'name' => 'Deploy to Production',
            'gate' => [
                'message' => 'Ready to deploy?',
                'timeout' => '4h',
            ],
        ]);

        $recipient = $this->createRecipient($action, [
            'chat_provider' => 'teams',
            'chat_destination' => 'https://outlook.office.com/webhook/test-webhook',
        ]);

        $result = $this->integration->sendDecisionCard($action, $recipient, 'test-token-123');

        $this->assertArrayHasKey('message_id', $result);
        $this->assertArrayHasKey('channel_id', $result);
        $this->assertEquals('https://outlook.office.com/webhook/test-webhook', $result['channel_id']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'outlook.office.com/webhook') &&
                $request->hasHeader('Content-Type', 'application/json') &&
                $request['type'] === 'message';
        });
    }

    public function test_send_decision_card_throws_on_empty_webhook(): void
    {
        $action = $this->createGatedAction();
        $recipient = $this->createRecipient($action, [
            'chat_destination' => '',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No Teams webhook URL configured');

        $this->integration->sendDecisionCard($action, $recipient, 'token');
    }

    public function test_send_decision_card_throws_on_failed_request(): void
    {
        Http::fake([
            'outlook.office.com/*' => Http::response('Unauthorized', 401),
        ]);

        $action = $this->createGatedAction();
        $recipient = $this->createRecipient($action, [
            'chat_destination' => 'https://outlook.office.com/webhook/test',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to send Teams message');

        $this->integration->sendDecisionCard($action, $recipient, 'token');
    }

    public function test_verify_webhook_signature_validates_required_fields(): void
    {
        // Valid payload
        $validRequest = Request::create('/webhook', 'POST', [
            'action' => 'confirm',
            'token' => 'test-token',
        ]);
        $this->assertTrue($this->integration->verifyWebhookSignature($validRequest));

        // Missing action
        $noActionRequest = Request::create('/webhook', 'POST', [
            'token' => 'test-token',
        ]);
        $this->assertFalse($this->integration->verifyWebhookSignature($noActionRequest));

        // Missing token
        $noTokenRequest = Request::create('/webhook', 'POST', [
            'action' => 'confirm',
        ]);
        $this->assertFalse($this->integration->verifyWebhookSignature($noTokenRequest));

        // Invalid action
        $invalidActionRequest = Request::create('/webhook', 'POST', [
            'action' => 'invalid',
            'token' => 'test-token',
        ]);
        $this->assertFalse($this->integration->verifyWebhookSignature($invalidActionRequest));
    }

    public function test_verify_webhook_accepts_valid_actions(): void
    {
        foreach (['confirm', 'decline', 'snooze'] as $action) {
            $request = Request::create('/webhook', 'POST', [
                'action' => $action,
                'token' => 'test-token',
            ]);
            $this->assertTrue($this->integration->verifyWebhookSignature($request));
        }
    }

    public function test_parse_webhook_payload_extracts_data(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'action' => 'confirm',
            'token' => 'my-response-token',
            'user_id' => 'user123',
        ]);

        $result = $this->integration->parseWebhookPayload($request);

        $this->assertEquals('confirm', $result['response']);
        $this->assertEquals('my-response-token', $result['token']);
        $this->assertEquals('user123', $result['user_id']);
    }

    public function test_parse_webhook_payload_defaults_user_id(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'action' => 'decline',
            'token' => 'token',
        ]);

        $result = $this->integration->parseWebhookPayload($request);

        $this->assertEquals('teams_user', $result['user_id']);
    }

    public function test_update_card_with_response_logs_without_error(): void
    {
        // This method is a no-op for webhooks, just verify it doesn't throw
        $this->integration->updateCardWithResponse(
            'message-id',
            'channel-id',
            'confirm',
            'Test User'
        );

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    private function createGatedAction(array $overrides = []): ScheduledAction
    {
        return ScheduledAction::create(array_merge([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Action',
            'mode' => ScheduledAction::MODE_GATED,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_value' => now()->addHour()->toIso8601String(),
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_AWAITING_RESPONSE,
            'gate' => [
                'message' => 'Please confirm',
                'recipients' => ['test@example.com'],
                'timeout' => '4h',
            ],
        ], $overrides));
    }

    private function createRecipient(ScheduledAction $action, array $overrides = []): ReminderRecipient
    {
        return ReminderRecipient::create(array_merge([
            'action_id' => $action->id,
            'email' => 'recipient@example.com',
            'status' => ReminderRecipient::STATUS_SENT,
            'response_token' => 'test_token_'.uniqid(),
        ], $overrides));
    }
}
