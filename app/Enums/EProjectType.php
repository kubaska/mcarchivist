<?php

namespace App\Enums;

enum EProjectType: int
{
    case MOD = 0;
    case MODPACK = 1;
    case PLUGIN = 2;
    case RESOURCE_PACK = 3;
    case DATAPACK = 4;
    case WORLD = 5;
    case SHADER = 6;
    case ADDON = 7;
    case CUSTOMIZATION = 8;
    case OTHER = 9;

    public function name(): string
    {
        return match($this) {
            self::MOD => 'mod',
            self::MODPACK => 'modpack',
            self::PLUGIN => 'plugin',
            self::RESOURCE_PACK => 'resource pack',
            self::DATAPACK => 'datapack',
            self::WORLD => 'world',
            self::SHADER => 'shader',
            self::ADDON => 'addon',
            self::CUSTOMIZATION => 'customization',
            self::OTHER => 'other',
        };
    }
}
