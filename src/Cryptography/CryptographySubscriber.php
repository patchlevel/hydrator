<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use Patchlevel\Hydrator\Event\PostExtract;
use Patchlevel\Hydrator\Event\PreHydrate;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CryptographySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PayloadCryptographer $cryptography,
    ) {
    }

    public function preHydrate(PreHydrate $event): void
    {
        $event->data = $this->cryptography->decrypt($event->metadata, $event->data);
    }

    public function postExtract(PostExtract $event): void
    {
        $event->data = $this->cryptography->encrypt($event->metadata, $event->data);
    }

    /** @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>> */
    public static function getSubscribedEvents(): array
    {
        return [
            PreHydrate::class => 'preHydrate',
            PostExtract::class => 'postExtract',
        ];
    }
}