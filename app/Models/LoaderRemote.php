<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoaderRemote extends Model
{
    protected $fillable = ['remote_id', 'platform'];

//    public function loader()
//    {
//        return $this->belongsTo(Loader::class);
//    }

    public function project_types()
    {
        return $this->belongsToMany(ProjectType::class);
    }
}
