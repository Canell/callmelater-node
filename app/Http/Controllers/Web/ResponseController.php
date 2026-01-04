<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Services\ActionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResponseController extends Controller
{
    public function __construct(
        private ActionService $actionService
    ) {}

    /**
     * Show the response confirmation page.
     */
    public function show(Request $request): View
    {
        $token = $request->query('token');
        $response = $request->query('response');
        $preset = $request->query('preset', '1h');

        $error = null;
        $success = null;
        $action = null;

        if (! $token || ! $response) {
            $error = 'Invalid response link.';
        } else {
            $recipient = ReminderRecipient::query()
                ->where('response_token', $token)
                ->first();

            if (! $recipient) {
                $error = 'Invalid or expired response token.';
            } elseif ($recipient->hasResponded()) {
                $error = 'You have already responded to this reminder.';
                $action = $recipient->action;
            } else {
                /** @var ScheduledAction $action */
                $action = $recipient->action;

                if ($action->token_expires_at && $action->token_expires_at->isPast()) {
                    $error = 'This reminder has expired.';
                } elseif ($action->resolution_status !== ScheduledAction::STATUS_AWAITING_RESPONSE) {
                    $error = 'This reminder is no longer active.';
                } else {
                    // Process the response
                    try {
                        $this->processResponse($recipient, $action, $response, $preset);
                        $success = $this->getSuccessMessage($response);
                    } catch (\Exception $e) {
                        $error = $e->getMessage();
                    }
                }
            }
        }

        return view('response', [
            'error' => $error,
            'success' => $success,
            'action' => $action,
            'response' => $response,
        ]);
    }

    private function processResponse(
        ReminderRecipient $recipient,
        ScheduledAction $action,
        string $response,
        string $preset
    ): void {
        switch ($response) {
            case 'confirm':
                $recipient->status = ReminderRecipient::STATUS_CONFIRMED;
                $recipient->responded_at = now();
                $recipient->save();

                ReminderEvent::create([
                    'reminder_id' => $action->id,
                    'event_type' => ReminderEvent::TYPE_CONFIRMED,
                    'actor_email' => $recipient->email,
                    'captured_timezone' => $action->timezone,
                ]);

                if ($action->confirmation_mode === ScheduledAction::CONFIRMATION_FIRST_RESPONSE) {
                    $this->actionService->markExecuted($action);
                } else {
                    $this->checkAllConfirmed($action);
                }
                break;

            case 'decline':
                $recipient->status = ReminderRecipient::STATUS_DECLINED;
                $recipient->responded_at = now();
                $recipient->save();

                ReminderEvent::create([
                    'reminder_id' => $action->id,
                    'event_type' => ReminderEvent::TYPE_DECLINED,
                    'actor_email' => $recipient->email,
                    'captured_timezone' => $action->timezone,
                ]);

                if ($action->confirmation_mode === ScheduledAction::CONFIRMATION_FIRST_RESPONSE) {
                    $action->resolution_status = ScheduledAction::STATUS_EXECUTED;
                    $action->executed_at_utc = now();
                    $action->save();
                }
                break;

            case 'snooze':
                if (! $action->canSnooze()) {
                    throw new \InvalidArgumentException('Maximum snoozes reached for this reminder.');
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

                // Reset all recipients and snooze
                ReminderRecipient::query()
                    ->where('action_id', $action->id)
                    ->update(['status' => ReminderRecipient::STATUS_PENDING, 'responded_at' => null]);

                $this->actionService->snooze($action, $preset);
                break;

            default:
                throw new \InvalidArgumentException('Invalid response type.');
        }
    }

    private function checkAllConfirmed(ScheduledAction $action): void
    {
        $total = $action->recipients()->count();
        $confirmed = $action->recipients()
            ->where('status', ReminderRecipient::STATUS_CONFIRMED)
            ->count();

        if ($confirmed >= $total) {
            $this->actionService->markExecuted($action);
        }
    }

    private function getSuccessMessage(string $response): string
    {
        return match ($response) {
            'confirm' => 'Thank you! Your confirmation has been recorded.',
            'decline' => 'Your response has been recorded.',
            'snooze' => 'Reminder snoozed. You will receive another reminder soon.',
            default => 'Your response has been recorded.',
        };
    }
}
