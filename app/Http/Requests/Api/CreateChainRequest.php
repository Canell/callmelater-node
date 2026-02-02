<?php

namespace App\Http\Requests\Api;

use App\Models\ActionChain;
use App\Models\ChatConnection;
use App\Models\Contact;
use App\Models\ScheduledAction;
use App\Models\UsageCounter;
use App\Services\IntentResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateChainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Chain metadata
            'name' => ['required', 'string', 'max:255'],
            'input' => ['nullable', 'array'],
            'error_handling' => ['nullable', 'string', Rule::in([ActionChain::ERROR_FAIL_CHAIN, ActionChain::ERROR_SKIP_STEP])],

            // Steps array
            'steps' => ['required', 'array', 'min:2', 'max:20'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.type' => ['required', 'string', Rule::in([ActionChain::STEP_HTTP_CALL, ActionChain::STEP_GATED, ActionChain::STEP_DELAY])],
            'steps.*.delay' => ['nullable', 'string', 'regex:/^\d+[mhd]$/'],
            'steps.*.condition' => ['nullable', 'string', 'max:500'],
            'steps.*.timezone' => ['nullable', 'string', 'timezone:all'],

            // HTTP Call step fields
            'steps.*.url' => ['required_if:steps.*.type,http_call', 'nullable', 'string', 'max:2048'],
            'steps.*.method' => ['nullable', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'steps.*.headers' => ['nullable', 'array'],
            'steps.*.body' => ['nullable', 'array'],
            'steps.*.max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'steps.*.retry_strategy' => ['nullable', 'string', Rule::in(['exponential', 'linear'])],

            // Gated step fields
            'steps.*.gate' => ['required_if:steps.*.type,gated', 'nullable', 'array'],
            'steps.*.gate.message' => ['required_with:steps.*.gate', 'string', 'max:5000'],
            'steps.*.gate.recipients' => ['nullable', 'array'],
            'steps.*.gate.recipients.*' => ['string'],
            'steps.*.gate.channels' => ['nullable', 'array'],
            'steps.*.gate.channels.*' => ['string', Rule::in(['email', 'sms', 'teams', 'slack'])],
            'steps.*.gate.timeout' => ['nullable', 'string', 'regex:/^\d+[hdw]$/'],
            'steps.*.gate.on_timeout' => ['nullable', 'string', Rule::in(['cancel', 'expire', 'approve'])],
            'steps.*.gate.max_snoozes' => ['nullable', 'integer', 'min:0', 'max:10'],
            'steps.*.gate.confirmation_mode' => ['nullable', 'string', Rule::in([
                ScheduledAction::CONFIRMATION_FIRST_RESPONSE,
                ScheduledAction::CONFIRMATION_ALL_REQUIRED,
            ])],
            'steps.*.gate.integration_ids' => ['nullable', 'array'],
            'steps.*.gate.integration_ids.*' => ['string', 'uuid'],

            // Delay step - required field
            // 'steps.*.delay' already defined above
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'steps.min' => 'A chain must have at least 2 steps.',
            'steps.max' => 'A chain can have at most 20 steps.',
            'steps.*.name.required' => 'Each step must have a name.',
            'steps.*.type.required' => 'Each step must have a type (http_call, gated, or delay).',
            'steps.*.type.in' => 'Step type must be one of: http_call, gated, delay.',
            'steps.*.url.required_if' => 'URL is required for HTTP call steps.',
            'steps.*.gate.required_if' => 'Gate configuration is required for gated steps.',
            'steps.*.gate.message.required_with' => 'A message is required for gated steps.',
            'steps.*.delay.regex' => 'Delay must be a number followed by m (minutes), h (hours), or d (days). Example: 5m, 1h, 2d',
            'steps.*.condition.max' => 'Step condition must be 500 characters or less.',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\User $user */
            $user = $this->user();

            // Validate each step
            $steps = $this->input('steps', []);
            foreach ($steps as $index => $step) {
                $this->validateStep($validator, $step, $index, $user);
            }

            // Plan-based limits
            $this->validatePlanLimits($validator, $user);
        });
    }

    /**
     * Validate a single step.
     *
     * @param  array<string, mixed>  $step
     */
    private function validateStep(\Illuminate\Validation\Validator $validator, array $step, int $index, \App\Models\User $user): void
    {
        $type = $step['type'] ?? '';

        // Delay step requires delay field
        if ($type === ActionChain::STEP_DELAY && empty($step['delay'])) {
            $validator->errors()->add("steps.{$index}.delay", 'Delay is required for delay steps.');
        }

        // Validate HTTP call URLs can contain variables
        if ($type === ActionChain::STEP_HTTP_CALL && isset($step['url'])) {
            // Allow variable placeholders like {{input.url}} or {{steps.0.response.id}}
            $url = $step['url'];
            // If URL contains variables, don't validate as URL
            if (! str_contains($url, '{{')) {
                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    $validator->errors()->add("steps.{$index}.url", 'URL must be a valid HTTP/HTTPS URL or contain variables.');
                }
            }
        }

        // Validate gated step recipients
        if ($type === ActionChain::STEP_GATED) {
            $gate = $step['gate'] ?? [];
            $recipients = $gate['recipients'] ?? [];
            $channels = $gate['channels'] ?? [];
            $hasChatChannel = ! empty(array_intersect($channels, ['teams', 'slack']));

            // Must have either recipients OR a chat channel
            if (empty($recipients) && ! $hasChatChannel) {
                $validator->errors()->add(
                    "steps.{$index}.gate.recipients",
                    'At least one recipient is required, or select Teams/Slack as a notification channel.'
                );
            }

            // Validate each recipient (allow variables too)
            foreach ($recipients as $recipientIndex => $recipient) {
                // Skip variable placeholders
                if (str_contains($recipient, '{{')) {
                    continue;
                }

                if (! $this->isValidRecipient($recipient, $user->account_id)) {
                    $validator->errors()->add(
                        "steps.{$index}.gate.recipients.{$recipientIndex}",
                        'Each recipient must be a valid email, phone number (E.164), team member ID, or variable placeholder.'
                    );
                }
            }

            // Validate integration IDs
            $integrationIds = $gate['integration_ids'] ?? [];
            foreach ($integrationIds as $intIndex => $integrationId) {
                // Skip variable placeholders
                if (str_contains($integrationId, '{{')) {
                    continue;
                }

                $connection = ChatConnection::where('id', $integrationId)
                    ->where('account_id', $user->account_id)
                    ->first();

                if (! $connection) {
                    $validator->errors()->add(
                        "steps.{$index}.gate.integration_ids.{$intIndex}",
                        'Invalid integration ID.'
                    );
                }
            }
        }
    }

    /**
     * Validate plan-based limits.
     */
    private function validatePlanLimits(\Illuminate\Validation\Validator $validator, \App\Models\User $user): void
    {
        // Check if plan supports chains (requires Pro or Business)
        $plan = $user->getPlan();
        if ($plan === 'free') {
            $validator->errors()->add('limit', 'Action chains require a Pro or Business plan.');

            return;
        }

        // Check monthly actions limit (each step counts as an action)
        $stepCount = count($this->input('steps', []));
        $maxActionsPerMonth = $user->getPlanLimit('max_actions_per_month');
        $counter = UsageCounter::forCurrentMonth($user->account_id);

        if ($counter->actions_created + $stepCount > $maxActionsPerMonth) {
            $remaining = max(0, $maxActionsPerMonth - $counter->actions_created);
            $validator->errors()->add(
                'limit',
                "This chain has {$stepCount} steps, but you only have {$remaining} actions remaining this month."
            );
        }
    }

    /**
     * Check if a recipient is valid (email, phone, user, contact, or channel URI).
     *
     * Supported formats:
     * - Plain email: user@example.com
     * - Plain phone (E.164): +15551234567
     * - Plain UUID: for backwards compatibility
     * - URI formats from unified selector:
     *   - email:user@example.com (manual email entry)
     *   - phone:+15551234567 (manual phone entry)
     *   - user:{id}:email or user:{id}:phone (workspace member)
     *   - contact:{uuid}:email or contact:{uuid}:phone (external contact)
     *   - channel:{uuid} (chat channel - validated separately)
     */
    private function isValidRecipient(string $recipient, ?string $accountId): bool
    {
        // Parse URI format
        if (str_contains($recipient, ':')) {
            $parts = explode(':', $recipient);
            $scheme = $parts[0];

            // email:user@example.com
            if ($scheme === 'email' && isset($parts[1])) {
                $email = implode(':', array_slice($parts, 1));
                return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
            }

            // phone:+15551234567
            if ($scheme === 'phone' && isset($parts[1])) {
                $phone = $parts[1];
                return preg_match('/^\+[1-9]\d{6,14}$/', $phone) === 1;
            }

            // user:{id}:email or user:{id}:phone
            if ($scheme === 'user' && isset($parts[1])) {
                $userId = $parts[1];
                if ($accountId) {
                    return \App\Models\User::where('id', $userId)
                        ->where('account_id', $accountId)
                        ->exists();
                }
                return false;
            }

            // contact:{uuid}:email or contact:{uuid}:phone
            if ($scheme === 'contact' && isset($parts[1])) {
                $contactId = $parts[1];
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $contactId) === 1 && $accountId) {
                    return Contact::where('id', $contactId)
                        ->where('account_id', $accountId)
                        ->exists();
                }
                return false;
            }

            // channel:{uuid} - validated separately via integration_ids
            if ($scheme === 'channel') {
                return true;
            }
        }

        // Legacy/plain formats for backwards compatibility

        // Email
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false) {
            return true;
        }

        // E.164 phone number
        if (preg_match('/^\+[1-9]\d{6,14}$/', $recipient) === 1) {
            return true;
        }

        // Contact UUID
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $recipient) === 1) {
            if ($accountId) {
                return Contact::where('id', $recipient)
                    ->where('account_id', $accountId)
                    ->exists();
            }
        }

        return false;
    }
}
