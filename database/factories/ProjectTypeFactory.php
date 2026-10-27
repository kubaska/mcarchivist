<?php

namespace Database\Factories;

use App\Enums\EProjectType;
use App\Models\ProjectType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectTypeFactory extends Factory
{
    protected $model = ProjectType::class;

    public function definition(): array
    {
        return [
            'name' => 'Mod',
            'type' => EProjectType::MOD->value
        ];
    }

    public function fromEnum(EProjectType $type)
    {
        return $this->state(['name' => $type->name(), 'type' => $type->value]);
    }
}
