<?php
declare(strict_types=1);

namespace Erased\Container;

use Closure;
use RuntimeException;
use Throwable;

final class ServiceContainer
{
    /** @var array<string,array{factory:Closure,shared:bool}> */
    private array $definitions = [];

    /** @var array<string,object> */
    private array $instances = [];

    /** @var array<string,string> */
    private array $aliases = [];

    /** @var array<string,true> */
    private array $resolving = [];

    public function set(string $id, Closure $factory, bool $shared = true): void
    {
        $this->assertId($id);
        $this->definitions[$id] = ['factory' => $factory, 'shared' => $shared];
        unset($this->instances[$id]);
    }

    public function instance(string $id, object $instance): void
    {
        $this->assertId($id);
        unset($this->definitions[$id]);
        $this->instances[$id] = $instance;
    }

    public function alias(string $alias, string $target): void
    {
        $this->assertId($alias);
        $this->assertId($target);
        if ($alias === $target) {
            throw new RuntimeException("Service alias '{$alias}' cannot reference itself.");
        }
        $this->aliases[$alias] = $target;
    }

    public function remove(string $id): void
    {
        unset($this->definitions[$id], $this->instances[$id], $this->aliases[$id]);
        foreach ($this->aliases as $alias => $target) {
            if ($target === $id) {
                unset($this->aliases[$alias]);
            }
        }
    }

    public function has(string $id): bool
    {
        try {
            $resolved = $this->resolveAlias($id);
        } catch (RuntimeException) {
            return false;
        }
        return isset($this->instances[$resolved]) || isset($this->definitions[$resolved]);
    }

    public function get(string $id): object
    {
        $resolved = $this->resolveAlias($id);

        if (isset($this->instances[$resolved])) {
            return $this->instances[$resolved];
        }

        $definition = $this->definitions[$resolved] ?? null;
        if ($definition === null) {
            throw new RuntimeException("Service '{$id}' is not registered.");
        }

        if (isset($this->resolving[$resolved])) {
            $chain = implode(' -> ', array_keys($this->resolving));
            throw new RuntimeException("Circular service dependency detected: {$chain} -> {$resolved}");
        }

        $this->resolving[$resolved] = true;
        try {
            $service = ($definition['factory'])($this);
        } catch (Throwable $error) {
            throw new RuntimeException("Failed to resolve service '{$resolved}': {$error->getMessage()}", 0, $error);
        } finally {
            unset($this->resolving[$resolved]);
        }

        if (!is_object($service)) {
            throw new RuntimeException("Service factory '{$resolved}' must return an object.");
        }

        if ($definition['shared']) {
            $this->instances[$resolved] = $service;
        }

        return $service;
    }

    /** @return list<string> */
    public function ids(): array
    {
        $ids = array_unique(array_merge(
            array_keys($this->definitions),
            array_keys($this->instances),
            array_keys($this->aliases),
        ));
        sort($ids);
        return array_values($ids);
    }

    /** @return array<string,string> */
    public function aliases(): array
    {
        $aliases = $this->aliases;
        ksort($aliases);
        return $aliases;
    }

    private function resolveAlias(string $id): string
    {
        $this->assertId($id);
        $seen = [];
        $current = $id;
        while (isset($this->aliases[$current])) {
            if (isset($seen[$current])) {
                $chain = implode(' -> ', array_keys($seen));
                throw new RuntimeException("Circular service alias detected: {$chain} -> {$current}");
            }
            $seen[$current] = true;
            $current = $this->aliases[$current];
        }
        return $current;
    }

    private function assertId(string $id): void
    {
        if (trim($id) === '') {
            throw new RuntimeException('Service id cannot be empty.');
        }
    }
}
