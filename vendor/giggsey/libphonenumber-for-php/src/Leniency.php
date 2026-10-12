<?php

namespace Mollie\libphonenumber;

use Mollie\libphonenumber\Leniency\Possible;
use Mollie\libphonenumber\Leniency\StrictGrouping;
use Mollie\libphonenumber\Leniency\Valid;
use Mollie\libphonenumber\Leniency\ExactGrouping;
class Leniency
{
    public static function POSSIBLE()
    {
        return new Possible();
    }
    public static function VALID()
    {
        return new Valid();
    }
    public static function STRICT_GROUPING()
    {
        return new StrictGrouping();
    }
    public static function EXACT_GROUPING()
    {
        return new ExactGrouping();
    }
}
