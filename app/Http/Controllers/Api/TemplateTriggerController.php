<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActionResource;
use App\Models\Account;
use App\Models\ActionTemplate;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\ActionService;
use App\Services\PlaceholderService;
use App\Services\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateTriggerController extends Controller
{
    public function __construct(
        private ActionService $actionService,
        private PlaceholderService $placeholderService,
        private QuotaService $quotaService
    ) {}

    /**
     * Trigger a template to create an action.
     * This is a public endpoint authenticated by the template token.
     */
    public function trigger(Request $request, string $token): JsonResponse
    {
        // Find active template by token
        $template = ActionTemplate::where('trigger_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Template not found or inactive.',
            ], 404);
        }

        // Get params from request body
        $params = $request->all();

        // Validate required placeholders
        $placeholders = $template->placeholders ?? [];
        $errors = $this->placeholderService->validateRequired($placeholders, $params);

        if (! empty($errors)) {
            return response()->json([
                'error' => 'validation_error',
                'message' => 'Missing required parameters.',
                'errors' => $errors,
            ], 422);
        }

        // Apply defaults for optional placeholders
        $params = $this->placeholderService->applyDefaults($placeholders, $params);

        // Get account (should always exist but check anyway)
        /** @var Account|null $account */
        $account = $template->account;
        if (! $account) {
            return response()->json([
                'error' => 'no_account',
                'message' => 'Template account not found.',
            ], 500);
        }

        // Check account quota before creating action
        if (! $this->quotaService->canCreateAction($account)) {
            return response()->json([
                'error' => 'quota_exceeded',
                'message' => 'Action quota exceeded for this account.',
            ], 429);
        }

        // Build action data from template with placeholder substitution
        $actionData = $this->buildActionData($template, $params);

        // Get user for action creation (use template creator or account owner)
        /** @var User|null $user */
        $user = $template->creator ?? $account->owner;

        if (! $user) {
            return response()->json([
                'error' => 'no_user',
                'message' => 'No valid user found for this template.',
            ], 500);
        }

        try {
            // Create the action via ActionService
            $result = $this->actionService->create($user, $actionData);

            // Update template stats
            $template->recordTrigger();

            return response()->json([
                'message' => 'Action created from template.',
                'data' => new ActionResource($result['action']),
                'meta' => $result['meta'],
            ], 201);

        } catch (\Exception $e) {
            // Check for domain verification errors
            if (str_contains($e->getMessage(), 'domain verification')) {
                return response()->json([
                    'error' => 'domain_verification_required',
                    'message' => $e->getMessage(),
                ], 403);
            }

            return response()->json([
                'error' => 'creation_failed',
                'message' => 'Failed to create action: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build action data from template configuration with placeholder substitution.
     *
     * @return array<string, mixed>
     */
    private function buildActionData(ActionTemplate $template, array $params): array
    {
        $data = [
            'name' => $this->placeholderService->substitute($template->name, $params),
            'mode' => $template->mode,
            'timezone' => $template->timezone,
            'max_attempts' => $template->max_attempts,
            'retry_strategy' => $template->retry_strategy,
            'template_id' => $template->id,
        ];

        // Process request config with placeholders
        if ($template->request_config) {
            /** @var array<string, mixed> $requestConfig */
            $requestConfig = $template->request_config;

            // Handle body stored as string template (for numeric placeholders like {{id}})
            if (isset($requestConfig['body']) && is_string($requestConfig['body'])) {
                $bodyString = $this->placeholderService->substitute($requestConfig['body'], $params);
                $parsedBody = json_decode($bodyString, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $requestConfig['body'] = $parsedBody;
                }
                // If JSON parse fails, leave as string - ActionService will handle validation
            }

            $data['request'] = $this->placeholderService->substituteDeep(
                $requestConfig,
                $params
            );
        }

        // Process gate config with placeholders
        if ($template->gate_config) {
            $data['gate'] = $this->placeholderService->substituteDeep(
                $template->gate_config,
                $params
            );
        }

        // Coordination config (passed through as-is, no placeholder substitution)
        if ($template->coordination_config) {
            $data['coordination'] = $template->coordination_config;
        }

        // Build coordination keys - merge template defaults with param-based keys
        // Apply placeholder substitution to coordination keys
        $coordinationKeys = $template->default_coordination_keys ?? [];
        if (isset($params['coordination_keys']) && is_array($params['coordination_keys'])) {
            $coordinationKeys = array_merge($coordinationKeys, $params['coordination_keys']);
        }
        if (! empty($coordinationKeys)) {
            // Apply placeholder substitution to each key
            $coordinationKeys = array_map(
                fn ($key) => $this->placeholderService->substitute($key, $params),
                $coordinationKeys
            );
            $data['coordination_keys'] = array_unique($coordinationKeys);
        }

        // Handle intent/scheduling
        // Allow override via params, otherwise default to immediate execution (1 second delay)
        if (isset($params['intent'])) {
            $data['intent'] = $params['intent'];
        } elseif (isset($params['execute_at'])) {
            $data['execute_at'] = $params['execute_at'];
        } else {
            // Default: execute in 1 second
            $data['intent'] = ['delay' => '1s'];
        }

        return $data;
    }
}
