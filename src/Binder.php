<?php

namespace AidanCasey\Laravel\RouteBinding;

use AidanCasey\Laravel\RouteBinding\Descriptors\MethodDescriptor;
use AidanCasey\Laravel\RouteBinding\Descriptors\ParameterDescriptor;
use AidanCasey\Laravel\RouteBinding\Descriptors\ClassDescriptor;
use BackedEnum;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Illuminate\Routing\Route;
use Illuminate\Support\Reflector;
use Illuminate\Support\Str;

class Binder
{
    private Route $route;

    private string|object $class;

    private ClassDescriptor $descriptor;

    private string $methodName;

    private array $parameters;

    public function __construct(Route $route, string|object $class, string $method = '__construct', array $parameters = [])
    {
        $this->route = $route;
        $this->class = $class;
        $this->descriptor = new ClassDescriptor($class);
        $this->methodName = $method;
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
        if ($this->methodName === '__construct') {
            return $this->bindClass();
        }

        return $this->bindMethod($this->methodName);
    }

    private function bindClass(): object
    {
        if (! is_string($this->class)) {
            return $this->class;
        }

        $method = $this->descriptor->getMethod('__construct');

        return $this->descriptor->newInstance(
            ($method) ? $this->getMethodDependencies($method) : []
        );
    }

    private function bindMethod(string $methodName): mixed
    {
        $instance = $this->bindClass();
        $method = $this->descriptor->getMethod($methodName);

        return call_user_func_array(
            [$instance, $methodName], $this->getMethodDependencies($method)
        );
    }

    private function getMethodDependencies(MethodDescriptor $method): array
    {
        $parameters = $this->resolveParametersForMethod($method);
        $parameters = $this->bindEnumsForMethod($method, $parameters);
        $parameters = $this->bindUrlRoutablesForMethod($method, $parameters);

        foreach ($method->getParameters() as $parameter) {
            // Skip parameters that have been resolved.
            if (isset($parameters[$parameter->getName()])) {
                continue;
            }

            $class = Reflector::getParameterClassName($parameter->getReflection());

            if (! $class) {
                continue;
            }

            $parameters[$parameter->getName()] = app()->make($class);
        }

        return $parameters;
    }

    private function resolveParametersForMethod(MethodDescriptor $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $parameterName = $parameter->getName();

            if ($name = $this->getParameterName($parameterName)) {
                $parameters[$parameterName] = $this->parameters[$name];
            }
        }

        return $parameters;
    }

    private function bindEnumsForMethod(MethodDescriptor $method, array $parameters): array
    {
        $enumDependencies = $method->getParameters()->filter(
            fn (ParameterDescriptor $dependency) => $dependency->hasStringBackedEnums()
        );

        foreach ($enumDependencies as $enumDependency) {
            $value = $parameters[$enumDependency->getName()] ?? null;

            if (! $value) {
                continue;
            }

            if ($resolved = $this->resolveEnumDependency($enumDependency, $value)) {
                $parameters[$enumDependency->getName()] = $resolved;
            }
        }

        return $parameters;
    }

    private function bindUrlRoutablesForMethod(MethodDescriptor $method, array $parameters): array
    {
        $routableDependencies = $method->getParameters(UrlRoutable::class);

        foreach ($routableDependencies as $routableDependency) {
            $value = $parameters[$routableDependency->getName()] ?? null;

            if (! $value) {
                continue;
            }

            if ($resolved = $this->resolveUrlRoutableDependency($routableDependency, $value)) {
                $parameters[$routableDependency->getName()] = $resolved;
            }
        }

        return $parameters;
    }

    private function resolveEnumDependency(ParameterDescriptor $parameter, mixed $value): ?BackedEnum
    {
        $enumClass = $parameter->getStringBackedEnums()->first();

        $enum = $enumClass::tryFrom($value);

        if (is_null($enum)) {
            throw new BackedEnumCaseNotFoundException($enumClass, $value);
        }

        return $enum;
    }

    private function resolveUrlRoutableDependency(ParameterDescriptor $parameter, mixed $value): ?UrlRoutable
    {
        // If this value has already been bound, ignore it.
        if ($value instanceof UrlRoutable) {
            return $value;
        }

        $name = $parameter->getName();

        $type = $parameter->getType(UrlRoutable::class)->first();

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
            return $model;
        }

        throw (new ModelNotFoundException)->setModel($type, [$value]);
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