<?php

namespace Tests;

use App\Services\SettingsService;
use Illuminate\Support\Arr;
use Laravel\Lumen\Testing\TestCase as BaseTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Filesystem\Path;
use Tests\Laravel\InteractsWithDatabase;
use Tests\Laravel\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use McaMakesHttpRequests, InteractsWithDatabase, InteractsWithFilesystem;

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanupTestFiles();
    }

    protected function getTestDataDir(...$path): string
    {
        return Path::join(base_path('tests'), 'data', ...$path);
    }

    /**
     * Creates a configured mock, handling callback returns.
     *
     * @param class-string $originalClassName
     * @param array $config
     * @return MockObject
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    protected function createConfiguredMockWithCallbacks(string $originalClassName, array $config): MockObject
    {
        $simple = Arr::where($config, fn($v) => !($v instanceof \Closure));
        $closures = Arr::where($config, fn($v) => $v instanceof \Closure);
        $class = $this->createConfiguredMock($originalClassName, $simple);
        foreach ($closures as $name => $callback) {
            $class->method($name)->willReturnCallback($callback);
        }
        return $class;
    }

    protected function setUpTraits()
    {
        $uses = array_flip(class_uses_recursive(static::class));

        if (isset($uses[RefreshDatabase::class])) {
            $this->refreshDatabase();
        }

        parent::setUpTraits();
    }

    /**
     * Creates the application.
     *
     * @return \Laravel\Lumen\Application
     */
    public function createApplication()
    {
        return require __DIR__.'/../bootstrap/app.php';
    }

    /**
     * Register an instance of an object in the container.
     *
     * @template TInstance of object
     *
     * @param  string  $abstract
     * @param  TInstance  $instance
     * @return TInstance
     */
    protected function instance($abstract, $instance)
    {
        $this->app->instance($abstract, $instance);

        return $instance;
    }

    protected function useAppSettings(): SettingsServiceFake
    {
        $this->instance(SettingsService::class, $fake = app(SettingsServiceFake::class));
        $this->beforeApplicationDestroyed(fn() => $fake->cleanupStorageDirectories());
        return $fake;
    }
}
