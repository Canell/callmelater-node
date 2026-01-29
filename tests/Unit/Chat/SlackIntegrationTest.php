<?php

namespace Tests\Unit\Chat;

use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\Chat\SlackIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private SlackIntegration $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = new SlackIntegration();
    }

    public function test_get_channel_returns_slack(): void
    {
        $this->assertEquals('slack', $this->integration->getChannel());
    }

    public function test_send_decision_card_success(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'ts' => '1234567890.123456',
                'channel' => 'C12345678',
            ]),
        ]);

        $user = User::factory()->create();
        $action = $this->createGatedAction([
            'account_id' => $user->account_id,
            'gate' => [
                'message' => 'Deploy to production?',
                'timeout' => '7d',
            ],
        ]);

        $recipient = ReminderRecipient::create([
            'action_id' => $action->id,
            'email' => 'slack:test',
            'chat_provider' => 'slack',
            'chat_destination' => 'xoxb-test-bot-token',
            'slack_channel_id' => 'C12345678',
            'status' => 'pending',
            'response_token' => 'test_token',
        ]);

        $result = $this->integration->sendDecisionCard($action, $recipient, 'test_token');

        $this->assertEquals('1234567890.123456', $result['message_id']);
        $this->assertEquals('C12345678', $result['channel_id']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://slack.com/api/chat.postMessage'
                && $data['channel'] === 'C12345678'
                && str_contains($data['text'], 'Action Required');
        });
    }

    public function test_send_decision_card_throws_on_missing_token(): void
    {
        $user = User::factory()->create();
        $action = $this->createGatedAction([
            'account_id' => $user->account_id,
            'gate' => ['message' => 'Test', 'timeout' => '7d'],
        ]);

        $recipient = ReminderRecipient::create([
            'action_id' => $action->id,
            'email' => 'slack:test',
            'chat_provider' => 'slack',
            'chat_destination' => null,
            'slack_channel_id' => null,
            'status' => 'pending',
            'response_token' => 'test_token',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->integration->sendDecisionCard($action, $recipient, 'test_token');
    }

    public function test_send_decision_card_throws_on_slack_error(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => false,
                'error' => 'channel_not_found',
            ]),
        ]);

        $user = User::factory()->create();
        $action = $this->createGatedAction([
            'account_id' => $user->account_id,
            'gate' => ['message' => 'Test', 'timeout' => '7d'],
        ]);

        $recipient = ReminderRecipient::create([
            'action_id' => $action->id,
            'email' => 'slack:test',
            'chat_provider' => 'slack',
            'chat_destination' => 'xoxb-test',
            'slack_channel_id' => 'C12345678',
            'status' => 'pending',
            'response_token' => 'test_token',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('channel_not_found');
        $this->integration->sendDecisionCard($action, $recipient, 'test_token');
    }

    public function test_verify_webhook_signature_success(): void
    {
        $secret = 'test_signing_secret';
        Config::set('services.slack.signing_secret', $secret);

        $body = 'payload=%7B%22actions%22%3A%5B%5D%7D';
        $timestamp = time();
        $sigBasestring = "v0:{$timestamp}:{$body}";
        $signature = 'v0='.hash_hmac('sha256', $sigBasestring, $secret);

        $request = Request::create('/webhook/slack', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Slack-Request-Timestamp', (string) $timestamp);
        $request->headers->set('X-Slack-Signature', $signature);

        $this->assertTrue($this->integration->verifyWebhookSignature($request));
    }

    public function test_verify_webhook_signature_fails_on_wrong_signature(): void
    {
        Config::set('services.slack.signing_secret', 'correct_secret');

        $timestamp = time();
        $request = Request::create('/webhook/slack', 'POST', [], [], [], [], 'test_body');
        $request->headers->set('X-Slack-Request-Timestamp', (string) $timestamp);
        $request->headers->set('X-Slack-Signature', 'v0=wrong_signature');

        $this->assertFalse($this->integration->verifyWebhookSignature($request));
    }

    public function test_verify_webhook_signature_fails_on_old_timestamp(): void
    {
        Config::set('services.slack.signing_secret', 'test_secret');

        $oldTimestamp = time() - 600; // 10 minutes ago
        $request = Request::create('/webhook/slack', 'POST');
        $request->headers->set('X-Slack-Request-Timestamp', (string) $oldTimestamp);
        $request->headers->set('X-Slack-Signature', 'v0=some_signature');

        $this->assertFalse($this->integration->verifyWebhookSignature($request));
    }

    public function test_parse_webhook_payload_extracts_response(): void
    {
        $payload = [
            'type' => 'block_actions',
            'user' => [
                'id' => 'U123',
                'name' => 'john.doe',
            ],
            'actions' => [
                [
                    'action_id' => 'confirm',
                    'value' => json_encode([
                        'action' => 'confirm',
                        'token' => 'response_token_123',
                    ]),
                ],
            ],
            'message' => ['ts' => '1234567890.123456'],
            'channel' => ['id' => 'C12345678'],
            'response_url' => 'https://hooks.slack.com/actions/xxx',
        ];

        $request = Request::create('/webhook/slack', 'POST', [
            'payload' => json_encode($payload),
        ]);

        $result = $this->integration->parseWebhookPayload($request);

        $this->assertEquals('confirm', $result['response']);
        $this->assertEquals('response_token_123', $result['token']);
        $this->assertEquals('john.doe', $result['user_id']);
        $this->assertEquals('1234567890.123456', $result['message_ts']);
        $this->assertEquals('C12345678', $result['channel_id']);
        $this->assertEquals('https://hooks.slack.com/actions/xxx', $result['response_url']);
    }

    public function test_parse_webhook_payload_throws_on_missing_actions(): void
    {
        $payload = [
            'type' => 'block_actions',
            'actions' => [],
        ];

        $request = Request::create('/webhook/slack', 'POST', [
            'payload' => json_encode($payload),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->integration->parseWebhookPayload($request);
    }

    public function test_update_message_via_response_url(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('', 200),
        ]);

        $user = User::factory()->create();
        $action = $this->createGatedAction([
            'account_id' => $user->account_id,
            'gate' => ['message' => 'Test message', 'timeout' => '7d'],
        ]);

        $this->integration->updateMessageViaResponseUrl(
            'https://hooks.slack.com/actions/xxx',
            $action,
            'confirm',
            'John Doe'
        );

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), 'hooks.slack.com')
                && $data['replace_original'] === true
                && ! empty($data['blocks']);
        });
    }

    /**
     * Helper to create a gated action.
     */
    private function createGatedAction(array $overrides = []): ScheduledAction
    {
        return ScheduledAction::create(array_merge([
            'account_id' => User::factory()->create()->account_id,
            'user_id' => 1,
            'name' => 'Test Gated Action',
            'mode' => ScheduledAction::MODE_GATED,
            'resolution_status' => ScheduledAction::STATUS_EXECUTING,
            'intent_type' => 'preset',
            'intent_payload' => ['preset' => 'tomorrow'],
            'timezone' => 'UTC',
            'gate' => [
                'message' => 'Test gate message',
                'recipients' => ['test@example.com'],
                'timeout' => '7d',
            ],
            'execute_at_utc' => now(),
        ], $overrides));
    }
}
