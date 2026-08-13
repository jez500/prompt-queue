<?php

namespace App\Http\Resources;

use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Prompt
 */
class PromptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->displayTitle,
            'rawTitle' => $this->title,
            'body' => $this->body,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'position' => $this->position,
            'projectId' => $this->project_id,
            'projectName' => $this->whenLoaded('project', fn () => $this->project?->name),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()->all(), []),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
