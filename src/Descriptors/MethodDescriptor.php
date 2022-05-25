<?php

namespace AidanCasey\Laravel\RouteBinding\Descriptors;

use Illuminate\Support\Collection;
use ReflectionMethod;
use ReflectionParameter;

final class MethodDescriptor
{
    private readonly ReflectionMethod $reflection;

    private Collection $parameters;

    public function __construct(ReflectionMethod $method)
    {
        $this->reflection = $method;

        $this->resolveParameters();
    }

    public function getName(): string
    {
        return $this->reflection->getName();
    }

    public function getClass(): string
    {
        return $this->reflection->getDeclaringClass()->getName();
    }

    public function getParameter(string $name): ParameterDescriptor
    {
        return $this->parameters->get($name);
    }

    /**
     * @return Collection<array-key,ParameterDescriptor>
     */
    public function getParameters(?string $type = null): Collection
    {
        if (! $type) {
            return $this->parameters->values();
        }

        return $this->parameters->filter(
            fn (ParameterDescriptor $parameter) => $parameter->hasType($type)
        )->values();
    }

    private function resolveParameters(): void
    {
        $this->parameters = collect($this->reflection->getParameters())
            ->mapWithKeys(
                fn (ReflectionParameter $parameter) => [$parameter->getName() => new ParameterDescriptor($parameter)]
            );
    }
}