<?php

namespace App\API\DTO\Library;

use Illuminate\Support\Collection;

class LibraryRuleset
{
    /**
     * @param Collection<LibraryRule> $rules
     */
    public function __construct(protected Collection $rules)
    {
    }

    /**
     * Determine if given operating system name is allowed by rules.
     *
     * @param string $os "windows", "linux", "osx"
     * @return bool
     */
    public function allowsOS(string $os): bool
    {
        // No rules mean every OS is allowed.
        if ($this->rules->isEmpty())
            return true;

        // Check if this specific OS is disallowed (disallow takes precedence)
        if ($this->rules->contains(fn(LibraryRule $rule) => $rule->os === $os && $rule->isDisallow()))
            return false;
        // Check if this specific OS is allowed
        if ($this->rules->contains(fn(LibraryRule $rule) => $rule->os === $os && $rule->isAllow()))
            return true;
        // Check if there is a general allow rule
        if ($this->rules->contains(fn(LibraryRule $rule) => $rule->os === null && $rule->isAllow()))
            return true;

        // If we didn't match a rule for the OS, it is disallowed
        return false;
    }
}
