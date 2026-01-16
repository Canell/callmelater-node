<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeamInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    /**
     * Get invitation details by token (public endpoint).
     */
    public function show(string $token): JsonResponse
    {
        $invitation = TeamInvitation::with(['team', 'inviter'])
            ->where('token', $token)
            ->first();

        if (! $invitation) {
            return response()->json(['error' => 'Invitation not found.'], 404);
        }

        if ($invitation->isAccepted()) {
            return response()->json(['error' => 'This invitation has already been accepted.'], 410);
        }

        if ($invitation->isExpired()) {
            return response()->json(['error' => 'This invitation has expired.'], 410);
        }

        return response()->json([
            'team_name' => $invitation->team->name,
            'inviter_name' => $invitation->inviter->name,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Accept an invitation (requires authentication).
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $user = $request->user();

        $invitation = TeamInvitation::with('team')
            ->where('token', $token)
            ->first();

        if (! $invitation) {
            return response()->json(['error' => 'Invitation not found.'], 404);
        }

        if ($invitation->isAccepted()) {
            return response()->json(['error' => 'This invitation has already been accepted.'], 410);
        }

        if ($invitation->isExpired()) {
            return response()->json(['error' => 'This invitation has expired.'], 410);
        }

        // Check if user email matches invitation email
        if (strtolower($user->email) !== strtolower($invitation->email)) {
            return response()->json([
                'error' => 'This invitation was sent to a different email address.',
            ], 403);
        }

        // Check if user is already a member
        if ($invitation->team->hasMember($user)) {
            return response()->json([
                'error' => 'You are already a member of this team.',
            ], 422);
        }

        // Accept the invitation
        $invitation->accept($user);

        return response()->json([
            'message' => "You've joined {$invitation->team->name}!",
            'team_id' => $invitation->team->id,
        ]);
    }
}
