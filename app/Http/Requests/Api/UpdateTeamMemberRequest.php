<?php

namespace App\Http\Requests\Api;

use App\Models\TeamMember;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamMemberRequest extends FormRequest
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
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\+[1-9]\d{6,14}$/', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone number must be in E.164 format (e.g., +15551234567).',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\User $user */
            $user = $this->user();
            $accountId = $user->account_id;
            $teamMemberId = $this->route('teamMember')?->id ?? $this->route('teamMember');

            // Check that at least one contact method will remain after update
            $currentMember = TeamMember::find($teamMemberId);
            if ($currentMember) {
                $newEmail = $this->has('email') ? $this->input('email') : $currentMember->email;
                $newPhone = $this->has('phone') ? $this->input('phone') : $currentMember->phone;

                if (empty($newEmail) && empty($newPhone)) {
                    $validator->errors()->add('email', 'At least one contact method (email or phone) is required.');
                }
            }

            // Check email uniqueness within account (excluding current member)
            if ($this->filled('email')) {
                $exists = TeamMember::where('account_id', $accountId)
                    ->where('email', $this->input('email'))
                    ->where('id', '!=', $teamMemberId)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('email', 'A team member with this email already exists.');
                }
            }

            // Check phone uniqueness within account (excluding current member)
            if ($this->filled('phone')) {
                $exists = TeamMember::where('account_id', $accountId)
                    ->where('phone', $this->input('phone'))
                    ->where('id', '!=', $teamMemberId)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('phone', 'A team member with this phone number already exists.');
                }
            }
        });
    }
}
