<?php

namespace App\API\Requests\Base;

use App\API\Contracts\ThirdPartyApi;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;

abstract class ThirdPartyApiRequest
{
    /** @var \Illuminate\Validation\Factory */
    protected Factory $validator;

    protected static array $transformers = [];
    protected static array $afterTransformCallbacks = [];

    public function __construct(protected Request $request)
    {
        $this->validator = app('validator');
    }

    abstract public static function getRequestName(): string;

    abstract public function getRequestExposedFields(): array;

    public function getDefaultMorphs(): array
    {
        return [];
    }

    public function getMorphMap(): array
    {
        return ['*' => $this->getDefaultMorphs(), ...self::$transformers[static::getRequestName()]];
    }

    public static function configure(string $class, array $transformers)
    {
        self::$transformers[static::getRequestName()][$class] = $transformers;
    }

    public static function configureAfterTransform(string $class, \Closure $callback)
    {
        self::$afterTransformCallbacks[static::getRequestName()][$class] = $callback;
    }

    public function transformTo(string $class): array
    {
        if (! isset($this->getMorphMap()[$class])) {
            return [];
        }

        $validator = $this->validator->make(
            $this->request->all(),
            array_reduce($this->getMorphMap()[$class], function ($carry, $i) {
                if (isset($i['validation']))
                    $carry[$i['key']] = $i['validation'];
                return $carry;
            }, [])
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $targetFields = [];

        foreach ($this->getMorphMap()[$class] as $morphField) {
            if (! $this->request->exists($morphField['key']))
                continue;

            // Check and transform value from global morph, if exists
            if (Arr::exists($this->getMorphMap(), '*')
                && $global = Arr::first($this->getMorphMap()['*'], fn($v) => $v['key'] === $morphField['key']))
            {
                $value = $global['transform_fn']($this->request->get($morphField['key']), $class);
            } else {
                $value = $this->request->get($morphField['key']);
            }

            // Transform a local value to remote equivalent according to the morph map
            $value = Arr::exists($morphField, 'transform_fn')
                ? $morphField['transform_fn']($value)
                : $value;

            // Check if value was already set, and if it was, merge them
            $targetFields[$morphField['target_key']] = isset($targetFields[$morphField['target_key']]) && is_array($targetFields[$morphField['target_key']])
                ? array_merge($targetFields[$morphField['target_key']], $value)
                : $value;
        }

        if (isset(self::$afterTransformCallbacks[static::getRequestName()][$class])) {
            $targetFields = static::$afterTransformCallbacks[static::getRequestName()][$class]($targetFields);
        }

        return $targetFields;
    }

    public function getSettings(): array
    {
        $result = [];

        /** @var class-string<ThirdPartyApi> $platform */
        foreach ($this->getMorphMap() as $platform => $fields) {
            if ($platform === '*') continue;

            foreach ($fields as $field) {
                $result[$platform::id()][$field['key']] = Arr::only($field, $this->getRequestExposedFields());
            }
        }

        return $result;
    }
}
