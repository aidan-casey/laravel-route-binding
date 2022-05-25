<?php

namespace AidanCasey\Laravel\RouteBinding\Tests\Fake\Bindables;

use AidanCasey\Laravel\RouteBinding\Tests\Fake\Models\Dog;
use Illuminate\Foundation\Auth\User;

class MethodBind
{
    public ?User $user = null;

    public ?Dog $dog = null;

    public function execute(User $user, Dog $dog): self
    {
        $this->dog = $dog;
        $this->user = $user;

        return $this;
    }
}