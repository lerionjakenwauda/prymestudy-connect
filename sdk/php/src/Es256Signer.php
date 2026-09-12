<?php

declare(strict_types=1);

namespace PrymeStudy\Connect;

use RuntimeException;

final class Es256Signer
{
    public static function jwt(array $claims, string $privateKeyPem, string $keyId): string
    {
        $header = ['alg' => 'ES256', 'kid' => $keyId, 'typ' => 'JWT'];
        $encodedHeader = self::base64Url(self::json($header));
        $encodedClaims = self::base64Url(self::json($claims));
        $input = $encodedHeader.'.'.$encodedClaims;
        return $input.'.'.self::signRaw($input, $privateKeyPem);
    }

    public static function signRaw(string $message, string $privateKeyPem): string
    {
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            throw new RuntimeException('Unable to load ES256 private key.');
        }
        self::assertP256($key);

        $derSignature = '';
        if (! openssl_sign($message, $derSignature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to create ES256 signature.');
        }
        return self::base64Url(self::derEcdsaToJose($derSignature, 32));
    }

    public static function verifyRaw(string $message, string $signatureBase64Url, string $publicKeyPem): bool
    {
        $raw = self::base64UrlDecode($signatureBase64Url);
        if (strlen($raw) !== 64) {
            return false;
        }

        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false) {
            return false;
        }
        try {
            self::assertP256($key);
            $der = self::joseEcdsaToDer($raw, 32);
        } catch (RuntimeException) {
            return false;
        }

        return openssl_verify($message, $der, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    private static function assertP256(\OpenSSLAsymmetricKey $key): void
    {
        $details = openssl_pkey_get_details($key);
        if (! is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC) {
            throw new RuntimeException('PrymeStudy Connect ES256 requires an EC key.');
        }
        $curve = $details['ec']['curve_name'] ?? null;
        if (! in_array($curve, ['prime256v1', 'secp256r1'], true)) {
            throw new RuntimeException('PrymeStudy Connect ES256 requires a P-256 (prime256v1/secp256r1) key.');
        }
    }

    private static function derEcdsaToJose(string $der, int $partLength): string
    {
        $offset = 0;
        if (self::readByte($der, $offset) !== 0x30) {
            throw new RuntimeException('Invalid DER ECDSA signature sequence.');
        }
        $sequenceLength = self::readDerLength($der, $offset);
        if ($sequenceLength !== strlen($der) - $offset) {
            throw new RuntimeException('Invalid DER ECDSA signature length.');
        }

        $r = self::readDerInteger($der, $offset);
        $s = self::readDerInteger($der, $offset);
        if ($offset !== strlen($der)) {
            throw new RuntimeException('Unexpected data after DER ECDSA signature.');
        }
        return self::normalizeInteger($r, $partLength).self::normalizeInteger($s, $partLength);
    }

    private static function joseEcdsaToDer(string $raw, int $partLength): string
    {
        $r = self::derInteger(substr($raw, 0, $partLength));
        $s = self::derInteger(substr($raw, $partLength, $partLength));
        $body = "\x02".self::derLength(strlen($r)).$r."\x02".self::derLength(strlen($s)).$s;
        return "\x30".self::derLength(strlen($body)).$body;
    }

    private static function readDerInteger(string $der, int &$offset): string
    {
        if (self::readByte($der, $offset) !== 0x02) {
            throw new RuntimeException('Invalid DER ECDSA integer.');
        }
        $length = self::readDerLength($der, $offset);
        if ($length <= 0 || $offset + $length > strlen($der)) {
            throw new RuntimeException('Invalid DER ECDSA integer length.');
        }
        $value = substr($der, $offset, $length);
        $offset += $length;
        return $value;
    }

    private static function readDerLength(string $der, int &$offset): int
    {
        $first = self::readByte($der, $offset);
        if (($first & 0x80) === 0) return $first;
        $count = $first & 0x7f;
        if ($count < 1 || $count > 4 || $offset + $count > strlen($der)) {
            throw new RuntimeException('Invalid DER length encoding.');
        }
        $length = 0;
        for ($i = 0; $i < $count; $i++) $length = ($length << 8) | self::readByte($der, $offset);
        return $length;
    }

    private static function readByte(string $value, int &$offset): int
    {
        if ($offset >= strlen($value)) throw new RuntimeException('Unexpected end of DER value.');
        return ord($value[$offset++]);
    }

    private static function normalizeInteger(string $value, int $length): string
    {
        $value = ltrim($value, "\x00");
        if (strlen($value) > $length) throw new RuntimeException('ECDSA integer exceeds expected width.');
        return str_pad($value, $length, "\x00", STR_PAD_LEFT);
    }

    private static function derInteger(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value === '') $value = "\x00";
        if ((ord($value[0]) & 0x80) !== 0) $value = "\x00".$value;
        return $value;
    }

    private static function derLength(int $length): string
    {
        if ($length < 0x80) return chr($length);
        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff).$bytes;
            $length >>= 8;
        }
        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = (4 - (strlen($value) % 4)) % 4;
        $decoded = base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);
        return is_string($decoded) ? $decoded : '';
    }
}
