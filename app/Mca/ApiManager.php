<?php

namespace App\Mca;

use App\API\Contracts\BaseThirdPartyApi;
use App\API\Contracts\ThirdPartyApi;
use App\API\Loader\Base\BaseLoader;
use App\Exceptions\PlatformDisabledException;
use App\Exceptions\PlatformNotFoundException;
use App\Services\SettingsService;
use App\Support\Utils;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ApiManager
{
    protected Collection $apis;
    protected Collection $loaders;

    public function __construct()
    {
        $this->apis = new Collection();
        $this->loaders = new Collection();
    }

    /**
     * @param class-string<ThirdPartyApi> $api
     */
    public function registerApi(string $api)
    {
        if ($this->apis->has($api::id())) {
            throw new \RuntimeException(sprintf('Platform %s [%s] is already registered', $api::name(), $api::id()));
        }

        $api::registerSettings(app(SettingsService::class));

        foreach (Utils::getRequests() as $request) {
            $api::configureRequest($request);
        }

        $this->apis->put($api::id(), [
            'id' => $api::id(),
            'name' => $api::name(),
            'name_lower' => Str::lower($api::name()),
            'slug' => Str::slug($api::name()),
            'theme_color' => $api::themeColor(),
            'class' => $api
        ]);
    }

    /**
     * @param class-string<BaseLoader> $loader
     */
    public function registerLoader(string $loader)
    {
        $name = $this->getClassName($loader);

        if ($this->loaders->has($name)) {
            throw new \RuntimeException(sprintf('Loader %s is already registered', $name));
        }

        $this->loaders->put($name, [
            'name' => $loader::name(),
            'class' => $loader
        ]);
    }

    public function has(string $id): bool
    {
        return $this->apis->has($id);
    }

    /**
     * @throws PlatformDisabledException
     * @throws PlatformNotFoundException
     */
    public function get(string $id): ThirdPartyApi
    {
        $api = $this->apis->get($id, fn() => throw new PlatformNotFoundException($id));

        /** @var BaseThirdPartyApi $instance */
        $instance = app($api['class']);

        if ($instance->isDisabled()) {
            throw new PlatformDisabledException(sprintf('%s API is disabled: %s', $api['name'], $instance->getDisableReason()));
        }

        return $instance;
    }

    /**
     * Invoke a function for each registered API.
     *
     * @param \Closure $fn
     */
    public function each(\Closure $fn)
    {
        $this->apis->each(function ($api) use ($fn) {
            try {
                $inst = $this->get($api['id']);
            } catch (PlatformDisabledException $e) {
                return;
            }

            $fn($inst);
        });
    }

    public function getAvailablePlatforms(): Collection
    {
        return $this->apis->map(
            fn($api) => [
                ...Arr::only($api, ['id', 'name', 'slug', 'disabled', 'theme_color']),
                'disabled' => app($api['class'])->getDisableReason() ?? false
            ]
        )->values();
    }

    public function getLoader(string $name): BaseLoader
    {
        $studly = Str::studly($name);

        if (! $this->loaders->has($studly)) {
            throw new \RuntimeException('No such Loader class: '.$studly);
        }

        return app($this->loaders->get($studly)['class']);
    }

    public function eachLoader(\Closure $fn)
    {
        $this->loaders->each(function ($loader) use ($fn) {
            return $fn(app($loader['class']), $loader);
        });
    }

    public function hasLoader(string $name): bool
    {
        return $this->loaders->has(Str::studly($name));
    }

    private function getClassName(string $class): string
    {
        return Str::afterLast($class, '\\');
    }
}
