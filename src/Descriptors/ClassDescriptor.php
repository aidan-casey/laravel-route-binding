<?php

namespace AidanCasey\Laravel\RouteBinding\Descriptors;

use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionMethod;

final class ClassDescriptor
{
    private ReflectionClass $reflection;

    private Collection $methods;

    public function __construct(string|object $class)
    {
        $this->reflection = new ReflectionClass($class);

        $this->resolveMethods();
    }

    public function newInstance(array $parameters = []): object
    {
        if ($this->hasConstructor()) {
            return $this->reflection->newInstanceArgs($parameters);
        }

        return $this->reflection->newInstanceWithoutConstructor();
    }

    public function getName(): string
    {
        return $this->reflection->getName();
    }

    public function getReflection(): ReflectionClass
    {
        return $this->reflection;
    }

    public function getMethod(string $method): ?MethodDescriptor
    {
        return $this->methods->get($method);
    }

    public function hasConstructor(): bool
    {
        return $this->reflection->hasMethod('__construct');
    }

    private function resolveMethods(): void
    {
        $this->methods = collect($this->reflection->getMethods())
            ->mapWithKeys(
                fn (ReflectionMethod $method) => [$method->getName() => new MethodDescriptor($method)]
            );
    }
}