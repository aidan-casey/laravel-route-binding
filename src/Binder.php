<?php

namespace AidanCasey\Laravel\RouteBinding;

use AidanCasey\Laravel\RouteBinding\Descriptors\BoundDependencyDescriptor;
use AidanCasey\Laravel\RouteBinding\Descriptors\BoundDescriptor;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use ReflectionMethod;

class Binder
{
    private Route $route;

    private string|object $class;

    private BoundDescriptor $descriptor;

    private array $parameters;

    public function __construct(Route $route, string|object $class, ?string $method = '__construct', array $parameters = [])
    {
        $this->route = $route;
        $this->class = $class;
        $this->descriptor = new BoundDescriptor($class, $method);

        $this->parameters = array_replace_recursive($route->parameters(), $parameters);
    }

    public static function call(string|object $class, string $method, array $parameters = []): mixed
    {
        $binder = app(Binder::class, [
            'class' => $class,
            'method' => $method,
            'parameters' => $parameters,
        ]);

        return $binder->bind();
    }

    /**
     * @template TDestination
     *
     * @param class-string<TDestination> $class
     *
     * @return TDestination
     */
    public static function make(string $class, array $parameters = [])
    {
        $binder = app(Binder::class, [
            'class' => $class,
            'parameters' => $parameters,
        ]);

        return $binder->bind();
    }

    public function bind(): mixed
    {
        $this->bindEnums();
        $this->bindUrlRoutables();

        $method = $this->descriptor->getMethod();

        if (! $method && ! $this->descriptor->hasConstructor()) {
            return new ($this->descriptor->getName());
        }

        $instance = $this->createClass($this->parameters);

        if (! $method || $method->getName() === '__construct') {
            return $instance;
        }

        $parameters = $this->route->resolveMethodDependencies($this->parameters, $method);

        return call_user_func_array([$instance, $method->getName()], $parameters);
    }

    private function createClass(array $parameters): object
    {
        if (! is_string($this->class)) {
            return $this->class;
        }

        $instance = $this->descriptor->getReflection()->newInstanceWithoutConstructor();

        if ($this->descriptor->hasConstructor()) {
            $parameters = $this->route->resolveMethodDependencies(
                $parameters, new ReflectionMethod($instance, '__construct')
            );

            call_user_func_array([$instance, '__construct'], $parameters);
        }

        return $instance;
    }

    private function bindEnums(): void
    {
        $enumDependencies = $this->descriptor->getDependencies()->filter(
            fn (BoundDependencyDescriptor $dependency) => $dependency->hasStringBackedEnums()
        );

        foreach ($enumDependencies as $enum) {
            $this->bindEnum($enum);
        }
    }

    private function bindUrlRoutables(): void
    {
        $routableDependencies = $this->descriptor->getDependencies(UrlRoutable::class);

        foreach ($routableDependencies as $routable) {
            $this->bindUrlRoutable($routable);
        }
    }

    private function bindUrlRoutable(BoundDependencyDescriptor $routable): void
    {
        if (! ($name = $this->getParameterName($routable->getName()))) {
            return;
        }

        $value = $this->parameters[$name];

        // If this value has already been bound, ignore it.
        if ($value instanceof UrlRoutable) {
            return;
        }

        $type = $routable->getType(UrlRoutable::class)->first();

        $instance = app()->make($type);

        $parent = $this->getParentOfParameter($name);

        $routeBindingMethod = $this->getBindingMethod($instance);

        $routeBindingField = $this->route->bindingFieldFor($name);

        if ($parent instanceof UrlRoutable && ($this->route->enforcesScopedBindings() || $routeBindingField)) {
            $childRouteBindingMethod = $this->getChildBindingMethod($instance);

            $model = $parent->{$childRouteBindingMethod}($name, $value, $routeBindingField);
        } else {
            $model = $instance->{$routeBindingMethod}($value, $routeBindingField);
        }

        if (isset($model)) {
            $this->parameters[$name] = $model;

            return;
        }

        throw (new ModelNotFoundException)->setModel($type, [$value]);
    }

    private function bindEnum(BoundDependencyDescriptor $enum): void
    {
        if (! ($name = $this->getParameterName($enum->getName()))) {
            return;
        }

        $value = (string) $this->parameters[$name];

        $enumClass = $enum->getStringBackedEnums()->first();

        $enum = $enumClass::tryFrom($value);

        if (is_null($enum)) {
            throw new BackedEnumCaseNotFoundException($enumClass, $value);
        }

        $this->parameters[$name] = $enum;
    }

    private function getParameterName(string $name): ?string
    {
        if (array_key_exists($name, $this->parameters)) {
            return $name;
        }

        if (array_key_exists($snakedName = Str::snake($name), $this->parameters)) {
            return $snakedName;
        }

        return null;
    }

    private function getParentOfParameter(string $parameter): mixed
    {
        $key = array_search($parameter, array_keys($this->parameters));

        if ($key === 0) {
            return null;
        }

        return array_values($this->parameters)[$key - 1];
    }

    private function getBindingMethod(UrlRoutable $instance): string
    {
        if (
            $this->route->allowsTrashedBindings() &&
            in_array(SoftDeletes::class, class_uses_recursive($instance))
        ) {
            return 'resolveSoftDeletableRouteBinding';
        }

        return 'resolveRouteBinding';
    }

    private function getChildBindingMethod(UrlRoutable $instance): string
    {
        if (
            $this->route->allowsTrashedBindings() &&
            in_array(SoftDeletes::class, class_uses_recursive($instance))
        ) {
            return 'resolveSoftDeletableChildRouteBinding';
        }

        return 'resolveChildRouteBinding';
    }
}