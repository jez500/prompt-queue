<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ProjectReorderRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * Reject anything that is not exactly the user's own set of projects.
     *
     * Accepting a partial list and silently skipping the rest would leave the
     * sidebar showing an order the database does not have.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ownedIds = $this->user()->projects()
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->all();

                $submittedIds = $this->projectIds();

                sort($ownedIds);
                sort($submittedIds);

                if ($ownedIds !== $submittedIds) {
                    $validator->errors()->add('ids', __('One or more projects do not belong to you.'));
                }
            },
        ];
    }

    /**
     * The project ids in their new order.
     *
     * @return array<int, int>
     */
    public function projectIds(): array
    {
        return array_map(intval(...), array_values($this->array('ids')));
    }
}
