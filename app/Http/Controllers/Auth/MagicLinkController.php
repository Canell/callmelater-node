<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Mail\SignupMagicLinkMail;
use App\Models\MagicLinkToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MagicLinkController extends Controller
{
    /**
     * Send a magic link for login (existing users only).
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = MagicLinkToken::normalizeEmail($validated['email']);
        $user = User::where('email', $email)->first();

        // Always return same response to prevent email enumeration
        $message = 'If an account exists with this email, we\'ve sent a magic link.';

        if (! $user) {
            Log::info('Magic link requested for non-existent email', ['email' => $email]);

            return response()->json(['message' => $message]);
        }

        $this->createAndSendLoginLink($user);

        return response()->json(['message' => $message]);
    }

    /**
     * Send a magic link for signup (creates new users, or logs in existing).
     */
    public function signup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = MagicLinkToken::normalizeEmail($validated['email']);
        $user = User::where('email', $email)->first();

        if ($user) {
            // User exists - silently send login link instead (prevents email enumeration)
            $this->createAndSendLoginLink($user);
            Log::info('Signup attempted for existing user, sent login link instead', ['email' => $email]);
        } else {
            // Create signup token
            $token = MagicLinkToken::create([
                'email' => $email,
                'token' => MagicLinkToken::generateToken(),
                'purpose' => MagicLinkToken::PURPOSE_SIGNUP,
                'expires_at' => now()->addHours(24),
            ]);

            Mail::to($email)->send(new SignupMagicLinkMail($token));
            Log::info('Signup magic link sent', ['email' => $email]);
        }

        // Same response regardless (prevents email enumeration)
        return response()->json(['message' => 'Check your email for the magic link.']);
    }

    /**
     * Verify a magic link token and log in the user.
     */
    public function verify(string $token): RedirectResponse
    {
        $magicLink = MagicLinkToken::where('token', $token)->first();

        if (! $magicLink) {
            Log::warning('Invalid magic link token attempted', ['token' => substr($token, 0, 8).'...']);

            return redirect('/login?error=invalid_link');
        }

        if ($magicLink->isExpired()) {
            Log::info('Expired magic link attempted', ['email' => $magicLink->email]);

            return redirect('/login?error=link_expired');
        }

        if ($magicLink->isUsed()) {
            Log::info('Already used magic link attempted', ['email' => $magicLink->email]);

            return redirect('/login?error=link_already_used');
        }

        if ($magicLink->isForSignup()) {
            // Check if user was created while waiting (e.g., someone else used email)
            $existingUser = User::where('email', $magicLink->email)->first();
            if ($existingUser) {
                // Just log them in instead
                $user = $existingUser;
                Log::info('Signup link used for existing user, logging in', ['email' => $magicLink->email]);
            } else {
                // Create new user without password
                $user = User::create([
                    'name' => $this->generateNameFromEmail($magicLink->email),
                    'email' => $magicLink->email,
                    'password' => null,
                    'email_verified_at' => now(),
                ]);
                Log::info('New user created via magic link signup', ['email' => $magicLink->email, 'user_id' => $user->id]);
            }
        } else {
            // Login link - user must exist
            $user = User::where('email', $magicLink->email)->first();
            if (! $user) {
                Log::warning('Login magic link for non-existent user', ['email' => $magicLink->email]);

                return redirect('/login?error=user_not_found');
            }
            // Clicking a magic link proves email ownership - verify if not already
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
            Log::info('User logged in via magic link', ['email' => $magicLink->email, 'user_id' => $user->id]);
        }

        $magicLink->markUsed();
        Auth::login($user, remember: true);

        // Redirect with auth signal for SPA to set localStorage
        return redirect('/dashboard?auth=magic');
    }

    /**
     * Create and send a login magic link.
     */
    private function createAndSendLoginLink(User $user): void
    {
        $token = MagicLinkToken::create([
            'email' => $user->email,
            'token' => MagicLinkToken::generateToken(),
            'purpose' => MagicLinkToken::PURPOSE_LOGIN,
            'expires_at' => now()->addMinutes(15),
        ]);

        Mail::to($user->email)->send(new MagicLinkMail($user, $token));
        Log::info('Login magic link sent', ['email' => $user->email]);
    }

    /**
     * Generate a display name from an email address.
     */
    private function generateNameFromEmail(string $email): string
    {
        $localPart = explode('@', $email)[0];
        // Convert "john.doe" or "john_doe" to "John Doe"
        $name = str_replace(['.', '_', '-'], ' ', $localPart);

        return ucwords($name);
    }
}
