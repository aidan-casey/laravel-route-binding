<?php

namespace AidanCasey\Laravel\RouteBinding\Descriptors;

use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

final class BoundDescriptor
{
    private ReflectionClass $reflection;

    private ?ReflectionMethod $method;

    private Collection $dependencies;

    public function __construct(string|object $class, ?string $method = null)
    {
        $this->reflection = new ReflectionClass($class);
        $this->method = ($method && $this->reflection->hasMethod($method)) ? $this->reflection->getMethod($method) : null;

        $this->resolveDependencies();
    }

    public function getName(): string
    {
        return $this->reflection->getName();
    }

    public function getReflection(): ReflectionClass
    {
        return $this->reflection;
    }

    public function getMethod(): ?ReflectionMethod
    {
        return $this->method;
    }

    /**
     * @return Collection<array-key,BoundDependencyDescriptor>
     */
    public function getDependencies(?string $type = null): Collection
    {
        if (! isset($type)) {
            return $this->dependencies;
        }

        return $this->dependencies->filter(
            fn (BoundDependencyDescriptor $dependency) => $dependency->hasType($type)
        );
    }

    public function hasConstructor(): bool
    {
        return $this->reflection->hasMethod('__construct');
    }

    private function resolveDependencies(): void
    {
        $this->dependencies = collect(
            $this->method?->getParameters()
        )->map(
            fn (ReflectionParameter $parameter) => new BoundDependencyDescriptor($parameter)
        );
    }
}