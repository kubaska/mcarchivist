<?php

namespace App\Rules;

use App\Exceptions\PlatformDisabledException;
use App\Exceptions\PlatformNotFoundException;
use App\Mca\ApiManager;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPlatformRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $manager = app(ApiManager::class);

        try {
            $manager->get($value);
        } catch (PlatformNotFoundException) {
            $fail('Platform does not exist');
        } catch (PlatformDisabledException) {
            $fail('Platform is disabled');
        }
    }
}
