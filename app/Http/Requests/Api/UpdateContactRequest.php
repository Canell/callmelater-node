<?php

namespace App\Http\Requests\Api;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
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
            $routeParam = $this->route('contact');
            $contactId = $routeParam instanceof Contact ? $routeParam->id : $routeParam;

            // Check that at least one contact method will remain after update
            $currentContact = Contact::find($contactId);
            if ($currentContact) {
                $newEmail = $this->has('email') ? $this->input('email') : $currentContact->email;
                $newPhone = $this->has('phone') ? $this->input('phone') : $currentContact->phone;

                if (empty($newEmail) && empty($newPhone)) {
                    $validator->errors()->add('email', 'At least one contact method (email or phone) is required.');
                }
            }

            // Check email uniqueness within account (excluding current contact)
            if ($this->filled('email')) {
                $exists = Contact::where('account_id', $accountId)
                    ->where('email', $this->input('email'))
                    ->where('id', '!=', $contactId)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('email', 'A contact with this email already exists.');
                }
            }

            // Check phone uniqueness within account (excluding current contact)
            if ($this->filled('phone')) {
                $exists = Contact::where('account_id', $accountId)
                    ->where('phone', $this->input('phone'))
                    ->where('id', '!=', $contactId)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('phone', 'A contact with this phone number already exists.');
                }
            }
        });
    }
}
