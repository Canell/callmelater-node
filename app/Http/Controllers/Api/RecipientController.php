<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConnection;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipientController extends Controller
{
    /**
     * List all available recipients for the unified selector.
     *
     * Returns workspace members, contacts, and chat channels in URI format:
     * - user:{id}:email or user:{id}:phone (workspace members)
     * - contact:{uuid}:email or contact:{uuid}:phone (external contacts)
     * - channel:{uuid} (chat channels)
     */
    public function index(Request $request): JsonResponse
    {
        $accountId = $request->user()->account_id;
        $recipients = [];

        // Get all workspace members (users in the same account)
        $workspaceMembers = User::where('account_id', $accountId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('name')
            ->get();

        foreach ($workspaceMembers as $user) {
            $displayName = $user->full_name ?: $user->name;

            // Add email entry
            if ($user->email) {
                $recipients[] = [
                    'uri' => "user:{$user->id}:email",
                    'label' => $displayName,
                    'sublabel' => $user->email,
                    'type' => 'user',
                    'contact_type' => 'email',
                ];
            }

            // Add phone entry if available
            if ($user->phone) {
                $recipients[] = [
                    'uri' => "user:{$user->id}:phone",
                    'label' => $displayName,
                    'sublabel' => $user->phone,
                    'type' => 'user',
                    'contact_type' => 'phone',
                ];
            }
        }

        // Get all contacts (external recipients)
        $contacts = Contact::where('account_id', $accountId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        foreach ($contacts as $contact) {
            // Add email entry if available
            if ($contact->email) {
                $recipients[] = [
                    'uri' => "contact:{$contact->id}:email",
                    'label' => $contact->full_name,
                    'sublabel' => $contact->email,
                    'type' => 'contact',
                    'contact_type' => 'email',
                ];
            }

            // Add phone entry if available
            if ($contact->phone) {
                $recipients[] = [
                    'uri' => "contact:{$contact->id}:phone",
                    'label' => $contact->full_name,
                    'sublabel' => $contact->phone,
                    'type' => 'contact',
                    'contact_type' => 'phone',
                ];
            }
        }

        // Get active chat connections
        $chatConnections = ChatConnection::where('account_id', $accountId)
            ->active()
            ->orderBy('name')
            ->get();

        foreach ($chatConnections as $connection) {
            if ($connection->isTeams()) {
                $sublabel = 'Teams';
            } else {
                $sublabel = $connection->slack_channel_name
                    ? "Slack · #{$connection->slack_channel_name}"
                    : 'Slack';
            }

            $recipients[] = [
                'uri' => "channel:{$connection->id}",
                'label' => $connection->name,
                'sublabel' => $sublabel,
                'type' => 'channel',
                'provider' => $connection->provider,
            ];
        }

        return response()->json([
            'data' => $recipients,
        ]);
    }
}
