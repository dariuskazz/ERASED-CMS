<?php
declare(strict_types=1);

namespace Erased\Events;

use Closure;
use RuntimeException;
use Throwable;

final class EventDispatcher
{
    /** @var array<string,list<array{priority:int,order:int,listener:Closure}>> */
    private array $listeners = [];

    private int $registrationOrder = 0;

    public function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->assertEventName($eventName);

        $this->listeners[$eventName][] = [
            'priority' => $priority,
            'order' => $this->registrationOrder++,
            'listener' => Closure::fromCallable($listener),
        ];
    }

    public function forget(string $eventName): void
    {
        $this->assertEventName($eventName);
        unset($this->listeners[$eventName]);
    }

    public function hasListeners(string $eventName): bool
    {
        $this->assertEventName($eventName);
        return isset($this->listeners[$eventName]) && $this->listeners[$eventName] !== [];
    }

    /** @return list<mixed> */
    public function dispatch(string $eventName, object $event): array
    {
        $this->assertEventName($eventName);
        $listeners = $this->listeners[$eventName] ?? [];

        usort(
            $listeners,
            static function (array $left, array $right): int {
                $priority = $right['priority'] <=> $left['priority'];
                return $priority !== 0 ? $priority : ($left['order'] <=> $right['order']);
            },
        );

        $results = [];
        foreach ($listeners as $entry) {
            try {
                $results[] = ($entry['listener'])($event);
            } catch (Throwable $error) {
                throw new RuntimeException(
                    "Event listener failed for '{$eventName}': {$error->getMessage()}",
                    0,
                    $error,
                );
            }
        }

        return $results;
    }

    /** @return array<string,int> */
    public function listenerCounts(): array
    {
        $counts = [];
        foreach ($this->listeners as $eventName => $listeners) {
            $counts[$eventName] = count($listeners);
        }
        ksort($counts);
        return $counts;
    }

    private function assertEventName(string $eventName): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $eventName) !== 1) {
            throw new RuntimeException("Invalid event name '{$eventName}'.");
        }
    }
}
