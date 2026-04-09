<?php

namespace Tests;

use App\Services\SettingsService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SettingsServiceFake extends SettingsService
{
    private const TEST_DIRECTORY_NAME = '__tests__';

    public function __construct()
    {
        parent::__construct();

        $this->configureTestPaths();
        $this->settingsLoaded = true;
    }

    // Filters settings down to storage paths.
    protected function getStorageSettings(): array
    {
        return array_filter($this->getDefault(), fn(string $k) => str_starts_with($k, 'general.storage.'), ARRAY_FILTER_USE_KEY);
    }

    public function configureTestPaths()
    {
        $storages = $this->getStorageSettings();

        // Make new paths for each type
        $new = Arr::mapWithKeys($storages, fn(string $v, string $k) => [
            $k => storage_path(self::TEST_DIRECTORY_NAME.DIRECTORY_SEPARATOR.Str::afterLast($k, '.'))
        ]);

        $this->save($new);
    }

    public function setSettings(array $settings): static
    {
        $this->settings = $settings;
        return $this;
    }

    protected function load()
    {
        return $this->settings;
    }

    public function save(array $settings)
    {
        $this->settings = array_merge($this->settings, $settings);
    }

    public function cleanupStorageDirectories()
    {
        foreach ($this->getStorageSettings() as $key => $_) {
            $path = $this->get($key);

            if ($path && is_dir($path) && str_contains($path, self::TEST_DIRECTORY_NAME)) {
                app(Filesystem::class)->cleanDirectory($path);
            }
        }
    }
}
