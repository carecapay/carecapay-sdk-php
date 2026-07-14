<?php

declare(strict_types=1);

namespace CarecaPay;

/**
 * SDK oficial PHP da CarecaPay.
 *
 *     $carecapay = new \CarecaPay\CarecaPay($_ENV['CARECAPAY_PRIVATE_KEY']);
 *     $charge = $carecapay->charges->create(['amount_cents' => 1990]);
 *     echo $charge['qr_code']; // copia e cola do Pix
 *
 * Zero dependências além de ext-curl/ext-json. Os arrays devolvidos têm
 * exatamente os shapes da API REST (snake_case).
 */
class CarecaPay
{
    public const VERSION = '0.1.0';

    private const BASE_URLS = [
        'sandbox' => 'https://sandbox.carecapay.com.br',
        'live' => 'https://api.carecapay.com.br',
    ];

    public readonly string $baseUrl;
    public readonly Charges $charges;
    public readonly Balance $balance;

    private string $secretKey;
    private int $timeoutSeconds;

    /**
     * @param array{base_url?: string, timeout_seconds?: int} $options
     */
    public function __construct(string $secretKey, array $options = [])
    {
        if (!preg_match('/^ccp_secret_(sandbox|live)_/', $secretKey, $match)) {
            throw new CarecaPayException(
                'invalid_secret_key',
                'a chave deve ser uma chave SECRETA da CarecaPay '
                    . '(ccp_secret_sandbox_... ou ccp_secret_live_...); '
                    . 'gere uma no painel, em Chaves de API',
            );
        }
        $this->secretKey = $secretKey;
        $this->timeoutSeconds = $options['timeout_seconds'] ?? 15;
        $this->baseUrl = rtrim($options['base_url'] ?? self::BASE_URLS[$match[1]], '/');
        $this->charges = new Charges($this);
        $this->balance = new Balance($this);
    }

    /**
     * @internal
     */
    public function request(string $method, string $path, ?array $body = null): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'User-Agent: carecapay-php/' . self::VERSION,
        ];
        $curl = curl_init($this->baseUrl . $path);
        $curlOptions = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        $curlOptions[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($curl, $curlOptions);

        $raw = curl_exec($curl);
        if ($raw === false) {
            $reason = curl_error($curl);
            curl_close($curl);
            throw new CarecaPayException(
                'network_error',
                "não foi possível falar com a API em {$this->baseUrl}: $reason",
            );
        }
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $payload = json_decode($raw, true) ?? [];
        if ($status < 200 || $status >= 300) {
            $error = $payload['error'] ?? [];
            throw new CarecaPayException(
                $error['code'] ?? 'internal_error',
                $error['message'] ?? "a API respondeu $status",
                $status,
            );
        }
        return $payload;
    }
}
