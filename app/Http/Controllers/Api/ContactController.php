<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateContactRequest;
use App\Http\Requests\Api\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    /**
     * List all contacts for the authenticated user's account.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Contact::where('account_id', $request->user()->account_id)
            ->orderBy('first_name')
            ->orderBy('last_name');

        // Search filter (case-insensitive)
        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $contacts = $query->paginate($perPage);

        return ContactResource::collection($contacts);
    }

    /**
     * Create a new contact.
     */
    public function store(CreateContactRequest $request): ContactResource
    {
        $contact = Contact::create([
            'account_id' => $request->user()->account_id,
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
        ]);

        return new ContactResource($contact);
    }

    /**
     * Get a specific contact.
     */
    public function show(Request $request, Contact $contact): ContactResource|JsonResponse
    {
        // Ensure contact belongs to user's account
        if ($contact->account_id !== $request->user()->account_id) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Contact not found.',
            ], 404);
        }

        return new ContactResource($contact);
    }

    /**
     * Update a contact.
     */
    public function update(UpdateContactRequest $request, Contact $contact): ContactResource|JsonResponse
    {
        // Ensure contact belongs to user's account
        if ($contact->account_id !== $request->user()->account_id) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Contact not found.',
            ], 404);
        }

        $contact->update($request->only(['first_name', 'last_name', 'email', 'phone']));

        return new ContactResource($contact);
    }

    /**
     * Delete a contact.
     */
    public function destroy(Request $request, Contact $contact): JsonResponse
    {
        // Ensure contact belongs to user's account
        if ($contact->account_id !== $request->user()->account_id) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Contact not found.',
            ], 404);
        }

        $contact->delete();

        return response()->json([
            'message' => 'Contact deleted successfully.',
        ]);
    }
}
