<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function children()
    {
        return $this->hasMany(static::class, 'parent_category_id', 'id');
    }

    public function project_types()
    {
        return $this->belongsToMany(ProjectType::class);
    }
}
