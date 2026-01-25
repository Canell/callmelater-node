<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateTeamMemberRequest;
use App\Http\Requests\Api\UpdateTeamMemberRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamMemberController extends Controller
{
    /**
     * List all team members for the authenticated user's account.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = TeamMember::where('account_id', $request->user()->account_id)
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
        $teamMembers = $query->paginate($perPage);

        return TeamMemberResource::collection($teamMembers);
    }

    /**
     * Create a new team member.
     */
    public function store(CreateTeamMemberRequest $request): TeamMemberResource
    {
        $teamMember = TeamMember::create([
            'account_id' => $request->user()->account_id,
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
        ]);

        return new TeamMemberResource($teamMember);
    }

    /**
     * Get a specific team member.
     */
    public function show(Request $request, TeamMember $teamMember): TeamMemberResource|JsonResponse
    {
        // Ensure team member belongs to user's account
        if ($teamMember->account_id !== $request->user()->account_id) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Team member not found.',
            ], 404);
        }

        return new TeamMemberResource($teamMember);
    }

    /**
     * Update a team member.
     */
    public function update(UpdateTeamMemberRequest $request, TeamMember $teamMember): TeamMemberResource|JsonResponse
    {
        // Ensure team member belongs to user's account
        if ($teamMember->account_id !== $request->user()->account_id) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Team member not found.',
            ], 404);
        }

        $teamMember->update($request->only(['first_name', 'last_name', 'email', 'phone']));

        return new TeamMemberResource($teamMember);
    }

    /**
     * Delete a team member.
     */
    public function destroy(Request $request, TeamMember $teamMember): JsonResponse
    {
        // Ensure team member belongs to user's account
        if ($teamMember->account_id !== $request->user()->account_id) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Team member not found.',
            ], 404);
        }

        $teamMember->delete();

        return response()->json([
            'message' => 'Team member deleted successfully.',
        ]);
    }
}
