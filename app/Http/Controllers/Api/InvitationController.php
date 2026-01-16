<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    /**
     * Get invitation details by token (public endpoint).
     */
    public function show(string $token): JsonResponse
    {
        $invitation = TeamInvitation::with(['team.account', 'inviter'])
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

        // Get the target account's plan info
        $targetAccount = $invitation->team->account;
        $targetPlan = $targetAccount->getPlan();

        return response()->json([
            'workspace_name' => $targetAccount->name,
            'inviter_name' => $invitation->inviter->name,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'plan' => $targetPlan,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Accept an invitation (requires authentication).
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'confirm_leave_account' => 'boolean',
        ]);

        $user = $request->user();
        $user->load('account');

        $invitation = TeamInvitation::with(['team.account'])
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

        $targetAccount = $invitation->team->account;

        // Check if user is already in the target account
        if ($user->account_id === $targetAccount->id) {
            // Just add to team if not already a member
            if ($invitation->team->hasMember($user)) {
                return response()->json([
                    'error' => 'You are already a member of this workspace.',
                ], 422);
            }

            $invitation->team->members()->attach($user->id, ['role' => $invitation->role]);
            $invitation->update(['accepted_at' => now()]);

            return response()->json([
                'message' => "You've joined {$targetAccount->name}!",
            ]);
        }

        // User is in a different account - check for conflicts
        /** @var Account|null $currentAccount */
        $currentAccount = $user->account;
        $isAccountOwner = $currentAccount && $currentAccount->owner_id === $user->id;
        $hasPaidSubscription = $currentAccount && $currentAccount->subscribed('default');
        $currentPlan = $currentAccount?->getPlan() ?? 'free';

        // If user has a conflict and hasn't confirmed, return conflict info
        if (($isAccountOwner || $hasPaidSubscription) && ! $request->boolean('confirm_leave_account')) {
            return response()->json([
                'requires_confirmation' => true,
                'conflict' => [
                    'current_account_name' => $currentAccount->name,
                    'current_plan' => $currentPlan,
                    'is_account_owner' => $isAccountOwner,
                    'has_paid_subscription' => $hasPaidSubscription,
                    'target_workspace_name' => $targetAccount->name,
                    'target_plan' => $targetAccount->getPlan(),
                ],
                'message' => 'You must leave your current account to join this workspace.',
            ], 409);
        }

        // User confirmed or has no conflict - proceed with account switch
        if ($currentAccount && $isAccountOwner) {
            // Cancel any active subscription on the old account
            if ($hasPaidSubscription) {
                $currentAccount->subscription('default')->cancelNow();
            }

            // If there are other members, transfer ownership or handle appropriately
            $otherMembers = $currentAccount->members()->where('user_id', '!=', $user->id)->get();
            if ($otherMembers->isEmpty()) {
                // No other members - account becomes orphaned (could be cleaned up later)
                // For now, just leave it as-is
            }
            // Note: For v1, we don't transfer ownership. The account becomes ownerless.
            // A future improvement could prompt to select a new owner.
        }

        // Accept the invitation (this updates user's account_id)
        $invitation->accept($user);

        return response()->json([
            'message' => "You've joined {$targetAccount->name}!",
        ]);
    }
}
