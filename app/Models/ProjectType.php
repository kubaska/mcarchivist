<?php

namespace App\Models;

use App\Enums\EProjectType;
use Illuminate\Database\Eloquent\Model;

class ProjectType extends Model
{
    protected $fillable = ['name', 'type'];
    protected $casts = [
        'type' => EProjectType::class
    ];
}
