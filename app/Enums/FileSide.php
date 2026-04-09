<?php

namespace App\Enums;

enum FileSide: int
{
    case UNIVERSAL = 0;
    case CLIENT = 1;
    case SERVER = 2;
    case DEVELOPER = 3;
}
