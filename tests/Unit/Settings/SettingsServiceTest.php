<?php

namespace Tests\Unit\Settings;

use App\Enums\AutomaticArchiveSetting;
use App\Exceptions\McaValidationException;
use App\Models\Setting;
use App\Services\SettingsService;
use App\Settings\McaSetting;
use App\Settings\McaSettingCollection;
use Carbon\Carbon;
use Tests\Laravel\RefreshDatabase;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public EmptySettingsService $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = new EmptySettingsService();
    }

    public function test_register_auto_archive_settings()
    {
        $this->settings->registerAutoArchiveSettings('test');

        $this->assertTrue($this->settings->has('test.automatic_archive'));
        $this->assertTrue($this->settings->has('test.automatic_archive.interval'));
        $this->assertTrue($this->settings->has('test.automatic_archive.interval_unit'));
        $this->assertTrue($this->settings->has('test.automatic_archive.last_check'));

        // Enum
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive' => null]));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive' => '']));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive' => 'foo']));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive' => 123]));

        $this->assertTrue($this->settings->save(['test.automatic_archive' => AutomaticArchiveSetting::ARCHIVE]));
        $this->assertSame(AutomaticArchiveSetting::ARCHIVE, $this->settings->get('test.automatic_archive'));
        $this->assertTrue($this->settings->save(['test.automatic_archive' => AutomaticArchiveSetting::REFRESH->value]));
        $this->assertSame(AutomaticArchiveSetting::REFRESH, $this->settings->get('test.automatic_archive'));

        // Interval
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.interval' => null]));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.interval' => '']));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.interval' => 'foo']));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.interval' => 0]));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.interval' => -1]));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.interval' => 31]));

        $this->assertTrue($this->settings->save(['test.automatic_archive.interval' => 7]));

        // Interval unit
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.interval_unit' => null]));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.interval_unit' => '']));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.interval_unit' => 'foo']));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.interval_unit' => 1]));

        $this->assertTrue($this->settings->save(['test.automatic_archive.interval_unit' => 'h']));

        // Last check date
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.last_check' => 'foo']));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.last_check' => '2026']));

        $this->assertTrue($this->settings->save(['test.automatic_archive.last_check' => null]));
        $this->assertNull($this->settings->get('test.automatic_archive.last_check'));
        $this->assertTrue($this->settings->save(['test.automatic_archive.last_check' => '']));
        $this->assertNull($this->settings->get('test.automatic_archive.last_check'));
        $this->assertTrue($this->settings->save(['test.automatic_archive.last_check' => Carbon::now()]));
        $this->assertTrue($this->settings->save(['test.automatic_archive.last_check' => Carbon::now()->toDateTimeString()]));
    }

    public function test_register_auto_archive_filter_setting()
    {
        $this->settings->registerAutoArchiveFilterSetting('test', 'latest', ['latest', 'all']);

        $this->assertTrue($this->settings->has('test.automatic_archive.filter'));
        $this->assertSame('latest', $this->settings->get('test.automatic_archive.filter'));

        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.filter' => null]));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.filter' => '']));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.filter' => 'foo']));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.filter' => 2]));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.filter' => ['latest']]));

        $this->assertTrue($this->settings->save(['test.automatic_archive.filter' => 'all']));
    }

    public function test_register_archiving_components_settings()
    {
        $this->settings->registerArchivingComponentsSettings('test', ['client'], ['client', 'server']);

        foreach (['manual_archive', 'automatic_archive'] as $subkey) {
            $key = "test.$subkey.components";

            $this->assertTrue($this->settings->has($key));
            $this->assertSame(['client'], $this->settings->get($key));

            $this->assertException(McaValidationException::class, fn() => $this->settings->save([$key => null]));
            $this->assertException(McaValidationException::class, fn() => $this->settings->save([$key => '']));
            $this->assertException(McaValidationException::class, fn() => $this->settings->save([$key => 'foo']));
            $this->assertException(McaValidationException::class, fn() => $this->settings->save([$key => 4]));

            $this->assertTrue($this->settings->save([$key => []]));
            $this->assertTrue($this->settings->save([$key => ['server']]));
            $this->assertTrue($this->settings->save([$key => ['universal']]));
        }
    }

    public function test_register_auto_archive_release_types_setting()
    {
        $this->settings->registerAutoArchiveReleaseTypesSetting('test', ['release'], ['release', 'snapshot']);

        $this->assertTrue($this->settings->has('test.automatic_archive.release_types'));
        $this->assertSame(['release'], $this->settings->get('test.automatic_archive.release_types'));

        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.release_types' => null]));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.release_types' => '']));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.release_types' => 'foo']));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.release_types' => 4]));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.release_types' => ['snapshot', 'other']]));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.release_types' => ['other']]));

        $this->assertTrue($this->settings->save(['test.automatic_archive.release_types' => []]));
        $this->assertTrue($this->settings->save(['test.automatic_archive.release_types' => ['release']]));
        $this->assertTrue($this->settings->save(['test.automatic_archive.release_types' => ['release', 'snapshot']]));
    }

    public function test_register_auto_archive_remove_old_setting()
    {
        $this->settings->registerAutoArchiveRemoveOldSetting('test');

        $this->assertTrue($this->settings->has('test.automatic_archive.remove_old'));
        $this->assertSame(true, $this->settings->get('test.automatic_archive.remove_old'));

        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.remove_old' => null]));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.remove_old' => '']));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.remove_old' => 'foo']));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.remove_old' => 4]));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.remove_old' => []]));
        $this->assertException(McaValidationException::class, fn() => $this->settings->save(['test.automatic_archive.remove_old' => ['foo']]));

        $this->assertTrue($this->settings->save(['test.automatic_archive.remove_old' => false]));
        $this->assertTrue($this->settings->save(['test.automatic_archive.remove_old' => true]));
    }

    public function test_has()
    {
        $this->assertTrue($this->settings->has('test.string'));

        $this->assertFalse($this->settings->has('test.invalid'));
    }

    public function test_get()
    {
        Setting::insert([
            ['key' => 'test.string', 'value' => 'foo bar'],
            ['key' => 'test.int', 'value' => 4],
            ['key' => 'test.array', 'value' => '["one","two"]'],
            ['key' => 'test.enum', 'value' => TestEnum::TWO->value],
            ['key' => 'test.bool', 'value' => 1],
        ]);

        $this->assertSame('foo bar', $this->settings->get('test.string'));
        $this->assertSame(4, $this->settings->get('test.int'));
        $this->assertSame(['one', 'two'], $this->settings->get('test.array'));
        $this->assertSame(TestEnum::TWO, $this->settings->get('test.enum'));
        $this->assertSame(true, $this->settings->get('test.bool'));
    }

    public function test_save()
    {
        // One setting
        $this->settings->save(['test.string' => 'foo bar']);
        $this->assertSame('foo bar', $this->settings->get('test.string'));
        $this->assertDatabaseHas('settings', ['key' => 'test.string', 'value' => 'foo bar']);

        // Multiple settings
        $this->settings->save([
            'test.string' => 'bar foo',
            'test.int' => 4,
            'test.array' => ['three', 'four'],
            'test.enum' => TestEnum::TWO->value,
            'test.bool' => true,
        ]);

        $this->assertSame('bar foo', $this->settings->get('test.string'));
        $this->assertSame(4, $this->settings->get('test.int'));
        $this->assertSame(['three', 'four'], $this->settings->get('test.array'));
        $this->assertSame(TestEnum::TWO, $this->settings->get('test.enum'));
        $this->assertSame(true, $this->settings->get('test.bool'));

        $this->assertDatabaseHas('settings', ['key' => 'test.string', 'value' => 'bar foo']);
        $this->assertDatabaseHas('settings', ['key' => 'test.int', 'value' => 4]);
        $this->assertDatabaseHas('settings', ['key' => 'test.array', 'value' => '["three","four"]']);
        $this->assertDatabaseHas('settings', ['key' => 'test.enum', 'value' => TestEnum::TWO->value]);
        $this->assertDatabaseHas('settings', ['key' => 'test.bool', 'value' => 1]);
    }
}

class EmptySettingsService extends SettingsService {
    public function __construct()
    {
        $this->store = new McaSettingCollection();

        $this->registerSetting(new McaSetting('test.string', 'test'));
        $this->registerSetting(new McaSetting('test.int', 3));
        $this->registerSetting(new McaSetting('test.array', ['one', 'two']));
        $this->registerSetting(new McaSetting('test.enum', TestEnum::ONE));
        $this->registerSetting(new McaSetting('test.bool', true));
    }
}

enum TestEnum: int {
    case ONE = 1;
    case TWO = 2;
}
