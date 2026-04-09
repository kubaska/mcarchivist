<?php

namespace App\Services;

use App\Enums\AutomaticArchiveSetting;
use App\Exceptions\McaValidationException;
use App\Models\Setting;
use App\Rules\WritableDirectoryPathRule;
use Carbon\Carbon;
use GuzzleHttp\Utils as PsrUtils;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\Filesystem\Path;

class SettingsService
{
    protected bool $settingsLoaded = false;
    protected array $store = [];
    protected array $settings = [];

    public function __construct()
    {
        $pathValidator = fn() => ['required', 'bail', 'string', new WritableDirectoryPathRule()];

        // === General
        $this->registerSetting('general.storage.assets', Path::join(storage_path('mca'), 'assets'), $pathValidator, 'Asset storage directory');
        $this->registerSetting('general.storage.game', Path::join(storage_path('mca'), 'game'), $pathValidator, 'Game storage directory');
        $this->registerSetting('general.storage.libraries', Path::join(storage_path('mca'), 'libraries'), $pathValidator, 'Library storage directory');
        $this->registerSetting('general.storage.loaders', Path::join(storage_path('mca'), 'loaders'), $pathValidator, 'Loader storage directory');
        $this->registerSetting('general.storage.projects', Path::join(storage_path('mca'), 'projects'), $pathValidator, 'Project storage directory');
        $this->registerSetting('general.storage.temp', Path::join(storage_path('mca'), 'temp'), $pathValidator, 'Temporary storage directory');

        // === Projects
        $this->registerAutomaticArchivingIntervalSettings('projects');

        // === Game versions
        $this->registerAutomaticArchivingSettings('game_versions');
        // client, server, server_windows, mappings_client, mappings_server
        $this->registerSetting('game_versions.manual_archive.components', ['client', 'server']);
        $this->registerSetting('game_versions.automatic_archive.components', ['client', 'server']);
        // release, beta, alpha, snapshot
        $this->registerSetting('game_versions.automatic_archive.release_types', ['release']);
        // windows, linux, macos
//        $this->registerSetting('game_versions.automatic_archive.os_type', ['windows', 'linux', 'macos']);

        // === Forge
        $this->registerSetting('loaders.forge.manual_archive.components', ['client', 'server', 'universal', 'installer']);
        $this->registerAutomaticArchivingSettings('loaders.forge');
        // highlighted, all
        $this->registerSetting('loaders.forge.automatic_archive.filter', 'highlighted');
        // client, server, universal, installer, src ("sources" in newer ver), userdev, mdk, changelog, launcher
        $this->registerSetting('loaders.forge.automatic_archive.components', ['client', 'server', 'universal', 'installer']);
        // release, snapshot
        $this->registerSetting('loaders.forge.automatic_archive.release_types', ['release']);
        $this->registerSetting('loaders.forge.automatic_archive.remove_old', true);

        // === Neoforge
        $this->registerAutomaticArchivingSettings('loaders.neoforge');
        // latest, all
        $this->registerSetting('loaders.neoforge.automatic_archive.filter', 'latest');
        // universal, installer, changelog, sources, userdev
        $this->registerSetting('loaders.neoforge.manual_archive.components', ['universal', 'installer']);
        $this->registerSetting('loaders.neoforge.automatic_archive.components', ['universal', 'installer']);
        // release, snapshot
        $this->registerSetting('loaders.neoforge.automatic_archive.release_types', ['release']);
        $this->registerSetting('loaders.neoforge.automatic_archive.remove_old', true);

        // Fabric
        $this->registerAutomaticArchivingSettings('loaders.fabric');
        // Latest, All
        $this->registerSetting('loaders.fabric.automatic_archive.filter', 'latest');
        $this->registerSetting('loaders.fabric.automatic_archive.remove_old', true);

        // === Fabric Intermediary
        $this->registerAutomaticArchivingSettings('loaders.fabric-intermediary');
        // Release, snapshot
        $this->registerSetting('loaders.fabric-intermediary.automatic_archive.release_types', ['release']);
    }

    public function registerAutomaticArchivingSettings(string $prefix)
    {
        $this->registerSetting($prefix.'.automatic_archive', AutomaticArchiveSetting::OFF, [new Enum(AutomaticArchiveSetting::class)]);
        $this->registerAutomaticArchivingIntervalSettings($prefix);
        $this->registerSetting($prefix.'.automatic_archive.last_check', null, ['date'], expose: false);
    }

    public function registerAutomaticArchivingIntervalSettings(string $prefix)
    {
        $this->registerSetting($prefix.'.automatic_archive.interval', 1, ['numeric', 'integer', 'min:1', 'max:30']);
        $this->registerSetting($prefix.'.automatic_archive.interval_unit', 'd', [Rule::in(['h', 'd'])]);
    }

    public function registerSetting(string $key, mixed $default, array|\Closure|null $validator = null, ?string $name = null, bool $expose = true)
    {
        $type = match (true) {
            $default instanceof \BackedEnum => 'enum',
            default => gettype($default)
        };

        $this->store[$key] = [
            'default' => $default,
            'type' => $type,
            'name' => $name ?? $key,
            'validator' => $validator,
            'expose' => $expose
        ];
    }

    protected function hydrate(mixed $value, mixed $default, string $type)
    {
        if ($type === 'array') return PsrUtils::jsonDecode($value);
        elseif ($type === 'enum') return get_class($default)::tryFrom($value);
        return $value;
    }

    protected function dehydrate(mixed $value)
    {
        if (is_array($value)) return PsrUtils::jsonEncode($value);
        return $value;
    }

    protected function load()
    {
        if ($this->settingsLoaded) return $this->settings;

        $settings = Setting::query()->get();
        $this->settings = $settings->reduce(function (array &$carry, Setting $s) {
            if (! isset($this->store[$s->key])) return $carry;
            $carry[$s->key] = $this->hydrate($s->value, $this->store[$s->key]['default'], $this->store[$s->key]['type']);
            return $carry;
        }, []);
        $this->settingsLoaded = true;

        return $this->settings;
    }

    public function has(string $key): bool
    {
        return isset($this->store[$key]);
    }

    public function get(string $key): mixed
    {
        $this->load();
        return $this->settings[$key] ?? $this->store[$key]['default'];
    }

    public function getMany(array $keys): array
    {
        return array_reduce($keys, function (array $carry, string $key) {
            if (isset($this->store[$key])) {
                $carry[$key] = $this->get($key);
            }

            return $carry;
        }, []);
    }

    public function getDate(string $key): ?Carbon
    {
        return Carbon::make($this->get($key));
    }

    public function getPath(string $keySuffix, string ...$directories): string
    {
        $storage = $this->get('general.storage.'.$keySuffix);

        if ($directories) {
            $storage = Path::join($storage, ...$directories);
        }

        return $storage;
    }

    public function getAll(): array
    {
        return Arr::map(array_filter($this->store, fn($i) => $i['expose']), fn($i, string $k) => $this->get($k));
    }

    public function getDefault(): array
    {
        return Arr::map(array_filter($this->store, fn($i) => $i['expose']), fn($i) => $i['default']);
    }

    public function save(array $settings)
    {
        // Discard any invalid settings
        $settings = array_filter($settings, function (mixed $v, string $k) {
            if (! isset($this->store[$k])) {
                Log::warning(sprintf('Rejecting non-existent settings key [%s] with value [%s]!', $k, $v));
                return false;
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);

        $validatableSettings = array_filter($settings, fn(mixed $v, string $k) => isset($this->store[$k]['validator']), ARRAY_FILTER_USE_BOTH);
        if (! empty($validatableSettings)) {
            $validator = Validator::make(
                $validatableSettings,
                Arr::mapWithKeys($validatableSettings, function (mixed $v, string $k) {
                    $rules = $this->store[$k]['validator'];
                    return [str_replace('.', '\.', $k) => $rules instanceof \Closure ? $rules() : $rules];
                }),
                attributes: Arr::map($validatableSettings, fn(mixed $v, string $k) => strtolower($this->store[$k]['name']))
            );

            if ($validator->fails()) {
                throw new McaValidationException($validator);
            }
        }

        foreach ($settings as $setting => $value) {
            $now = Carbon::now();
            Setting::query()->mcaUpdateOrInsert(
                ['key' => $setting],
                ['value' => $this->dehydrate($value), 'updated_at' => $now],
                ['created_at' => $now]
            );
        }
    }
}
