<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ruleset extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'custom'];

    // Relations

    public function archive_rules()
    {
        return $this->morphMany(ArchiveRule::class, 'ruleable');
    }
}
