<?php

namespace App\API\Loader\Base;

use App\API\McaHttp;
use App\Services\SettingsService;
use Illuminate\Support\Str;

abstract class BaseLoader implements LoaderContract
{
    public function __construct(protected McaHttp $http, protected SettingsService $settings)
    {
    }

    public static function id(): string
    {
        return Str::slug(static::name());
    }

    public function slug(): string
    {
        return Str::slug(static::name());
    }

    public function getSettingPrefix(): string
    {
        return 'loaders.'.$this->slug();
    }

    public function getMirrorList(): array
    {
        return [];
    }
}
