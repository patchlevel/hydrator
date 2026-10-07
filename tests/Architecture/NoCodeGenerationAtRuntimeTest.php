<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Architecture;

use Patchlevel\Hydrator\Extension\Generated\ClassFingerprint;
use Patchlevel\Hydrator\Extension\Generated\ClassPlan;
use Patchlevel\Hydrator\Extension\Generated\ClassPlanner;
use Patchlevel\Hydrator\Extension\Generated\CodeEmitter;
use Patchlevel\Hydrator\Extension\Generated\GeneratedCode;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformer;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerFactory;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerWarmer;
use Patchlevel\Hydrator\Extension\Generated\NestedPlan;
use Patchlevel\Hydrator\Extension\Generated\PropertyPlan;
use Patchlevel\Hydrator\Extension\Generated\Templates;
use Patchlevel\Hydrator\Extension\Generated\TransformerFiles;
use Patchlevel\Hydrator\Extension\Generated\TransformerGenerator;
use Patchlevel\Hydrator\Extension\Generated\ValueKind;
use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * The generated code is only written by the GeneratedTransformerWarmer. The classes which run when the hydrator is
 * used must not even know how to generate it.
 */
final class NoCodeGenerationAtRuntimeTest
{
    public function testRuntimeDoesNotDependOnGeneration(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::classname(GeneratedTransformerExtension::class),
                Selector::classname(GeneratedTransformerFactory::class),
                Selector::classname(GeneratedTransformer::class),
                Selector::classname(TransformerFiles::class),
                Selector::classname(ClassFingerprint::class),
            )
            ->shouldNotDependOn()
            ->classes(
                Selector::classname(GeneratedTransformerWarmer::class),
                Selector::classname(TransformerGenerator::class),
                Selector::classname(ClassPlanner::class),
                Selector::classname(ClassPlan::class),
                Selector::classname(PropertyPlan::class),
                Selector::classname(NestedPlan::class),
                Selector::classname(ValueKind::class),
                Selector::classname(CodeEmitter::class),
                Selector::classname(Templates::class),
                Selector::classname(GeneratedCode::class),
            );
    }
}
