<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Middleware;

use Patchlevel\Hydrator\Middleware\NoMoreMiddleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Tests\Unit\Fixture\DummyMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stack::class)]
final class StackTest extends TestCase
{
    public function testStack(): void
    {
        $middleware1 = new DummyMiddleware();
        $middleware2 = new DummyMiddleware();

        $stack = new Stack([$middleware1, $middleware2]);

        self::assertSame($middleware1, $stack->next());
        self::assertSame($middleware2, $stack->next());
    }

    public function testStackThrowsExceptionWhenNoMoreMiddlewareIsAvailable(): void
    {
        $middleware1 = new DummyMiddleware();
        $middleware2 = new DummyMiddleware();

        $stack = new Stack([$middleware1, $middleware2]);

        $stack->next();
        $stack->next();

        $this->expectException(NoMoreMiddleware::class);
        $this->expectExceptionMessage(
            'The next middleware in Patchlevel\Hydrator\Tests\Unit\Fixture\DummyMiddleware was requested, but no further middleware exists. The following middlewares were executed: Patchlevel\Hydrator\Tests\Unit\Fixture\DummyMiddleware, Patchlevel\Hydrator\Tests\Unit\Fixture\DummyMiddleware',
        );

        $stack->next();
    }
}
