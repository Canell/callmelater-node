<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Services\ActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResponseController extends Controller
{
    public function __construct(
        private ActionService $actionService
    ) {}

    /**
     * Handle a reminder response via signed token.
     * This endpoint is public (no authentication required).
     */
    public function respond(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'response' => ['required', 'string', 'in:confirm,decline,snooze'],
            'snooze_preset' => ['required_if:response,snooze', 'string'],
        ]);

        // Find recipient by token
        $recipient = ReminderRecipient::query()
            ->where('response_token', $validated['token'])
            ->first();

        if (! $recipient) {
            return response()->json(['message' => 'Invalid response token'], 404);
        }

        // Check if already responded
        if ($recipient->hasResponded()) {
            return response()->json([
                'message' => 'You have already responded to this reminder',
                'status' => $recipient->status,
            ], 422);
        }

        // Get the action
        /** @var ScheduledAction $action */
        $action = $recipient->action;

        // Check token expiry
        if ($action->token_expires_at !== null && $action->token_expires_at->isPast()) {
            return response()->json(['message' => 'This reminder has expired'], 410);
        }

        // Check if action is still awaiting response
        if ($action->resolution_status !== ScheduledAction::STATUS_AWAITING_RESPONSE) {
            return response()->json([
                'message' => 'This reminder is no longer active',
                'status' => $action->resolution_status,
            ], 422);
        }

        // Process the response
        $response = $validated['response'];

        switch ($response) {
            case 'confirm':
                $this->handleConfirm($recipient, $action);
                break;

            case 'decline':
                $this->handleDecline($recipient, $action);
                break;

            case 'snooze':
                $this->handleSnooze($recipient, $action, $validated['snooze_preset']);
                break;
        }

        return response()->json([
            'message' => 'Response recorded successfully',
            'response' => $response,
        ]);
    }

    private function handleConfirm(ReminderRecipient $recipient, ScheduledAction $action): void
    {
        $recipient->status = ReminderRecipient::STATUS_CONFIRMED;
        $recipient->responded_at = now();
        $recipient->save();

        ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_CONFIRMED,
            'actor_email' => $recipient->email,
            'captured_timezone' => $action->timezone,
        ]);

        // Check if action should be marked executed based on confirmation mode
        if ($action->confirmation_mode === ScheduledAction::CONFIRMATION_FIRST_RESPONSE) {
            $this->actionService->markExecuted($action);
        } elseif ($action->confirmation_mode === ScheduledAction::CONFIRMATION_ALL_REQUIRED) {
            $this->checkAllConfirmed($action);
        }
    }

    private function handleDecline(ReminderRecipient $recipient, ScheduledAction $action): void
    {
        $recipient->status = ReminderRecipient::STATUS_DECLINED;
        $recipient->responded_at = now();
        $recipient->save();

        ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_DECLINED,
            'actor_email' => $recipient->email,
            'captured_timezone' => $action->timezone,
        ]);

        // If first_response mode and someone declines, mark as executed (declined)
        if ($action->confirmation_mode === ScheduledAction::CONFIRMATION_FIRST_RESPONSE) {
            $action->resolution_status = ScheduledAction::STATUS_EXECUTED;
            $action->executed_at_utc = now();
            $action->save();
        }
    }

    private function handleSnooze(ReminderRecipient $recipient, ScheduledAction $action, string $preset): void
    {
        if (! $action->canSnooze()) {
            throw new \InvalidArgumentException('Maximum snoozes reached');
        }

        $recipient->status = ReminderRecipient::STATUS_SNOOZED;
        $recipient->responded_at = now();
        $recipient->save();

        ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_SNOOZED,
            'actor_email' => $recipient->email,
            'captured_timezone' => $action->timezone,
            'notes' => "Snoozed with preset: {$preset}",
        ]);

        // Reset all recipient statuses for the snoozed reminder
        ReminderRecipient::query()
            ->where('action_id', $action->id)
            ->update(['status' => ReminderRecipient::STATUS_PENDING, 'responded_at' => null]);

        // Snooze the action (this will re-resolve the intent)
        $this->actionService->snooze($action, $preset);
    }

    private function checkAllConfirmed(ScheduledAction $action): void
    {
        $totalRecipients = $action->recipients()->count();
        $confirmedRecipients = $action->recipients()
            ->where('status', ReminderRecipient::STATUS_CONFIRMED)
            ->count();

        if ($confirmedRecipients >= $totalRecipients) {
            $this->actionService->markExecuted($action);
        }
    }
}
