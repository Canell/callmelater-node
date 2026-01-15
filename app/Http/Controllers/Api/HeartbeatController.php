<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduledAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HeartbeatController extends Controller
{
    /**
     * Receive heartbeat ping from health monitor.
     */
    public function ping(Request $request): JsonResponse
    {
        $actionId = $request->header('X-Action-Id');
        $heartbeatId = $request->header('X-Heartbeat-Id');

        Log::info('Heartbeat received', [
            'action_id' => $actionId,
            'heartbeat_id' => $heartbeatId,
            'ip' => $request->ip(),
        ]);

        // Optionally track heartbeat metrics
        $this->recordHeartbeat($actionId, $heartbeatId);

        return response()->json([
            'status' => 'ok',
            'received_at' => now()->toIso8601String(),
            'message' => 'Heartbeat acknowledged',
        ]);
    }

    /**
     * Record heartbeat for metrics tracking.
     */
    private function recordHeartbeat(?string $actionId, ?string $heartbeatId): void
    {
        if (!$actionId) {
            return;
        }

        // Update cache with last successful heartbeat
        cache()->put('health_monitor:last_heartbeat', [
            'action_id' => $actionId,
            'heartbeat_id' => $heartbeatId,
            'received_at' => now()->toIso8601String(),
        ], now()->addHours(1));
    }

    /**
     * Get heartbeat status (for admin dashboard).
     */
    public function status(): JsonResponse
    {
        $lastHeartbeat = cache()->get('health_monitor:last_heartbeat');

        $status = 'unknown';
        if ($lastHeartbeat) {
            $receivedAt = \Carbon\Carbon::parse($lastHeartbeat['received_at']);
            $minutesAgo = $receivedAt->diffInMinutes(now());

            // If last heartbeat was within 10 minutes, we're healthy
            $status = $minutesAgo <= 10 ? 'healthy' : 'stale';
        }

        return response()->json([
            'status' => $status,
            'last_heartbeat' => $lastHeartbeat,
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
