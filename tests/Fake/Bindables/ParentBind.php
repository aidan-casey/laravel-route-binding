<?php

namespace AidanCasey\Laravel\RouteBinding\Tests\Fake\Bindables;

use Illuminate\Foundation\Auth\User;

class ParentBind
{
    public function __construct(public User $user)
    {
        //
    }
}