<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

function jwt_encode(array $payload, string $secret, int $ttlSeconds): string
{
    $now = time();
    $claims = array_merge($payload, array(
        'iat' => $now,
        'exp' => $now + $ttlSeconds,
    ));

    $header = array('alg' => 'HS256', 'typ' => 'JWT');
    $segments = array(
        base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES)),
        base64url_encode(json_encode($claims, JSON_UNESCAPED_SLASHES)),
    );
    $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
    $segments[] = base64url_encode($signature);

    return implode('.', $segments);
}

function jwt_decode(string $token, string $secret): array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new RuntimeException('Token invalido');
    }

    [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $secret, true));
    if (!hash_equals($expected, $signatureEncoded)) {
        throw new RuntimeException('Firma JWT invalida');
    }

    $payload = json_decode((string) base64url_decode($payloadEncoded), true);
    if (!is_array($payload)) {
        throw new RuntimeException('Payload JWT invalido');
    }

    if ((int) ($payload['exp'] ?? 0) < time()) {
        throw new RuntimeException('Token expirado');
    }

    return $payload;
}

function base64url_encode(string|false $value): string
{
    if ($value === false) {
        throw new RuntimeException('No se pudo codificar JWT');
    }

    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64url_decode(string $value): string|false
{
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($value, '-_', '+/'), true);
}
