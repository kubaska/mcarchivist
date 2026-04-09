<?php

namespace App\API\Contracts;

use App\API\Requests\Base\ThirdPartyApiRequest;
use App\Services\SettingsService;
use Illuminate\Support\Collection;

abstract class BaseThirdPartyApi implements ThirdPartyApi
{
    protected ?string $disabledReason = null;

    public function isDisabled(): bool
    {
        return ! is_null($this->disabledReason);
    }

    public function getDisableReason(): ?string
    {
        return $this->disabledReason;
    }

    protected function setDisabled(string $reason)
    {
        $this->disabledReason = $reason;
    }

    public static function registerSettings(SettingsService $settings)
    {

    }

    public static function configureRequest(string $request) {

    }

    protected function getOptions(ThirdPartyApiRequest|array $options): array
    {
        if ($options instanceof ThirdPartyApiRequest) {
            return $options->transformTo(get_class($this));
        }

        return $options;
    }

    public function makeCategoryTree(Collection $categories): Collection
    {
        return $categories;
    }
}
