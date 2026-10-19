<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Architecture;

use Patchlevel\Hydrator\Extension\Generated\ChainNestedMapper;
use Patchlevel\Hydrator\Extension\Generated\ClassPlan;
use Patchlevel\Hydrator\Extension\Generated\ClassPlanner;
use Patchlevel\Hydrator\Extension\Generated\CodeEmitter;
use Patchlevel\Hydrator\Extension\Generated\FilteredMapper;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapper;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperFactory;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperWarmer;
use Patchlevel\Hydrator\Extension\Generated\MapperGenerator;
use Patchlevel\Hydrator\Extension\Generated\Templates;
use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/** The classes the hydrator loads at runtime never pull in the code generation, only the warmer does. */
final class NoCodeGenerationAtRuntimeTest
{
    public function testRuntimeDoesNotDependOnTheGenerator(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::classname(GeneratedMapperExtension::class),
                Selector::classname(GeneratedMapperFactory::class),
                Selector::classname(GeneratedMapper::class),
                Selector::classname(ChainNestedMapper::class),
                Selector::classname(FilteredMapper::class),
            )
            ->shouldNotDependOn()
            ->classes(
                Selector::classname(GeneratedMapperWarmer::class),
                Selector::classname(MapperGenerator::class),
                Selector::classname(CodeEmitter::class),
                Selector::classname(ClassPlanner::class),
                Selector::classname(ClassPlan::class),
                Selector::classname(Templates::class),
            );
    }
}
