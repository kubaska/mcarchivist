<?php

namespace App\Models;

use App\Enums\EProjectType;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjectType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type'];
    protected $casts = [
        'type' => EProjectType::class
    ];
}
