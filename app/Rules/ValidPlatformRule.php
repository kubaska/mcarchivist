<?php

namespace App\Rules;

use App\Mca\ApiManager;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPlatformRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $manager = app(ApiManager::class);

        if (! $manager->has($value)) {
            $fail('Platform does not exist');
            return;
        }
    }
}
