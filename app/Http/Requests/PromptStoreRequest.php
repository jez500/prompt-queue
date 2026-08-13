<?php

namespace App\Http\Requests;

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromptStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:65535'],
            'status' => ['nullable', Rule::enum(PromptStatus::class)],
            'priority' => ['nullable', Rule::enum(PromptPriority::class)],
            'project' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }

    /**
     * The ordering bucket this prompt belongs to. Null means the Inbox.
     */
    public function bucketProjectId(): ?int
    {
        return $this->filled('project') ? $this->integer('project') : null;
    }

    /**
     * The title to capture with, if one was typed before the first save.
     */
    public function title(): ?string
    {
        return $this->filled('title') ? $this->string('title')->toString() : null;
    }

    /**
     * The requested initial status, defaulting to Todo.
     */
    public function status(): PromptStatus
    {
        if (! $this->filled('status')) {
            return PromptStatus::Todo;
        }

        return PromptStatus::from($this->string('status')->toString());
    }

    /**
     * The requested initial priority, defaulting to Normal.
     */
    public function priority(): PromptPriority
    {
        if (! $this->filled('priority')) {
            return PromptPriority::Normal;
        }

        return PromptPriority::from($this->string('priority')->toString());
    }
}
