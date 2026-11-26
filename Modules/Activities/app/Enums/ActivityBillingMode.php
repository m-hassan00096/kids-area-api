<?php

namespace Modules\Activities\Enums;

enum ActivityBillingMode: int
{
    case HOURLY = 0;
    case SUBSCRIPTION = 1;
}
