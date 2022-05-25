<?php

namespace AidanCasey\Laravel\RouteBinding\Descriptors;

use Illuminate\Support\Collection;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionParameter;
use StringBackedEnum;

final class ParameterDescriptor
{
    private ReflectionParameter $reflection;

    private Collection $types;

    public function __construct(ReflectionParameter $reflectionParameter)
    {
        $this->reflection = $reflectionParameter;

        $this->resolveTypes();
    }

    public function getReflection(): ReflectionParameter
    {
        return $this->reflection;
    }

    public function getName(): string
    {
        return $this->reflection->getName();
    }

    public function getTypes(): Collection
    {
        return $this->types;
    }

    /**
     * @return Collection<array-key,StringBackedEnum>
     */
    public function getStringBackedEnums(): Collection
    {
        return $this->types->filter(function ($type) {
            if (! enum_exists($type)) {
                return false;
            }

            $reflection = new ReflectionEnum($type);

            if ($reflection->getBackingType()?->getName() !== 'string') {
                return false;
            }

            return true;
        });
    }

    public function hasStringBackedEnums(): bool
    {
        return $this->getStringBackedEnums()->isNotEmpty();
    }

    public function getType(string $type): Collection
    {
        return $this->types->filter(
            fn (string $resolvedType) => $type === $resolvedType || is_subclass_of($resolvedType, $type)
        );
    }

    public function hasType(string $type): bool
    {
        return $this->getType($type)->isNotEmpty();
    }

    private function resolveTypes(): void
    {
        $type = $this->reflection->getType();
        $types = match (get_class($type)) {
            'ReflectionNamedType' => [ $type ],
            'ReflectionUnionType' => $type->getTypes(),
            'ReflectionIntersectionType' => $type->getTypes(),
            default => []
        };

        $this->types = collect($types)
            ->filter(
                fn ($type) => ($type instanceof ReflectionNamedType) && $type->getName()
            )
            ->map(
                fn (ReflectionNamedType $type) => $type->getName()
            );
    }
}