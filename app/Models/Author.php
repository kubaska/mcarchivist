<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Author extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function projects()
    {
        return $this->belongsToMany(Author::class, 'authors_projects')->withPivot(['role']);
    }
}
