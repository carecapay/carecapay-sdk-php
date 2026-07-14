<?php

declare(strict_types=1);

namespace CarecaPay\Tests;

use CarecaPay\CarecaPayWebhookException;
use CarecaPay\Webhooks;
use PHPUnit\Framework\TestCase;

final class WebhooksTest extends TestCase
{
    private const SECRET = 'ccp_whsec_abc123';

    private function sign(int $t, string $payload): string
    {
        $mac = hash_hmac('sha256', "$t.$payload", self::SECRET);
        return "t=$t,v1=$mac";
    }

    public function testVerifySignatureAceitaValidaERecente(): void
    {
        $payload = json_encode(['id' => 'evt_1', 'type' => 'charge.paid']);
        $header = $this->sign(time(), $payload);
        $this->assertTrue(Webhooks::verifySignature($payload, $header, self::SECRET));
    }

    public function testVerifySignatureRejeitaSecretErradoCorpoAdulteradoEReplay(): void
    {
        $payload = json_encode(['id' => 'evt_1', 'type' => 'charge.paid']);
        $now = time();
        $header = $this->sign($now, $payload);

        $this->assertFalse(Webhooks::verifySignature($payload, $header, 'outro_secret'));

        $tampered = json_encode(['id' => 'evt_1', 'type' => 'charge.paid', 'tampered' => true]);
        $this->assertFalse(Webhooks::verifySignature($tampered, $header, self::SECRET));

        $oldHeader = $this->sign($now - 600, $payload);
        $this->assertFalse(Webhooks::verifySignature($payload, $oldHeader, self::SECRET));
    }

    public function testConstructEventDevolveEventoQuandoValido(): void
    {
        $payload = json_encode([
            'id' => 'evt_1',
            'type' => 'charge.paid',
            'environment' => 'sandbox',
            'created_at' => '2026-07-14T00:00:00Z',
            'data' => ['id' => 'txn_1'],
        ]);
        $header = $this->sign(time(), $payload);
        $event = Webhooks::constructEvent($payload, $header, self::SECRET);
        $this->assertSame('charge.paid', $event['type']);
        $this->assertSame('evt_1', $event['id']);
    }

    public function testConstructEventLancaQuandoInvalido(): void
    {
        $this->expectException(CarecaPayWebhookException::class);
        Webhooks::constructEvent('{}', 't=0,v1=deadbeef', self::SECRET);
    }
}
