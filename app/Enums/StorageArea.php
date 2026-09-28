<?php

namespace App\Enums;

enum StorageArea: int
{
    case ASSETS = 0;
    case GAME = 1;
    case LIBRARIES = 2;
    case LOADERS = 3;
    case PROJECTS = 4;
    case TEMP = 5;

    public function getSettingKey(): string
    {
        return match($this) {
            self::ASSETS => 'assets',
            self::GAME => 'game',
            self::LIBRARIES => 'libraries',
            self::LOADERS => 'loaders',
            self::PROJECTS => 'projects',
            self::TEMP => 'temp'
        };
    }
}
