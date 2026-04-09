<?php

namespace App\API;

use App\API\DTO\DTO;
use App\API\DTO\PaginationDTO;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;

/**
 * @template T
 */
class ThirdPartyApiResponse implements Responsable
{
    protected ?PaginationDTO $pagination;

    /**
     * @param T|Collection<T> $data
     * @param bool $cached
     */
    public function __construct(protected DTO|Collection $data, protected bool $cached)
    {
    }

    public function isCached(): bool
    {
        return $this->cached;
    }

    /**
     * @return T|Collection<T>
     */
    public function getData()
    {
        return $this->data;
    }

    public function getPagination(): ?PaginationDTO
    {
        if (! isset($this->pagination)) return null;
        return $this->pagination;
    }

    public function tapTransformedData(\Closure $fn): static
    {
        $fn($this->data);
        return $this;
    }

    public function withPagination(PaginationDTO $pagination): static
    {
        $this->pagination = $pagination;
        return $this;
    }

    public function toResponse($request): array
    {
        $response = [
            'cached' => $this->cached,
        ];

        if ($this->data instanceof Arrayable) {
            $response['data'] = $this->data->toArray();
        } else {
            $response['data'] = $this->data;
        }

        if (isset($this->pagination))
            $response['meta'] = $this->pagination->toArray();

        return $response;
    }
}
