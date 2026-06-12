<?php

namespace App\Settings;

use App\Rules\ArrayStrictRule;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class McaSetting
{
    protected string $name;
    protected string $type;
    protected array $options;
    protected array $validationRules = [];

    public function __construct(
        public readonly string $key,
        public readonly mixed $default,
        public readonly bool $expose = true
    )
    {
        $this->type = match (true) {
            $default instanceof \BackedEnum => 'enum',
            default => gettype($default)
        };
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        if (isset($this->name)) return $this->name;

        return $this->key;
    }

    /**
     * Set setting name.
     * This is not used anywhere at this point.
     *
     * @param string $name
     * @return McaSetting
     */
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getOptions(): ?array
    {
        if (isset($this->options)) return $this->options;

        return null;
    }

    /**
     * @param array $options
     * @param bool $allowMultiple
     * @param bool $strict
     * @return $this
     */
    public function setOptions(array $options, bool $allowMultiple = false, bool $strict = true): static
    {
        $this->options = array_map(fn($option) => $this->normalizeOption($option), $options);
        $optionIds = array_map(fn(array $option) => $option['id'], $this->options);

        if ($allowMultiple) {
            $this->addValidationRule([new ArrayStrictRule]);

            if ($strict) {
                $this->addValidationRule(['required', Rule::in($optionIds)], '*');
            } else {
                $this->addValidationRule(['required', 'string'], '*');
            }
        }
        else {
            $this->addValidationRule(['string']);

            if ($strict) {
                $this->addValidationRule(['required', Rule::in($optionIds)]);
            } else {
                // Nothing
            }
        }

        return $this;
    }

    public function hasValidationRules(): bool
    {
        return ! empty($this->validationRules);
    }

    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    public function setValidationRules(array $rules): static
    {
        if (Arr::isAssoc($rules)) {
            foreach ($rules as $key => $rulesForKey) {
                if (str_starts_with($this->key, $key)) {
                    $this->validationRules[$key] = $rulesForKey;
                }
            }
        }
        else {
            $this->validationRules = [];
            $this->validationRules[$this->key] = $rules;
        }

        return $this;
    }

    protected function addValidationRule($rules, string $suffix = '')
    {
        $key = $suffix ? $this->key.'.'.$suffix : $this->key;

        if (! isset($this->validationRules[$key])) {
            $this->validationRules[$key] = [];
        }

        foreach ($rules as $rule) {
            if (! in_array($rule, $this->validationRules[$key])) {
                $this->validationRules[$key][] = $rule;
            }
        }
    }

    private function normalizeOption(array|string $option): array
    {
        if (is_string($option)) {
            return ['id' => $option, 'name' => ucfirst($option)];
        }

        $option = Arr::only($option, ['id', 'name', 'hint']);

        if (! isset($option['id'])) {
            throw new \RuntimeException(sprintf('One of the options in setting [%s] is missing an ID', $this->key));
        }
        if (! isset($option['name'])) {
            $option['name'] = ucfirst($option['id']);
        }

        return $option;
    }
}
