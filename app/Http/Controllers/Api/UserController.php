<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
     * Delete the user's account.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        // Cancel any active subscriptions
        if ($user->subscribed('default')) {
            $user->subscription('default')->cancelNow();
        }

        // Delete all API tokens
        $user->tokens()->delete();

        // Delete all actions (cascade will handle related records)
        $user->actions()->delete();

        // Delete the user
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ]);
    }
}
