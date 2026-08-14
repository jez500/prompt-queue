<?php

namespace App\Models;

use App\Enums\SsoProvider;
use Database\Factories\UserIdentityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A login at an external identity provider, bound to a local user.
 *
 * @property int $id
 * @property int $user_id
 * @property SsoProvider $provider
 * @property string $provider_user_id
 * @property string|null $email
 * @property string|null $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['provider', 'provider_user_id', 'email', 'name'])]
class UserIdentity extends Model
{
    /** @use HasFactory<UserIdentityFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => SsoProvider::class,
        ];
    }

    /**
     * The local user this identity signs in as.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
