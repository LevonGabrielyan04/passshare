<?php

namespace App\Models\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\BinaryCodec;

class BelongsToManyWithBinaryUlidParentKey extends BelongsToMany
{
    /**
     * Set the where clause for the relation query.
     *
     * @return $this
     */
    protected function addWhereConstraints()
    {
        $this->query->where(
            $this->getQualifiedForeignPivotKeyName(),
            '=',
            $this->encodeParentKey($this->parent->{$this->parentKey}),
        );

        return $this;
    }

    /** {@inheritDoc} */
    public function addEagerConstraints(array $models)
    {
        $whereIn = $this->whereInMethod($this->parent, $this->parentKey);

        $this->whereInEager(
            $whereIn,
            $this->getQualifiedForeignPivotKeyName(),
            $this->encodeParentKeys($models),
        );
    }

    /**
     * @param  array<int, Model>  $models
     * @return array<int, string>
     */
    protected function encodeParentKeys(array $models): array
    {
        return collect($models)
            ->map(fn (Model $model) => $this->encodeParentKey($model->{$this->parentKey}))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    protected function encodeParentKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        return BinaryCodec::encode($key, 'ulid');
    }
}
