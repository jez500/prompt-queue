# Prompt Queue Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A private, low-friction holding pen for prompts — capture with one keystroke, group by project, find again by search, status, priority or tag, and copy out to the clipboard.

**Architecture:** A single Inertia page (`prompts/Index`) backed by four small Laravel controllers. Filter state lives in the URL query string and drives Inertia partial reloads. Prompts are ordered by an integer `position` scoped to `(user_id, project_id)`; the reorder endpoint rewrites the whole bucket in a transaction. Everything is scoped to the authenticated user and enforced by policies that deny as 404.

**Tech Stack:** Laravel 13 / PHP 8.3, SQLite, Inertia v3, Vue 3 + TypeScript, Tailwind v4, reka-ui (shadcn-vue), Wayfinder, Pest 4.

**Spec:** `docs/superpowers/specs/2026-08-12-prompt-queue-design.md`

## Global Constraints

- PHP 8.3, Laravel 13. Use `php artisan make:*` with `--no-interaction` to create files.
- **Only one new dependency is approved:** `sortablejs` + `vuedraggable@next` (Vue 3 build, v4). Do not add any other package. `pest-plugin-browser` was explicitly declined — write no browser tests.
- Models use PHP attribute config (`#[Fillable([...])]` from `Illuminate\Database\Eloquent\Attributes\Fillable`), matching `app/Models/User.php`.
- Local query scopes use the `#[Scope]` attribute (`Illuminate\Database\Eloquent\Attributes\Scope`) on `protected` methods — not the `scopeXxx` prefix.
- Enum cases are `TitleCase`: `PromptStatus::Todo`, not `PromptStatus::TODO`.
- Explicit return types and parameter type hints on every method. PHPDoc array shapes where arrays are passed.
- Curly braces on all control structures, even single-line bodies.
- Inertia pages set their layout via `defineOptions({ layout: { breadcrumbs: [...] } })`, matching `resources/js/pages/Dashboard.vue`.
- All frontend route calls go through Wayfinder imports (`@/actions/...`, `@/routes/...`). No hardcoded URL strings.
- Toasts are flashed server-side with `Inertia::flash('toast', ['type' => ..., 'message' => ...])`, matching `ProfileController@update`.
- After any PHP change: `vendor/bin/pint --dirty --format agent`.
- Run tests with `php artisan test --compact --filter=...`.
- Tailwind v4 cannot generate classes from interpolated strings. Colour classes must appear as complete literals in source.

---

### Task 0: Initialise the git repository

The project is not currently a git repository, so no later task can commit.

**Files:**
- Create: `.gitignore` already exists — verify only

- [ ] **Step 1: Confirm this is not already a repo**

Run: `git rev-parse --is-inside-work-tree`
Expected: `fatal: not a git repository`

- [ ] **Step 2: Initialise and make the baseline commit**

```bash
git init
git add .
git commit -m "chore: initial commit of Laravel starter kit"
```

- [ ] **Step 3: Verify the working tree is clean**

Run: `git status --short`
Expected: no output

---

### Task 1: Enums

Three backed string enums used by migrations, models, validation and the frontend.

**Files:**
- Create: `app/Enums/PromptStatus.php`
- Create: `app/Enums/PromptPriority.php`
- Create: `app/Enums/ProjectColor.php`
- Test: `tests/Unit/EnumsTest.php`

**Interfaces:**
- Consumes: nothing
- Produces:
  - `PromptStatus: string` with cases `Todo = 'todo'`, `Implementing = 'implementing'`, `Done = 'done'`, and `public static function open(): array` returning `[PromptStatus::Todo, PromptStatus::Implementing]`
  - `PromptPriority: string` with cases `Low = 'low'`, `Normal = 'normal'`, `High = 'high'`
  - `ProjectColor: string` with cases `Slate = 'slate'`, `Rose = 'rose'`, `Amber = 'amber'`, `Emerald = 'emerald'`, `Sky = 'sky'`, `Violet = 'violet'`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/EnumsTest.php`:

```php
<?php

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;

test('open statuses are todo and implementing', function () {
    expect(PromptStatus::open())->toBe([PromptStatus::Todo, PromptStatus::Implementing]);
});

test('statuses are backed by lowercase strings', function () {
    expect(PromptStatus::Todo->value)->toBe('todo')
        ->and(PromptStatus::Implementing->value)->toBe('implementing')
        ->and(PromptStatus::Done->value)->toBe('done');
});

test('priorities are backed by lowercase strings', function () {
    expect(PromptPriority::Low->value)->toBe('low')
        ->and(PromptPriority::Normal->value)->toBe('normal')
        ->and(PromptPriority::High->value)->toBe('high');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=EnumsTest`
Expected: FAIL — `Class "App\Enums\PromptStatus" not found`

- [ ] **Step 3: Create the enums**

```bash
php artisan make:enum PromptStatus --string --no-interaction
php artisan make:enum PromptPriority --string --no-interaction
php artisan make:enum ProjectColor --string --no-interaction
```

`app/Enums/PromptStatus.php`:

```php
<?php

namespace App\Enums;

enum PromptStatus: string
{
    case Todo = 'todo';
    case Implementing = 'implementing';
    case Done = 'done';

    /**
     * The statuses shown by default — everything that is not finished.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Todo, self::Implementing];
    }
}
```

`app/Enums/PromptPriority.php`:

```php
<?php

namespace App\Enums;

enum PromptPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
}
```

`app/Enums/ProjectColor.php`:

```php
<?php

namespace App\Enums;

enum ProjectColor: string
{
    case Slate = 'slate';
    case Rose = 'rose';
    case Amber = 'amber';
    case Emerald = 'emerald';
    case Sky = 'sky';
    case Violet = 'violet';
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=EnumsTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums tests/Unit/EnumsTest.php
git commit -m "feat: add prompt status, priority and project colour enums"
```

---

### Task 2: Project model, migration and factory

**Files:**
- Create: `app/Models/Project.php`
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_create_projects_table.php`
- Create: `database/factories/ProjectFactory.php`
- Modify: `app/Models/User.php` (add the `projects` relation)
- Test: `tests/Feature/ProjectModelTest.php`

**Interfaces:**
- Consumes: `App\Enums\ProjectColor` (Task 1)
- Produces:
  - `Project` with fillable `['name', 'color']`, cast `color => ProjectColor::class`, relations `user(): BelongsTo` and `prompts(): HasMany`
  - `User::projects(): HasMany`
  - `ProjectFactory` with `definition()` and a `forUser(User $user): static` state

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ProjectModelTest.php`:

```php
<?php

use App\Enums\ProjectColor;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a project belongs to a user and casts its colour', function () {
    $user = User::factory()->create();

    $project = Project::factory()->forUser($user)->create(['color' => 'sky']);

    expect($project->user->is($user))->toBeTrue()
        ->and($project->color)->toBe(ProjectColor::Sky)
        ->and($user->projects()->count())->toBe(1);
});

test('a user cannot have two projects with the same name', function () {
    $user = User::factory()->create();

    Project::factory()->forUser($user)->create(['name' => 'Prompt Queue']);

    expect(fn () => Project::factory()->forUser($user)->create(['name' => 'Prompt Queue']))
        ->toThrow(QueryException::class);
});

test('two users may each have a project with the same name', function () {
    Project::factory()->forUser(User::factory()->create())->create(['name' => 'Shared']);
    Project::factory()->forUser(User::factory()->create())->create(['name' => 'Shared']);

    expect(Project::query()->where('name', 'Shared')->count())->toBe(2);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=ProjectModelTest`
Expected: FAIL — `Class "App\Models\Project" not found`

- [ ] **Step 3: Create the model, migration and factory**

```bash
php artisan make:model Project --migration --factory --no-interaction
```

Migration `create_projects_table`:

```php
public function up(): void
{
    Schema::create('projects', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->string('color')->default('slate');
        $table->timestamps();

        $table->unique(['user_id', 'name']);
    });
}
```

`app/Models/Project.php`:

```php
<?php

namespace App\Models;

use App\Enums\ProjectColor;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property ProjectColor $color
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'color'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => ProjectColor::class,
        ];
    }

    /**
     * The user who owns the project.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The prompts filed under this project.
     *
     * @return HasMany<Prompt, $this>
     */
    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }
}
```

`database/factories/ProjectFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\ProjectColor;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(ProjectColor::cases()),
        ];
    }

    /**
     * Assign the project to the given user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
```

Add to `app/Models/User.php` — the `projects` relation, plus the imports for `HasMany`:

```php
/**
 * The projects this user has created.
 *
 * @return HasMany<Project, $this>
 */
public function projects(): HasMany
{
    return $this->hasMany(Project::class);
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=ProjectModelTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models database/migrations database/factories tests/Feature/ProjectModelTest.php
git commit -m "feat: add project model with per-user unique names"
```

---

### Task 3: Tag model, migration and factory

**Files:**
- Create: `app/Models/Tag.php`
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_create_tags_table.php`
- Create: `database/factories/TagFactory.php`
- Modify: `app/Models/User.php` (add the `tags` relation)
- Test: `tests/Feature/TagModelTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks
- Produces:
  - `Tag` with fillable `['name']`, relations `user(): BelongsTo` and `prompts(): BelongsToMany`
  - `User::tags(): HasMany`
  - `TagFactory` with a `forUser(User $user): static` state

The `prompt_tag` pivot is **not** created here — it has a foreign key to `prompts`, which does not exist until Task 4, so its migration lives there. `Tag::prompts()` is written now but is not exercised by this task's tests.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TagModelTest.php`:

```php
<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a tag belongs to a user', function () {
    $user = User::factory()->create();

    $tag = Tag::factory()->forUser($user)->create(['name' => 'refactor']);

    expect($tag->user->is($user))->toBeTrue()
        ->and($user->tags()->count())->toBe(1);
});

test('a user cannot have two tags with the same name', function () {
    $user = User::factory()->create();

    Tag::factory()->forUser($user)->create(['name' => 'bug']);

    expect(fn () => Tag::factory()->forUser($user)->create(['name' => 'bug']))
        ->toThrow(QueryException::class);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=TagModelTest`
Expected: FAIL — `Class "App\Models\Tag" not found`

- [ ] **Step 3: Create the model, migrations and factory**

```bash
php artisan make:model Tag --migration --factory --no-interaction
```

Migration `create_tags_table`:

```php
public function up(): void
{
    Schema::create('tags', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->timestamps();

        $table->unique(['user_id', 'name']);
    });
}
```

`app/Models/Tag.php`:

```php
<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /**
     * The user who owns the tag.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The prompts carrying this tag.
     *
     * @return BelongsToMany<Prompt, $this>
     */
    public function prompts(): BelongsToMany
    {
        return $this->belongsToMany(Prompt::class);
    }
}
```

`database/factories/TagFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->word(),
        ];
    }

    /**
     * Assign the tag to the given user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
```

Add to `app/Models/User.php`:

```php
/**
 * The tags this user has created.
 *
 * @return HasMany<Tag, $this>
 */
public function tags(): HasMany
{
    return $this->hasMany(Tag::class);
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=TagModelTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models database/migrations database/factories tests/Feature/TagModelTest.php
git commit -m "feat: add per-user tag model and prompt_tag pivot"
```

---

### Task 4: Prompt model, migration, factory and scopes

The heart of the data model: the `displayTitle` fallback and the `inBucket` scope that every later task depends on.

**Files:**
- Create: `app/Models/Prompt.php`
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_create_prompts_table.php`
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_create_prompt_tag_table.php`
- Create: `database/factories/PromptFactory.php`
- Modify: `app/Models/User.php` (add the `prompts` relation)
- Test: `tests/Feature/PromptModelTest.php`

**Interfaces:**
- Consumes: `PromptStatus`, `PromptPriority` (Task 1), `Project` (Task 2), `Tag` (Task 3)
- Produces:
  - `Prompt` fillable `['project_id', 'title', 'body', 'status', 'priority', 'position']`
  - `Prompt::$displayTitle` — string accessor: `title` when present, otherwise the first line of `body` truncated to 80 characters
  - `Prompt::user(): BelongsTo`, `Prompt::project(): BelongsTo`, `Prompt::tags(): BelongsToMany`
  - Scope `inBucket(?int $projectId)` — `whereNull('project_id')` when `$projectId` is null, otherwise `where('project_id', $projectId)`
  - Scope `search(?string $term)` — `LIKE` on `title` or `body`, no-op when blank
  - Scope `withStatuses(array $statuses)` — `array<int, PromptStatus>`, no-op when empty
  - Scope `withPriorities(array $priorities)` — `array<int, PromptPriority>`, no-op when empty
  - Scope `withTagNames(array $names)` — `array<int, string>`, AND semantics, no-op when empty
  - `User::prompts(): HasMany`
  - `PromptFactory` with `forUser(User $user): static`, `inProject(Project $project): static`, `status(PromptStatus $status): static`, `atPosition(int $position): static`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PromptModelTest.php`:

```php
<?php

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Tag;
use App\Models\User;

test('display title falls back to the first line of the body', function () {
    $prompt = Prompt::factory()->create([
        'title' => null,
        'body' => "Refactor the billing service\n\nIt has grown to 900 lines.",
    ]);

    expect($prompt->displayTitle)->toBe('Refactor the billing service');
});

test('display title truncates a long first line', function () {
    $prompt = Prompt::factory()->create([
        'title' => null,
        'body' => str_repeat('a', 200),
    ]);

    expect(mb_strlen($prompt->displayTitle))->toBeLessThanOrEqual(80);
});

test('an explicit title wins over the body', function () {
    $prompt = Prompt::factory()->create([
        'title' => 'Billing refactor',
        'body' => "Something else entirely\nmore text",
    ]);

    expect($prompt->displayTitle)->toBe('Billing refactor');
});

test('in bucket scope separates inbox from projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();

    Prompt::factory()->forUser($user)->create(['project_id' => null]);
    Prompt::factory()->forUser($user)->inProject($project)->create();

    expect(Prompt::query()->inBucket(null)->count())->toBe(1)
        ->and(Prompt::query()->inBucket($project->id)->count())->toBe(1);
});

test('search matches both title and body', function () {
    Prompt::factory()->create(['title' => 'Kafka consumer', 'body' => 'nothing here']);
    Prompt::factory()->create(['title' => null, 'body' => 'rewrite the kafka producer']);
    Prompt::factory()->create(['title' => 'Unrelated', 'body' => 'unrelated']);

    expect(Prompt::query()->search('kafka')->count())->toBe(2);
});

test('status and priority scopes narrow the results', function () {
    Prompt::factory()->status(PromptStatus::Todo)->create(['priority' => PromptPriority::High]);
    Prompt::factory()->status(PromptStatus::Done)->create(['priority' => PromptPriority::Low]);

    expect(Prompt::query()->withStatuses(PromptStatus::open())->count())->toBe(1)
        ->and(Prompt::query()->withPriorities([PromptPriority::Low])->count())->toBe(1);
});

test('tag filtering requires every named tag', function () {
    $user = User::factory()->create();
    $bug = Tag::factory()->forUser($user)->create(['name' => 'bug']);
    $ui = Tag::factory()->forUser($user)->create(['name' => 'ui']);

    $both = Prompt::factory()->forUser($user)->create();
    $both->tags()->attach([$bug->id, $ui->id]);

    $onlyBug = Prompt::factory()->forUser($user)->create();
    $onlyBug->tags()->attach($bug->id);

    expect(Prompt::query()->withTagNames(['bug'])->count())->toBe(2)
        ->and(Prompt::query()->withTagNames(['bug', 'ui'])->count())->toBe(1);
});

test('scopes are no-ops when given nothing', function () {
    Prompt::factory()->count(3)->create();

    expect(Prompt::query()->search(null)->withStatuses([])->withPriorities([])->withTagNames([])->count())
        ->toBe(3);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=PromptModelTest`
Expected: FAIL — `Class "App\Models\Prompt" not found`

- [ ] **Step 3: Create the migration, model and factory**

```bash
php artisan make:model Prompt --migration --factory --no-interaction
php artisan make:migration create_prompt_tag_table --no-interaction
```

Run these in this order so the pivot migration's timestamp sorts after the prompts one — the pivot's foreign key needs the `prompts` table to already exist.

Migration `create_prompts_table`:

```php
public function up(): void
{
    Schema::create('prompts', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        $table->string('title')->nullable();
        $table->text('body');
        $table->string('status')->default('todo');
        $table->string('priority')->default('normal');
        $table->unsignedInteger('position')->default(0);
        $table->timestamps();

        $table->index(['user_id', 'project_id', 'position']);
    });
}
```

Migration `create_prompt_tag_table`:

```php
public function up(): void
{
    Schema::create('prompt_tag', function (Blueprint $table) {
        $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
        $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

        $table->primary(['prompt_id', 'tag_id']);
    });
}
```

`app/Models/Prompt.php`:

```php
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

            return Str::limit(trim(Str::before($this->body, "\n")), self::DERIVED_TITLE_LENGTH);
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
```

`database/factories/PromptFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prompt>
 */
class PromptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => null,
            'title' => null,
            'body' => fake()->paragraph(),
            'status' => PromptStatus::Todo,
            'priority' => PromptPriority::Normal,
            'position' => 0,
        ];
    }

    /**
     * Assign the prompt to the given user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * File the prompt under the given project, and its owner.
     */
    public function inProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
            'user_id' => $project->user_id,
        ]);
    }

    /**
     * Give the prompt a specific status.
     */
    public function status(PromptStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Give the prompt a specific position within its bucket.
     */
    public function atPosition(int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }
}
```

Add to `app/Models/User.php`:

```php
/**
 * The prompts this user has captured.
 *
 * @return HasMany<Prompt, $this>
 */
public function prompts(): HasMany
{
    return $this->hasMany(Prompt::class);
}
```

- [ ] **Step 4: Verify the migrations run in the right order**

Run: `php artisan migrate:fresh`
Expected: every migration succeeds. A foreign key error on `prompt_tag` means its filename sorts before `create_prompts_table` — rename it to a later timestamp and re-run.

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=PromptModelTest`
Expected: PASS (8 tests)

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models database/migrations database/factories tests/Feature/PromptModelTest.php
git commit -m "feat: add prompt model with bucket, search and filter scopes"
```

---

### Task 5: Policies

**Files:**
- Create: `app/Policies/PromptPolicy.php`
- Create: `app/Policies/ProjectPolicy.php`
- Test: `tests/Feature/PromptPolicyTest.php`

**Interfaces:**
- Consumes: `Prompt` (Task 4), `Project` (Task 2)
- Produces: `PromptPolicy::update(User, Prompt): Response`, `PromptPolicy::delete(User, Prompt): Response`, `ProjectPolicy::update(User, Project): Response`, `ProjectPolicy::delete(User, Project): Response` — all returning `Response::denyAsNotFound()` for non-owners so controllers produce a 404, never a 403.

Laravel 13 auto-discovers `App\Policies\XPolicy` for `App\Models\X`. No registration needed.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PromptPolicyTest.php`:

```php
<?php

use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('an owner may update and delete their prompt', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->create();

    expect(Gate::forUser($user)->allows('update', $prompt))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $prompt))->toBeTrue();
});

test('a stranger is denied as not found', function () {
    $stranger = User::factory()->create();
    $prompt = Prompt::factory()->create();

    $response = Gate::forUser($stranger)->inspect('update', $prompt);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
});

test('a stranger is denied as not found for projects', function () {
    $stranger = User::factory()->create();
    $project = Project::factory()->create();

    $response = Gate::forUser($stranger)->inspect('update', $project);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=PromptPolicyTest`
Expected: FAIL — the gate allows nothing, or `Class "App\Policies\PromptPolicy" not found`

- [ ] **Step 3: Create the policies**

```bash
php artisan make:policy PromptPolicy --model=Prompt --no-interaction
php artisan make:policy ProjectPolicy --model=Project --no-interaction
```

Replace the generated `app/Policies/PromptPolicy.php` with only the methods used — delete the scaffolded `viewAny`, `view`, `create`, `restore` and `forceDelete`:

```php
<?php

namespace App\Policies;

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PromptPolicy
{
    /**
     * Determine whether the user can update the prompt.
     */
    public function update(User $user, Prompt $prompt): Response
    {
        return $this->owns($user, $prompt);
    }

    /**
     * Determine whether the user can delete the prompt.
     */
    public function delete(User $user, Prompt $prompt): Response
    {
        return $this->owns($user, $prompt);
    }

    /**
     * Deny as not found so a stranger cannot confirm the prompt exists.
     */
    private function owns(User $user, Prompt $prompt): Response
    {
        return $user->id === $prompt->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
```

`app/Policies/ProjectPolicy.php` is the same shape against `Project` and `$project->user_id`:

```php
<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): Response
    {
        return $this->owns($user, $project);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): Response
    {
        return $this->owns($user, $project);
    }

    /**
     * Deny as not found so a stranger cannot confirm the project exists.
     */
    private function owns(User $user, Project $project): Response
    {
        return $user->id === $project->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=PromptPolicyTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies tests/Feature/PromptPolicyTest.php
git commit -m "feat: add prompt and project policies denying as not found"
```

---

### Task 6: Capture — the store endpoint

**Files:**
- Create: `app/Http/Controllers/PromptController.php` (with `store` only for now)
- Create: `app/Http/Requests/PromptStoreRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Prompts/CaptureTest.php`

**Interfaces:**
- Consumes: `Prompt`, `PromptStatus`, `PromptPriority`
- Produces:
  - Route `prompts.store` → `POST /prompts`
  - `PromptStoreRequest::bucketProjectId(): ?int` — reads the `project` field; `null` means Inbox
  - New prompts are created at `position` 0 with every existing prompt in the same bucket incremented

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Prompts/CaptureTest.php`:

```php
<?php

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;

test('capturing with only a body creates an inbox prompt', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('prompts.store'), ['body' => "Refactor billing\n\nIt is 900 lines."])
        ->assertRedirect();

    $prompt = Prompt::query()->sole();

    expect($prompt->user_id)->toBe($user->id)
        ->and($prompt->project_id)->toBeNull()
        ->and($prompt->title)->toBeNull()
        ->and($prompt->status)->toBe(PromptStatus::Todo)
        ->and($prompt->priority)->toBe(PromptPriority::Normal)
        ->and($prompt->position)->toBe(0)
        ->and($prompt->displayTitle)->toBe('Refactor billing');
});

test('capturing into a project files it there', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('prompts.store'), ['body' => 'Add tests', 'project' => $project->id])
        ->assertRedirect();

    expect(Prompt::query()->sole()->project_id)->toBe($project->id);
});

test('a new prompt goes to the top and pushes the bucket down', function () {
    $user = User::factory()->create();
    $existing = Prompt::factory()->forUser($user)->atPosition(0)->create();

    $this->actingAs($user)->post(route('prompts.store'), ['body' => 'Newest thought']);

    expect($existing->refresh()->position)->toBe(1)
        ->and(Prompt::query()->where('body', 'Newest thought')->sole()->position)->toBe(0);
});

test('positions in another bucket are untouched', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    $elsewhere = Prompt::factory()->forUser($user)->inProject($project)->atPosition(0)->create();

    $this->actingAs($user)->post(route('prompts.store'), ['body' => 'Inbox thought']);

    expect($elsewhere->refresh()->position)->toBe(0);
});

test('a body is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('prompts.store'), ['body' => '   '])
        ->assertSessionHasErrors('body');
});

test('a prompt cannot be captured into another user\'s project', function () {
    $user = User::factory()->create();
    $foreign = Project::factory()->create();

    $this->actingAs($user)
        ->post(route('prompts.store'), ['body' => 'Sneaky', 'project' => $foreign->id])
        ->assertSessionHasErrors('project');
});

test('guests cannot capture', function () {
    $this->post(route('prompts.store'), ['body' => 'Hello'])->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=CaptureTest`
Expected: FAIL — `Route [prompts.store] not defined`

- [ ] **Step 3: Create the request, controller and route**

```bash
php artisan make:request PromptStoreRequest --no-interaction
php artisan make:controller PromptController --no-interaction
```

`app/Http/Requests/PromptStoreRequest.php`:

```php
<?php

namespace App\Http\Requests;

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
            'body' => ['required', 'string', 'max:65535'],
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
}
```

`app/Http/Controllers/PromptController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use App\Http\Requests\PromptStoreRequest;
use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PromptController extends Controller
{
    /**
     * Capture a new prompt at the top of its bucket.
     */
    public function store(PromptStoreRequest $request): RedirectResponse
    {
        $projectId = $request->bucketProjectId();

        DB::transaction(function () use ($request, $projectId): void {
            Prompt::query()
                ->whereBelongsTo($request->user())
                ->inBucket($projectId)
                ->increment('position');

            $request->user()->prompts()->create([
                'project_id' => $projectId,
                'body' => $request->string('body')->toString(),
                'status' => PromptStatus::Todo,
                'priority' => PromptPriority::Normal,
                'position' => 0,
            ]);
        });

        return back();
    }
}
```

`routes/web.php` — add inside the existing `auth`/`verified` group:

```php
use App\Http\Controllers\PromptController;

Route::post('prompts', [PromptController::class, 'store'])->name('prompts.store');
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=CaptureTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http routes/web.php tests/Feature/Prompts/CaptureTest.php
git commit -m "feat: capture prompts with body only into the top of a bucket"
```

---

### Task 7: The index page — filters, resources and the reorder gate

**Files:**
- Create: `app/Http/Requests/PromptIndexRequest.php`
- Create: `app/Http/Resources/PromptResource.php`
- Create: `app/Http/Resources/ProjectResource.php`
- Modify: `app/Http/Controllers/PromptController.php` (add `index`)
- Modify: `routes/web.php`
- Test: `tests/Feature/Prompts/IndexTest.php`

**Interfaces:**
- Consumes: `Prompt` scopes (Task 4), `PromptStatus`, `PromptPriority`
- Produces:
  - Route `prompts.index` → `GET /prompts`, rendering the `prompts/Index` Inertia component
  - Query string contract: `?project=inbox|<id>`, `?q=`, `?status[]=`, `?priority[]=`, `?tags[]=`
  - `PromptIndexRequest::hasBucket(): bool`, `::bucketProjectId(): ?int`, `::statuses(): array<int, PromptStatus>` (defaults to `PromptStatus::open()`), `::priorities(): array<int, PromptPriority>`, `::tagNames(): array<int, string>`, `::searchTerm(): ?string`, `::canReorder(): bool`
  - Props: `prompts` (array of `PromptResource`), `filters` (echo of the query string), `canReorder` (bool)
  - `PromptResource` shape: `{ id, title, rawTitle, body, status, priority, position, projectId, tags: string[], updatedAt }` where `title` is the display title
  - `ProjectResource` shape: `{ id, name, color, openPromptsCount }`

`canReorder` is true only when a single bucket is selected, there is no search term, the status filter is at its default, and no priority or tag filters are applied.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Prompts/IndexTest.php`:

```php
<?php

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('the index renders the workbench with open prompts only', function () {
    $user = User::factory()->create();
    Prompt::factory()->forUser($user)->status(PromptStatus::Todo)->create(['body' => 'Open one']);
    Prompt::factory()->forUser($user)->status(PromptStatus::Done)->create(['body' => 'Finished']);

    $this->actingAs($user)
        ->get(route('prompts.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('prompts/Index')
            ->has('prompts', 1)
            ->where('prompts.0.title', 'Open one')
        );
});

test('done prompts appear when asked for', function () {
    $user = User::factory()->create();
    Prompt::factory()->forUser($user)->status(PromptStatus::Done)->create();

    $this->actingAs($user)
        ->get(route('prompts.index', ['status' => ['done']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('prompts', 1));
});

test('prompts are returned in position order', function () {
    $user = User::factory()->create();
    Prompt::factory()->forUser($user)->atPosition(1)->create(['body' => 'Second']);
    Prompt::factory()->forUser($user)->atPosition(0)->create(['body' => 'First']);

    $this->actingAs($user)
        ->get(route('prompts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('prompts.0.title', 'First')
            ->where('prompts.1.title', 'Second')
        );
});

test('search matches title and body', function () {
    $user = User::factory()->create();
    Prompt::factory()->forUser($user)->create(['title' => 'Kafka consumer', 'body' => 'x']);
    Prompt::factory()->forUser($user)->create(['title' => null, 'body' => 'rewrite the kafka producer']);
    Prompt::factory()->forUser($user)->create(['title' => null, 'body' => 'unrelated']);

    $this->actingAs($user)
        ->get(route('prompts.index', ['q' => 'kafka']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('prompts', 2));
});

test('a project filter shows only that project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    Prompt::factory()->forUser($user)->inProject($project)->create(['body' => 'In project']);
    Prompt::factory()->forUser($user)->create(['body' => 'In inbox']);

    $this->actingAs($user)
        ->get(route('prompts.index', ['project' => $project->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('prompts', 1)
            ->where('prompts.0.title', 'In project')
        );

    $this->actingAs($user)
        ->get(route('prompts.index', ['project' => 'inbox']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('prompts', 1)
            ->where('prompts.0.title', 'In inbox')
        );
});

test('priority and tag filters narrow the list', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->forUser($user)->create(['name' => 'bug']);
    $tagged = Prompt::factory()->forUser($user)->create(['priority' => PromptPriority::High]);
    $tagged->tags()->attach($tag);
    Prompt::factory()->forUser($user)->create(['priority' => PromptPriority::Low]);

    $this->actingAs($user)
        ->get(route('prompts.index', ['priority' => ['high']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('prompts', 1));

    $this->actingAs($user)
        ->get(route('prompts.index', ['tags' => ['bug']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('prompts', 1));
});

test('another user\'s prompts are never listed', function () {
    $user = User::factory()->create();
    Prompt::factory()->create(['body' => 'Not yours']);

    $this->actingAs($user)
        ->get(route('prompts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('prompts', 0));
});

test('reordering is allowed only in a single unfiltered bucket', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();

    $assertCanReorder = function (array $query, bool $expected) use ($user) {
        $this->actingAs($user)
            ->get(route('prompts.index', $query))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canReorder', $expected));
    };

    $assertCanReorder(['project' => $project->id], true);
    $assertCanReorder(['project' => 'inbox'], true);
    $assertCanReorder([], false);
    $assertCanReorder(['project' => $project->id, 'q' => 'kafka'], false);
    $assertCanReorder(['project' => $project->id, 'status' => ['done']], false);
    $assertCanReorder(['project' => $project->id, 'priority' => ['high']], false);
    $assertCanReorder(['project' => $project->id, 'tags' => ['bug']], false);
});

test('an unknown status is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('prompts.index', ['status' => ['banana']]))
        ->assertSessionHasErrors('status.0');
});

test('guests are redirected to login', function () {
    $this->get(route('prompts.index'))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=IndexTest`
Expected: FAIL — `Route [prompts.index] not defined`

- [ ] **Step 3: Create the request, resources and index action**

```bash
php artisan make:request PromptIndexRequest --no-interaction
php artisan make:resource PromptResource --no-interaction
php artisan make:resource ProjectResource --no-interaction
```

`app/Http/Requests/PromptIndexRequest.php`:

```php
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
```

`app/Http/Resources/PromptResource.php`:

```php
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
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()->all(), []),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

`app/Http/Resources/ProjectResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
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
            'name' => $this->name,
            'color' => $this->color->value,
            'openPromptsCount' => (int) ($this->open_prompts_count ?? 0),
        ];
    }
}
```

Add `index` to `app/Http/Controllers/PromptController.php`:

```php
/**
 * Show the prompt workbench.
 */
public function index(PromptIndexRequest $request): Response
{
    $prompts = Prompt::query()
        ->whereBelongsTo($request->user())
        ->when($request->hasBucket(), fn (Builder $query) => $query->inBucket($request->bucketProjectId()))
        ->search($request->searchTerm())
        ->withStatuses($request->statuses())
        ->withPriorities($request->priorities())
        ->withTagNames($request->tagNames())
        ->with('tags')
        ->orderBy('position')
        ->orderByDesc('id')
        ->get();

    return Inertia::render('prompts/Index', [
        'prompts' => PromptResource::collection($prompts),
        'canReorder' => $request->canReorder(),
        'filters' => [
            'project' => $request->string('project')->toString() ?: null,
            'q' => $request->searchTerm(),
            'status' => array_map(fn (PromptStatus $status): string => $status->value, $request->statuses()),
            'priority' => array_map(fn (PromptPriority $priority): string => $priority->value, $request->priorities()),
            'tags' => $request->tagNames(),
        ],
    ]);
}
```

Add the imports `Illuminate\Database\Eloquent\Builder`, `Inertia\Inertia`, `Inertia\Response`, `App\Http\Requests\PromptIndexRequest`, `App\Http\Resources\PromptResource`.

`routes/web.php` — add inside the `auth`/`verified` group, **above** the `store` route for readability:

```php
Route::get('prompts', [PromptController::class, 'index'])->name('prompts.index');
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=IndexTest`
Expected: PASS (10 tests)

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http routes/web.php tests/Feature/Prompts/IndexTest.php
git commit -m "feat: add the prompt index with search, filters and a reorder gate"
```

---

### Task 8: Update and delete, including tag sync and project moves

**Files:**
- Create: `app/Http/Requests/PromptUpdateRequest.php`
- Create: `app/Actions/SyncPromptTags.php`
- Modify: `app/Http/Controllers/PromptController.php` (add `update`, `destroy`)
- Modify: `routes/web.php`
- Test: `tests/Feature/Prompts/UpdateTest.php`

**Interfaces:**
- Consumes: `PromptPolicy` (Task 5), `Prompt`, `Tag`
- Produces:
  - Routes `prompts.update` → `PATCH /prompts/{prompt}`, `prompts.destroy` → `DELETE /prompts/{prompt}`
  - `SyncPromptTags::__invoke(Prompt $prompt, User $user, array $names): void` — first-or-creates each named tag for the user and syncs the pivot
  - Moving a prompt to a different project puts it at position 0 of the new bucket and shifts that bucket down

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Prompts/UpdateTest.php`:

```php
<?php

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Tag;
use App\Models\User;

test('a prompt can be retitled, rewritten and reprioritised', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->patch(route('prompts.update', $prompt), [
            'title' => 'Billing refactor',
            'body' => 'Split the service',
            'status' => 'done',
            'priority' => 'high',
        ])
        ->assertRedirect();

    $prompt->refresh();

    expect($prompt->title)->toBe('Billing refactor')
        ->and($prompt->body)->toBe('Split the service')
        ->and($prompt->status)->toBe(PromptStatus::Done)
        ->and($prompt->priority)->toBe(PromptPriority::High);
});

test('clearing the title restores the body fallback', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->create([
        'title' => 'Explicit',
        'body' => "Derived line\nrest",
    ]);

    $this->actingAs($user)->patch(route('prompts.update', $prompt), [
        'title' => null,
        'body' => "Derived line\nrest",
    ]);

    expect($prompt->refresh()->displayTitle)->toBe('Derived line');
});

test('tags are created for the user and synced', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->create();

    $this->actingAs($user)->patch(route('prompts.update', $prompt), [
        'body' => $prompt->body,
        'tags' => ['refactor', 'bug'],
    ]);

    expect($prompt->refresh()->tags->pluck('name')->sort()->values()->all())->toBe(['bug', 'refactor'])
        ->and(Tag::query()->where('user_id', $user->id)->count())->toBe(2);
});

test('an existing tag is reused rather than duplicated', function () {
    $user = User::factory()->create();
    Tag::factory()->forUser($user)->create(['name' => 'bug']);
    $prompt = Prompt::factory()->forUser($user)->create();

    $this->actingAs($user)->patch(route('prompts.update', $prompt), [
        'body' => $prompt->body,
        'tags' => ['bug'],
    ]);

    expect(Tag::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('moving a prompt to another project puts it at the top of that bucket', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    $sitting = Prompt::factory()->forUser($user)->inProject($project)->atPosition(0)->create();
    $moving = Prompt::factory()->forUser($user)->atPosition(0)->create();

    $this->actingAs($user)->patch(route('prompts.update', $moving), [
        'body' => $moving->body,
        'project' => $project->id,
    ]);

    expect($moving->refresh()->position)->toBe(0)
        ->and($moving->project_id)->toBe($project->id)
        ->and($sitting->refresh()->position)->toBe(1);
});

test('staying in the same project leaves the position alone', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->atPosition(3)->create();

    $this->actingAs($user)->patch(route('prompts.update', $prompt), ['body' => 'Changed']);

    expect($prompt->refresh()->position)->toBe(3);
});

test('a prompt can be deleted', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->create();

    $this->actingAs($user)->delete(route('prompts.destroy', $prompt))->assertRedirect();

    expect(Prompt::query()->count())->toBe(0);
});

test('another user\'s prompt is a 404 for update and delete', function () {
    $user = User::factory()->create();
    $foreign = Prompt::factory()->create();

    $this->actingAs($user)->patch(route('prompts.update', $foreign), ['body' => 'Mine now'])->assertNotFound();
    $this->actingAs($user)->delete(route('prompts.destroy', $foreign))->assertNotFound();

    expect($foreign->refresh()->body)->not->toBe('Mine now');
});

test('a prompt cannot be moved into another user\'s project', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->create();
    $foreignProject = Project::factory()->create();

    $this->actingAs($user)
        ->patch(route('prompts.update', $prompt), ['body' => $prompt->body, 'project' => $foreignProject->id])
        ->assertSessionHasErrors('project');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=UpdateTest`
Expected: FAIL — `Route [prompts.update] not defined`

- [ ] **Step 3: Create the request, action and controller methods**

```bash
php artisan make:request PromptUpdateRequest --no-interaction
php artisan make:class Actions/SyncPromptTags --no-interaction
```

`app/Http/Requests/PromptUpdateRequest.php`:

```php
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
```

`app/Actions/SyncPromptTags.php`:

```php
<?php

namespace App\Actions;

use App\Models\Prompt;
use App\Models\User;

class SyncPromptTags
{
    /**
     * Attach exactly the named tags to the prompt, creating any that are new to the user.
     *
     * @param  array<int, string>  $names
     */
    public function __invoke(Prompt $prompt, User $user, array $names): void
    {
        $ids = array_map(
            fn (string $name): int => $user->tags()->firstOrCreate(['name' => $name])->id,
            $names,
        );

        $prompt->tags()->sync($ids);
    }
}
```

Add to `app/Http/Controllers/PromptController.php`:

```php
/**
 * Update a prompt, moving it between projects and syncing its tags.
 */
public function update(PromptUpdateRequest $request, Prompt $prompt, SyncPromptTags $syncTags): RedirectResponse
{
    Gate::authorize('update', $prompt);

    $targetProjectId = $request->bucketProjectId();

    DB::transaction(function () use ($request, $prompt, $targetProjectId, $syncTags): void {
        $attributes = $request->fillableAttributes();

        if ($targetProjectId !== $prompt->project_id) {
            Prompt::query()
                ->whereBelongsTo($request->user())
                ->inBucket($targetProjectId)
                ->increment('position');

            $attributes['project_id'] = $targetProjectId;
            $attributes['position'] = 0;
        }

        $prompt->update($attributes);

        $syncTags($prompt, $request->user(), $request->tagNames());
    });

    return back();
}

/**
 * Delete a prompt.
 */
public function destroy(Request $request, Prompt $prompt): RedirectResponse
{
    Gate::authorize('delete', $prompt);

    $prompt->delete();

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Prompt deleted.')]);

    return back();
}
```

Add imports `Illuminate\Http\Request`, `Illuminate\Support\Facades\Gate`, `App\Actions\SyncPromptTags`, `App\Http\Requests\PromptUpdateRequest`.

`routes/web.php`:

```php
Route::patch('prompts/{prompt}', [PromptController::class, 'update'])->name('prompts.update');
Route::delete('prompts/{prompt}', [PromptController::class, 'destroy'])->name('prompts.destroy');
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=UpdateTest`
Expected: PASS (9 tests)

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions app/Http routes/web.php tests/Feature/Prompts/UpdateTest.php
git commit -m "feat: update and delete prompts with tag sync and project moves"
```

---

### Task 9: The copy-button status flip

**Files:**
- Create: `app/Http/Controllers/PromptStatusController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Prompts/StatusTest.php`

**Interfaces:**
- Consumes: `PromptPolicy` (Task 5), `PromptStatus`
- Produces: Route `prompts.status` → `PATCH /prompts/{prompt}/status`. Advances `todo` to `implementing` and leaves every other status untouched. Takes no request body.

This endpoint exists solely for the copy button. Manual status changes go through `PromptController@update`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Prompts/StatusTest.php`:

```php
<?php

use App\Enums\PromptStatus;
use App\Models\Prompt;
use App\Models\User;

test('copying a todo prompt advances it to implementing', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->status(PromptStatus::Todo)->create();

    $this->actingAs($user)->patch(route('prompts.status', $prompt))->assertRedirect();

    expect($prompt->refresh()->status)->toBe(PromptStatus::Implementing);
});

test('copying an implementing prompt changes nothing', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->status(PromptStatus::Implementing)->create();

    $this->actingAs($user)->patch(route('prompts.status', $prompt));

    expect($prompt->refresh()->status)->toBe(PromptStatus::Implementing);
});

test('copying a done prompt does not resurrect it', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->status(PromptStatus::Done)->create();

    $this->actingAs($user)->patch(route('prompts.status', $prompt));

    expect($prompt->refresh()->status)->toBe(PromptStatus::Done);
});

test('another user\'s prompt is a 404', function () {
    $user = User::factory()->create();
    $foreign = Prompt::factory()->status(PromptStatus::Todo)->create();

    $this->actingAs($user)->patch(route('prompts.status', $foreign))->assertNotFound();

    expect($foreign->refresh()->status)->toBe(PromptStatus::Todo);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=StatusTest`
Expected: FAIL — `Route [prompts.status] not defined`

- [ ] **Step 3: Create the controller and route**

```bash
php artisan make:controller PromptStatusController --invokable --no-interaction
```

```php
<?php

namespace App\Http\Controllers;

use App\Enums\PromptStatus;
use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PromptStatusController extends Controller
{
    /**
     * Advance a freshly copied prompt from todo to implementing.
     *
     * Any other status is left alone: re-copying something mid-flight is not a
     * state change, and re-copying a finished prompt must not resurrect it.
     */
    public function __invoke(Request $request, Prompt $prompt): RedirectResponse
    {
        Gate::authorize('update', $prompt);

        if ($prompt->status === PromptStatus::Todo) {
            $prompt->update(['status' => PromptStatus::Implementing]);
        }

        return back();
    }
}
```

`routes/web.php`:

```php
Route::patch('prompts/{prompt}/status', PromptStatusController::class)->name('prompts.status');
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=StatusTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http routes/web.php tests/Feature/Prompts/StatusTest.php
git commit -m "feat: advance copied prompts from todo to implementing"
```

---

### Task 10: The reorder endpoint

**Files:**
- Create: `app/Http/Controllers/PromptOrderController.php`
- Create: `app/Http/Requests/PromptReorderRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Prompts/ReorderTest.php`

**Interfaces:**
- Consumes: `Prompt::inBucket` (Task 4)
- Produces: Route `prompts.reorder` → `PATCH /prompts/reorder`, body `{ project: int|null, ids: int[] }`. Rewrites `position` to the array index for every id, in a transaction. Rejects with 422 if any id is not owned by the user or not already in the named bucket.

**Route ordering is load-bearing:** `prompts/reorder` must be registered **before** `prompts/{prompt}`, or `{prompt}` will swallow the literal `reorder` and the endpoint will 404.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Prompts/ReorderTest.php`:

```php
<?php

use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;

test('reordering rewrites positions to the given order', function () {
    $user = User::factory()->create();
    $first = Prompt::factory()->forUser($user)->atPosition(0)->create();
    $second = Prompt::factory()->forUser($user)->atPosition(1)->create();
    $third = Prompt::factory()->forUser($user)->atPosition(2)->create();

    $this->actingAs($user)
        ->patch(route('prompts.reorder'), [
            'project' => null,
            'ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertRedirect();

    expect($third->refresh()->position)->toBe(0)
        ->and($first->refresh()->position)->toBe(1)
        ->and($second->refresh()->position)->toBe(2);
});

test('reordering works within a project bucket', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    $a = Prompt::factory()->forUser($user)->inProject($project)->atPosition(0)->create();
    $b = Prompt::factory()->forUser($user)->inProject($project)->atPosition(1)->create();

    $this->actingAs($user)
        ->patch(route('prompts.reorder'), ['project' => $project->id, 'ids' => [$b->id, $a->id]])
        ->assertRedirect();

    expect($b->refresh()->position)->toBe(0)
        ->and($a->refresh()->position)->toBe(1);
});

test('an id from another bucket is rejected', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    $inInbox = Prompt::factory()->forUser($user)->atPosition(0)->create();
    $inProject = Prompt::factory()->forUser($user)->inProject($project)->atPosition(0)->create();

    $this->actingAs($user)
        ->patch(route('prompts.reorder'), ['project' => null, 'ids' => [$inProject->id, $inInbox->id]])
        ->assertSessionHasErrors('ids');

    expect($inProject->refresh()->position)->toBe(0);
});

test('another user\'s prompt id is rejected', function () {
    $user = User::factory()->create();
    $mine = Prompt::factory()->forUser($user)->atPosition(0)->create();
    $theirs = Prompt::factory()->atPosition(0)->create();

    $this->actingAs($user)
        ->patch(route('prompts.reorder'), ['project' => null, 'ids' => [$theirs->id, $mine->id]])
        ->assertSessionHasErrors('ids');

    expect($theirs->refresh()->position)->toBe(0)
        ->and($mine->refresh()->position)->toBe(0);
});

test('duplicate ids are rejected', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->forUser($user)->atPosition(0)->create();

    $this->actingAs($user)
        ->patch(route('prompts.reorder'), ['project' => null, 'ids' => [$prompt->id, $prompt->id]])
        ->assertSessionHasErrors('ids.0');
});

test('an empty list is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('prompts.reorder'), ['project' => null, 'ids' => []])
        ->assertSessionHasErrors('ids');
});

test('guests cannot reorder', function () {
    $this->patch(route('prompts.reorder'), ['project' => null, 'ids' => [1]])
        ->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=ReorderTest`
Expected: FAIL — `Route [prompts.reorder] not defined`

- [ ] **Step 3: Create the request, controller and route**

```bash
php artisan make:request PromptReorderRequest --no-interaction
php artisan make:controller PromptOrderController --invokable --no-interaction
```

`app/Http/Requests/PromptReorderRequest.php`:

```php
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
```

`app/Http/Controllers/PromptOrderController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptReorderRequest;
use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PromptOrderController extends Controller
{
    /**
     * Rewrite every position in one bucket to match the order supplied by the client.
     */
    public function __invoke(PromptReorderRequest $request): RedirectResponse
    {
        $projectId = $request->bucketProjectId();

        DB::transaction(function () use ($request, $projectId): void {
            foreach ($request->promptIds() as $position => $id) {
                Prompt::query()
                    ->whereBelongsTo($request->user())
                    ->inBucket($projectId)
                    ->whereKey($id)
                    ->update(['position' => $position]);
            }
        });

        return back();
    }
}
```

`routes/web.php` — **place this line above the `prompts/{prompt}` routes:**

```php
Route::patch('prompts/reorder', PromptOrderController::class)->name('prompts.reorder');
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=ReorderTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Verify route ordering explicitly**

Run: `php artisan route:list --path=prompts --except-vendor`
Expected: `prompts/reorder` is listed above `prompts/{prompt}`

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http routes/web.php tests/Feature/Prompts/ReorderTest.php
git commit -m "feat: reorder a prompt bucket in one transactional write"
```

---

### Task 11: Project management and shared props

**Files:**
- Create: `app/Http/Controllers/ProjectController.php`
- Create: `app/Http/Requests/ProjectStoreRequest.php`
- Create: `app/Http/Requests/ProjectUpdateRequest.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Projects/ProjectManagementTest.php`

**Interfaces:**
- Consumes: `ProjectPolicy` (Task 5), `ProjectResource` (Task 7)
- Produces:
  - Routes `projects.store` → `POST /projects`, `projects.update` → `PATCH /projects/{project}`, `projects.destroy` → `DELETE /projects/{project}`
  - Shared Inertia props `projects` (array of `ProjectResource` with `openPromptsCount`, name-ordered) and `tags` (array of tag name strings), both empty for guests
  - Deleting a project moves its prompts to the Inbox, appended after the existing Inbox prompts in their previous relative order

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Projects/ProjectManagementTest.php`:

```php
<?php

use App\Enums\PromptStatus;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('a project can be created', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => 'Prompt Queue', 'color' => 'sky'])
        ->assertRedirect();

    expect($user->projects()->sole()->name)->toBe('Prompt Queue');
});

test('a duplicate project name is rejected for the same user', function () {
    $user = User::factory()->create();
    Project::factory()->forUser($user)->create(['name' => 'Prompt Queue']);

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => 'Prompt Queue', 'color' => 'sky'])
        ->assertSessionHasErrors('name');
});

test('an unknown colour is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => 'Fine', 'color' => 'chartreuse'])
        ->assertSessionHasErrors('color');
});

test('a project can be renamed', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->patch(route('projects.update', $project), ['name' => 'Renamed', 'color' => 'rose'])
        ->assertRedirect();

    expect($project->refresh()->name)->toBe('Renamed');
});

test('deleting a project moves its prompts to the inbox', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create();
    Prompt::factory()->forUser($user)->atPosition(0)->create(['body' => 'Already in inbox']);
    $moved = Prompt::factory()->forUser($user)->inProject($project)->atPosition(0)->create();

    $this->actingAs($user)->delete(route('projects.destroy', $project))->assertRedirect();

    expect(Project::query()->count())->toBe(0)
        ->and($moved->refresh()->project_id)->toBeNull()
        ->and($moved->position)->toBe(1);
});

test('another user\'s project is a 404', function () {
    $user = User::factory()->create();
    $foreign = Project::factory()->create();

    $this->actingAs($user)->patch(route('projects.update', $foreign), ['name' => 'Mine', 'color' => 'sky'])->assertNotFound();
    $this->actingAs($user)->delete(route('projects.destroy', $foreign))->assertNotFound();
});

test('projects and tags are shared with every authenticated page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->forUser($user)->create(['name' => 'Alpha']);
    Prompt::factory()->forUser($user)->inProject($project)->status(PromptStatus::Todo)->create();
    Prompt::factory()->forUser($user)->inProject($project)->status(PromptStatus::Done)->create();
    Tag::factory()->forUser($user)->create(['name' => 'bug']);

    $this->actingAs($user)
        ->get(route('prompts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('projects', 1)
            ->where('projects.0.name', 'Alpha')
            ->where('projects.0.openPromptsCount', 1)
            ->where('tags', ['bug'])
        );
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=ProjectManagementTest`
Expected: FAIL — `Route [projects.store] not defined`

- [ ] **Step 3: Create the requests, controller, shared props and routes**

```bash
php artisan make:request ProjectStoreRequest --no-interaction
php artisan make:request ProjectUpdateRequest --no-interaction
php artisan make:controller ProjectController --no-interaction
```

`app/Http/Requests/ProjectStoreRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Enums\ProjectColor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('projects', 'name')->where('user_id', $this->user()->id),
            ],
            'color' => ['required', Rule::enum(ProjectColor::class)],
        ];
    }
}
```

`app/Http/Requests/ProjectUpdateRequest.php` is identical except the uniqueness rule ignores the current project:

```php
Rule::unique('projects', 'name')
    ->where('user_id', $this->user()->id)
    ->ignore($this->route('project')),
```

`app/Http/Controllers/ProjectController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Models\Project;
use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProjectController extends Controller
{
    /**
     * Create a project for the current user.
     */
    public function store(ProjectStoreRequest $request): RedirectResponse
    {
        $request->user()->projects()->create($request->validated());

        return back();
    }

    /**
     * Rename or recolour a project.
     */
    public function update(ProjectUpdateRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->update($request->validated());

        return back();
    }

    /**
     * Delete a project, returning its prompts to the Inbox rather than destroying them.
     */
    public function destroy(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        DB::transaction(function () use ($request, $project): void {
            $offset = (int) Prompt::query()
                ->whereBelongsTo($request->user())
                ->inBucket(null)
                ->max('position');

            $project->prompts()->orderBy('position')->get()
                ->each(function (Prompt $prompt, int $index) use ($offset): void {
                    $prompt->update([
                        'project_id' => null,
                        'position' => $offset + $index + 1,
                    ]);
                });

            $project->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted. Its prompts are in the Inbox.')]);

        return back();
    }
}
```

Note: `max('position')` returns `null` for an empty Inbox, which casts to `0`, so the first moved prompt lands at position 1. That matches the test above; an empty Inbox leaves position 0 unused, which is harmless because ordering is relative.

`app/Http/Middleware/HandleInertiaRequests.php` — extend `share()`:

```php
public function share(Request $request): array
{
    $user = $request->user();

    return [
        ...parent::share($request),
        'name' => config('app.name'),
        'auth' => [
            'user' => $user,
        ],
        'projects' => $user
            ? ProjectResource::collection(
                $user->projects()
                    ->withCount(['prompts as open_prompts_count' => fn (Builder $query) => $query->whereIn('status', [
                        PromptStatus::Todo->value,
                        PromptStatus::Implementing->value,
                    ])])
                    ->orderBy('name')
                    ->get()
            )
            : [],
        'tags' => $user ? $user->tags()->orderBy('name')->pluck('name')->all() : [],
        'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
    ];
}
```

Add imports `App\Enums\PromptStatus`, `App\Http\Resources\ProjectResource`, `Illuminate\Database\Eloquent\Builder`.

`routes/web.php`:

```php
Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=ProjectManagementTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Run the whole backend suite**

Run: `php artisan test --compact`
Expected: PASS — all tests including the pre-existing auth and settings tests

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http routes/web.php tests/Feature/Projects/ProjectManagementTest.php
git commit -m "feat: manage projects and share them with every authenticated page"
```

---

### Task 12: Frontend types, colour map and the sidebar

**Files:**
- Create: `resources/js/types/prompts.ts`
- Create: `resources/js/lib/projectColors.ts`
- Create: `resources/js/components/projects/ProjectSidebarNav.vue`
- Modify: `resources/js/types/index.ts`
- Modify: `resources/js/components/AppSidebar.vue`

**Interfaces:**
- Consumes: the shared props and resource shapes from Tasks 7 and 11
- Produces:
  - Types `PromptStatus`, `PromptPriority`, `ProjectColor`, `Prompt`, `Project`, `PromptFilters` exported from `@/types`
  - `PROJECT_DOT_CLASSES: Record<ProjectColor, string>` — complete Tailwind class literals
  - `ProjectSidebarNav.vue` rendering All / Inbox / each project with its dot and open count

The shared props are not typed by default. `resources/js/types/global.d.ts` declares them inside `declare module '@inertiajs/core'` as `InertiaConfig['sharedPageProps']`, which currently lists `name`, `auth`, `sidebarOpen` and an `[key: string]: unknown` catch-all. That catch-all means `usePage().props.projects` resolves to `unknown` and every use of it fails type-checking, so the two new keys must be added explicitly.

- [ ] **Step 1: Write the types**

`resources/js/types/prompts.ts`:

```ts
export type PromptStatus = 'todo' | 'implementing' | 'done';

export type PromptPriority = 'low' | 'normal' | 'high';

export type ProjectColor =
    | 'slate'
    | 'rose'
    | 'amber'
    | 'emerald'
    | 'sky'
    | 'violet';

export type Prompt = {
    id: number;
    title: string;
    rawTitle: string | null;
    body: string;
    status: PromptStatus;
    priority: PromptPriority;
    position: number;
    projectId: number | null;
    tags: string[];
    updatedAt: string | null;
};

export type Project = {
    id: number;
    name: string;
    color: ProjectColor;
    openPromptsCount: number;
};

export type PromptFilters = {
    project: string | null;
    q: string | null;
    status: PromptStatus[];
    priority: PromptPriority[];
    tags: string[];
};
```

Add to `resources/js/types/index.ts`:

```ts
export * from './prompts';
```

Edit the `sharedPageProps` block in `resources/js/types/global.d.ts` so it reads:

```ts
declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            projects: Project[];
            tags: string[];
            [key: string]: unknown;
        };
    }
}
```

and add the import at the top of the file, alongside the existing `Auth` import:

```ts
import type { Project } from '@/types/prompts';
```

- [ ] **Step 2: Write the colour map**

`resources/js/lib/projectColors.ts` — every class is a complete literal so Tailwind v4 can find it:

```ts
import type { ProjectColor } from '@/types';

export const PROJECT_DOT_CLASSES: Record<ProjectColor, string> = {
    slate: 'bg-slate-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
    emerald: 'bg-emerald-500',
    sky: 'bg-sky-500',
    violet: 'bg-violet-500',
};

export const PROJECT_COLORS: ProjectColor[] = [
    'slate',
    'rose',
    'amber',
    'emerald',
    'sky',
    'violet',
];
```

- [ ] **Step 3: Write the sidebar nav**

`resources/js/components/projects/ProjectSidebarNav.vue`:

```vue
<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Inbox, Layers } from '@lucide/vue';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { PROJECT_DOT_CLASSES } from '@/lib/projectColors';
import { index } from '@/routes/prompts';

const page = usePage();

const projects = computed(() => page.props.projects);

const currentProject = computed<string | null>(() => {
    const value = new URL(page.url, 'http://localhost').searchParams.get(
        'project',
    );

    return value;
});
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Prompts</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton
                    as-child
                    :is-active="currentProject === null"
                    tooltip="All prompts"
                >
                    <Link :href="index()">
                        <Layers />
                        <span>All</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>

            <SidebarMenuItem>
                <SidebarMenuButton
                    as-child
                    :is-active="currentProject === 'inbox'"
                    tooltip="Inbox"
                >
                    <Link :href="index({ query: { project: 'inbox' } })">
                        <Inbox />
                        <span>Inbox</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>

            <SidebarMenuItem v-for="project in projects" :key="project.id">
                <SidebarMenuButton
                    as-child
                    :is-active="currentProject === String(project.id)"
                    :tooltip="project.name"
                >
                    <Link
                        :href="index({ query: { project: String(project.id) } })"
                    >
                        <span
                            class="size-2 shrink-0 rounded-full"
                            :class="PROJECT_DOT_CLASSES[project.color]"
                        />
                        <span class="truncate">{{ project.name }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuBadge v-if="project.openPromptsCount > 0">
                    {{ project.openPromptsCount }}
                </SidebarMenuBadge>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
```

Add it to `resources/js/components/AppSidebar.vue` inside `<SidebarContent>`, above the existing `<NavMain>`:

```vue
<ProjectSidebarNav />
```

with `import ProjectSidebarNav from '@/components/projects/ProjectSidebarNav.vue';`.

- [ ] **Step 4: Generate Wayfinder routes and type-check**

```bash
php artisan wayfinder:generate
npm run types:check
npm run lint:check
```

Expected: no errors. `SidebarMenuBadge` is exported by `@/components/ui/sidebar` — verified against the installed component set.

- [ ] **Step 5: Commit**

```bash
git add resources/js
git commit -m "feat: add prompt types, project colours and the project sidebar"
```

---

### Task 13: The workbench page — capture, filters and rows

**Files:**
- Create: `resources/js/pages/prompts/Index.vue`
- Create: `resources/js/components/prompts/QuickCapture.vue`
- Create: `resources/js/components/prompts/FilterBar.vue`
- Create: `resources/js/components/prompts/PromptRow.vue`
- Create: `resources/js/components/prompts/PromptList.vue`
- Create: `resources/js/composables/usePromptFilters.ts`
- Create: `resources/js/composables/useCopyPrompt.ts`

**Interfaces:**
- Consumes: props `prompts: Prompt[]`, `filters: PromptFilters`, `canReorder: boolean` (Task 7); shared `projects`, `tags` (Task 11); Wayfinder actions for `PromptController`, `PromptStatusController`
- Produces:
  - `usePromptFilters()` returning `{ filters, setFilter(key, value), search }` — writes the query string via `router.get` with `preserveState: true`, `preserveScroll: true`, `replace: true`, debounced 250ms for the search term
  - `useCopyPrompt()` returning `{ copy(prompt: Prompt): Promise<void> }` — clipboard first, status PATCH only on success, fallback selection plus a toast when the Clipboard API is unavailable
  - `PromptList.vue` props `{ prompts: Prompt[]; canReorder: boolean }`, emits `edit` with a `Prompt`

- [ ] **Step 1: Write the filter composable**

`resources/js/composables/usePromptFilters.ts`:

```ts
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed } from 'vue';
import { index } from '@/routes/prompts';
import type { PromptFilters } from '@/types';

type FilterValue = string | string[] | null;

export function usePromptFilters(current: () => PromptFilters) {
    const filters = computed(current);

    const visit = (next: Record<string, FilterValue>): void => {
        const query: Record<string, FilterValue> = {
            project: filters.value.project,
            q: filters.value.q,
            status: filters.value.status,
            priority: filters.value.priority,
            tags: filters.value.tags,
            ...next,
        };

        Object.keys(query).forEach((key) => {
            const value = query[key];

            if (value === null || value === '' || (Array.isArray(value) && value.length === 0)) {
                delete query[key];
            }
        });

        router.get(index.url(), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['prompts', 'filters', 'canReorder'],
        });
    };

    const setFilter = (key: keyof PromptFilters, value: FilterValue): void => {
        visit({ [key]: value });
    };

    const search = useDebounceFn((term: string): void => {
        visit({ q: term });
    }, 250);

    return { filters, setFilter, search };
}
```

`index.url()` returns the bare path without a query string, which is what `router.get` needs — the query object is passed separately as the second argument.

- [ ] **Step 2: Write the copy composable**

`resources/js/composables/useCopyPrompt.ts`:

```ts
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import PromptStatusController from '@/actions/App/Http/Controllers/PromptStatusController';
import type { Prompt } from '@/types';

export function useCopyPrompt() {
    const copy = async (prompt: Prompt): Promise<void> => {
        const clipboard = navigator.clipboard;

        if (!clipboard || !window.isSecureContext) {
            selectBody(prompt.id);
            toast.error('Clipboard unavailable — the text is selected, copy it manually.');

            return;
        }

        try {
            await clipboard.writeText(prompt.body);
        } catch {
            selectBody(prompt.id);
            toast.error('Copy failed — the text is selected, copy it manually.');

            return;
        }

        toast.success('Copied to clipboard.');

        if (prompt.status !== 'todo') {
            return;
        }

        router.patch(
            PromptStatusController.url({ prompt: prompt.id }),
            {},
            { preserveScroll: true, preserveState: true, only: ['prompts'] },
        );
    };

    const selectBody = (promptId: number): void => {
        const element = document.querySelector<HTMLElement>(
            `[data-prompt-body="${promptId}"]`,
        );

        if (!element) {
            return;
        }

        const range = document.createRange();
        range.selectNodeContents(element);
        window.getSelection()?.removeAllRanges();
        window.getSelection()?.addRange(range);
    };

    return { copy };
}
```

The `prompt.status !== 'todo'` guard is a UX nicety that avoids a pointless request. The server enforces the same rule (Task 9), so a stale client cannot resurrect a done prompt.

- [ ] **Step 3: Write QuickCapture**

`resources/js/components/prompts/QuickCapture.vue`:

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import PromptController from '@/actions/App/Http/Controllers/PromptController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

const { projectId } = defineProps<{ projectId: number | null }>();

const textarea = ref<HTMLTextAreaElement | null>(null);

const form = useForm<{ body: string; project: number | null }>({
    body: '',
    project: null,
});

onMounted(() => {
    textarea.value?.focus();
});

const submit = (): void => {
    if (form.body.trim() === '') {
        return;
    }

    form.project = projectId;

    form.post(PromptController.store.url(), {
        preserveScroll: true,
        only: ['prompts', 'projects', 'canReorder'],
        onSuccess: () => {
            form.reset('body');
            textarea.value?.focus();
        },
    });
};
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border">
        <textarea
            ref="textarea"
            v-model="form.body"
            rows="3"
            placeholder="Type a prompt… ⌘/Ctrl + Enter to save"
            class="w-full resize-y bg-transparent font-mono text-sm outline-none placeholder:text-muted-foreground"
            @keydown.enter.meta.prevent="submit"
            @keydown.enter.ctrl.prevent="submit"
        />
        <InputError class="mt-2" :message="form.errors.body" />
        <div class="mt-2 flex justify-end">
            <Button size="sm" :disabled="form.processing" @click="submit">
                Capture
            </Button>
        </div>
    </div>
</template>
```

- [ ] **Step 4: Write FilterBar**

`resources/js/components/prompts/FilterBar.vue`:

```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import type { PromptFilters, PromptPriority, PromptStatus } from '@/types';

const { filters } = defineProps<{ filters: PromptFilters }>();

const emit = defineEmits<{
    search: [term: string];
    toggleStatus: [status: PromptStatus];
    togglePriority: [priority: PromptPriority];
    toggleTag: [tag: string];
}>();

const page = usePage();
const term = ref(filters.q ?? '');

watch(
    () => filters.q,
    (value) => {
        term.value = value ?? '';
    },
);

const statuses: PromptStatus[] = ['todo', 'implementing', 'done'];
const priorities: PromptPriority[] = ['high', 'normal', 'low'];
const tags = computed(() => page.props.tags);
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Input
            v-model="term"
            type="search"
            placeholder="Search prompts…"
            class="max-w-xs"
            @input="emit('search', term)"
        />

        <div class="flex gap-1">
            <Badge
                v-for="status in statuses"
                :key="status"
                :variant="filters.status.includes(status) ? 'default' : 'outline'"
                class="cursor-pointer capitalize"
                @click="emit('toggleStatus', status)"
            >
                {{ status }}
            </Badge>
        </div>

        <div class="flex gap-1">
            <Badge
                v-for="priority in priorities"
                :key="priority"
                :variant="filters.priority.includes(priority) ? 'default' : 'outline'"
                class="cursor-pointer capitalize"
                @click="emit('togglePriority', priority)"
            >
                {{ priority }}
            </Badge>
        </div>

        <div v-if="tags.length > 0" class="flex flex-wrap gap-1">
            <Badge
                v-for="tag in tags"
                :key="tag"
                :variant="filters.tags.includes(tag) ? 'default' : 'outline'"
                class="cursor-pointer"
                @click="emit('toggleTag', tag)"
            >
                #{{ tag }}
            </Badge>
        </div>
    </div>
</template>
```

- [ ] **Step 5: Write PromptRow and PromptList**

`resources/js/components/prompts/PromptRow.vue`:

```vue
<script setup lang="ts">
import { Copy, GripVertical, Pencil } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCopyPrompt } from '@/composables/useCopyPrompt';
import type { Prompt } from '@/types';

const { prompt, draggable } = defineProps<{
    prompt: Prompt;
    draggable: boolean;
}>();

const emit = defineEmits<{ edit: [prompt: Prompt] }>();

const { copy } = useCopyPrompt();
</script>

<template>
    <div
        class="flex items-start gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
    >
        <GripVertical
            v-if="draggable"
            class="prompt-drag-handle mt-1 size-4 shrink-0 cursor-grab text-muted-foreground"
        />

        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium">{{ prompt.title }}</p>
            <p
                :data-prompt-body="prompt.id"
                class="mt-1 line-clamp-2 font-mono text-xs text-muted-foreground"
            >
                {{ prompt.body }}
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-1">
                <Badge variant="outline" class="capitalize">{{ prompt.status }}</Badge>
                <Badge
                    v-if="prompt.priority !== 'normal'"
                    variant="secondary"
                    class="capitalize"
                >
                    {{ prompt.priority }}
                </Badge>
                <Badge v-for="tag in prompt.tags" :key="tag" variant="outline">
                    #{{ tag }}
                </Badge>
            </div>
        </div>

        <div class="flex shrink-0 gap-1">
            <Button
                size="icon"
                variant="ghost"
                aria-label="Copy prompt"
                @click="copy(prompt)"
            >
                <Copy class="size-4" />
            </Button>
            <Button
                size="icon"
                variant="ghost"
                aria-label="Edit prompt"
                @click="emit('edit', prompt)"
            >
                <Pencil class="size-4" />
            </Button>
        </div>
    </div>
</template>
```

`resources/js/components/prompts/PromptList.vue` — non-draggable for now; Task 15 adds the drag wrapper:

```vue
<script setup lang="ts">
import PromptRow from '@/components/prompts/PromptRow.vue';
import type { Prompt } from '@/types';

const { prompts, canReorder } = defineProps<{
    prompts: Prompt[];
    canReorder: boolean;
}>();

const emit = defineEmits<{ edit: [prompt: Prompt] }>();
</script>

<template>
    <div class="flex flex-col gap-2">
        <p
            v-if="prompts.length === 0"
            class="rounded-lg border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Nothing here yet. Type a prompt above to capture one.
        </p>

        <PromptRow
            v-for="prompt in prompts"
            :key="prompt.id"
            :prompt="prompt"
            :draggable="canReorder"
            @edit="emit('edit', $event)"
        />
    </div>
</template>
```

- [ ] **Step 6: Write the page**

`resources/js/pages/prompts/Index.vue`:

```vue
<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FilterBar from '@/components/prompts/FilterBar.vue';
import PromptList from '@/components/prompts/PromptList.vue';
import QuickCapture from '@/components/prompts/QuickCapture.vue';
import { usePromptFilters } from '@/composables/usePromptFilters';
import { index } from '@/routes/prompts';
import type { Prompt, PromptFilters, PromptPriority, PromptStatus } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Prompts', href: index() }],
    },
});

const props = defineProps<{
    prompts: Prompt[];
    filters: PromptFilters;
    canReorder: boolean;
}>();

const page = usePage();
const editing = ref<Prompt | null>(null);

const { filters, setFilter, search } = usePromptFilters(() => props.filters);

const captureProjectId = computed<number | null>(() => {
    const project = filters.value.project;

    if (project === null || project === 'inbox') {
        return null;
    }

    return Number(project);
});

const heading = computed<string>(() => {
    const project = filters.value.project;

    if (project === null) {
        return 'All prompts';
    }

    if (project === 'inbox') {
        return 'Inbox';
    }

    return (
        page.props.projects.find((candidate) => String(candidate.id) === project)
            ?.name ?? 'Prompts'
    );
});

const toggle = <T extends string>(list: T[], value: T): T[] =>
    list.includes(value)
        ? list.filter((entry) => entry !== value)
        : [...list, value];
</script>

<template>
    <Head title="Prompts" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4">
        <h1 class="text-lg font-semibold">{{ heading }}</h1>

        <QuickCapture :project-id="captureProjectId" />

        <FilterBar
            :filters="filters"
            @search="search"
            @toggle-status="setFilter('status', toggle<PromptStatus>(filters.status, $event))"
            @toggle-priority="setFilter('priority', toggle<PromptPriority>(filters.priority, $event))"
            @toggle-tag="setFilter('tags', toggle<string>(filters.tags, $event))"
        />

        <PromptList
            :prompts="props.prompts"
            :can-reorder="props.canReorder"
            @edit="editing = $event"
        />
    </div>
</template>
```

- [ ] **Step 7: Verify the build**

```bash
php artisan wayfinder:generate
npm run types:check
npm run lint:check
npm run build
```

Expected: all four succeed. Fix any type errors by aligning with the resource shapes in Task 7 — do not widen types to `any`.

- [ ] **Step 8: Commit**

```bash
git add resources/js
git commit -m "feat: add the prompt workbench with capture, filters and copy"
```

---

### Task 14: The edit slide-over

**Files:**
- Create: `resources/js/components/prompts/PromptEditSheet.vue`
- Create: `resources/js/components/prompts/TagInput.vue`
- Modify: `resources/js/pages/prompts/Index.vue` (mount the sheet)

**Interfaces:**
- Consumes: `Prompt` type, Wayfinder `PromptController.update` / `PromptController.destroy`, shared `projects` and `tags`
- Produces: `PromptEditSheet.vue` with props `{ prompt: Prompt | null }` and an `update:open` style `close` emit; `TagInput.vue` with `v-model` of `string[]` and datalist autocomplete over the shared tags

- [ ] **Step 1: Write TagInput**

`resources/js/components/prompts/TagInput.vue`:

```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';

const model = defineModel<string[]>({ required: true });

const page = usePage();
const draft = ref('');

const suggestions = computed(() =>
    page.props.tags.filter((tag) => !model.value.includes(tag)),
);

const add = (): void => {
    const name = draft.value.trim();

    if (name === '' || model.value.includes(name)) {
        draft.value = '';

        return;
    }

    model.value = [...model.value, name];
    draft.value = '';
};

const remove = (name: string): void => {
    model.value = model.value.filter((tag) => tag !== name);
};
</script>

<template>
    <div class="flex flex-col gap-2">
        <div v-if="model.length > 0" class="flex flex-wrap gap-1">
            <Badge v-for="tag in model" :key="tag" variant="secondary">
                #{{ tag }}
                <button
                    type="button"
                    class="ml-1"
                    :aria-label="`Remove ${tag}`"
                    @click="remove(tag)"
                >
                    <X class="size-3" />
                </button>
            </Badge>
        </div>

        <Input
            v-model="draft"
            list="tag-suggestions"
            placeholder="Add a tag and press Enter"
            @keydown.enter.prevent="add"
        />

        <datalist id="tag-suggestions">
            <option v-for="tag in suggestions" :key="tag" :value="tag" />
        </datalist>
    </div>
</template>
```

- [ ] **Step 2: Write PromptEditSheet**

`resources/js/components/prompts/PromptEditSheet.vue`:

```vue
<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import PromptController from '@/actions/App/Http/Controllers/PromptController';
import InputError from '@/components/InputError.vue';
import TagInput from '@/components/prompts/TagInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { Prompt, PromptPriority, PromptStatus } from '@/types';

const { prompt } = defineProps<{ prompt: Prompt | null }>();

const emit = defineEmits<{ close: [] }>();

const page = usePage();
const projects = computed(() => page.props.projects);

const form = useForm<{
    title: string;
    body: string;
    status: PromptStatus;
    priority: PromptPriority;
    project: string;
    tags: string[];
}>({
    title: '',
    body: '',
    status: 'todo',
    priority: 'normal',
    project: 'inbox',
    tags: [],
});

watch(
    () => prompt,
    (value) => {
        if (!value) {
            return;
        }

        form.defaults({
            title: value.rawTitle ?? '',
            body: value.body,
            status: value.status,
            priority: value.priority,
            project: value.projectId === null ? 'inbox' : String(value.projectId),
            tags: [...value.tags],
        });
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

const save = (): void => {
    if (!prompt) {
        return;
    }

    form
        .transform((data) => ({
            ...data,
            title: data.title.trim() === '' ? null : data.title,
            project: data.project === 'inbox' ? null : Number(data.project),
        }))
        .patch(PromptController.update.url({ prompt: prompt.id }), {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
};

const destroy = (): void => {
    if (!prompt) {
        return;
    }

    form.delete(PromptController.destroy.url({ prompt: prompt.id }), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <Sheet
        :open="prompt !== null"
        @update:open="(open: boolean) => !open && emit('close')"
    >
        <SheetContent class="flex w-full flex-col gap-4 overflow-y-auto sm:max-w-lg">
            <SheetHeader>
                <SheetTitle>Edit prompt</SheetTitle>
                <SheetDescription>
                    Leave the title empty to use the first line of the body.
                </SheetDescription>
            </SheetHeader>

            <div class="grid gap-2">
                <Label for="prompt-title">Title</Label>
                <Input id="prompt-title" v-model="form.title" placeholder="Optional" />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid gap-2">
                <Label for="prompt-body">Prompt</Label>
                <textarea
                    id="prompt-body"
                    v-model="form.body"
                    rows="12"
                    class="w-full rounded-md border border-input bg-transparent p-2 font-mono text-sm outline-none"
                />
                <InputError :message="form.errors.body" />
            </div>

            <div class="grid gap-2">
                <Label>Project</Label>
                <Select v-model="form.project">
                    <SelectTrigger>
                        <SelectValue placeholder="Inbox" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="inbox">Inbox</SelectItem>
                        <SelectItem
                            v-for="project in projects"
                            :key="project.id"
                            :value="String(project.id)"
                        >
                            {{ project.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.project" />
            </div>

            <div class="grid gap-2">
                <Label>Status</Label>
                <Select v-model="form.status">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="todo">Todo</SelectItem>
                        <SelectItem value="implementing">Implementing</SelectItem>
                        <SelectItem value="done">Done</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-2">
                <Label>Priority</Label>
                <Select v-model="form.priority">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="low">Low</SelectItem>
                        <SelectItem value="normal">Normal</SelectItem>
                        <SelectItem value="high">High</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-2">
                <Label>Tags</Label>
                <TagInput v-model="form.tags" />
            </div>

            <div class="mt-auto flex items-center justify-between gap-2 pt-4">
                <Button variant="destructive" :disabled="form.processing" @click="destroy">
                    Delete
                </Button>
                <Button :disabled="form.processing" @click="save">Save</Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
```

- [ ] **Step 3: Mount it on the page**

In `resources/js/pages/prompts/Index.vue`, add the import and render it after `<PromptList>`:

```vue
<PromptEditSheet :prompt="editing" @close="editing = null" />
```

- [ ] **Step 4: Verify the build**

```bash
npm run types:check
npm run lint:check
npm run build
```

Expected: all three succeed.

- [ ] **Step 5: Commit**

```bash
git add resources/js
git commit -m "feat: add the prompt edit slide-over with tags and project moves"
```

---

### Task 15: Drag reordering

The one approved dependency lands here.

**Files:**
- Modify: `package.json` (add `sortablejs`, `vuedraggable`)
- Modify: `resources/js/components/prompts/PromptList.vue`
- Modify: `resources/js/pages/prompts/Index.vue` (pass the new `projectId` prop)

**Interfaces:**
- Consumes: `canReorder` prop (Task 7), route `prompts.reorder` (Task 10)
- Produces: `PromptList.vue` renders a `<draggable>` wrapper when `canReorder` is true, PATCHes the new order, and reverts the local order if the request fails

- [ ] **Step 1: Install the dependency**

```bash
npm install sortablejs vuedraggable@next
npm install --save-dev @types/sortablejs
```

Confirm `vuedraggable` resolves to version 4.x — that is the Vue 3 build. Version 2.x is Vue 2 and will not work.

Run: `npm ls vuedraggable`
Expected: `vuedraggable@4.x.x`

- [ ] **Step 2: Rewrite PromptList with the drag wrapper**

`resources/js/components/prompts/PromptList.vue`:

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
import PromptRow from '@/components/prompts/PromptRow.vue';
import { reorder } from '@/routes/prompts';
import type { Prompt } from '@/types';

const { prompts, canReorder, projectId } = defineProps<{
    prompts: Prompt[];
    canReorder: boolean;
    projectId: number | null;
}>();

const emit = defineEmits<{ edit: [prompt: Prompt] }>();

const ordered = ref<Prompt[]>([...prompts]);

watch(
    () => prompts,
    (value) => {
        ordered.value = [...value];
    },
);

const persist = (): void => {
    const snapshot = [...prompts];

    router.patch(
        reorder.url(),
        {
            project: projectId,
            ids: ordered.value.map((prompt) => prompt.id),
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['prompts'],
            onError: () => {
                ordered.value = snapshot;
            },
        },
    );
};
</script>

<template>
    <div class="flex flex-col gap-2">
        <p
            v-if="ordered.length === 0"
            class="rounded-lg border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Nothing here yet. Type a prompt above to capture one.
        </p>

        <draggable
            v-else-if="canReorder"
            v-model="ordered"
            item-key="id"
            handle=".prompt-drag-handle"
            class="flex flex-col gap-2"
            @end="persist"
        >
            <template #item="{ element }: { element: Prompt }">
                <PromptRow
                    :prompt="element"
                    :draggable="true"
                    @edit="emit('edit', $event)"
                />
            </template>
        </draggable>

        <template v-else>
            <PromptRow
                v-for="prompt in ordered"
                :key="prompt.id"
                :prompt="prompt"
                :draggable="false"
                @edit="emit('edit', $event)"
            />
        </template>
    </div>
</template>
```

- [ ] **Step 3: Pass the bucket id from the page**

In `resources/js/pages/prompts/Index.vue`, add `:project-id="captureProjectId"` to `<PromptList>`. `captureProjectId` already resolves `inbox` and `null` to `null`, which is exactly what the reorder endpoint expects for the Inbox bucket.

- [ ] **Step 4: Verify the build**

```bash
npm run types:check
npm run lint:check
npm run build
```

Expected: all three succeed. If `vuedraggable` has no bundled types, add a module declaration in `resources/js/types/vue-shims.d.ts`:

```ts
declare module 'vuedraggable' {
    import type { DefineComponent } from 'vue';

    const draggable: DefineComponent<Record<string, unknown>>;

    export default draggable;
}
```

- [ ] **Step 5: Confirm the reorder endpoint still passes**

Run: `php artisan test --compact --filter=ReorderTest`
Expected: PASS (7 tests)

- [ ] **Step 6: Commit**

```bash
git add package.json package-lock.json resources/js
git commit -m "feat: drag to reorder prompts within a single unfiltered bucket"
```

---

### Task 16: Project creation UI and final verification

**Files:**
- Create: `resources/js/components/projects/ProjectFormDialog.vue`
- Modify: `resources/js/components/projects/ProjectSidebarNav.vue` (add the "New project" trigger)

**Interfaces:**
- Consumes: Wayfinder `ProjectController.store`, `PROJECT_COLORS` and `PROJECT_DOT_CLASSES` (Task 12)
- Produces: a dialog for creating a project with a name and a colour swatch picker

- [ ] **Step 1: Write the dialog**

`resources/js/components/projects/ProjectFormDialog.vue`:

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PROJECT_COLORS, PROJECT_DOT_CLASSES } from '@/lib/projectColors';
import type { ProjectColor } from '@/types';

const open = ref(false);

const form = useForm<{ name: string; color: ProjectColor }>({
    name: '',
    color: 'slate',
});

const submit = (): void => {
    form.post(ProjectController.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>New project</DialogTitle>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="project-name">Name</Label>
                <Input
                    id="project-name"
                    v-model="form.name"
                    placeholder="Prompt Queue"
                    @keydown.enter.prevent="submit"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label>Colour</Label>
                <div class="flex gap-2">
                    <button
                        v-for="color in PROJECT_COLORS"
                        :key="color"
                        type="button"
                        :aria-label="color"
                        class="size-6 rounded-full ring-offset-2 ring-offset-background"
                        :class="[
                            PROJECT_DOT_CLASSES[color],
                            form.color === color ? 'ring-2 ring-ring' : '',
                        ]"
                        @click="form.color = color"
                    />
                </div>
                <InputError :message="form.errors.color" />
            </div>

            <div class="flex justify-end">
                <Button :disabled="form.processing" @click="submit">Create</Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
```

- [ ] **Step 2: Add the trigger to the sidebar**

In `resources/js/components/projects/ProjectSidebarNav.vue`, add below the project list, inside `<SidebarMenu>`:

```vue
<SidebarMenuItem>
    <ProjectFormDialog>
        <SidebarMenuButton>
            <Plus />
            <span>New project</span>
        </SidebarMenuButton>
    </ProjectFormDialog>
</SidebarMenuItem>
```

with `import { Plus } from '@lucide/vue';` and `import ProjectFormDialog from '@/components/projects/ProjectFormDialog.vue';`.

- [ ] **Step 3: Run the full verification sweep**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run types:check
npm run lint:check
npm run build
vendor/bin/phpstan analyse
```

Expected: every command succeeds. `phpstan` may report pre-existing issues in the starter kit — only fix ones in files this plan created or modified.

- [ ] **Step 4: Manually confirm the app runs**

```bash
php artisan migrate:fresh
composer run dev
```

Then log in and check, in order: capture a prompt with only a body; it appears at the top; the copy button copies and flips the status to implementing; create a project and capture into it; drag to reorder inside that project; search and confirm the drag handles disappear; open the edit sheet, add a tag, move the prompt, save.

- [ ] **Step 5: Commit**

```bash
git add resources/js
git commit -m "feat: create projects from the sidebar"
```

---

## Notes for the implementer

- **Route order matters twice.** `prompts/reorder` must precede `prompts/{prompt}`. Verify with `php artisan route:list --path=prompts`.
- **Migration order matters.** `create_prompts_table` must run before `create_prompt_tag_table`, which has a foreign key to it. Rename the migration files if the generator produces the wrong order.
- **`inBucket(null)` is not `where('project_id', null)`.** SQL `= NULL` never matches. The scope handles this; do not inline the condition anywhere.
- **Tailwind v4 cannot see interpolated class names.** Every colour class must exist as a complete literal string in `projectColors.ts`.
- **If a Wayfinder import path is wrong,** run `php artisan wayfinder:generate` and read the generated file under `resources/js/actions/` to get the exact export name.
