<?php

namespace App\Http\Requests\Api;

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
        // Default mode to 'immediate' if not provided
        if (! $this->has('mode')) {
            $this->merge(['mode' => ScheduledAction::MODE_IMMEDIATE]);
        }

        // Auto-generate name if not provided
        if (! $this->filled('name')) {
            $mode = $this->input('mode', ScheduledAction::MODE_IMMEDIATE);
            $this->merge(['name' => $mode === ScheduledAction::MODE_IMMEDIATE ? 'HTTP Action' : 'Gated Action']);
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
            'gate.recipients' => ['required_with:gate', 'array', 'min:1'],
            'gate.recipients.*' => ['required', 'string', 'regex:/^([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}|\+[1-9]\d{6,14})$/'],
            'gate.channels' => ['nullable', 'array'],
            'gate.channels.*' => ['string', Rule::in(['email', 'sms'])],
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
            'gate.recipients.*.regex' => 'Each recipient must be a valid email address or phone number (E.164 format, e.g. +15551234567).',
            'gate.timeout.regex' => 'Timeout must be a number followed by h (hours), d (days), or w (weeks). Example: 4h, 7d, 1w',
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

            // Validate timezone if provided
            if ($this->filled('timezone')) {
                $resolver = app(IntentResolver::class);
                if (! $resolver->isValidTimezone($this->input('timezone'))) {
                    $validator->errors()->add('timezone', 'Invalid timezone.');
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
}
