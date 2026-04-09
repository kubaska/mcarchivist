<?php

namespace App\API\DTO\Library;

class LibraryRule
{
    public function __construct(
        public readonly string $action,
        public readonly ?string $os,
        public readonly ?string $version
    )
    {
    }

    public function isAllow(): bool
    {
        return $this->action === 'allow';
    }

    public function isDisallow(): bool
    {
        return $this->action === 'disallow';
    }
}
