<?php

namespace App\Http\Requests;

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromptUpdateRequest extends FormRequest
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
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * The project the prompt should end up in. Null means the Inbox.
     */
    public function bucketProjectId(): ?int
    {
        return $this->filled('project') ? $this->integer('project') : null;
    }

    /**
     * The attributes to write, excluding the project and tags which need extra work.
     *
     * Status and priority are only included when supplied, so a partial save
     * from the slide-over cannot silently reset them.
     *
     * @return array<string, mixed>
     */
    public function fillableAttributes(): array
    {
        $attributes = [
            'title' => $this->filled('title') ? $this->string('title')->toString() : null,
            'body' => $this->string('body')->toString(),
        ];

        if ($this->filled('status')) {
            $attributes['status'] = PromptStatus::from($this->string('status')->toString());
        }

        if ($this->filled('priority')) {
            $attributes['priority'] = PromptPriority::from($this->string('priority')->toString());
        }

        return $attributes;
    }

    /**
     * The tag names to sync.
     *
     * @return array<int, string>
     */
    public function tagNames(): array
    {
        return array_values(array_unique(array_filter(
            array_map(trim(...), $this->array('tags'))
        )));
    }
}
