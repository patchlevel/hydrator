<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use Patchlevel\Hydrator\Hydrator\HydratorException;

use function sprintf;

final class PropertyMetadataNotFound extends HydratorException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function withName(string $name): self
    {
        return new self(sprintf('property metadata with the field name "%s" not found', $name));
    }
}
