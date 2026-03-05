<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Cipher;

use JsonException;

use function base64_decode;
use function base64_encode;
use function json_decode;
use function json_encode;
use function openssl_cipher_iv_length;
use function openssl_decrypt;
use function openssl_encrypt;
use function openssl_random_pseudo_bytes;

use const JSON_THROW_ON_ERROR;

/** @experimental */
final class OpensslCipher implements Cipher
{
    public function encrypt(CipherKey $key, mixed $data): EncryptedData
    {
        $ivLength = @openssl_cipher_iv_length($key->method);

        if ($ivLength === false) {
            throw EncryptionFailed::invalidIvLength($key->method);
        }

        $nonce = $ivLength > 0 ? openssl_random_pseudo_bytes($ivLength) : null;
        $tag = null;

        $encryptedData = @openssl_encrypt(
            json_encode($data, JSON_THROW_ON_ERROR),
            $key->method,
            $key->key,
            0,
            $nonce ?? '',
            $tag,
        );

        if ($encryptedData === false) {
            throw EncryptionFailed::forMethod($key->method);
        }

        return new EncryptedData(
            base64_encode($encryptedData),
            $key->method,
            $nonce !== null ? base64_encode($nonce) : null,
            $tag !== null ? base64_encode($tag) : null,
        );
    }

    public function decrypt(CipherKey $key, EncryptedData $parameter): mixed
    {
        $tag = $parameter->tag !== null ? base64_decode($parameter->tag, true) : null;

        if ($parameter->tag !== null && $tag === false) {
            throw DecryptionFailed::invalidBase64('tag');
        }

        $nonce = $parameter->nonce !== null ? base64_decode($parameter->nonce, true) : null;

        if ($parameter->nonce !== null && $nonce === false) {
            throw DecryptionFailed::invalidBase64('nonce');
        }

        $data = @openssl_decrypt(
            base64_decode($parameter->data, true),
            $parameter->method,
            $key->key,
            0,
            $nonce ?: '',
            $tag ?: '',
        );

        if ($data === false) {
            throw DecryptionFailed::forMethod($parameter->method);
        }

        try {
            return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw DecryptionFailed::invalidJson($e);
        }
    }
}
