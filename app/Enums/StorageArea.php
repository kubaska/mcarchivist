<?php

namespace App\Enums;

enum StorageArea: string
{
    case ASSETS = 'assets';
    case GAME = 'game';
    case LIBRARIES = 'libraries';
    case LOADERS = 'loaders';
    case PROJECTS = 'projects';
    case TEMP = 'temp';
}
