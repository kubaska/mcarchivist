<?php

namespace App\Casts;

use App\Support\HashList;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AsHashListCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     * @return HashList
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): HashList
    {
        if (is_string($value)) {
            if (! $value) return new HashList([]);

            $hashes = Arr::mapWithKeys(explode(',', $value), function (string $hash) {
                $parts = explode(':', $hash, 2);
                return [$parts[0] => $parts[1]];
            });
            return new HashList($hashes);
        }

        throw new \InvalidArgumentException('The given value is not a string');
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes)
    {
        if (! $value instanceof HashList) {
            throw new \InvalidArgumentException('The given value is not a HashList instance.');
        }

        return implode(',', Arr::map($value->all(), fn(string $hash, string $algo) => "$algo:$hash"));
    }
}
