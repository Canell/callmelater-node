<?php

namespace App\Http\Requests\Api;

use App\Models\ScheduledAction;
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::in([ScheduledAction::TYPE_HTTP, ScheduledAction::TYPE_REMINDER])],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],

            // Scheduling - either execute_at or intent
            'execute_at' => ['nullable', 'date', 'after:now'],
            'intent' => ['nullable', 'array'],
            'intent.preset' => ['nullable', 'string'],
            'intent.delay' => ['nullable', 'string'],
            'intent.at' => ['nullable', 'string'],
            'intent.on' => ['nullable', 'date'],

            // HTTP-specific
            'http_request' => ['required_if:type,http', 'array'],
            'http_request.url' => ['required_if:type,http', 'url:http,https'],
            'http_request.method' => ['nullable', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'http_request.headers' => ['nullable', 'array'],
            'http_request.body' => ['nullable', 'array'],
            'http_request.timeout' => ['nullable', 'integer', 'min:1', 'max:120'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'retry_strategy' => ['nullable', 'string', Rule::in(['exponential', 'linear'])],
            'webhook_secret' => ['nullable', 'string', 'max:255'],

            // Reminder-specific
            'message' => ['required_if:type,reminder', 'string', 'max:5000'],
            'confirmation_mode' => ['nullable', 'string', Rule::in([
                ScheduledAction::CONFIRMATION_FIRST_RESPONSE,
                ScheduledAction::CONFIRMATION_ALL_REQUIRED,
            ])],
            'escalation_rules' => ['nullable', 'array'],
            'escalation_rules.recipients' => ['required_if:type,reminder', 'array', 'min:1'],
            'escalation_rules.recipients.*' => ['required', 'string', 'regex:/^([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}|\+[1-9]\d{6,14})$/'],
            'escalation_rules.channels' => ['nullable', 'array'],
            'escalation_rules.channels.*' => ['string', Rule::in(['email', 'sms'])],
            'escalation_rules.token_expiry_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'escalation_rules.escalate_after_hours' => ['nullable', 'integer', 'min:1'],
            'escalation_rules.escalation_contacts' => ['nullable', 'array'],
            'escalation_rules.escalation_contacts.*' => ['email'],
            'max_snoozes' => ['nullable', 'integer', 'min:0', 'max:10'],
            'callback_url' => ['nullable', 'url:http,https'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'execute_at.after' => 'The execution time must be in the future.',
            'http_request.url.url' => 'The URL must be a valid HTTP or HTTPS URL.',
            'escalation_rules.recipients.required_if' => 'At least one recipient is required for reminders.',
            'escalation_rules.recipients.*.regex' => 'Each recipient must be a valid email address or phone number (E.164 format, e.g. +15551234567).',
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

        // Check recipients limit for reminders
        if ($this->input('type') === ScheduledAction::TYPE_REMINDER) {
            $recipients = $this->input('escalation_rules.recipients', []);
            $maxRecipients = $user->getPlanLimit('max_recipients');

            if (count($recipients) > $maxRecipients) {
                $validator->errors()->add('escalation_rules.recipients', "Your plan allows up to {$maxRecipients} recipients per reminder.");
            }

            // Check SMS channel requires paid plan
            $channels = $this->input('escalation_rules.channels', []);
            if (in_array('sms', $channels) && $user->getPlan() === 'free') {
                $validator->errors()->add('escalation_rules.channels', 'SMS reminders require a Pro or Business plan.');
            }
        }

        // Check max retries limit for HTTP actions
        if ($this->input('type') === ScheduledAction::TYPE_HTTP && $this->filled('max_attempts')) {
            $maxRetries = $user->getPlanLimit('max_retries');
            $requestedAttempts = (int) $this->input('max_attempts');

            if ($requestedAttempts > $maxRetries) {
                $validator->errors()->add('max_attempts', "Your plan allows up to {$maxRetries} retry attempts.");
            }
        }
    }
}
