<?php

namespace Tests\Feature;

use App\Jobs\DeliverHttpAction;
use App\Jobs\DeliverReminder;
use App\Jobs\DispatcherJob;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\ResponseProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Test the new "gated action with HTTP request" feature
 * where human approval triggers HTTP execution.
 */
class GatedWithRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        Queue::fake();
        Mail::fake();
    }

    public function test_can_create_gated_action_with_request(): void
    {
        $response = $this->postJson('/api/v1/actions', [
            'name' => 'Deploy to Production',
            'mode' => 'gated',
            'execute_at' => now()->addHour()->toIso8601String(),
            'gate' => [
                'message' => 'Ready to deploy v2.1?',
                'recipients' => ['ops@example.com'],
                'channels' => ['email'],
                'timeout' => '4h',
                'on_timeout' => 'cancel',
            ],
            'request' => [
                'url' => 'https://api.example.com/deploy',
                'method' => 'POST',
                'body' => ['version' => '2.1'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.mode', 'gated');

        $action = ScheduledAction::find($response->json('data.id'));
        $this->assertNotNull($action->gate);
        $this->assertNotNull($action->request);
        $this->assertTrue($action->isGated());
        $this->assertTrue($action->hasRequest());
    }

    public function test_gated_action_dispatches_reminder_first(): void
    {
        $action = $this->createGatedActionWithRequest();

        (new DispatcherJob)->handle();

        Queue::assertPushed(DeliverReminder::class);
        Queue::assertNotPushed(DeliverHttpAction::class);
    }

    public function test_http_executes_after_gate_approval(): void
    {
        $action = $this->createGatedActionWithRequest(ScheduledAction::STATUS_AWAITING_RESPONSE);
        $recipient = $this->createRecipient($action);

        $processor = app(ResponseProcessor::class);
        $processor->handleConfirm($recipient, $action);

        $action->refresh();
        $this->assertNotNull($action->gate_passed_at);

        Queue::assertPushed(DeliverHttpAction::class);
    }

    public function test_gate_passed_action_dispatches_http(): void
    {
        $action = $this->createGatedActionWithRequest(ScheduledAction::STATUS_RESOLVED);
        $action->update(['gate_passed_at' => now()]);

        (new DispatcherJob)->handle();

        Queue::assertPushed(DeliverHttpAction::class);
        Queue::assertNotPushed(DeliverReminder::class);
    }

    public function test_gated_only_action_does_not_dispatch_http(): void
    {
        // Gated action WITHOUT request - callback only
        $action = ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Callback Only',
            'mode' => ScheduledAction::MODE_GATED,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subMinute()->toIso8601String()],
            'resolution_status' => ScheduledAction::STATUS_AWAITING_RESPONSE,
            'execute_at_utc' => now()->subMinute(),
            'gate' => [
                'message' => 'Confirm?',
                'recipients' => ['test@example.com'],
                'channels' => ['email'],
            ],
            'token_expires_at' => now()->addDays(7),
            // No 'request' field
        ]);
        $recipient = $this->createRecipient($action);

        $processor = app(ResponseProcessor::class);
        $processor->handleConfirm($recipient, $action);

        $action->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTED, $action->resolution_status);
        $this->assertNull($action->gate_passed_at); // No gate_passed_at for callback-only
        Queue::assertNotPushed(DeliverHttpAction::class);
    }

    private function createGatedActionWithRequest(string $status = ScheduledAction::STATUS_RESOLVED): ScheduledAction
    {
        return ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Deploy to Production',
            'mode' => ScheduledAction::MODE_GATED,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_payload' => ['execute_at' => now()->subMinute()->toIso8601String()],
            'resolution_status' => $status,
            'execute_at_utc' => now()->subMinute(),
            'gate' => [
                'message' => 'Ready to deploy?',
                'recipients' => ['ops@example.com'],
                'channels' => ['email'],
                'timeout' => '4h',
                'on_timeout' => 'cancel',
                'max_snoozes' => 3,
            ],
            'request' => [
                'url' => 'https://api.example.com/deploy',
                'method' => 'POST',
            ],
            'token_expires_at' => now()->addDays(7),
        ]);
    }

    private function createRecipient(ScheduledAction $action): ReminderRecipient
    {
        return ReminderRecipient::create([
            'action_id' => $action->id,
            'email' => 'ops@example.com',
            'status' => ReminderRecipient::STATUS_SENT,
            'response_token' => 'test_token_'.uniqid(),
        ]);
    }
}
