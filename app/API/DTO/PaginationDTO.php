<?php

namespace App\API\DTO;

use Illuminate\Contracts\Support\Arrayable;

class PaginationDTO extends DTO implements Arrayable
{
    public function __construct(
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
        public readonly int $lastPage
    )
    {
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage
        ];
    }

    public function hasMore(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function getIndex(): int
    {
        return ($this->perPage * $this->currentPage) - $this->perPage;
    }
}
