<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Upcast\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class UpcasterFor
{
    /**
     * @param class-string $className
     * @param int|null     $from      the version of the data the upcaster expects, it returns the data of the next
     *                                version. The upcaster then only runs for data of this or an older version, see
     *                                {@see \Patchlevel\Hydrator\Extension\Upcast\UpcastExtension::VERSIONS}.
     */
    public function __construct(
        public readonly string $className,
        public readonly int|null $from = null,
    ) {
    }
}
