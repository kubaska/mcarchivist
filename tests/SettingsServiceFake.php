<?php

namespace Tests;

use App\Services\SettingsService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Filesystem\Path;

class SettingsServiceFake extends SettingsService
{
    private string $workDir;

    public function __construct()
    {
        parent::__construct();

        $this->workDir = Path::join(sys_get_temp_dir(), 'mcatest_'.microtime(true));

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
            $k => $this->workDir.DIRECTORY_SEPARATOR.Str::afterLast($k, '.')
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

    public function save(array $settings): bool
    {
        $this->settings = array_merge($this->settings, $settings);
        return true;
    }

    public function __destruct()
    {
        app(Filesystem::class)->deleteDirectory($this->workDir);
    }
}
