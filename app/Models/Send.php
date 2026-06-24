<?php

namespace App\Models;

use App\Models\Relations\BelongsToManyWithBinaryUlidParentKey;
use App\Models\Traits\HasPublicAndPrivateIds;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsBinary;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\BinaryCodec;
use Illuminate\Support\Str;

#[Table(key: 'id', keyType: 'string', incrementing: false)]
#[WithoutTimestamps]
class Send extends Model
{
    use HasPublicAndPrivateIds, HasUuids;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    protected $hidden = ['user_id', 'id'];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => AsBinary::ulid(),
            'public_id' => AsBinary::uuid(),
            'message' => 'encrypted',
        ];
    }

    /**
     * Resolve route model bindings using the public identifier.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Retrieve the model for a bound value, encoding the public identifier
     * to match the binary column it is stored in.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        if ($field === 'public_id' && Str::isUuid($value)) {
            $value = BinaryCodec::encode($value, 'uuid');
        }

        return $query->where($field, $value);
    }

    /**
     * Generate a new UUID for the public identifier.
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Determine if the given key is a valid unique identifier.
     *
     * @param  mixed  $value
     */
    protected function isValidUniqueId($value): bool
    {
        return Str::isUuid($value) || Str::isUlid($value);
    }

    /**
     * The users authorized for this send.
     *
     * @return BelongsToMany<User, $this, SendUser>
     */
    public function authorizedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'send_user')
            ->using(SendUser::class);
    }

    /**
     * @return BelongsToMany<User, $this, SendUser>
     */
    protected function newBelongsToMany(
        Builder $query,
        Model $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
    ): BelongsToMany {
        return new BelongsToManyWithBinaryUlidParentKey(
            $query,
            $parent,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName,
        );
    }

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['id', 'public_id'];
    }
}
