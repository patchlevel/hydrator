<?php

namespace Patchlevel\Hydrator\Cryptography;

use Closure;
use Patchlevel\Hydrator\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\Hydrator\Normalizer\ContextAwareNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\Normalizer;

final class CryptoNormalizer implements Normalizer, ContextAwareNormalizer
{
    public function __construct(
        private readonly Cryptographer $cryptographer,
        private readonly string $subjectIdName,
        private readonly mixed $fallback = null,
        private readonly Normalizer|null $normalizer = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws InvalidArgument
     */
    public function normalize(mixed $value, array $context = []): mixed
    {
        if ($this->normalizer !== null) {
            $value = $this->normalizer->normalize($value, $context);
        }

        if ($value === null) {
            return null;
        }

        $subjectId = $context[SubjectIds::class]->get($this->subjectIdName);

        return ['__enc' => $this->cryptographer->encrypt($subjectId, $value)];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws InvalidArgument
     */
    public function denormalize(mixed $value, array $context = []): mixed
    {
        if (!is_array($value) || !array_key_exists('__enc', $value)) {
            if ($this->normalizer === null) {
                return $value;
            }

            return $this->normalizer->denormalize($value, $context);
        }

        $subjectId = $context[SubjectIds::class]->get($this->subjectIdName);

        try {
            $data = $this->cryptographer->decrypt($subjectId, $value['__enc']);
        } catch (DecryptionFailed|CipherKeyNotExists) {
            if ($this->fallback instanceof Closure) {
                return ($this->fallback)($subjectId, $value['__enc']);
            }

            return $this->fallback;
        }

        if ($this->normalizer === null) {
            return $data;
        }

        return $this->normalizer->denormalize($data, $context);
    }
}