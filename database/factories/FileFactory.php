<?php

namespace Database\Factories;

use App\Models\File;
use App\Support\HashList;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class FileFactory extends Factory
{
    protected $model = File::class;

    protected static int $remoteId = 100;

    public function definition(): array
    {
        $fileName = Str::random(8);
        $sha1 = Str::random(64);

        return [
            'remote_id' => ++static::$remoteId,
            'component' => null,
            'side' => null,
            'path' => 'files',
            'file_name' => $fileName.'.jar',
            'original_file_name' => $fileName.'_orig.jar',
            'hashes' => new HashList(['sha1' => $sha1]),
            'size' => mt_rand(999, 999999),
            'primary' => true,
            'created_by' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(fn(File $file) => $file->fill(['id' => $file->remote_id]));
    }

    public function withHash(array $hashes): Factory
    {
        return $this->state(fn(array $attributes) => ['hashes' => new HashList($hashes)]);
    }

    public function createdByUser(): Factory
    {
        return $this->state(fn(array $attributes) => ['created_by' => 1]);
    }
}
