<?php

namespace App\API\DTO;

use App\Models\Author;

class AuthorDTO extends DTO
{
    public function __construct(
        public readonly string $remoteId,
        public readonly string $name,
        public readonly ?string $role,
        public readonly ?string $avatarUrl,
        public readonly ?string $url,
    )
    {
    }

    public static function fromLocal(Author $author): AuthorDTO
    {
        return new self(
            $author->remote_id,
            $author->name,
            $author->relationLoaded('pivot')
                ? $author->pivot->role
                : null,
            $author->avatar,
            null
        );
    }

    public static function fromArray(array $author): AuthorDTO
    {
        return new self(
            $author['remote_id'],
            $author['name'],
            $author['role'],
            $author['avatar'],
            $author['url'],
        );
    }

    public function toArray(): array
    {
        return [
            'remote_id' => $this->remoteId,
            'name' => $this->name,
            'role' => $this->role,
            'avatar' => $this->avatarUrl,
            'url' => $this->url
        ];
    }
}
