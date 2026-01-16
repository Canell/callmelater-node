<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamResource;
use App\Mail\TeamInvitationMail;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Mail;

class TeamController extends Controller
{
    /**
     * List all teams for the current user's account.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $account = $user->account;

        $teams = Team::with(['owner', 'members'])
            ->where('account_id', $account->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return TeamResource::collection($teams);
    }

    /**
     * Create a new team.
     */
    public function store(Request $request): TeamResource|JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $account = $user->account;

        // Check Business plan
        if ($account->getPlan() !== 'business') {
            return response()->json([
                'error' => 'Creating teams requires a Business plan.',
                'upgrade_url' => '/pricing',
            ], 403);
        }

        // Only account owner/admin can create teams
        if (! $account->userCanManage($user)) {
            return response()->json([
                'error' => 'You do not have permission to create teams.',
            ], 403);
        }

        $team = Team::create([
            'name' => $request->input('name'),
            'account_id' => $account->id,
            'owner_id' => $user->id,
        ]);

        // Add creator as owner member
        $team->members()->attach($user->id, ['role' => 'owner']);

        $team->load(['owner', 'members']);

        return new TeamResource($team);
    }

    /**
     * Get a specific team.
     */
    public function show(Request $request, Team $team): TeamResource|JsonResponse
    {
        $user = $request->user();

        // Check team belongs to user's account
        if ($team->account_id !== $user->account_id) {
            return response()->json(['error' => 'Team not found.'], 404);
        }

        $team->load(['owner', 'members']);

        return new TeamResource($team);
    }

    /**
     * Update a team.
     */
    public function update(Request $request, Team $team): TeamResource|JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();

        // Check team belongs to user's account
        if ($team->account_id !== $user->account_id) {
            return response()->json(['error' => 'Team not found.'], 404);
        }

        // Only team owner/admin can update
        if (! $team->userCanManage($user)) {
            return response()->json([
                'error' => 'You do not have permission to update this team.',
            ], 403);
        }

        $team->update([
            'name' => $request->input('name'),
        ]);

        $team->load(['owner', 'members']);

        return new TeamResource($team);
    }

    /**
     * Delete a team.
     */
    public function destroy(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();

        // Check team belongs to user's account
        if ($team->account_id !== $user->account_id) {
            return response()->json(['error' => 'Team not found.'], 404);
        }

        // Only team owner can delete
        if (! $team->isOwner($user)) {
            return response()->json([
                'error' => 'Only the team owner can delete the team.',
            ], 403);
        }

        $team->delete();

        return response()->json(['message' => 'Team deleted.']);
    }

    /**
     * Add a member to a team or send invitation.
     */
    public function addMember(Request $request, Team $team): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'nullable|in:member,admin',
        ]);

        $user = $request->user();
        $email = strtolower($request->input('email'));
        $role = $request->input('role', 'member');

        // Check team belongs to user's account
        if ($team->account_id !== $user->account_id) {
            return response()->json(['error' => 'Team not found.'], 404);
        }

        // Only team owner/admin can add members
        if (! $team->userCanManage($user)) {
            return response()->json([
                'error' => 'You do not have permission to add members.',
            ], 403);
        }

        // Check if there's already a pending invitation
        $existingInvitation = TeamInvitation::where('team_id', $team->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existingInvitation) {
            return response()->json([
                'error' => 'An invitation has already been sent to this email.',
            ], 422);
        }

        // Find the user by email
        $newMember = User::where('email', $email)->first();

        // If user exists and is part of the same account, add directly
        if ($newMember && $newMember->account_id === $team->account_id) {
            // Check if already a member
            if ($team->hasMember($newMember)) {
                return response()->json([
                    'error' => 'User is already a team member.',
                ], 422);
            }

            // Add member directly
            $team->members()->attach($newMember->id, ['role' => $role]);
            $team->load(['owner', 'members']);

            return response()->json([
                'message' => 'Member added.',
                'team' => new TeamResource($team),
            ]);
        }

        // User doesn't exist or isn't in the account - send invitation
        $invitation = TeamInvitation::createForTeam($team, $email, $user, $role);

        // Send invitation email
        Mail::to($email)->queue(new TeamInvitationMail($invitation));

        $team->load(['owner', 'members']);

        return response()->json([
            'message' => 'Invitation sent to ' . $email,
            'invitation_sent' => true,
            'team' => new TeamResource($team),
        ]);
    }

    /**
     * List pending invitations for a team.
     */
    public function invitations(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();

        // Check team belongs to user's account
        if ($team->account_id !== $user->account_id) {
            return response()->json(['error' => 'Team not found.'], 404);
        }

        $invitations = TeamInvitation::where('team_id', $team->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->with('inviter:id,name,email')
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'email' => $inv->email,
                'role' => $inv->role,
                'invited_by' => $inv->inviter->name,
                'expires_at' => $inv->expires_at->toIso8601String(),
                'created_at' => $inv->created_at->toIso8601String(),
            ]);

        return response()->json([
            'invitations' => $invitations,
        ]);
    }

    /**
     * Cancel a pending invitation.
     */
    public function cancelInvitation(Request $request, Team $team, TeamInvitation $invitation): JsonResponse
    {
        $user = $request->user();

        // Check team belongs to user's account
        if ($team->account_id !== $user->account_id) {
            return response()->json(['error' => 'Team not found.'], 404);
        }

        // Check invitation belongs to this team
        if ($invitation->team_id !== $team->id) {
            return response()->json(['error' => 'Invitation not found.'], 404);
        }

        // Only team owner/admin can cancel invitations
        if (! $team->userCanManage($user)) {
            return response()->json([
                'error' => 'You do not have permission to cancel invitations.',
            ], 403);
        }

        $invitation->delete();

        return response()->json(['message' => 'Invitation cancelled.']);
    }

    /**
     * Remove a member from a team.
     */
    public function removeMember(Request $request, Team $team, int $userId): JsonResponse
    {
        $user = $request->user();

        // Check team belongs to user's account
        if ($team->account_id !== $user->account_id) {
            return response()->json(['error' => 'Team not found.'], 404);
        }

        // Cannot remove the owner
        if ($team->owner_id === $userId) {
            return response()->json([
                'error' => 'Cannot remove the team owner.',
            ], 422);
        }

        // User can remove themselves, or owner/admin can remove others
        $isSelf = $user->id === $userId;
        if (! $isSelf && ! $team->userCanManage($user)) {
            return response()->json([
                'error' => 'You do not have permission to remove members.',
            ], 403);
        }

        // Remove from team
        $team->members()->detach($userId);

        return response()->json(['message' => 'Member removed.']);
    }
}
