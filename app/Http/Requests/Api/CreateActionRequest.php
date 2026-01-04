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
            'team_id' => ['nullable', 'uuid', 'exists:teams,id'],
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
            'escalation_rules.recipients.*' => ['email'],
            'escalation_rules.token_expiry_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'escalation_rules.escalate_after_hours' => ['nullable', 'integer', 'min:1'],
            'escalation_rules.escalation_contacts' => ['nullable', 'array'],
            'escalation_rules.escalation_contacts.*' => ['email'],
            'max_snoozes' => ['nullable', 'integer', 'min:0', 'max:10'],
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
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
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

            // Check idempotency key uniqueness for user
            if ($this->filled('idempotency_key')) {
                $exists = ScheduledAction::query()
                    ->forUser($this->user()->id)
                    ->where('idempotency_key', $this->input('idempotency_key'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('idempotency_key', 'This idempotency key has already been used.');
                }
            }
        });
    }
}
