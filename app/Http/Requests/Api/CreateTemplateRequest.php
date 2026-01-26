<?php

namespace App\Http\Requests\Api;

use App\Models\ActionTemplate;
use App\Models\ScheduledAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTemplateRequest extends FormRequest
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
        $mode = $this->input('mode', ScheduledAction::MODE_IMMEDIATE);

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'mode' => ['required', 'string', Rule::in([ScheduledAction::MODE_IMMEDIATE, ScheduledAction::MODE_GATED])],
            'timezone' => ['nullable', 'string', 'timezone:all'],

            // Request config (required for immediate, optional for gated)
            'request_config' => [$mode === ScheduledAction::MODE_IMMEDIATE ? 'required' : 'nullable', 'array'],
            'request_config.url' => ['required_with:request_config', 'string', 'max:2000'],
            'request_config.method' => ['nullable', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'request_config.headers' => ['nullable', 'array'],
            'request_config.body' => ['nullable'], // Can be array or string (for templates with numeric placeholders)
            'request_config.timeout' => ['nullable', 'integer', 'min:1', 'max:120'],

            // Gate config (required for gated mode)
            'gate_config' => ['required_if:mode,gated', 'array'],
            'gate_config.message' => ['required_with:gate_config', 'string', 'max:5000'],
            'gate_config.recipients' => ['nullable', 'array'],
            'gate_config.recipients.*' => ['string'],
            'gate_config.channels' => ['nullable', 'array'],
            'gate_config.channels.*' => ['string', Rule::in(['email', 'sms'])],
            'gate_config.timeout' => ['nullable', 'string', 'regex:/^\d+[hdw]$/'],
            'gate_config.on_timeout' => ['nullable', 'string', Rule::in(['cancel', 'expire', 'approve'])],
            'gate_config.max_snoozes' => ['nullable', 'integer', 'min:0', 'max:10'],
            'gate_config.confirmation_mode' => ['nullable', 'string', Rule::in(['first_response', 'all_required'])],
            'gate_config.escalation' => ['nullable', 'array'],
            'gate_config.escalation.after_hours' => ['nullable', 'numeric', 'min:0.5'],
            'gate_config.escalation.contacts' => ['nullable', 'array'],

            // Retry settings
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'retry_strategy' => ['nullable', 'string', Rule::in(['exponential', 'linear'])],

            // Coordination
            'coordination_config' => ['nullable', 'array'],
            'coordination_config.on_create' => ['nullable', 'string', Rule::in(['replace_existing', 'skip_if_exists'])],
            'default_coordination_keys' => ['nullable', 'array', 'max:10'],
            'default_coordination_keys.*' => ['string', 'max:255'], // Validated in withValidator to allow {{placeholders}}

            // Placeholders definition
            'placeholders' => ['nullable', 'array'],
            'placeholders.*.name' => ['required', 'string', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'placeholders.*.required' => ['nullable', 'boolean'],
            'placeholders.*.default' => ['nullable', 'string', 'max:1000'],
            'placeholders.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'request_config.required' => 'Request configuration is required for immediate mode templates.',
            'request_config.url.required_with' => 'A URL is required in the request configuration.',
            'gate_config.required_if' => 'Gate configuration is required for gated mode templates.',
            'gate_config.message.required_with' => 'A message is required in the gate configuration.',
            'gate_config.timeout.regex' => 'Timeout must be a number followed by h (hours), d (days), or w (weeks).',
            'placeholders.*.name.regex' => 'Placeholder names must start with a letter or underscore and contain only alphanumeric characters and underscores.',
            'default_coordination_keys.*.regex' => 'Coordination keys may only contain letters, numbers, underscores, colons, dots, dashes, and {{placeholders}}.',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();

            // Check template limit based on plan
            $maxTemplates = $user->getPlanLimit('max_templates');
            $currentCount = ActionTemplate::where('account_id', $user->account_id)->count();

            if ($currentCount >= $maxTemplates) {
                $validator->errors()->add(
                    'limit',
                    "You have reached your limit of {$maxTemplates} templates. Upgrade your plan to create more."
                );
            }

            // Validate URL format (allow placeholders)
            $url = $this->input('request_config.url');
            if ($url) {
                // Remove placeholders temporarily to validate URL structure
                $testUrl = preg_replace('/\{\{[^}]+\}\}/', 'placeholder', $url);
                if (! str_starts_with($testUrl, 'http://') && ! str_starts_with($testUrl, 'https://')) {
                    $validator->errors()->add('request_config.url', 'The URL must start with http:// or https://');
                }
            }

            // Validate body is either an array or a valid JSON string (with placeholders)
            $body = $this->input('request_config.body');
            if ($body !== null && ! is_array($body)) {
                if (! is_string($body)) {
                    $validator->errors()->add('request_config.body', 'Body must be a JSON object or a JSON string template.');
                } else {
                    // Replace placeholders with valid JSON values to validate structure
                    $testBody = preg_replace('/\{\{[^}]+\}\}/', '"__placeholder__"', $body);
                    $decoded = json_decode($testBody, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $validator->errors()->add('request_config.body', 'Body must be valid JSON (placeholders like {{name}} are allowed as values).');
                    } elseif (! is_array($decoded)) {
                        $validator->errors()->add('request_config.body', 'Body must be a JSON object.');
                    }
                }
            }

            // Validate placeholder names are unique
            $placeholders = $this->input('placeholders', []);
            $names = array_column($placeholders, 'name');
            if (count($names) !== count(array_unique($names))) {
                $validator->errors()->add('placeholders', 'Placeholder names must be unique.');
            }

            // Validate coordination keys (allow {{placeholders}})
            $coordinationKeys = $this->input('default_coordination_keys', []);
            foreach ($coordinationKeys as $index => $key) {
                // Remove placeholders and check remaining characters
                $keyWithoutPlaceholders = preg_replace('/\{\{[a-zA-Z_][a-zA-Z0-9_]*\}\}/', '', $key);
                if ($keyWithoutPlaceholders !== '' && ! preg_match('/^[a-zA-Z0-9_:.\-]+$/', $keyWithoutPlaceholders)) {
                    $validator->errors()->add(
                        "default_coordination_keys.{$index}",
                        'Coordination keys may only contain letters, numbers, underscores, colons, dots, dashes, and {{placeholders}}.'
                    );
                }
            }
        });
    }
}
