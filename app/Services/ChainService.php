<?php

namespace App\Services;

use App\Jobs\ResolveIntentJob;
use App\Models\Account;
use App\Models\ActionChain;
use App\Models\ActionCoordinationKey;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChainService
{
    public function __construct(
        private ActionService $actionService
    ) {}

    /**
     * Create a new action chain.
     *
     * @param  array<string, mixed>  $data
     */
    public function createChain(Account $account, array $data, ?User $user = null): ActionChain
    {
        return DB::transaction(function () use ($account, $data, $user) {
            // Create the chain record
            $chain = ActionChain::create([
                'account_id' => $account->id,
                'created_by_user_id' => $user?->id,
                'name' => $data['name'],
                'steps' => $data['steps'],
                'input' => $data['input'] ?? [],
                'context' => ['steps' => []],
                'status' => ActionChain::STATUS_PENDING,
                'current_step' => 0,
                'error_handling' => $data['error_handling'] ?? ActionChain::ERROR_FAIL_CHAIN,
            ]);

            // Create the first step action
            $this->createStepAction($chain, 0, $user);

            // Mark chain as running
            $chain->markRunning();

            Log::info('Action chain created', [
                'chain_id' => $chain->id,
                'name' => $chain->name,
                'total_steps' => $chain->getTotalSteps(),
            ]);

            return $chain;
        });
    }

    /**
     * Advance the chain after a step completes successfully.
     *
     * @param  array<string, mixed>  $response  The response from the completed step
     */
    public function advanceChain(ActionChain $chain, ScheduledAction $completedStep, array $response = []): void
    {
        // Determine the step's outcome status
        $status = $this->determineStepStatus($completedStep);

        // Store the step's result in the chain context
        $chain->storeStepResponse($completedStep->chain_step, $status, $response);

        Log::info('Chain step completed', [
            'chain_id' => $chain->id,
            'step' => $completedStep->chain_step,
            'status' => $status,
        ]);

        // Check if this is the last step
        if (! $chain->hasNextStep()) {
            $chain->markCompleted();
            Log::info('Action chain completed', ['chain_id' => $chain->id]);

            return;
        }

        // Advance to next step
        $chain->advanceStep();
        $nextStepIndex = $chain->current_step;
        $nextStepDef = $chain->getStepDefinition($nextStepIndex);

        // Evaluate condition if present
        if (isset($nextStepDef['condition'])) {
            $conditionMet = $this->evaluateCondition($nextStepDef['condition'], $chain);

            if (! $conditionMet) {
                Log::info('Chain step condition not met, skipping', [
                    'chain_id' => $chain->id,
                    'step' => $nextStepIndex,
                    'condition' => $nextStepDef['condition'],
                ]);

                // Skip this step and try the next one
                $chain->storeStepResponse($nextStepIndex, 'skipped', ['reason' => 'condition_not_met']);

                if ($chain->hasNextStep()) {
                    $chain->advanceStep();
                    $this->createStepAction($chain, $chain->current_step, $chain->creator);
                } else {
                    $chain->markCompleted();
                }

                return;
            }
        }

        // Create the next step action
        $this->createStepAction($chain, $nextStepIndex, $chain->creator);
    }

    /**
     * Handle a step failure.
     */
    public function handleStepFailure(ActionChain $chain, ScheduledAction $failedStep, string $reason): void
    {
        $chain->storeStepResponse($failedStep->chain_step, 'failed', ['error' => $reason]);

        if ($chain->shouldFailOnError()) {
            $chain->markFailed("Step {$failedStep->chain_step} failed: {$reason}");
            Log::error('Action chain failed', [
                'chain_id' => $chain->id,
                'step' => $failedStep->chain_step,
                'reason' => $reason,
            ]);

            return;
        }

        // Skip on error - continue to next step
        if ($chain->hasNextStep()) {
            $chain->advanceStep();
            $this->createStepAction($chain, $chain->current_step, $chain->creator);
        } else {
            $chain->markCompleted();
        }
    }

    /**
     * Cancel a chain and all its pending actions.
     */
    public function cancelChain(ActionChain $chain): void
    {
        DB::transaction(function () use ($chain) {
            // Cancel all pending/awaiting actions
            $chain->actions()
                ->whereNotIn('resolution_status', ScheduledAction::TERMINAL_STATUSES)
                ->each(function (ScheduledAction $action) {
                    if ($action->canBeCancelled()) {
                        $action->cancel();
                    }
                });

            $chain->markCancelled();

            Log::info('Action chain cancelled', ['chain_id' => $chain->id]);
        });
    }

    /**
     * Create a scheduled action for a chain step.
     */
    private function createStepAction(ActionChain $chain, int $stepIndex, ?User $user): ScheduledAction
    {
        $stepDef = $chain->getStepDefinition($stepIndex);

        if (! $stepDef) {
            throw new \InvalidArgumentException("Invalid step index: {$stepIndex}");
        }

        $stepType = $stepDef['type'];

        // Handle delay step
        if ($stepType === ActionChain::STEP_DELAY) {
            return $this->createDelayAction($chain, $stepIndex, $stepDef, $user);
        }

        // Interpolate variables in the step definition
        $stepDef = $this->interpolateStepDefinition($stepDef, $chain);

        $action = new ScheduledAction;
        $action->account_id = $chain->account_id;
        $action->chain_id = $chain->id;
        $action->chain_step = $stepIndex;
        $action->created_by_user_id = $user?->id;
        $action->name = $stepDef['name'] ?? "Chain step {$stepIndex}";
        $action->timezone = $stepDef['timezone'] ?? 'UTC';

        // Set mode and configurations based on step type
        if ($stepType === ActionChain::STEP_HTTP_CALL) {
            $action->mode = ScheduledAction::MODE_IMMEDIATE;
            $action->request = [
                'url' => $stepDef['url'],
                'method' => $stepDef['method'] ?? 'POST',
                'headers' => $stepDef['headers'] ?? [],
                'body' => $stepDef['body'] ?? null,
            ];
            $action->max_attempts = $stepDef['max_attempts'] ?? 5;
            $action->retry_strategy = $stepDef['retry_strategy'] ?? 'exponential';
            $action->webhook_secret = $user?->webhook_secret ?? Str::random(32);
        } elseif ($stepType === ActionChain::STEP_GATED) {
            $action->mode = ScheduledAction::MODE_GATED;
            $action->gate = $stepDef['gate'];
        }

        // Set intent - execute immediately (or use step's delay)
        if (isset($stepDef['delay'])) {
            $action->intent_type = ScheduledAction::INTENT_WALL_CLOCK;
            $action->intent_payload = ['delay' => $stepDef['delay']];
        } else {
            $action->intent_type = ScheduledAction::INTENT_ABSOLUTE;
            $action->intent_payload = ['execute_at' => now()->toIso8601String()];
        }

        $action->resolution_status = ScheduledAction::STATUS_PENDING_RESOLUTION;
        $action->save();

        // Add chain coordination key
        ActionCoordinationKey::create([
            'action_id' => $action->id,
            'coordination_key' => "chain:{$chain->id}",
        ]);

        // Dispatch intent resolution
        ResolveIntentJob::dispatch($action);

        Log::info('Chain step action created', [
            'chain_id' => $chain->id,
            'step' => $stepIndex,
            'action_id' => $action->id,
            'type' => $stepType,
        ]);

        return $action;
    }

    /**
     * Create a delay action (waits for specified time, then advances chain).
     */
    private function createDelayAction(ActionChain $chain, int $stepIndex, array $stepDef, ?User $user): ScheduledAction
    {
        $action = new ScheduledAction;
        $action->account_id = $chain->account_id;
        $action->chain_id = $chain->id;
        $action->chain_step = $stepIndex;
        $action->created_by_user_id = $user?->id;
        $action->name = $stepDef['name'] ?? "Delay: {$stepDef['delay']}";
        $action->mode = ScheduledAction::MODE_IMMEDIATE;
        $action->timezone = 'UTC';

        // No HTTP request - just a delay
        $action->request = null;

        // Set the delay intent
        $action->intent_type = ScheduledAction::INTENT_WALL_CLOCK;
        $action->intent_payload = ['delay' => $stepDef['delay']];

        $action->resolution_status = ScheduledAction::STATUS_PENDING_RESOLUTION;
        $action->save();

        // Add chain coordination key
        ActionCoordinationKey::create([
            'action_id' => $action->id,
            'coordination_key' => "chain:{$chain->id}",
        ]);

        // Dispatch intent resolution
        ResolveIntentJob::dispatch($action);

        return $action;
    }

    /**
     * Interpolate variables in a step definition.
     *
     * Supports:
     * - {{input.field}} - From chain input
     * - {{steps.N.response.field}} - From step N's response
     * - {{steps.N.status}} - Status of step N
     * - {{chain.id}} - Chain ID
     * - {{chain.name}} - Chain name
     *
     * @param  array<string, mixed>  $stepDef
     * @return array<string, mixed>
     */
    private function interpolateStepDefinition(array $stepDef, ActionChain $chain): array
    {
        $json = json_encode($stepDef);

        if ($json === false) {
            return $stepDef;
        }

        // Find all {{...}} patterns
        $interpolated = preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($chain) {
            return $this->resolveVariable($matches[1], $chain);
        }, $json);

        $result = json_decode($interpolated, true);

        return is_array($result) ? $result : $stepDef;
    }

    /**
     * Resolve a single variable reference.
     */
    private function resolveVariable(string $path, ActionChain $chain): string
    {
        $parts = explode('.', trim($path));
        $root = array_shift($parts);

        $value = match ($root) {
            'input' => data_get($chain->input, implode('.', $parts)),
            'steps' => $this->resolveStepVariable($parts, $chain),
            'chain' => $this->resolveChainVariable($parts, $chain),
            default => null,
        };

        // Return as JSON-safe string
        if (is_array($value) || is_object($value)) {
            return json_encode($value) ?: '';
        }

        return (string) ($value ?? '');
    }

    /**
     * Resolve a step variable reference (e.g., steps.0.response.id).
     *
     * @param  array<string>  $parts
     */
    private function resolveStepVariable(array $parts, ActionChain $chain): mixed
    {
        if (empty($parts)) {
            return null;
        }

        $stepIndex = (int) array_shift($parts);
        $field = array_shift($parts) ?? 'response';

        if ($field === 'status') {
            return $chain->getStepStatus($stepIndex);
        }

        if ($field === 'response') {
            $response = $chain->getStepResponse($stepIndex);

            if (empty($parts)) {
                return $response;
            }

            return data_get($response, implode('.', $parts));
        }

        return null;
    }

    /**
     * Resolve a chain variable reference.
     *
     * @param  array<string>  $parts
     */
    private function resolveChainVariable(array $parts, ActionChain $chain): mixed
    {
        if (empty($parts)) {
            return null;
        }

        $field = $parts[0];

        return match ($field) {
            'id' => $chain->id,
            'name' => $chain->name,
            'status' => $chain->status,
            'current_step' => $chain->current_step,
            default => null,
        };
    }

    /**
     * Evaluate a condition expression.
     *
     * Supports simple comparisons:
     * - {{steps.0.status}} == 'executed'
     * - {{steps.1.response.approved}} == true
     */
    private function evaluateCondition(string $condition, ActionChain $chain): bool
    {
        // First, interpolate all variables
        $interpolated = preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($chain) {
            $value = $this->resolveVariable($matches[1], $chain);

            // Wrap strings in quotes for evaluation
            if (is_string($value) && ! is_numeric($value)) {
                return "'{$value}'";
            }

            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            if (is_null($value)) {
                return 'null';
            }

            return (string) $value;
        }, $condition);

        // Parse simple comparison: left operator right
        if (preg_match('/^(.+?)\s*(==|!=|>|<|>=|<=)\s*(.+)$/', trim($interpolated), $matches)) {
            $left = $this->parseConditionValue(trim($matches[1]));
            $operator = $matches[2];
            $right = $this->parseConditionValue(trim($matches[3]));

            return match ($operator) {
                '==' => $left == $right,
                '!=' => $left != $right,
                '>' => $left > $right,
                '<' => $left < $right,
                '>=' => $left >= $right,
                '<=' => $left <= $right,
                default => false,
            };
        }

        // If no operator found, treat as truthy check
        $value = $this->parseConditionValue(trim($interpolated));

        return (bool) $value;
    }

    /**
     * Parse a value from a condition expression.
     */
    private function parseConditionValue(string $value): mixed
    {
        // Boolean literals
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        if ($value === 'null') {
            return null;
        }

        // Numeric
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        // Quoted string - remove quotes
        if (preg_match('/^[\'"](.*)[\'""]$/', $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }

    /**
     * Determine the status string for a completed step.
     */
    private function determineStepStatus(ScheduledAction $action): string
    {
        return match ($action->resolution_status) {
            ScheduledAction::STATUS_EXECUTED => $action->isGated() ?
                ($action->gatePassed() ? 'confirmed' : 'executed') : 'executed',
            ScheduledAction::STATUS_FAILED => 'failed',
            ScheduledAction::STATUS_CANCELLED => 'cancelled',
            ScheduledAction::STATUS_EXPIRED => 'expired',
            default => 'unknown',
        };
    }

    /**
     * Interpolate variables in a string value.
     * Public method for use in other services.
     */
    public function interpolateString(string $template, ActionChain $chain): string
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($chain) {
            return $this->resolveVariable($matches[1], $chain);
        }, $template) ?? $template;
    }
}
