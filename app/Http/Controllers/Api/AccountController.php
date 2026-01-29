<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Get the current user's account.
     */
    public function show(Request $request): AccountResource|JsonResponse
    {
        $user = $request->user();
        $account = Account::with(['owner', 'members'])->find($user->account_id);

        if (! $account) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        return new AccountResource($account);
    }

    /**
     * Update account name.
     */
    public function update(Request $request): AccountResource|JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $account = $user->account;

        // Only owner/admin can update
        if (! $account->userCanManage($user)) {
            return response()->json(['error' => 'Only account owner or admin can update the account'], 403);
        }

        $account->update([
            'name' => $request->input('name'),
        ]);

        $account->load(['owner', 'members']);

        return new AccountResource($account);
    }

    /**
     * Add a member to the account (Business plan only).
     */
    public function addMember(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'nullable|in:member,admin',
        ]);

        $user = $request->user();
        $account = $user->account;

        // Check Business plan
        if ($account->getPlan() !== 'business') {
            return response()->json([
                'error' => 'Adding team members requires a Business plan.',
                'upgrade_url' => '/pricing',
            ], 403);
        }

        // Only owner/admin can add members
        if (! $account->userCanManage($user)) {
            return response()->json(['error' => 'You do not have permission to add members'], 403);
        }

        // Find the user by email
        $newMember = User::where('email', $request->input('email'))->first();

        if (! $newMember) {
            return response()->json(['error' => 'User not found. They must create an account first.'], 404);
        }

        // Check if already a member
        if ($account->members()->where('user_id', $newMember->id)->exists()) {
            return response()->json(['error' => 'User is already an account member'], 422);
        }

        // Add member and update their account_id
        $role = $request->input('role', 'member');
        $account->members()->attach($newMember->id, ['role' => $role]);
        $newMember->update(['account_id' => $account->id]);

        $account->load(['owner', 'members']);

        return response()->json([
            'message' => 'Member added successfully',
            'account' => new AccountResource($account),
        ]);
    }

    /**
     * Update account branding (Business plan only).
     */
    public function updateBranding(Request $request): JsonResponse
    {
        $request->validate([
            'logo_url' => 'nullable|url|max:2000',
            'brand_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $user = $request->user();
        $account = $user->account;

        // Check Business plan
        if ($account->getPlan() !== 'business') {
            return response()->json([
                'error' => 'Custom branding requires a Business plan.',
                'upgrade_url' => '/pricing',
            ], 403);
        }

        // Only owner/admin can update branding
        if (! $account->userCanManage($user)) {
            return response()->json(['error' => 'You do not have permission to update branding'], 403);
        }

        $account->update([
            'logo_url' => $request->input('logo_url'),
            'brand_color' => $request->input('brand_color'),
        ]);

        return response()->json([
            'message' => 'Branding updated successfully',
            'logo_url' => $account->logo_url,
            'brand_color' => $account->brand_color,
        ]);
    }

    /**
     * Remove a member from the account.
     */
    public function removeMember(Request $request, string $userId): JsonResponse
    {
        $user = $request->user();
        $account = $user->account;

        // Cannot remove the owner
        if ($account->owner_id === (int) $userId) {
            return response()->json(['error' => 'Cannot remove the account owner'], 422);
        }

        // User can remove themselves, or owner/admin can remove others
        $isSelf = $user->id === (int) $userId;
        if (! $isSelf && ! $account->userCanManage($user)) {
            return response()->json(['error' => 'You do not have permission to remove members'], 403);
        }

        // Remove from account and create their own personal account
        $removedUser = User::find($userId);
        if ($removedUser) {
            $account->members()->detach($userId);

            // Create a new personal account for the removed user
            $newAccount = Account::create([
                'name' => "{$removedUser->name}'s Account",
                'owner_id' => $removedUser->id,
            ]);
            $removedUser->update(['account_id' => $newAccount->id]);
            $newAccount->members()->attach($removedUser->id, ['role' => 'owner']);
        }

        return response()->json(['message' => 'Member removed']);
    }
}
