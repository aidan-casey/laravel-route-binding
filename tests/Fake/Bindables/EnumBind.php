<?php

namespace AidanCasey\Laravel\RouteBinding\Tests\Fake\Bindables;

use AidanCasey\Laravel\RouteBinding\Tests\Fake\Enums\BindableEnum;

class EnumBind
{
    public function __construct(public BindableEnum $enum)
    {
        //
    }
}