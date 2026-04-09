<?php

namespace App\Enums;

enum JobType: int
{
    case ARCHIVING = 0;
    case REVALIDATING = 1;
    case UPDATING_INDEX = 2;
}
