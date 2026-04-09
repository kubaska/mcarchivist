<?php

namespace App\Models;

use App\Enums\VersionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameVersion extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'official', 'released_at'];

    protected $casts = [
        'type' => VersionType::class,
        'official' => 'boolean',
        'released_at' => 'datetime'
    ];

    public function version()
    {
        return $this->morphOne(Version::class, 'versionable');
    }

    public function scopeOfficial(Builder $query): Builder
    {
        return $query->where('official', true);
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
