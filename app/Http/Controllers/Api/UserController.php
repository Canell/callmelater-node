<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\AdminNotificationPreference;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Update the user's profile (timezone).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        $request->user()->update([
            'timezone' => $validated['timezone'],
        ]);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $request->user()->fresh(),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => $validated['password'],
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Regenerate the user's webhook signing secret.
     */
    public function regenerateWebhookSecret(Request $request): JsonResponse
    {
        $secret = 'whsec_'.Str::random(32);

        $request->user()->update([
            'webhook_secret' => $secret,
        ]);

        return response()->json([
            'message' => 'Webhook secret regenerated',
            'secret' => $secret,
        ]);
    }

    /**
     * Get the user's webhook secret.
     */
    public function getWebhookSecret(Request $request): JsonResponse
    {
        $user = $request->user();

        // Generate a secret if one doesn't exist
        if (! $user->webhook_secret) {
            $user->webhook_secret = 'whsec_'.Str::random(32);
            $user->save();
        }

        return response()->json([
            'secret' => $user->webhook_secret,
        ]);
    }

    /**
     * Update the user's notification preferences.
     */
    public function updateNotifications(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action_failures' => ['sometimes', 'boolean'],
            'reminder_expired' => ['sometimes', 'boolean'],
            'usage_limits' => ['sometimes', 'boolean'],
        ]);

        $request->user()->update([
            'notification_preferences' => $validated,
        ]);

        return response()->json([
            'message' => 'Notification preferences updated',
            'preferences' => $validated,
        ]);
    }

    /**
     * Get admin notification preferences (admins only).
     */
    public function getAdminNotifications(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Admin access required',
            ], 403);
        }

        $prefs = AdminNotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'health_alerts' => true,
                'incident_alerts' => true,
                'channels' => ['email'],
            ]
        );

        return response()->json([
            'health_alerts' => $prefs->health_alerts,
            'incident_alerts' => $prefs->incident_alerts,
            'channels' => $prefs->channels,
        ]);
    }

    /**
     * Update admin notification preferences (admins only).
     */
    public function updateAdminNotifications(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Admin access required',
            ], 403);
        }

        $validated = $request->validate([
            'health_alerts' => ['sometimes', 'boolean'],
            'incident_alerts' => ['sometimes', 'boolean'],
            'channels' => ['sometimes', 'array'],
            'channels.*' => ['string', 'in:email,sms'],
        ]);

        $prefs = AdminNotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return response()->json([
            'message' => 'Admin notification preferences updated',
            'health_alerts' => $prefs->health_alerts,
            'incident_alerts' => $prefs->incident_alerts,
            'channels' => $prefs->channels,
        ]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->account;

        // Cancel any active subscriptions on the account
        if ($account && $account->subscribed('default')) {
            $account->subscription('default')->cancelNow();
        }

        // Delete all API tokens
        $user->tokens()->delete();

        // Delete all actions from the account (cascade will handle related records)
        if ($account) {
            $account->actions()->delete();
        }

        // Delete the user
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ]);
    }
}
