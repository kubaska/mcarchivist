<?php

namespace App\API;

use App\Exceptions\McaHttpException;
use App\Exceptions\NotFoundApiException;
use App\Exceptions\RateLimitedApiException;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\LaravelCacheStorage;

class McaHttp
{
    protected CacheRepository $cache;
    protected bool $useCache = true;
    protected array $headers = [];
    protected string $userAgent;
    private string $baseUrl = '';

    public function __construct()
    {
        $cache = app('cache');
        $this->cache = $cache->store($cache->getDefaultDriver());
    }

    protected function getHttp()
    {
        return Http::withMiddleware(new CacheMiddleware(
            new McaCachingStrategy(new LaravelCacheStorage($this->cache), 1800)
        ));
    }

    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    public function setBaseUrl(string $url): static
    {
        $this->baseUrl = $url;
        return $this;
    }

    public function setUserAgent(string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function setCacheDriver($driver)
    {
        $this->cache = $driver;
    }

    public function head(string $url): Response
    {
        return Http::withHeaders($this->headers)->head($this->baseUrl.$url);
    }

    public function get(string $url, array|string|null $data = null)
    {
        assert(!!$url, 'Invalid HTTP url provided');

        $response = $this->getHttp()
            ->withHeaders($this->headers)
            ->when(isset($this->userAgent), fn(PendingRequest $http) => $http->withUserAgent($this->userAgent))
            ->retry(2, function (int $attempt, \Exception $exception) {
                if ($exception instanceof RequestException) {
                    if ($exception->response->serverError()) {
                        return 1000;
                    }
                }

                return null;
            }, function (\Throwable $exception) {
                if ($exception instanceof RequestException) {
                    return $exception->response->serverError();
                }

                return false;
            }, false)
            ->get($this->baseUrl.$url, $data);

        if (! $response->successful()) {
            if ($response->notFound()) {
                throw new NotFoundApiException($response);
            }
            if ($response->tooManyRequests()) {
                throw new RateLimitedApiException($response);
            }

            throw new McaHttpException($response);
        }

        return new McaResponse(
            $response,
            $response->header(CacheMiddleware::HEADER_CACHE_INFO) === CacheMiddleware::HEADER_CACHE_HIT
        );
    }

    public function getText(string $url): string
    {
        $response = $this->getHttp()->withHeaders($this->headers)->get($this->baseUrl.$url);

        if (! $response->successful()) {
            throw new McaHttpException($response);
        }

        return $response->body();
    }

    public function post(string $url, array|string|null $data = null): McaResponse
    {
        $response = Http::withHeaders($this->headers)->post($this->baseUrl.$url, $data);

        return new McaResponse($response, false);
    }
}
