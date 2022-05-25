<?php

namespace AidanCasey\Laravel\RouteBinding\Tests\Fake\Bindables;

use AidanCasey\Laravel\RouteBinding\Tests\Fake\Models\Dog;
use Illuminate\Foundation\Auth\User;

class ParentAndChildBind
{
    public function __construct(public Dog $dog, public User $user)
    {
        //
    }
}