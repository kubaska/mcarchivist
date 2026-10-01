<?php

namespace App\Models;

use App\Enums\EProjectType;

class ProjectType extends Model
{
    protected $fillable = ['name', 'type'];
    protected $casts = [
        'type' => EProjectType::class
    ];
}
