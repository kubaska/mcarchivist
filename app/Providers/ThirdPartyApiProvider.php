<?php

namespace App\Providers;

use App\API\Platform\Curseforge;
use App\API\Loader\Fabric;
use App\API\Loader\FabricIntermediary;
use App\API\Loader\Forge;
use App\API\Loader\NeoForge;
use App\API\Platform\Modrinth;
use App\API\Mojang;
use App\Mca\ApiManager;
use Illuminate\Support\ServiceProvider;

class ThirdPartyApiProvider extends ServiceProvider
{
    protected function getPlatforms(): array
    {
        return [Modrinth::class, Curseforge::class];
    }

    protected function getLoaders(): array
    {
        return [
            Forge::class,
            NeoForge::class,
            Fabric::class,
            FabricIntermediary::class
        ];
    }

    public function register()
    {
        $this->app->singleton(Mojang::class);

        foreach ($this->getPlatforms() as $platform) {
            $this->app->singleton($platform);
        }

        foreach ($this->getLoaders() as $loader) {
            $this->app->singleton($loader);
        }
    }

    public function boot()
    {
        $manager = app(ApiManager::class);

        foreach ($this->getPlatforms() as $platform) {
            $manager->registerApi($platform);
        }

        foreach ($this->getLoaders() as $loader) {
            $manager->registerLoader($loader);
        }
    }
}
