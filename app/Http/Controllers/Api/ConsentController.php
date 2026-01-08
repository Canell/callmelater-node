<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    public function __construct(
        private ConsentService $consentService
    ) {}

    /**
     * Accept opt-in request.
     */
    public function accept(Request $request, string $token): JsonResponse|RedirectResponse
    {
        $consent = $this->consentService->acceptOptIn($token);

        if (!$consent) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'invalid_token',
                    'message' => 'Invalid or expired consent token.',
                ], 400);
            }

            return redirect('/consent/error?reason=invalid_token');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'You have successfully opted in to receive reminders.',
            ]);
        }

        return redirect('/consent/accepted');
    }

    /**
     * Decline opt-in request.
     */
    public function decline(Request $request, string $token): JsonResponse|RedirectResponse
    {
        $consent = $this->consentService->declineOptIn($token);

        if (!$consent) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'invalid_token',
                    'message' => 'Invalid or expired consent token.',
                ], 400);
            }

            return redirect('/consent/error?reason=invalid_token');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'You have declined to receive reminders.',
            ]);
        }

        return redirect('/consent/declined?token=' . $token);
    }

    /**
     * Unsubscribe from reminders (from email link).
     */
    public function unsubscribe(Request $request, string $token): JsonResponse|RedirectResponse
    {
        $consent = $this->consentService->declineOptIn($token, 'unsubscribed');

        if (!$consent) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'invalid_token',
                    'message' => 'Invalid or expired consent token.',
                ], 400);
            }

            return redirect('/consent/error?reason=invalid_token');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'You have been unsubscribed from reminder emails.',
            ]);
        }

        return redirect('/consent/unsubscribed?token=' . $token);
    }
}
