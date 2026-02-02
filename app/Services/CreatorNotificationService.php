<?php

namespace App\Services;

use App\Mail\ResponseNotificationMail;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreatorNotificationService
{
    /**
     * Notify the action creator about a response.
     * Sends via email to the creator's email address.
     */
    public function notifyCreator(
        ScheduledAction $action,
        string $response,
        ?ReminderRecipient $respondent = null
    ): void {
        // Check if notifications are enabled for this action
        if (! $action->notify_creator_on_response) {
            return;
        }

        // Get the creator
        /** @var \App\Models\User|null $creator */
        $creator = $action->createdByUser;
        if (! $creator) {
            // Fall back to account owner
            $creator = $action->account?->owner;
        }

        if (! $creator || ! $creator->email) {
            Log::warning('Cannot notify creator - no email address', [
                'action_id' => $action->id,
            ]);

            return;
        }

        // Don't notify if the respondent is the creator themselves
        if ($respondent && $respondent->email === $creator->email) {
            Log::info('Skipping creator notification - respondent is creator', [
                'action_id' => $action->id,
            ]);

            return;
        }

        // Send the notification
        try {
            Mail::to($creator->email)->queue(new ResponseNotificationMail(
                action: $action,
                response: $response,
                respondentName: $this->getRespondentName($respondent),
            ));

            Log::info('Creator notification sent', [
                'action_id' => $action->id,
                'creator_email' => $creator->email,
                'response' => $response,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send creator notification', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get a display name for the respondent.
     */
    private function getRespondentName(?ReminderRecipient $respondent): string
    {
        if (! $respondent) {
            return 'Someone';
        }

        // Use contact name if available
        if ($respondent->contact) {
            return $respondent->contact->full_name;
        }

        // Use email
        return $respondent->email;
    }
}
