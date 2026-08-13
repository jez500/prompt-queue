<?php

namespace App\Http\Requests;

use App\Enums\PromptPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PromptPriorityRequest extends FormRequest
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
            'priority' => ['required', Rule::enum(PromptPriority::class)],
        ];
    }

    /**
     * The requested priority.
     */
    public function priority(): PromptPriority
    {
        return PromptPriority::from($this->string('priority')->toString());
    }
}
