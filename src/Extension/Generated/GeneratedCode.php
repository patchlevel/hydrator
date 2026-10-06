<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

/** @internal */
final class GeneratedCode
{
    /**
     * @param string                       $code    the php file of the transformer
     * @param non-empty-list<class-string> $classes the class of the transformer first, followed by the inlined nested classes
     */
    public function __construct(
        public readonly string $code,
        public readonly array $classes,
    ) {
    }
}
