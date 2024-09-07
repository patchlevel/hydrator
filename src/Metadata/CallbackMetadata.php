<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use ReflectionMethod;

/**
 * @psalm-type serialized = array{
 *     className: class-string,
 *     method: string,
 * }
 */
final class CallbackMetadata
{
    public function __construct(
        private readonly ReflectionMethod $reflection,
    ) {
    }

    public function reflection(): ReflectionMethod
    {
        return $this->reflection;
    }

    public function methodName(): string
    {
        return $this->reflection->getName();
    }

    public function invoke(object $object): void
    {
        $this->reflection->invoke($object);
    }

    /** @return serialized */
    public function __serialize(): array
    {
        return [
            'className' => $this->reflection->getDeclaringClass()->getName(),
            'method' => $this->reflection->getName(),
        ];
    }

    /** @param serialized $data */
    public function __unserialize(array $data): void
    {
        $this->reflection = new ReflectionMethod($data['className'], $data['method']);
    }
}
