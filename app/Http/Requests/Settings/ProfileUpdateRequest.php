<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules($this->user()->id);
    }

    /**
     * Canonicalise the email before it is validated or stored.
     *
     * Fortify lowercases the submitted username when authenticating
     * (`fortify.lowercase_usernames`), so an address stored with capitals here
     * would never match at login again. Normalising also stops the unique rule
     * from being sidestepped by changing the case of an address already taken.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => Str::lower($this->string('email')->toString()),
            ]);
        }
    }
}
