<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Cipher;

use Patchlevel\Hydrator\Extension\Cryptography\Cipher\MethodNotSupported;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\OpensslCipherKeyFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function strlen;

#[CoversClass(OpensslCipherKeyFactory::class)]
final class OpensslCipherKeyFactoryTest extends TestCase
{
    public function testCreateKey(): void
    {
        $cipherKeyFactory = new OpensslCipherKeyFactory();
        $cipherKey = $cipherKeyFactory('test-subject');

        $this->assertSame(32, strlen($cipherKey->key));
        $this->assertSame('aes-128-gcm', $cipherKey->method);
        $this->assertSame('test-subject', $cipherKey->subjectId);
    }

    public function testMethodNotSupported(): void
    {
        $this->expectException(MethodNotSupported::class);

        $cipherKeyFactory = new OpensslCipherKeyFactory(method: 'foo');
        $cipherKeyFactory('test-subject');
    }
}
