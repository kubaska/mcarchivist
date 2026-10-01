<?php

namespace App\API\DTO;

use App\API\Mojang;
use App\Enums\FileSide;
use App\Models\File;
use App\Support\HashList;
use App\Support\Utils;
use Illuminate\Contracts\Support\Arrayable;

class FileDTO extends DTO implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly string $remoteId,
        public readonly ?string $component,
        public readonly ?FileSide $side,
        public ?string $dir,
        public readonly string $name,
        public readonly ?string $url,
        public readonly ?int $size,
        public readonly HashList $hashes,
        public readonly bool $primary,
        public bool $local = false
    )
    {
    }

    public static function fromLocal(File $file): FileDTO
    {
        return new self(
            $file->id,
            $file->remote_id,
            $file->component,
            $file->side,
            $file->path,
            $file->original_file_name,
            route('download', ['id' => $file->id, 'fileName' => $file->original_file_name]),
            $file->size,
            $file->hashes,
            $file->primary,
            true
        );
    }

    public static function fromMojang(string $component, string $fileName, string $url, string $hash, int $size): FileDTO
    {
        return new self(
            $component,
            $component,
            $component,
            Mojang::getFileSide($component),
            null,
            $fileName,
            $url,
            $size,
            new HashList(['sha1' => $hash]),
            Utils::isPrimaryComponent($component)
        );
    }

    public static function fromForge(string $filename, string $classifier, string $url, ?string $hash, bool $primary, ?int $size = null): FileDTO
    {
        return new self(
            $classifier,
            $classifier,
            $classifier,
            null,
            null,
            $filename,
            $url,
            $size,
            new HashList($hash ? ['md5' => $hash] : []),
            $primary
        );
    }

    public static function fromFabric(string $filename, string $classifier, string $url, ?string $hash, bool $primary): FileDTO
    {
        return new self(
            $classifier,
            $classifier,
            $classifier,
            null,
            null,
            $filename,
            $url,
            null,
            new HashList($hash ? ['sha1' => $hash] : []),
            $primary
        );
    }

    public function setLocal(bool $isLocal): static
    {
        $this->local = $isLocal;
        return $this;
    }

    public static function fromArray(array $file): FileDTO
    {
        return new self(
            $file['id'],
            $file['remote_id'],
            $file['component'],
            isset($file['side']) ? FileSide::tryFrom($file['side']) : null,
            null,
            $file['name'],
            $file['url'],
            $file['size'],
            new HashList($file['hashes']),
            $file['primary'],
            $file['local']
        );
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'remote_id' => $this->remoteId,
            'component' => $this->component,
            'dir' => $this->dir,
            'name' => $this->name,
            'url' => $this->url,
            'size' => $this->size,
            'hashes' => $this->hashes->toArray(),
            'primary' => $this->primary,
            'local' => $this->local
        ];
    }
}
