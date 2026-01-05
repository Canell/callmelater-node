<?php

namespace App\Http\Controllers;

use App\Services\StatusService;
use Illuminate\Http\JsonResponse;

class PublicStatusController extends Controller
{
    public function __construct(
        private StatusService $statusService
    ) {}

    /**
     * Get the public status data.
     *
     * This endpoint is publicly accessible and heavily cached.
     * It returns the current status of all visible system components
     * and any active or recent incidents.
     */
    public function index(): JsonResponse
    {
        $status = $this->statusService->getPublicStatus();

        return response()->json($status)
            ->header('Cache-Control', 'public, max-age=60')
            ->header('Access-Control-Allow-Origin', '*');
    }
}
