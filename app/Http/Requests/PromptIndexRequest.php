<?php

namespace App\Http\Requests;

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromptIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project' => ['nullable', 'string', 'regex:/^(inbox|[1-9][0-9]*)$/'],
            'prompt' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(PromptStatus::class)],
            'priority' => ['nullable', 'array'],
            'priority.*' => [Rule::enum(PromptPriority::class)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * Whether a single ordering bucket is selected.
     */
    public function hasBucket(): bool
    {
        return $this->filled('project');
    }

    /**
     * The selected bucket. Null means the Inbox, and is only meaningful when hasBucket() is true.
     */
    public function bucketProjectId(): ?int
    {
        $project = $this->string('project')->toString();

        return $project === 'inbox' ? null : (int) $project;
    }

    /**
     * The prompt the editor should open, if the URL names one.
     */
    public function selectedPromptId(): ?int
    {
        return $this->filled('prompt') ? $this->integer('prompt') : null;
    }

    /**
     * The free-text search term, if any.
     */
    public function searchTerm(): ?string
    {
        return $this->filled('q') ? $this->string('q')->toString() : null;
    }

    /**
     * The statuses to show, defaulting to everything unfinished.
     *
     * @return array<int, PromptStatus>
     */
    public function statuses(): array
    {
        if (! $this->filled('status')) {
            return PromptStatus::open();
        }

        return array_map(PromptStatus::from(...), $this->array('status'));
    }

    /**
     * The priorities to show. An empty array means all of them.
     *
     * @return array<int, PromptPriority>
     */
    public function priorities(): array
    {
        return array_map(PromptPriority::from(...), $this->array('priority'));
    }

    /**
     * The tag names that must all be present. An empty array means no tag filter.
     *
     * @return array<int, string>
     */
    public function tagNames(): array
    {
        return array_values(array_filter($this->array('tags')));
    }

    /**
     * Dragging is only unambiguous in a single, wholly unfiltered bucket.
     */
    public function canReorder(): bool
    {
        return $this->hasBucket()
            && $this->searchTerm() === null
            && ! $this->filled('status')
            && $this->priorities() === []
            && $this->tagNames() === [];
    }
}
