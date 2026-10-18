<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

/** @internal */
final class GeneratedCode
{
    /**
     * @param string             $code   php code of the mapper
     * @param list<class-string> $nested classes whose objects may be mapped in place, they need a mapper too
     */
    public function __construct(
        public readonly string $code,
        public readonly array $nested,
    ) {
    }
}
