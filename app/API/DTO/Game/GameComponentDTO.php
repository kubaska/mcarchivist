<?php

namespace App\API\DTO\Game;

use App\API\DTO\DTO;
use App\API\DTO\FileDTO;
use Illuminate\Support\Str;

class GameComponentDTO extends DTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $url,
        public readonly string $hash,
        public readonly int $size
    )
    {
    }

    public function getFileName(): string
    {
        return Str::afterLast($this->url, '/');
    }

    public static function fromMojang(string $name, array $component): GameComponentDTO
    {
        return new self(
            $name,
            $component['url'],
            $component['sha1'],
            $component['size'],
        );
    }

    public function toFileDTO(): FileDTO
    {
        return FileDTO::fromMojang(
            ['url' => $this->url, 'sha1' => $this->hash, 'size' => $this->size],
            $this->name,
            $this->getFileName()
        );
    }
}
