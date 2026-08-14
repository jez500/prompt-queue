<?php

namespace App\Http\Middleware;

use App\Enums\PromptStatus;
use App\Http\Resources\ProjectResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
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
                )->resolve()
                : [],
            /* Only tags that are actually on a prompt. They are deleted when
               their last prompt goes, but rows orphaned before that ran would
               otherwise sit in the filter bar with nothing to filter. */
            'tags' => $user
                ? $user->tags()->whereHas('prompts')->orderBy('name')->pluck('name')->all()
                : [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
