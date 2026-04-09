<?php

namespace App\API;

use Illuminate\Http\Client\Response;

class McaResponse
{
    public function __construct(protected Response $response, protected bool $cached)
    {
    }

    public function getData(): mixed
    {
        return $this->response->json();
    }

    public function json($key = null, $default = null): mixed
    {
        return $this->response->json($key, $default);
    }

    public function isCached(): bool
    {
        return $this->cached;
    }

    public function getHeader($name): string
    {
        return $this->response->header($name);
    }

    public function getHeaders(): array
    {
        return $this->response->headers();
    }
}
