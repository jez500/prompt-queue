<?php

namespace App\Http\Resources;

use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Prompt
 */
class PromptResource extends JsonResource
{
    /**
     * The length of the one-line preview shown in the list.
     */
    private const EXCERPT_LENGTH = 120;

    /**
     * Whether the full body should be included.
     */
    private bool $withBody = false;

    /**
     * Include the body, for the one prompt the editor has open.
     *
     * The list deliberately does not carry it: a body can run to 64KB and the
     * card renders a single line of it, so sending every one would put the
     * whole queue on the wire for every keystroke that triggers a save.
     */
    public function withBody(): self
    {
        $this->withBody = true;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->displayTitle,
            'rawTitle' => $this->title,
            'excerpt' => $this->excerpt(),
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'position' => $this->position,
            'projectId' => $this->project_id,
            'projectName' => $this->whenLoaded('project', fn () => $this->project?->name),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()->all(), []),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->withBody) {
            $data['body'] = $this->body;
        }

        return $data;
    }

    /**
     * The preview line shown under the title on a card.
     *
     * Normally the first line of the body. When the title is that same line —
     * because it was derived from it, or because there is no title at all and
     * the card falls back to it — the preview skips ahead to the first line
     * that says something new, rather than printing the title twice.
     */
    private function excerpt(): string
    {
        $body = $this->body;

        if ($this->titleRepeatsBody()) {
            /* Str::after hands back the whole string when the needle is
               absent, which would print the one line the title already is. */
            $body = Str::contains($body, "\n") ? Str::after($body, "\n") : '';
        }

        $line = collect(preg_split('/\R/u', $body) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->first(fn (string $line): bool => $line !== '');

        return Str::limit((string) $line, self::EXCERPT_LENGTH);
    }
}
