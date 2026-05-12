<?php

namespace App\Models;

use App\Enums\DependencyQualifier;
use App\Enums\VersionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArchiveRule extends Model
{
    use HasFactory;

    protected $casts = [
        'with_snapshots' => 'boolean',
        'release_type' => VersionType::class,
        'release_type_priority' => 'boolean',
        'sorting' => 'boolean',
        'dependencies' => DependencyQualifier::class
    ];

    public function ruleable()
    {
        return $this->morphTo();
    }

    public function loader()
    {
        return $this->belongsTo(Loader::class);
    }

    /**
     * Check if versions should be sorted in descending order (by newest).
     *
     * @return bool
     */
    public function getSortingDescAttribute(): bool
    {
        return $this->sorting === false;
    }
}
