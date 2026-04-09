<?php

namespace App\Providers;

use App\Mca\ApiManager;
use App\Services\JobService;
use App\Services\SettingsService;
use Illuminate\Bus\Events\BatchDispatched;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(ApiManager::class);

        $this->app->singleton(HtmlSanitizer::class, fn() => new HtmlSanitizer($this->makeDefaultHtmlSanitizerConfig()));
    }

    public function boot()
    {
        Event::listen(BatchDispatched::class, function (BatchDispatched $event) {
            JobService::onBatchCreated($event->batch);
        });
        Queue::before(function (JobProcessing $event) {
            JobService::onBeforeQueueEvent($event);
        });
        Queue::after(function (JobProcessed $event) {
            JobService::onAfterQueueEvent($event);
        });
        Queue::failing(function (JobFailed $event) {
            JobService::onFailedQueueEvent($event);
        });

        /**
         * Insert or update macro that allows passing new attributes by third argument.
         *
         * @param  array  $attributes
         * @param  array  $values
         * @param  array  $valuesWhenNew
         * @return bool
         */
        Builder::macro('mcaUpdateOrInsert', function (array $attributes, array $values = [], array $valuesWhenNew = []) {
            return $this->updateOrInsert(
                $attributes,
                fn($exists) => $exists ? $values : array_merge($values, $valuesWhenNew)
            );
        });

        /**
         * Determines if collection contains any of the specified items.
         */
        Collection::macro('containsAny', function (array|Collection $items) {
            if (empty($this->getArrayableItems($items))) return true;
            return $this->intersect($items)->isNotEmpty();
        });

        /**
         * Collection macro to check if it contains all items specified in parameter.
         */
        Collection::macro('containsAll', function (array|Collection $items) {
            return empty(array_diff($this->getArrayableItems($items), $this->items));
        });

        /**
         * Collection macro that groups selected values, sorts each group separately and then sorts grouped keys.
         *
         * @param array|string $groupBy Key and optionally sort direction
         *                              e.g. 'type' or ['type', 'desc']
         * @param array|string $sortBy Key and optionally sort direction
         *                             e.g. 'created_at' or [['created_at', 'desc']] or [['version', 'asc'], ['created_at', 'desc']]
         * @return Collection
         */
        Collection::macro('sortGrouping', function ($groupBy, $sortBy) {
            $groupByValue = is_array($groupBy) ? $groupBy[0] : $groupBy;
            $groupBySortAscending = is_array($groupBy) && ($groupBy[1] === true || $groupBy[1] === 'asc');

            /** @var Collection $grouped */
            $grouped = $this->groupBy($groupByValue)
                ->map(fn(Collection $group) => $group->sortBy($sortBy))
                ->sortKeys(descending: ! $groupBySortAscending);

            return $grouped->flatten(1);
        });

        /**
         * Split collection based on item uniqueness.
         *
         * @param  (callable(TValue, TKey): mixed)|string|null  $key
         * @param  bool  $strict
         * @return array
         */
        Collection::macro('uniquePartition', function ($key = null, bool $strict = false) {
            $unique = $this->unique($key, $strict);
            return [$unique, $this->diffKeys($unique)];
        });

        DB::macro('tryTransaction', function (\Closure $callback, \Closure $onFail) {
            DB::beginTransaction();

            try {
                $callback();
            } catch (\Throwable $e) {
                DB::rollBack();
                $onFail($e);
                throw $e;
            }

            DB::commit();
        });

        /**
         * Sanitize a HTML fragment.
         *
         * @param  string $input
         * @param \Closure $config
         * @return string
         */
        Str::macro('sanitize', function (string $input, ?\Closure $config = null) {
            if ($config) {
                return (new HtmlSanitizer($config(AppServiceProvider::makeDefaultHtmlSanitizerConfig())))->sanitize($input);
            }

            return app(HtmlSanitizer::class)->sanitize($input);
        });
    }

    public static function makeDefaultHtmlSanitizerConfig(): HtmlSanitizerConfig
    {
        return (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowStaticElements()
            ->withMaxInputLength(65000)
            ->forceAttribute('img', 'referrerpolicy', 'no-referrer');
    }
}
