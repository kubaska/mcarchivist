<?php

namespace App\Settings;

use Illuminate\Support\Collection;

class McaSettingCollection extends Collection
{
    public function get($key, $default = null): ?McaSetting
    {
        return parent::get($key, $default);
    }
}
