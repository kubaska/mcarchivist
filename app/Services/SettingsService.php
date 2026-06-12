<?php

namespace App\Services;

use App\Enums\AutomaticArchiveSetting;
use App\Exceptions\McaValidationException;
use App\Models\Setting;
use App\Rules\WritableDirectoryPathRule;
use App\Settings\McaSetting;
use App\Settings\McaSettingCollection;
use App\Support\Utils;
use Carbon\Carbon;
use GuzzleHttp\Utils as PsrUtils;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\Filesystem\Path;

class SettingsService
{
    protected bool $settingsLoaded = false;
    protected McaSettingCollection $store;
    protected array $settings = [];

    public function __construct()
    {
        $this->store = new McaSettingCollection();

        // === General
        $this->registerPathSetting('assets', 'assets');
        $this->registerPathSetting('game', 'game');
        $this->registerPathSetting('libraries', 'libraries');
        $this->registerPathSetting('loaders', 'loaders');
        $this->registerPathSetting('projects', 'projects');
        $this->registerPathSetting('temp', 'temp');

        // === Projects
        $this->registerAutoArchiveIntervalSettings('projects');

        // === Game versions
        $this->registerAutoArchiveSettings('game_versions');

        $this->registerArchivingComponentsSettings('game_versions', ['client', 'server'], [
            'client',
            'server',
            ['id' => 'server_windows', 'name' => 'Server (Windows)'],
            ['id' => 'client_mappings', 'name' => 'Client mappings'],
            ['id' => 'server_mappings', 'name' => 'Server mappings']
        ]);
        $this->registerAutoArchiveReleaseTypesSetting('game_versions', ['release'], ['release', 'beta', 'alpha', 'snapshot']);
    }

    protected function registerPathSetting(string $key, string $directory)
    {
        $this->registerSetting(
            (new McaSetting('general.storage.'.$key, Path::join(storage_path('mca'), $directory)))
                ->setName($key.' storage directory')
                ->setValidationRules(['required', 'bail', 'string', new WritableDirectoryPathRule()])
        );
    }

    public function registerAutoArchiveSettings(string $prefix)
    {
        $this->registerSetting(
            (new McaSetting($prefix.'.automatic_archive', AutomaticArchiveSetting::OFF))
                ->setValidationRules(['required', new Enum(AutomaticArchiveSetting::class)])
        );

        $this->registerAutoArchiveIntervalSettings($prefix);

        $this->registerSetting(
            (new McaSetting($prefix.'.automatic_archive.last_check', null, expose: false))
                ->setValidationRules(['nullable', 'date'])
        );
    }

    public function registerAutoArchiveIntervalSettings(string $prefix)
    {
        $this->registerSetting(
            (new McaSetting($prefix.'.automatic_archive.interval', 1))
                ->setValidationRules(['required', 'integer:strict', 'min:1', 'max:30'])
        );
        $this->registerSetting(
            (new McaSetting($prefix.'.automatic_archive.interval_unit', 'd'))
                ->setValidationRules(['required', Rule::in(['h', 'd'])])
        );
    }

    public function registerAutoArchiveFilterSetting(string $prefix, string $default, array $options)
    {
        $this->registerSetting(
            (new McaSetting($prefix.'.automatic_archive.filter', $default))
                ->setOptions($options)
        );
    }

    public function registerArchivingComponentsSettings(string $prefix, array $components, array $options)
    {
        $this->registerSetting(
            (new McaSetting($prefix.'.manual_archive.components', $components))
                ->setOptions($options, true, false)
        );

        $this->registerSetting(
            (new McaSetting($prefix.'.automatic_archive.components', $components))
                ->setOptions($options, true, false)
        );
    }

    public function registerAutoArchiveReleaseTypesSetting(string $prefix, array $default, array $options)
    {
        $this->registerSetting(
            (new McaSetting($prefix.'.automatic_archive.release_types', $default))
                ->setOptions($options, true, true)
        );
    }

    public function registerAutoArchiveRemoveOldSetting(string $prefix, bool $default = true)
    {
        $this->registerSetting(
            (new McaSetting($prefix.'.automatic_archive.remove_old', $default))
                ->setValidationRules(['required', 'boolean'])
        );
    }

    public function registerSetting(McaSetting $setting)
    {
        if ($this->has($setting->key)) {
            Log::error(sprintf('Setting [%s] is already registered!', $setting->key));
            return;
        }

        $this->store[$setting->key] = $setting;
    }

    protected function hydrate(mixed $value, mixed $default, string $type)
    {
        return match ($type) {
            'array' => PsrUtils::jsonDecode($value),
            'boolean' => (bool)$value,
            'enum' => get_class($default)::tryFrom($value),
            'integer' => (int)$value,
            default => $value === '' ? null : $value
        };
    }

    protected function dehydrate(mixed $value)
    {
        return match (true) {
            is_array($value) => PsrUtils::jsonEncode($value),
            Utils::isEnum($value) => Utils::getEnumValue($value),
            is_null($value) => '',
            default => $value
        };
    }

    protected function load()
    {
        if ($this->settingsLoaded) return $this->settings;

        $settings = Setting::query()->get();
        $this->settings = $settings->reduce(function (array &$carry, Setting $s) {
            if (! $this->has($s->key)) return $carry;

            $carry[$s->key] = $this->hydrate(
                $s->value,
                $this->store->get($s->key)->getDefault(),
                $this->store->get($s->key)->getType()
            );

            return $carry;
        }, []);

        $this->settingsLoaded = true;

        return $this->settings;
    }

    public function has(string $key): bool
    {
        return $this->store->has($key);
    }

    public function get(string $key): mixed
    {
        $this->load();
        return $this->settings[$key] ?? $this->store->get($key)?->getDefault();
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

    protected function getAllExposed(): Collection
    {
        return $this->store->filter(fn(McaSetting $setting) => $setting->expose);
    }

    /**
     * Get all registered exposed settings.
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return $this->getAllExposed()
            ->map(function (McaSetting $setting) {
                $result = ['value' => $this->get($setting->key)];

                if ($setting->getOptions() !== null) {
                    $result['options'] = $setting->getOptions();
                }

                return $result;
            });
    }

    /**
     * Get all registered exposed settings' default values.
     *
     * @return array
     */
    public function getDefault(): array
    {
        return $this->getAllExposed()->map(fn(McaSetting $setting) => $setting->getDefault())->toArray();
    }

    public function save(array $settings): bool
    {
        // Discard any invalid settings
        $settings = array_filter($settings, function (mixed $v, string $k) {
            if (! $this->has($k)) {
                Log::warning(sprintf('Rejecting non-existent settings key [%s] with value "%s"!', $k, $v));
                return false;
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);

        $validatableSettings = array_filter(
            $settings,
            fn(string $k) => $this->store->get($k)?->hasValidationRules(),
            ARRAY_FILTER_USE_KEY
        );

        if (! empty($validatableSettings)) {
            $validator = Validator::make(
                $validatableSettings,
                Arr::mapWithKeys($validatableSettings, function (mixed $v, string $settingKey) {
                    return Arr::mapWithKeys(
                        $this->store->get($settingKey)->getValidationRules(),
                        fn(array $rules, string $key) => [
                            str_replace($settingKey, str_replace('.', '\.', $settingKey), $key) => $rules
                        ]
                    );
                }),
                attributes: Arr::map($validatableSettings, fn(mixed $v, string $k) => strtolower($this->store->get($k)->getName()))
            );

            if ($validator->fails()) {
                throw new McaValidationException($validator);
            }
        }

        foreach ($settings as $setting => $value) {
            $now = Carbon::now();
            $dehydratedValue = $this->dehydrate($value);

            Setting::query()->mcaUpdateOrInsert(
                ['key' => $setting],
                ['value' => $dehydratedValue, 'updated_at' => $now],
                ['created_at' => $now]
            );

            $this->settings[$setting] = $this->hydrate(
                $dehydratedValue,
                $this->store->get($setting)->getDefault(),
                $this->store->get($setting)->getType()
            );
        }

        return true;
    }
}
