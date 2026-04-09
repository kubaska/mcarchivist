<?php

namespace App\Enums;

enum AutomaticArchiveSetting: int
{
    case OFF = 0;
    case REFRESH = 1;
    case ARCHIVE = 2;
}
