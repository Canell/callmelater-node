<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResponseResource;
use App\Models\ReminderRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResponseDashboardController extends Controller
{
    /**
     * List all responses across reminders for the authenticated user's account.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = ReminderRecipient::query()
            ->whereIn('status', [
                ReminderRecipient::STATUS_CONFIRMED,
                ReminderRecipient::STATUS_DECLINED,
                ReminderRecipient::STATUS_SNOOZED,
            ])
            ->whereHas('action', fn ($q) => $q->where('account_id', $user->account_id))
            ->with(['action:id,name', 'contact:id,first_name,last_name,email,phone'])
            ->orderBy('responded_at', 'desc');

        // Filter by response type
        if ($request->filled('response_type')) {
            $query->where('status', $request->input('response_type'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('responded_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('responded_at', '<=', $request->input('date_to'));
        }

        // Search by email or contact name
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', $search)
                    ->orWhereHas('contact', fn ($c) => $c->where('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search)
                    );
            });
        }

        $responses = $query->paginate($request->input('per_page', 25));

        return ResponseResource::collection($responses);
    }
}
