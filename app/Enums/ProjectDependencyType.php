<?php

namespace App\Enums;

enum ProjectDependencyType: int
{
    case REQUIRED = 0;
    case OPTIONAL = 1;
    case INCOMPATIBLE = 2;
    case EMBEDDED = 3;

    public static function fromName(string $name): ProjectDependencyType
    {
        return match($name) {
            'required' => self::REQUIRED,
            'optional' => self::OPTIONAL,
            'incompatible' => self::INCOMPATIBLE,
            'embedded' => self::EMBEDDED
        };
    }

    public function name(): string
    {
        return match($this) {
            self::REQUIRED => 'required',
            self::OPTIONAL => 'optional',
            self::INCOMPATIBLE => 'incompatible',
            self::EMBEDDED => 'embedded'
        };
    }
}
