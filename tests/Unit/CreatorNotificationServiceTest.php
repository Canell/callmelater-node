<?php

namespace Tests\Unit;

use App\Mail\ResponseNotificationMail;
use App\Models\Account;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\CreatorNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CreatorNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreatorNotificationService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CreatorNotificationService();
        $this->user = User::factory()->create(['email' => 'creator@example.com']);
        Mail::fake();
    }

    public function test_notifies_creator_on_response(): void
    {
        $action = $this->createGatedAction([
            'notify_creator_on_response' => true,
        ]);

        $respondent = $this->createRecipient($action, [
            'email' => 'responder@example.com',
        ]);

        $this->service->notifyCreator($action, 'confirm', $respondent);

        Mail::assertQueued(ResponseNotificationMail::class, function ($mail) {
            return $mail->hasTo('creator@example.com');
        });
    }

    public function test_does_not_notify_when_disabled(): void
    {
        $action = $this->createGatedAction([
            'notify_creator_on_response' => false,
        ]);

        $respondent = $this->createRecipient($action);

        $this->service->notifyCreator($action, 'confirm', $respondent);

        Mail::assertNothingQueued();
    }

    public function test_does_not_notify_when_respondent_is_creator(): void
    {
        $action = $this->createGatedAction([
            'notify_creator_on_response' => true,
        ]);

        $respondent = $this->createRecipient($action, [
            'email' => 'creator@example.com', // Same as creator
        ]);

        $this->service->notifyCreator($action, 'confirm', $respondent);

        Mail::assertNothingQueued();
    }

    public function test_falls_back_to_account_owner_when_no_creator(): void
    {
        // Create a user which automatically creates an account
        $owner = User::factory()->create([
            'email' => 'owner@example.com',
        ]);
        $account = $owner->account;

        $action = ScheduledAction::create([
            'account_id' => $account->id,
            'created_by_user_id' => null, // No creator
            'name' => 'Test Action',
            'mode' => ScheduledAction::MODE_GATED,
            'intent_type' => ScheduledAction::INTENT_ABSOLUTE,
            'intent_value' => now()->addHour()->toIso8601String(),
            'intent_payload' => [],
            'resolution_status' => ScheduledAction::STATUS_AWAITING_RESPONSE,
            'notify_creator_on_response' => true,
            'gate' => [
                'message' => 'Please confirm',
                'recipients' => ['test@example.com'],
                'timeout' => '4h',
            ],
        ]);

        $respondent = $this->createRecipient($action, [
            'email' => 'other@example.com',
        ]);

        $this->service->notifyCreator($action, 'decline', $respondent);

        Mail::assertQueued(ResponseNotificationMail::class, function ($mail) use ($owner) {
            return $mail->hasTo($owner->email);
        });
    }

    public function test_uses_team_member_name_when_available(): void
    {
        $action = $this->createGatedAction([
            'notify_creator_on_response' => true,
        ]);

        $teamMember = TeamMember::factory()->create([
            'account_id' => $this->user->account_id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $respondent = $this->createRecipient($action, [
            'email' => 'john@example.com',
            'team_member_id' => $teamMember->id,
        ]);

        $this->service->notifyCreator($action, 'confirm', $respondent);

        Mail::assertQueued(ResponseNotificationMail::class);
    }

    public function test_handles_null_respondent(): void
    {
        $action = $this->createGatedAction([
            'notify_creator_on_response' => true,
        ]);

        $this->service->notifyCreator($action, 'snooze', null);

        Mail::assertQueued(ResponseNotificationMail::class);
    }

    public function test_handles_different_response_types(): void
    {
        foreach (['confirm', 'decline', 'snooze'] as $response) {
            Mail::fake(); // Reset for each iteration

            $action = $this->createGatedAction([
                'notify_creator_on_response' => true,
            ]);

            $respondent = $this->createRecipient($action, [
                'email' => "responder-{$response}@example.com",
            ]);

            $this->service->notifyCreator($action, $response, $respondent);

            Mail::assertQueued(ResponseNotificationMail::class);
        }
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
