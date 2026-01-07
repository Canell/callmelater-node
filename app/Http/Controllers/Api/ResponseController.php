<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Services\ResponseProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResponseController extends Controller
{
    public function __construct(
        private ResponseProcessor $responseProcessor
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
        try {
            $this->responseProcessor->process(
                $recipient,
                $action,
                $validated['response'],
                $validated['snooze_preset'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Response recorded successfully',
            'response' => $validated['response'],
        ]);
    }
}
