<?php

namespace App\API;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class ApiResponseTransformer
{
    /**
     * Transforms raw data to DTOs.
     *
     * @param class-string $class
     * @param array $data
     * @param array $extraParameters
     * @param bool $preserveKeys
     * @return Collection
     */
    public static function collection(string $class, array $data, array $extraParameters = [], bool $preserveKeys = false): Collection
    {
        $transformFn = 'to'.Str::afterLast($class, '\\');

        if ($preserveKeys) {
            return collect(Arr::mapWithKeys(
                $data,
                fn($i, $k) => [$k => static::$transformFn($i, ...$extraParameters)]
            ));
        } else {
            return collect(array_map(
                fn ($i) => static::$transformFn($i, ...$extraParameters),
                $data
            ));
        }
    }
}
