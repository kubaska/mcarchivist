<?php

namespace App\API\DTO;

use App\Models\Library;
use App\Support\HashList;
use Illuminate\Contracts\Support\Arrayable;

class FileDetailsDTO extends DTO implements Arrayable
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly HashList $hashes,
        public readonly int $size
    )
    {
    }

    public static function fromLibrary(Library $library): FileDetailsDTO
    {
        return new self($library->getFileName(), $library->getFilePath(), $library->hashes, $library->size);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'hashes' => $this->hashes->toArray(),
            'size' => $this->size
        ];
    }
}
