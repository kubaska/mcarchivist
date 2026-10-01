<?php

namespace App\Models;

use App\Models\Concerns\HasMcaRelationships;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class Model extends EloquentModel
{
    use HasMcaRelationships;
}
