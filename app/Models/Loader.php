<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loader extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function remotes()
    {
        return $this->hasMany(LoaderRemote::class);
    }

    public function versions()
    {
        return $this->morphMany(Version::class, 'versionable');
    }

    /**
     * Relationship to versions, but without constraint on morph model name.
     * Do not use for saving the models!
     */
    public function all_versions()
    {
        return $this->belongsToMany(Version::class);
    }

    public function scopeHasVersionsFor(Builder $query, string $model, int $id): Builder
    {
        return $query->whereHas('all_versions', function (Builder $q) use ($id, $model) {
            $q->where('versionable_id', $id)
                ->where('versionable_type', Model::getActualClassNameForMorph($model));
        });
    }
}
