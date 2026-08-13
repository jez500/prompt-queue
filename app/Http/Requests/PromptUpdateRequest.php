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
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'max:65535'],
            'status' => ['nullable', Rule::enum(PromptStatus::class)],
            'priority' => ['nullable', Rule::enum(PromptPriority::class)],
            'project' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where('user_id', $this->user()->id),
            ],
            'tags' => ['sometimes', 'array'],
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
     * Whether the request asked for the prompt to be filed somewhere.
     *
     * Omitting `project` leaves the prompt where it is; sending it as null
     * moves it to the Inbox.
     */
    public function shouldMoveProject(): bool
    {
        return $this->has('project');
    }

    /**
     * The attributes to write, excluding the project and tags which need extra work.
     *
     * Every field is optional: only what the request actually carried is
     * written, so a save that changes one field cannot reset the others to a
     * stale copy the client happened to be holding.
     *
     * @return array<string, mixed>
     */
    public function fillableAttributes(): array
    {
        $attributes = [];

        if ($this->has('title')) {
            $attributes['title'] = $this->filled('title') ? $this->string('title')->toString() : null;
        }

        if ($this->has('body')) {
            $attributes['body'] = $this->string('body')->toString();
        }

        if ($this->filled('status')) {
            $attributes['status'] = PromptStatus::from($this->string('status')->toString());
        }

        if ($this->filled('priority')) {
            $attributes['priority'] = PromptPriority::from($this->string('priority')->toString());
        }

        return $attributes;
    }

    /**
     * Whether the request asked for the tag list to be rewritten.
     *
     * Omitting `tags` leaves the existing ones attached; sending an empty
     * array detaches them all.
     */
    public function shouldSyncTags(): bool
    {
        return $this->has('tags');
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
