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

    public static function slug(): string
    {
        return Str::slug(static::name());
    }

    public static function getSettingPrefix(): string
    {
        return 'loaders.'.static::slug();
    }

    public static function registerSettings(SettingsService $settings)
    {

    }

    public function hasSetting(string $key): bool
    {
        return app(SettingsService::class)->has(self::getSettingPrefix().'.'.$key);
    }

    public function getSetting(string $key): mixed
    {
        return app(SettingsService::class)->get(self::getSettingPrefix().'.'.$key);
    }

    public function getMirrorList(): array
    {
        return [];
    }
}
