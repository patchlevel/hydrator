<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Groups;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\StackHydratorBuilder;

final readonly class GroupsExtension implements Extension
{
    /** Context key to select the groups, accepts a string or a list of strings. */
    public const GROUPS = 'groups';

    /** Context key to exclude groups, accepts a string or a list of strings. */
    public const IGNORED_GROUPS = 'ignored_groups';

    /** Group that matches every property, including the ones without groups. */
    public const ALL = '*';

    public function configure(StackHydratorBuilder $builder): void
    {
        $builder->addMetadataEnricher(new GroupsMetadataEnricher());
        $builder->addPropertyFilter(new GroupsPropertyFilter());
    }
}
