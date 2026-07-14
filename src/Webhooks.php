<?php

declare(strict_types=1);

namespace CarecaPay;

/**
 * Verificação de webhooks (header X-CarecaPay-Signature).
 *
 * Use SEMPRE o corpo CRU da requisição (file_get_contents('php://input')):
 * re-serializar o JSON muda os bytes e invalida o HMAC.
 */
final class Webhooks
{
    /** Diz se a entrega veio mesmo da CarecaPay (HMAC + janela anti-replay). */
    public static function verifySignature(
        string $payload,
        string $header,
        string $secret,
        int $toleranceSeconds = 300,
    ): bool {
        $parts = [];
        foreach (explode(',', $header) as $piece) {
            if (str_contains($piece, '=')) {
                [$key, $value] = explode('=', $piece, 2);
                $parts[$key] = $value;
            }
        }
        if (!isset($parts['t'], $parts['v1']) || !ctype_digit($parts['t'])) {
            return false;
        }
        $timestamp = (int) $parts['t'];
        if (abs(time() - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', "$timestamp.$payload", $secret);
        return hash_equals($expected, $parts['v1']);
    }

    /**
     * Verifica a assinatura e devolve o evento parseado; lança se não valer.
     *
     * @throws CarecaPayWebhookException
     */
    public static function constructEvent(
        string $payload,
        string $header,
        string $secret,
        int $toleranceSeconds = 300,
    ): array {
        if (!self::verifySignature($payload, $header, $secret, $toleranceSeconds)) {
            throw new CarecaPayWebhookException(
                'assinatura de webhook inválida ou expirada — confira o secret '
                    . 'e use o corpo cru da requisição',
            );
        }
        return json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
    }
}
