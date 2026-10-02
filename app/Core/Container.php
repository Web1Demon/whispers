<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Lightweight PSR-11 inspired Dependency Injection Container
 */
class Container
{
    private array $instances = [];
    private array $bindings = [];

    public function set(string $abstract, mixed $concrete): void
    {
        $this->instances[$abstract] = $concrete;
    }

    public function bind(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = function (Container $c) use ($concrete, $abstract) {
            static $instance = null;
            if ($instance === null) {
                if (is_callable($concrete)) {
                    $instance = $concrete($c);
                } else {
                    $instance = $c->build($concrete);
                }
            }
            return $instance;
        };
    }

    public function get(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];
            if (is_callable($concrete)) {
                return $concrete($this);
            }
            return $this->build($concrete);
        }

        return $this->build($abstract);
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract]) || isset($this->bindings[$abstract]) || class_exists($abstract);
    }

    public function build(string $className): object
    {
        if (!class_exists($className)) {
            throw new RuntimeException("Target class [{$className}] does not exist.");
        }

        $reflector = new ReflectionClass($className);

        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("Target class [{$className}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();
        if ($constructor === null) {
            return new $className();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new RuntimeException("Cannot resolve parameter [{$parameter->getName()}] in class [{$className}].");
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}
