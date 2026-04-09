<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\Filesystem\Path;

class WritableDirectoryPathRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Path::isRelative($value)) {
            $fail('The :attribute can not be a relative path.');
            return;
        }

        if (! is_dir($value)) {
            $fail('The :attribute path does not exist.');
            return;
        }

        if (! is_writable($value)) {
            $fail('The :attribute path is not writable.');
        }
    }
}
