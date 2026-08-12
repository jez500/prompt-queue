<?php

namespace App\Models;

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use Database\Factories\PromptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $project_id
 * @property string|null $title
 * @property string $body
 * @property PromptStatus $status
 * @property PromptPriority $priority
 * @property int $position
 * @property-read string $displayTitle
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'title', 'body', 'status', 'priority', 'position'])]
class Prompt extends Model
{
    /** @use HasFactory<PromptFactory> */
    use HasFactory;

    /**
     * The maximum length of a title derived from the body.
     */
    private const DERIVED_TITLE_LENGTH = 80;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PromptStatus::class,
            'priority' => PromptPriority::class,
            'position' => 'integer',
        ];
    }

    /**
     * The title to show in lists, falling back to the first line of the body.
     *
     * @return Attribute<string, never>
     */
    protected function displayTitle(): Attribute
    {
        return Attribute::get(function (): string {
            if (filled($this->title)) {
                return $this->title;
            }

            return Str::limit(trim(Str::before($this->body, "\n")), self::DERIVED_TITLE_LENGTH, '');
        });
    }

    /**
     * The user who owns the prompt.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The project the prompt is filed under, if any.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The tags attached to the prompt.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Limit the query to one ordering bucket. A null project id means the Inbox.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inBucket(Builder $query, ?int $projectId): void
    {
        if ($projectId === null) {
            $query->whereNull('project_id');

            return;
        }

        $query->where('project_id', $projectId);
    }

    /**
     * Limit the query to prompts whose title or body contains the term.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $escaped = str_replace(['%', '_'], ['\%', '\_'], $term);

        $query->where(function (Builder $query) use ($escaped): void {
            $query->where('title', 'like', "%{$escaped}%")
                ->orWhere('body', 'like', "%{$escaped}%");
        });
    }

    /**
     * Limit the query to the given statuses.
     *
     * @param  Builder<self>  $query
     * @param  array<int, PromptStatus>  $statuses
     */
    #[Scope]
    protected function withStatuses(Builder $query, array $statuses): void
    {
        if ($statuses === []) {
            return;
        }

        $query->whereIn('status', array_map(fn (PromptStatus $status): string => $status->value, $statuses));
    }

    /**
     * Limit the query to the given priorities.
     *
     * @param  Builder<self>  $query
     * @param  array<int, PromptPriority>  $priorities
     */
    #[Scope]
    protected function withPriorities(Builder $query, array $priorities): void
    {
        if ($priorities === []) {
            return;
        }

        $query->whereIn('priority', array_map(fn (PromptPriority $priority): string => $priority->value, $priorities));
    }

    /**
     * Limit the query to prompts carrying every one of the given tag names.
     *
     * @param  Builder<self>  $query
     * @param  array<int, string>  $names
     */
    #[Scope]
    protected function withTagNames(Builder $query, array $names): void
    {
        foreach ($names as $name) {
            $query->whereHas('tags', fn (Builder $query) => $query->where('name', $name));
        }
    }
}
