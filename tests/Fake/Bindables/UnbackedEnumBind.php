<?php

namespace AidanCasey\Laravel\RouteBinding\Tests\Fake\Bindables;

use AidanCasey\Laravel\RouteBinding\Tests\Fake\Enums\UnbindableEnum;

class UnbackedEnumBind
{
    public function __construct(public UnbindableEnum $enum)
    {
        //
    }
}