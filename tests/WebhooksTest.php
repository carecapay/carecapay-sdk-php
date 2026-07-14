<?php

declare(strict_types=1);

namespace CarecaPay\Tests;

use CarecaPay\CarecaPayWebhookException;
use CarecaPay\Webhooks;
use PHPUnit\Framework\TestCase;

final class WebhooksTest extends TestCase
{
    private const SECRET = 'ccp_whsec_teste';
    private const PAYLOAD = '{"id":"evt_1","type":"charge.paid","data":{"id":"txn_1","amount_cents":1990}}';

    private function sign(string $payload, int $timestamp, string $secret = self::SECRET): string
    {
        $v1 = hash_hmac('sha256', "$timestamp.$payload", $secret);
        return "t=$timestamp,v1=$v1";
    }

    public function testAceitaAssinaturaValida(): void
    {
        $now = time();
        $this->assertTrue(
            Webhooks::verifySignature(self::PAYLOAD, $this->sign(self::PAYLOAD, $now), self::SECRET),
        );
    }

    public function testRejeitaSecretErradoCorpoAdulteradoEReplay(): void
    {
        $now = time();
        $this->assertFalse(Webhooks::verifySignature(
            self::PAYLOAD,
            $this->sign(self::PAYLOAD, $now, 'ccp_whsec_outro'),
            self::SECRET,
        ));
        $this->assertFalse(Webhooks::verifySignature(
            str_replace('1990', '1', self::PAYLOAD),
            $this->sign(self::PAYLOAD, $now),
            self::SECRET,
        ));
        $this->assertFalse(Webhooks::verifySignature(
            self::PAYLOAD,
            $this->sign(self::PAYLOAD, $now - 600),
            self::SECRET,
        ));
        $this->assertTrue(Webhooks::verifySignature(
            self::PAYLOAD,
            $this->sign(self::PAYLOAD, $now - 600),
            self::SECRET,
            toleranceSeconds: 3600,
        ));
    }

    public function testConstructEvent(): void
    {
        $event = Webhooks::constructEvent(self::PAYLOAD, $this->sign(self::PAYLOAD, time()), self::SECRET);
        $this->assertSame('charge.paid', $event['type']);
        $this->assertSame(1990, $event['data']['amount_cents']);

        $this->expectException(CarecaPayWebhookException::class);
        Webhooks::constructEvent(self::PAYLOAD, 't=123,v1=deadbeef', self::SECRET);
    }

    public function testHeaderMalformadoLanca(): void
    {
        $this->expectException(CarecaPayWebhookException::class);
        Webhooks::constructEvent(self::PAYLOAD, 'formato-invalido', self::SECRET);
    }
}
