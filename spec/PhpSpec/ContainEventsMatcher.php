<?php namespace spec\OrderFulfillment\PhpSpec;

use OrderFulfillment\EventSourcing\DomainEvent;
use OrderFulfillment\EventSourcing\DomainEvents;
use OrderFulfillment\EventSourcing\StreamEvent;
use OrderFulfillment\EventSourcing\StreamEvents;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Matcher\Matcher;

class ContainEventsMatcher implements Matcher {

    /**
     * Checks if matcher supports provided subject and matcher name.
     *
     * @param string $name
     * @param mixed $subject
     * @param array $arguments
     *
     * @return Boolean
     */
    public function supports($name, $subject, array $arguments) {
        return $name === 'containEvents' || $name === 'containEvent';
    }

    /**
     * Evaluates positive match.
     *
     * Yes, I know that this is some really hard to understand code.
     *
     * @param string $name
     * @param mixed $subject
     * @param array $arguments
     */
    public function positiveMatch($name, $subject, array $arguments) {
        list($realEvents, $targetEvents) = $this->formatArguments($subject, $arguments);

        $notFoundEvents = $this->eventsNotFound($realEvents, $targetEvents);
        if ( ! empty($notFoundEvents)) {
            $eventNames = join(', ', array_map(function (DomainEvent $event) {
                return '<label>' . get_class($event) . '</label>';
            }, $notFoundEvents));

            throw new FailureException("Expected event(s) {$eventNames} not found.");
        }
    }

    /**
     * Evaluates negative match.
     *
     * @param string $name
     * @param mixed $subject
     * @param array $arguments
     */
    public function negativeMatch($name, $subject, array $arguments) {

    }

    /**
     * Returns matcher priority.
     *
     * @return integer
     */
    public function getPriority() {
        return 50;
    }

    /**
     * @param $subject
     * @param array $arguments
     * @return array
     */
    private function formatArguments($subject, array $arguments):array {
        $realEvents = $subject;

        if ($realEvents instanceof StreamEvents) {
            $realEvents = DomainEvents::make(
                $realEvents->map(function(StreamEvent $streamEvent) {
                    return $streamEvent->event();
                })->toArray()
            );
        } elseif ($realEvents instanceof DomainEvent) {
            $realEvents = DomainEvents::make([$realEvents]);
        }

        $targetEvents = DomainEvents::make(
            is_array($arguments[0]) ? $arguments[0] : [$arguments[0]]
        );

        return array($realEvents, $targetEvents);
    }

    private function eventsNotFound($realEvents, $targetEvents) {
        return $targetEvents->filter(function ($targetEvent) use ($realEvents) {
            return ! $this->eventIsFound($targetEvent, $realEvents);
        })->toArray();
    }

    // returns true if an event is found

    private function eventIsFound(DomainEvent $targetEvent, DomainEvents $realEvents) {
        $found = $realEvents->filter(function ($realEvent) use ($targetEvent) {
            return $this->eventsAreEqual($realEvent, $targetEvent);
        });
        return $found->count() != 0;
    }

    // pull requests accepted

    private function eventsAreEqual($e1, $e2) {
        // events aren't equal if they have different classes
        if (get_class($e1) != get_class($e2)) {
            return false;
        }

        // compare their values..
        $reflection = new \ReflectionClass($e1);

        $fields = array_map(function ($property) {
            return $property->name;
        }, $reflection->getProperties());

        $allMatch = true;

        foreach ($fields as $field) {
            // compare the single field across both events
            $property = new \ReflectionProperty(get_class($e1), $field);
            $property->setAccessible(true);
            $e1Value = $property->getValue($e1);
            $e2Value = $property->getValue($e2);

            if (is_scalar($e1Value)) {
                if ($e1Value !== $e2Value) {
                    throw new FailureException("event <label>" . get_class($e1) . "</label> field <code>" . $property->getName() . "</code> expected <value>{$e2Value}</value> but got <value>{$e1Value}</value>.");
                    $allMatch = false;
                }
            } elseif ($e1Value instanceof \DateTimeImmutable) {
                if ($e1Value->format('Y-m-d H:i:s') !== $e2Value->format('Y-m-d H:i:s')) {
                    throw new FailureException("event <label>" . get_class($e1) . "</label> field <code>" . $property->getName() . "</code> expected <value>{$e2Value->format('Y-m-d H:i:s')}</value> but got <value>{$e1Value->format('Y-m-d H:i:s')}</value>.");
                    $allMatch = false;
                }
            } else {
                if ( ! $e1Value->equals($e2Value)) {
                    throw new FailureException("event <label>" . get_class($e1) . "</label> field <code>" . $property->getName() . "</code> expected <value>{$e2Value->toString()}</value> but got <value>{$e1Value->toString()}</value>.");
                    $allMatch = false;
                }
            }
        }

        return $allMatch;
    }
}

