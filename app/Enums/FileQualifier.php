<?php

namespace App\Enums;

enum FileQualifier: int
{
    case PRIMARY_ONLY = 0;
    case ALL = 1;
}
