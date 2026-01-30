<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActionResource;
use App\Http\Resources\ChainResource;
use App\Models\Account;
use App\Models\ActionChain;
use App\Models\ActionTemplate;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\ActionService;
use App\Services\ChainService;
use App\Services\PlaceholderService;
use App\Services\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateTriggerController extends Controller
{
    public function __construct(
        private ActionService $actionService,
        private ChainService $chainService,
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
            // Handle chain templates differently
            if ($template->isChain()) {
                return $this->triggerChain($template, $account, $user, $params);
            }

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
            // Check for domain verification errors (safe to expose to user)
            if (str_contains($e->getMessage(), 'domain verification')) {
                return response()->json([
                    'error' => 'domain_verification_required',
                    'message' => $e->getMessage(),
                ], 403);
            }

            // Check for validation errors (safe to expose to user)
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'error' => 'validation_error',
                    'message' => 'Action data validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }

            // Log the full exception for debugging, but return generic message to client
            \Log::error('Template trigger failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'creation_failed',
                'message' => 'Failed to create action. Please check your template configuration and try again.',
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

    /**
     * Trigger a chain template to create a chain.
     *
     * @param  array<string, mixed>  $params
     */
    private function triggerChain(ActionTemplate $template, Account $account, User $user, array $params): JsonResponse
    {
        // Build chain data from template with placeholder substitution
        $chainData = $this->buildChainData($template, $params);

        // Create the chain via ChainService
        $chain = $this->chainService->createChain($account, $chainData, $user);

        // Update template stats
        $template->recordTrigger();

        return response()->json([
            'message' => 'Chain created from template.',
            'data' => new ChainResource($chain),
        ], 201);
    }

    /**
     * Build chain data from template configuration with placeholder substitution.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function buildChainData(ActionTemplate $template, array $params): array
    {
        $data = [
            'name' => $this->placeholderService->substitute($template->name, $params),
            'error_handling' => $template->getChainErrorHandling(),
            'input' => $params, // Pass all params as chain input
        ];

        // Process chain steps with placeholder substitution
        $steps = [];
        foreach ($template->getChainSteps() as $step) {
            $processedStep = $this->processChainStep($step, $params);
            $steps[] = $processedStep;
        }
        $data['steps'] = $steps;

        return $data;
    }

    /**
     * Process a single chain step with placeholder substitution.
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function processChainStep(array $step, array $params): array
    {
        $processed = [
            'name' => $this->placeholderService->substitute($step['name'] ?? 'Step', $params),
            'type' => $step['type'],
        ];

        // Handle condition
        if (! empty($step['condition'])) {
            $processed['condition'] = $this->placeholderService->substitute($step['condition'], $params);
        }

        // Handle delay
        if (! empty($step['delay'])) {
            $processed['delay'] = $step['delay'];
        }

        // Type-specific processing
        if ($step['type'] === ActionChain::STEP_HTTP_CALL) {
            $processed['url'] = $this->placeholderService->substitute($step['url'] ?? '', $params);
            $processed['method'] = $step['method'] ?? 'POST';

            if (! empty($step['headers'])) {
                $processed['headers'] = $this->placeholderService->substituteDeep($step['headers'], $params);
            }

            if (! empty($step['body'])) {
                // Handle body stored as string template
                if (is_string($step['body'])) {
                    $bodyString = $this->placeholderService->substitute($step['body'], $params);
                    $parsedBody = json_decode($bodyString, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $processed['body'] = $parsedBody;
                    }
                } else {
                    $processed['body'] = $this->placeholderService->substituteDeep($step['body'], $params);
                }
            }
        } elseif ($step['type'] === ActionChain::STEP_GATED) {
            $gate = $step['gate'] ?? [];
            $processed['gate'] = [
                'message' => $this->placeholderService->substitute($gate['message'] ?? '', $params),
                'channels' => $gate['channels'] ?? ['email'],
            ];

            // Process recipients with placeholder substitution
            if (! empty($gate['recipients'])) {
                $processed['gate']['recipients'] = array_map(
                    fn ($r) => $this->placeholderService->substitute($r, $params),
                    $gate['recipients']
                );
            }

            // Copy other gate settings
            foreach (['timeout', 'on_timeout', 'max_snoozes', 'confirmation_mode', 'integration_ids'] as $key) {
                if (isset($gate[$key])) {
                    $processed['gate'][$key] = $gate[$key];
                }
            }
        }
        // Delay steps just need the delay field which is already handled above

        return $processed;
    }
}
