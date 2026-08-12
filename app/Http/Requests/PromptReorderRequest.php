<?php

namespace App\Http\Requests;

use App\Models\Prompt;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PromptReorderRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project' => ['present', 'nullable', 'integer'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * Reject any id the user does not own or that is not already in this bucket.
     *
     * A silent skip would leave the UI showing an order the database does not have.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ids = $this->promptIds();

                $valid = Prompt::query()
                    ->whereBelongsTo($this->user())
                    ->inBucket($this->bucketProjectId())
                    ->whereIn('id', $ids)
                    ->count();

                if ($valid !== count($ids)) {
                    $validator->errors()->add('ids', __('One or more prompts do not belong to this list.'));
                }
            },
        ];
    }

    /**
     * The bucket being reordered. Null means the Inbox.
     */
    public function bucketProjectId(): ?int
    {
        return $this->filled('project') ? $this->integer('project') : null;
    }

    /**
     * The prompt ids in their new order.
     *
     * @return array<int, int>
     */
    public function promptIds(): array
    {
        return array_map(intval(...), array_values($this->array('ids')));
    }
}
