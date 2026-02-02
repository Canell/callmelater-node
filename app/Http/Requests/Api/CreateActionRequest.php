<?php

namespace App\Http\Requests\Api;

use App\Models\ChatConnection;
use App\Models\Contact;
use App\Models\ScheduledAction;
use App\Models\UsageCounter;
use App\Services\IntentResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // =====================================================
        // Terminology aliasing: Accept both old and new terms
        // =====================================================

        // Map type/mode new terms -> old terms
        if ($this->has('type') && ! $this->has('mode')) {
            $typeMap = [
                'approval' => ScheduledAction::MODE_GATED,
                'webhook' => ScheduledAction::MODE_IMMEDIATE,
            ];
            $type = $this->input('type');
            if (isset($typeMap[$type])) {
                $this->merge(['mode' => $typeMap[$type]]);
            } else {
                // Pass through if not a mapped value (e.g., 'immediate', 'gated')
                $this->merge(['mode' => $type]);
            }
        }

        // Map dedup_keys -> coordination_keys
        if ($this->has('dedup_keys') && ! $this->has('coordination_keys')) {
            $this->merge(['coordination_keys' => $this->input('dedup_keys')]);
        }

        // Map schedule -> intent
        if ($this->has('schedule') && ! $this->has('intent')) {
            $schedule = $this->input('schedule');
            // Map wait -> delay within schedule
            if (is_array($schedule) && isset($schedule['wait']) && ! isset($schedule['delay'])) {
                $schedule['delay'] = $schedule['wait'];
                unset($schedule['wait']);
            }
            $this->merge(['intent' => $schedule]);
        }

        // Also map wait -> delay if intent is provided directly with wait
        if ($this->has('intent')) {
            $intent = $this->input('intent');
            if (is_array($intent) && isset($intent['wait']) && ! isset($intent['delay'])) {
                $intent['delay'] = $intent['wait'];
                unset($intent['wait']);
                $this->merge(['intent' => $intent]);
            }
        }

        // Map scheduled_for -> execute_at
        if ($this->has('scheduled_for') && ! $this->has('execute_at')) {
            $this->merge(['execute_at' => $this->input('scheduled_for')]);
        }

        // =====================================================
        // Original transformations
        // =====================================================

        // Default mode to 'immediate' if not provided
        if (! $this->has('mode')) {
            $this->merge(['mode' => ScheduledAction::MODE_IMMEDIATE]);
        }

        // Auto-generate name if not provided
        if (! $this->filled('name')) {
            $mode = $this->input('mode', ScheduledAction::MODE_IMMEDIATE);
            $this->merge(['name' => $mode === ScheduledAction::MODE_IMMEDIATE ? 'Webhook Action' : 'Approval Action']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $mode = $this->input('mode', ScheduledAction::MODE_IMMEDIATE);

        return [
            // Common fields
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'mode' => ['nullable', 'string', Rule::in([ScheduledAction::MODE_IMMEDIATE, ScheduledAction::MODE_GATED])],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'callback_url' => ['nullable', 'url:http,https'],

            // Coordination keys (for grouping/filtering related actions)
            'coordination_keys' => ['nullable', 'array', 'max:10'],
            'coordination_keys.*' => ['string', 'max:255', 'regex:/^[a-zA-Z0-9_:.\-]+$/'],

            // Coordination behavior on create
            'coordination' => ['nullable', 'array'],
            'coordination.on_create' => ['nullable', 'string', Rule::in(['replace_existing', 'skip_if_exists'])],

            // Coordination behavior on execute
            'coordination.on_execute' => ['nullable', 'array'],
            'coordination.on_execute.condition' => [
                'nullable', 'string',
                Rule::in(['skip_if_previous_pending', 'execute_if_previous_failed', 'execute_if_previous_succeeded', 'wait_for_previous']),
            ],
            'coordination.on_execute.on_condition_not_met' => ['nullable', 'string', Rule::in(['cancel', 'reschedule', 'fail'])],
            'coordination.on_execute.reschedule_delay' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'coordination.on_execute.max_reschedules' => ['nullable', 'integer', 'min:1', 'max:100'],

            // Scheduling - either execute_at or intent
            'execute_at' => ['nullable', 'date', 'after:now'],
            'intent' => ['nullable', 'array'],
            'intent.preset' => ['nullable', 'string'],
            'intent.delay' => ['nullable', 'string'],
            'intent.at' => ['nullable', 'string'],
            'intent.on' => ['nullable', 'date'],

            // Request block (required for immediate, optional for gated)
            'request' => [$mode === ScheduledAction::MODE_IMMEDIATE ? 'required' : 'nullable', 'array'],
            'request.url' => ['required_with:request', 'url:http,https'],
            'request.method' => ['nullable', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'request.headers' => ['nullable', 'array'],
            'request.body' => ['nullable', 'array'],
            'request.timeout' => ['nullable', 'integer', 'min:1', 'max:120'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'retry_strategy' => ['nullable', 'string', Rule::in(['exponential', 'linear'])],
            'webhook_secret' => ['nullable', 'string', 'max:255'],

            // Gate block (required for gated mode)
            'gate' => ['required_if:mode,gated', 'array'],
            'gate.message' => ['required_with:gate', 'string', 'max:5000'],
            'gate.recipients' => ['nullable', 'array'],
            'gate.recipients.*' => ['required', 'string'],
            'gate.channels' => ['nullable', 'array'],
            'gate.channels.*' => ['string', Rule::in(['email', 'sms', 'teams', 'slack'])],
            'gate.timeout' => ['nullable', 'string', 'regex:/^\d+[hdw]$/'],
            'gate.on_timeout' => ['nullable', 'string', Rule::in(['cancel', 'expire', 'approve'])],
            'gate.max_snoozes' => ['nullable', 'integer', 'min:0', 'max:10'],
            'gate.confirmation_mode' => ['nullable', 'string', Rule::in([
                ScheduledAction::CONFIRMATION_FIRST_RESPONSE,
                ScheduledAction::CONFIRMATION_ALL_REQUIRED,
            ])],
            'gate.escalation' => ['nullable', 'array'],
            'gate.escalation.after_hours' => ['nullable', 'numeric', 'min:0.5'],
            'gate.escalation.contacts' => ['nullable', 'array'],
            'gate.escalation.contacts.*' => ['email'],

            // Integration IDs (specific Teams/Slack connections to use)
            'gate.integration_ids' => ['nullable', 'array'],
            'gate.integration_ids.*' => ['string', 'uuid'],

            // Creator notification
            'notify_creator_on_response' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'execute_at.after' => 'The execution time must be in the future.',
            'request.url.url' => 'The URL must be a valid HTTP or HTTPS URL.',
            'request.required' => 'A request configuration is required for immediate actions.',
            'gate.required_if' => 'Gate configuration is required for gated actions.',
            'gate.message.required_with' => 'A message is required for the gate.',
            'gate.recipients.required_with' => 'At least one recipient is required for the gate.',
            'gate.timeout.regex' => 'Timeout must be a number followed by h (hours), d (days), or w (weeks). Example: 4h, 7d, 1w',
            'coordination_keys.max' => 'You can specify up to 10 coordination keys per action.',
            'coordination_keys.*.regex' => 'Coordination keys may only contain letters, numbers, underscores, colons, dots, and dashes.',
            'coordination.on_create.in' => 'The on_create value must be one of: replace_existing, skip_if_exists.',
            'coordination.on_execute.condition.in' => 'The on_execute condition must be one of: skip_if_previous_pending, execute_if_previous_failed, execute_if_previous_succeeded, wait_for_previous.',
            'coordination.on_execute.on_condition_not_met.in' => 'The on_condition_not_met value must be one of: cancel, reschedule, fail.',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\User $user */
            $user = $this->user();

            // Ensure either execute_at or intent is provided
            if (! $this->filled('execute_at') && ! $this->filled('intent')) {
                $validator->errors()->add('execute_at', 'Either execute_at or intent must be provided.');
            }

            // Coordination on_create requires at least one coordination_key
            if ($this->filled('coordination.on_create') && ! $this->filled('coordination_keys')) {
                $validator->errors()->add('coordination_keys', 'At least one coordination_key is required when using coordination.on_create.');
            }

            // Coordination on_execute requires at least one coordination_key
            if ($this->filled('coordination.on_execute.condition') && ! $this->filled('coordination_keys')) {
                $validator->errors()->add('coordination_keys', 'At least one coordination_key is required when using coordination.on_execute.');
            }

            // Validate timezone if provided
            if ($this->filled('timezone')) {
                $resolver = app(IntentResolver::class);
                if (! $resolver->isValidTimezone($this->input('timezone'))) {
                    $validator->errors()->add('timezone', 'Invalid timezone.');
                }
            }

            // Validate gate recipients (email, phone, or team member UUID)
            $recipients = $this->input('gate.recipients', []);
            $channels = $this->input('gate.channels', []);
            $hasChatChannel = ! empty(array_intersect($channels, ['teams', 'slack']));

            // Must have either recipients OR a chat channel
            if ($this->input('mode') === ScheduledAction::MODE_GATED) {
                if (empty($recipients) && ! $hasChatChannel) {
                    $validator->errors()->add(
                        'gate.recipients',
                        'At least one recipient is required, or select Teams/Slack as a notification channel.'
                    );
                }
            }

            foreach ($recipients as $index => $recipient) {
                if (! $this->isValidRecipient($recipient, $user->account_id)) {
                    $validator->errors()->add(
                        "gate.recipients.{$index}",
                        'Each recipient must be a valid email address, phone number (E.164 format, e.g. +15551234567), or team member ID.'
                    );
                }
            }

            // Check idempotency key uniqueness for account
            if ($this->filled('idempotency_key')) {
                $exists = ScheduledAction::query()
                    ->forAccount($user->account_id)
                    ->where('idempotency_key', $this->input('idempotency_key'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('idempotency_key', 'This idempotency key has already been used.');
                }
            }

            // Plan-based limits
            $this->validatePlanLimits($validator, $user);
        });
    }

    /**
     * Validate plan-based limits.
     */
    private function validatePlanLimits(\Illuminate\Validation\Validator $validator, \App\Models\User $user): void
    {
        // Check monthly actions limit
        $maxActionsPerMonth = $user->getPlanLimit('max_actions_per_month');
        $counter = UsageCounter::forCurrentMonth($user->account_id);

        if ($counter->actions_created >= $maxActionsPerMonth) {
            $validator->errors()->add(
                'limit',
                "You have reached your monthly limit of {$maxActionsPerMonth} actions. Upgrade your plan to create more actions."
            );
        }

        // Check pending actions limit (per account)
        $maxPending = $user->getPlanLimit('max_pending_actions');
        $currentPending = ScheduledAction::query()
            ->forAccount($user->account_id)
            ->whereIn('resolution_status', [
                ScheduledAction::STATUS_PENDING_RESOLUTION,
                ScheduledAction::STATUS_RESOLVED,
                ScheduledAction::STATUS_AWAITING_RESPONSE,
            ])
            ->count();

        if ($currentPending >= $maxPending) {
            $validator->errors()->add('limit', "You have reached the maximum of {$maxPending} pending actions for your plan.");
        }

        // Check schedule date limit
        if ($this->filled('execute_at')) {
            $maxDays = $user->getPlanLimit('max_schedule_days');
            $executeAt = new \DateTime($this->input('execute_at'));
            $maxDate = new \DateTime("+{$maxDays} days");

            if ($executeAt > $maxDate) {
                $validator->errors()->add('execute_at', "Your plan allows scheduling up to {$maxDays} days in advance.");
            }
        }

        // Check gated action limits
        if ($this->input('mode') === ScheduledAction::MODE_GATED) {
            $recipients = $this->input('gate.recipients', []);
            $maxRecipients = $user->getPlanLimit('max_recipients');

            if (count($recipients) > $maxRecipients) {
                $validator->errors()->add('gate.recipients', "Your plan allows up to {$maxRecipients} recipients per gated action.");
            }

            // Check SMS channel requires paid plan and has remaining quota
            $channels = $this->input('gate.channels', []);
            if (in_array('sms', $channels)) {
                if ($user->getPlan() === 'free') {
                    $validator->errors()->add('gate.channels', 'SMS notifications require a Pro or Business plan.');
                } else {
                    // Check SMS monthly quota
                    $smsLimit = $user->getPlanLimit('sms_per_month');
                    $currentUsage = $user->account?->getSmsUsageThisMonth() ?? 0;

                    // Count phone numbers in the new recipients
                    $newSmsCount = 0;
                    foreach ($recipients as $recipient) {
                        if ($this->isPhoneNumber($recipient)) {
                            $newSmsCount++;
                        }
                    }

                    if ($currentUsage + $newSmsCount > $smsLimit) {
                        $remaining = max(0, $smsLimit - $currentUsage);
                        $validator->errors()->add(
                            'gate.channels',
                            "You have {$remaining} SMS remaining this month (limit: {$smsLimit}). This action requires {$newSmsCount} SMS."
                        );
                    }
                }
            }

            // Validate integration_ids if provided
            $integrationIds = $this->input('gate.integration_ids', []);
            if (! empty($integrationIds)) {
                // Check plan allows chat integrations
                if (! $user->getPlanLimit('chat_integrations')) {
                    $validator->errors()->add('gate.integration_ids', 'Teams and Slack notifications require a Pro or Business plan.');
                } else {
                    // Validate each integration ID exists and belongs to the account
                    foreach ($integrationIds as $index => $integrationId) {
                        $connection = ChatConnection::where('id', $integrationId)
                            ->where('account_id', $user->account_id)
                            ->first();

                        if (! $connection) {
                            $validator->errors()->add(
                                "gate.integration_ids.{$index}",
                                'Invalid integration ID or integration does not belong to your account.'
                            );
                        } elseif (! $connection->is_active) {
                            $validator->errors()->add(
                                "gate.integration_ids.{$index}",
                                "Integration '{$connection->name}' is disabled. Please enable it in Settings first."
                            );
                        }
                    }
                }
            }

            // Check chat channels (Teams/Slack) require Pro/Business plan and active connection
            // Only validate channels if integration_ids is not provided (legacy support)
            $chatChannels = array_intersect($channels, ['teams', 'slack']);
            if (! empty($chatChannels) && empty($integrationIds)) {
                // Check plan allows chat integrations
                if (! $user->getPlanLimit('chat_integrations')) {
                    $validator->errors()->add('gate.channels', 'Teams and Slack notifications require a Pro or Business plan.');
                } else {
                    // Check for active connection for each chat channel
                    foreach ($chatChannels as $chatChannel) {
                        $hasConnection = ChatConnection::where('account_id', $user->account_id)
                            ->where('provider', $chatChannel)
                            ->where('is_active', true)
                            ->exists();

                        if (! $hasConnection) {
                            $channelName = ucfirst($chatChannel);
                            $validator->errors()->add(
                                'gate.channels',
                                "No active {$channelName} connection found. Please connect {$channelName} in Settings first."
                            );
                        }
                    }
                }
            }
        }

        // Check max retries limit for actions with request
        if ($this->filled('request') && $this->filled('max_attempts')) {
            $maxRetries = $user->getPlanLimit('max_retries');
            $requestedAttempts = (int) $this->input('max_attempts');

            if ($requestedAttempts > $maxRetries) {
                $validator->errors()->add('max_attempts', "Your plan allows up to {$maxRetries} retry attempts.");
            }
        }
    }

    /**
     * Check if a value is a phone number.
     */
    private function isPhoneNumber(string $value): bool
    {
        // Simple check for phone numbers (starts with + or contains only digits, spaces, dashes)
        return preg_match('/^\+?[\d\s\-\(\)]+$/', $value) === 1 && strlen(preg_replace('/\D/', '', $value)) >= 10;
    }

    /**
     * Check if a value is a valid UUID.
     */
    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    /**
     * Check if a recipient is valid (email, phone, or team member UUID).
     */
    private function isValidRecipient(string $recipient, ?string $accountId): bool
    {
        // Check if it's a valid email
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false) {
            return true;
        }

        // Check if it's a valid E.164 phone number
        if (preg_match('/^\+[1-9]\d{6,14}$/', $recipient) === 1) {
            return true;
        }

        // Check if it's a valid UUID that exists as a contact
        if ($this->isUuid($recipient) && $accountId) {
            return Contact::where('id', $recipient)
                ->where('account_id', $accountId)
                ->exists();
        }

        return false;
    }
}
