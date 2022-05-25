<?php

namespace AidanCasey\Laravel\RouteBinding\Tests;

use AidanCasey\Laravel\RouteBinding\Binder;
use AidanCasey\Laravel\RouteBinding\Tests\Fake\Bindables\EnumBind;
use AidanCasey\Laravel\RouteBinding\Tests\Fake\Bindables\MethodBind;
use AidanCasey\Laravel\RouteBinding\Tests\Fake\Bindables\ParentAndChildBind;
use AidanCasey\Laravel\RouteBinding\Tests\Fake\Bindables\ParentBind;
use AidanCasey\Laravel\RouteBinding\Tests\Fake\Bindables\UnbackedEnumBind;
use AidanCasey\Laravel\RouteBinding\Tests\Fake\Enums\UnbindableEnum;
use AidanCasey\Laravel\RouteBinding\Tests\Fake\Models\Dog;
use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Throwable;

class BinderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        User::query()->forceCreate([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => Hash::make('password'),
        ]);

        Dog::query()->forceCreate([
            'user_id' => 1,
            'name' => 'Scooter Harvey Wooferton',
        ]);
    }

    public function test_it_instantiates_class_if_it_has_no_constructor()
    {
        Route::get('test', function () {
            $class = Binder::make(MethodBind::class);

            return response()->json(['instance' => get_class($class)]);
        });

        $this
            ->get('test')
            ->assertOk()
            ->assertJson([
                'instance' => MethodBind::class
            ]);
    }

    public function test_it_binds_models()
    {
        Route::get('users/{user}', function () {
            $instance = Binder::make(ParentBind::class);

            return response()->json(['email' => $instance->user?->email]);
        });

        $this
            ->get('users/1')
            ->assertOk()
            ->assertJson([
                'email' => 'john.doe@example.com',
            ]);
    }

    public function test_it_binds_parent_and_child()
    {
        Route::get('users/{user}/dogs/{dog}', function () {
            $instance = Binder::make(ParentAndChildBind::class);

            return response()->json([
                'email' => $instance->user?->email,
                'dog' => $instance->dog?->name,
            ]);
        });

        $this
            ->get('users/1/dogs/1')
            ->assertOk()
            ->assertJson([
                'email' => 'john.doe@example.com',
                'dog' => 'Scooter Harvey Wooferton',
            ]);
    }

    public function test_it_binds_enums()
    {
        Route::get('/option/{enum}', function () {
            $instance = Binder::make(EnumBind::class);

            return response()->json([
                'instance' => get_class($instance),
                'value' => $instance->enum->value,
            ]);
        });

        $this
            ->get('option/test1')
            ->assertOk()
            ->assertJson([
                'instance' => EnumBind::class,
                'value' => 'test1',
            ]);

        $this->get('option/test4')->assertNotFound();
    }

    public function test_it_calls_methods_on_uninstantiated_classes()
    {
        Route::get('users/{user}/dogs/{dog}', function () {
            $instance = Binder::call(MethodBind::class, 'execute');

            return response()->json([
                'email' => $instance->user?->email,
                'dog' => $instance->dog?->name,
            ]);
        });

        $this
            ->get('users/1/dogs/1')
            ->assertOk()
            ->assertJson([
                'email' => 'john.doe@example.com',
                'dog' => 'Scooter Harvey Wooferton',
            ]);
    }

    public function test_it_calls_methods_on_instantiated_classes()
    {
        Route::get('users/{user}/dogs/{dog}', function () {
            $instance = new MethodBind();

            Binder::call($instance, 'execute');

            return response()->json([
                'email' => $instance->user?->email,
                'dog' => $instance->dog?->name,
            ]);
        });

        $this
            ->get('users/1/dogs/1')
            ->assertOk()
            ->assertJson([
                'email' => 'john.doe@example.com',
                'dog' => 'Scooter Harvey Wooferton',
            ]);
    }
}