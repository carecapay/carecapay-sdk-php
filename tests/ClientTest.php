<?php

declare(strict_types=1);

namespace CarecaPay\Tests;

use CarecaPay\CarecaPay;
use CarecaPay\CarecaPayException;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private static $serverProcess;
    private static string $baseUrl;
    private static string $stateDir;

    public static function setUpBeforeClass(): void
    {
        self::$stateDir = sys_get_temp_dir() . '/carecapay-sdk-php-tests';
        @mkdir(self::$stateDir);

        $port = random_int(49152, 60999);
        self::$baseUrl = "http://127.0.0.1:$port";
        self::$serverProcess = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/fake-server.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
        );
        usleep(300_000); // espera o php -S subir
    }

    public static function tearDownAfterClass(): void
    {
        proc_terminate(self::$serverProcess);
    }

    private function client(): CarecaPay
    {
        return new CarecaPay('ccp_secret_sandbox_key_secret', ['base_url' => self::$baseUrl]);
    }

    private function respond(int $status, array $body): void
    {
        file_put_contents(
            self::$stateDir . '/next-response.json',
            json_encode(['status' => $status, 'body' => $body]),
        );
    }

    private function lastRequest(): array
    {
        return json_decode(file_get_contents(self::$stateDir . '/last-request.json'), true);
    }

    public function testCreateChargeAutenticado(): void
    {
        $this->respond(201, ['id' => 'txn_1', 'status' => 'pending', 'amount_cents' => 1990]);

        $charge = $this->client()->charges->create([
            'amount_cents' => 1990,
            'description' => 'Pedido #42',
        ]);

        $request = $this->lastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertSame('/v1/charges', $request['path']);
        $this->assertSame('Bearer ccp_secret_sandbox_key_secret', $request['auth']);
        $this->assertSame(
            ['amount_cents' => 1990, 'description' => 'Pedido #42'],
            json_decode($request['body'], true),
        );
        $this->assertSame('txn_1', $charge['id']);
    }

    public function testCreateChargeMandaMethodECurrencyQuandoInformados(): void
    {
        $this->respond(201, ['id' => 'txn_2', 'status' => 'pending', 'method' => 'pix']);

        $this->client()->charges->create([
            'amount_cents' => 500,
            'method' => 'pix',
            'currency' => 'BRL',
        ]);

        $request = $this->lastRequest();
        $this->assertSame(
            ['amount_cents' => 500, 'method' => 'pix', 'currency' => 'BRL'],
            json_decode($request['body'], true),
        );
    }

    public function testGetListESimulate(): void
    {
        $this->respond(200, ['id' => 'txn_9', 'status' => 'paid']);
        $this->client()->charges->get('txn_9');
        $this->assertSame('/v1/charges/txn_9', $this->lastRequest()['path']);

        $this->respond(200, ['data' => [], 'count' => 0]);
        $list = $this->client()->charges->list(['status' => 'paid', 'limit' => 5]);
        $this->assertSame('/v1/charges?status=paid&limit=5', $this->lastRequest()['path']);
        $this->assertSame(0, $list['count']);

        $this->respond(200, ['id' => 'txn_2', 'status' => 'paid']);
        $paid = $this->client()->charges->simulatePayment('txn_2');
        $this->assertSame('/v1/charges/txn_2/simulate-payment', $this->lastRequest()['path']);
        $this->assertSame('POST', $this->lastRequest()['method']);
        $this->assertSame('paid', $paid['status']);
    }

    public function testBalance(): void
    {
        $this->respond(200, ['available_cents' => 100, 'pending_cents' => 50]);
        $balance = $this->client()->balance->get();
        $this->assertSame('/v1/balance', $this->lastRequest()['path']);
        $this->assertSame(100, $balance['available_cents']);
    }

    public function testErroDaApiViraExcecaoTipada(): void
    {
        $this->respond(400, [
            'error' => ['code' => 'invalid_amount', 'message' => 'deve ser positivo'],
        ]);

        try {
            $this->client()->charges->create(['amount_cents' => 0]);
            $this->fail('deveria ter lançado CarecaPayException');
        } catch (CarecaPayException $err) {
            $this->assertSame('invalid_amount', $err->code);
            $this->assertSame(400, $err->status);
            $this->assertStringContainsString('positivo', $err->getMessage());
        }
    }

    public function testBaseUrlInferidaDaChave(): void
    {
        $sandbox = new CarecaPay('ccp_secret_sandbox_x_y');
        $live = new CarecaPay('ccp_secret_live_x_y');
        $this->assertSame('https://api-sandbox.carecapay.com', $sandbox->baseUrl);
        $this->assertSame('https://api.carecapay.com', $live->baseUrl);
    }

    public function testChaveInvalidaRejeitadaNaConstrucao(): void
    {
        foreach (['', 'sk_test_outro', 'ccp_private_sandbox_abc'] as $bad) {
            try {
                new CarecaPay($bad);
                $this->fail("chave '$bad' deveria ter sido rejeitada");
            } catch (CarecaPayException $err) {
                $this->assertSame('invalid_secret_key', $err->code);
            }
        }
    }
}
