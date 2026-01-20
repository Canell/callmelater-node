<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Services\ResponseProcessor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResponseController extends Controller
{
    public function __construct(
        private ResponseProcessor $responseProcessor
    ) {}

    /**
     * Show the response confirmation page.
     */
    public function show(Request $request): View
    {
        $token = $request->query('token');
        $response = $request->query('response');
        $preset = $request->query('preset', '1h');

        // If token exists but no response, show the choice page (for SMS links)
        if ($token && ! $response) {
            return $this->showChoicePage($token);
        }

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
                        $this->responseProcessor->process($recipient, $action, $response, $preset);
                        $success = $this->responseProcessor->getSuccessMessage($response);
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

    /**
     * Handle short URL format: /r/{token}
     * Used in SMS to save characters.
     */
    public function showShort(string $token): View
    {
        return $this->showChoicePage($token);
    }

    /**
     * Show the response choice page (for SMS links without pre-selected response).
     */
    private function showChoicePage(string $token): View
    {
        $recipient = ReminderRecipient::query()
            ->where('response_token', $token)
            ->first();

        if (! $recipient) {
            return view('response', [
                'error' => 'Invalid or expired response token.',
                'success' => null,
                'action' => null,
                'response' => null,
            ]);
        }

        if ($recipient->hasResponded()) {
            return view('response', [
                'error' => 'You have already responded to this reminder.',
                'success' => null,
                'action' => $recipient->action,
                'response' => null,
            ]);
        }

        $action = $recipient->action;

        if ($action->token_expires_at && $action->token_expires_at->isPast()) {
            return view('response', [
                'error' => 'This reminder has expired.',
                'success' => null,
                'action' => null,
                'response' => null,
            ]);
        }

        if ($action->resolution_status !== ScheduledAction::STATUS_AWAITING_RESPONSE) {
            return view('response', [
                'error' => 'This reminder is no longer active.',
                'success' => null,
                'action' => null,
                'response' => null,
            ]);
        }

        // Check if snooze is available
        $canSnooze = $action->snooze_count < $action->max_snoozes;

        return view('response-choice', [
            'token' => $token,
            'action' => $action,
            'canSnooze' => $canSnooze,
        ]);
    }
}
