<?php

namespace App\Http\Requests;

use App\Enums\PromptStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PromptStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('prompt'));

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(PromptStatus::class)],
        ];
    }

    /**
     * The requested status, or null when the request should use copy advancement.
     */
    public function status(): ?PromptStatus
    {
        if (! $this->filled('status')) {
            return null;
        }

        return PromptStatus::from($this->string('status')->toString());
    }
}
