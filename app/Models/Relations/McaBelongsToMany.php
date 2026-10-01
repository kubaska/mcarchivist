<?php

namespace App\Models\Relations;

use Closure;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 * @template TPivotModel of \Illuminate\Database\Eloquent\Relations\Pivot = \Illuminate\Database\Eloquent\Relations\Pivot
 *
 * @extends \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, TDeclaringModel, \Illuminate\Database\Eloquent\Collection<int, TRelatedModel&object{pivot: TPivotModel}>>
 */
class McaBelongsToMany extends BelongsToMany
{
    /**
     * Get the first related model record matching the attributes *that belongs to parent model*, or instantiate it.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return TRelatedModel&object{pivot: TPivotModel}
     */
    public function firstOrNew(array $attributes = [], array $values = [])
    {
        if (is_null($instance = (clone $this)->where($attributes)->first())) {
            $instance = $this->related->newInstance(array_merge($attributes, $values));
        }

        return $instance;
    }

    /**
     * Get the first record matching the attributes, *that belongs to parent model*.
     * If the record is not found, create it.
     *
     * @param  array  $attributes
     * @param  (\Closure(): array)|array  $values
     * @param  array  $joining
     * @param  bool  $touch
     * @return TRelatedModel&object{pivot: TPivotModel}
     */
    public function firstOrCreate(array $attributes = [], array|Closure $values = [], array $joining = [], $touch = true)
    {
        if (is_null($instance = (clone $this)->where($attributes)->first())) {
            $instance = $this->createOrFirst($attributes, $values, $joining, $touch);
        }

        return $instance;
    }
}
