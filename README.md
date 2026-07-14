# CarecaPay — SDK PHP

SDK oficial da [CarecaPay](https://carecapay.com.br) para PHP: cobranças Pix,
saldo e verificação de webhooks. Sem dependências além de `ext-curl`/`ext-json`
(PHP 8.1+).

## Instalação

```bash
composer require carecapay/carecapay
```

> **Beta**: enquanto o pacote não está no Packagist, aponte um repositório
> `path`/`vcs` do Composer para `carecapay-sdk-php`.

## Pré-requisito: a chave privada (obrigatória)

Toda chamada é autenticada pela sua **chave secreta** (`ccp_secret_...`),
gerada no painel em **Chaves de API** — ela aparece uma única vez. O construtor
exige uma chave privada válida e falha na hora com chave pública, vazia ou de
outro formato:

```php
use CarecaPay\CarecaPay;

$carecapay = new CarecaPay($_ENV['CARECAPAY_SECRET_KEY']);
```

O ambiente vem embutido na chave (`ccp_secret_sandbox_...` → sandbox,
`ccp_secret_live_...` → produção). Para desenvolvimento local:

```php
$carecapay = new CarecaPay($key, ['base_url' => 'http://localhost:8080']);
```

## Uso

```php
$charge = $carecapay->charges->create([
    'amount_cents' => 1990,          // R$ 19,90 — sempre em centavos, obrigatório
    'description' => 'Assinatura',   // opcional
]);
echo $charge['qr_code'];             // copia e cola do Pix

$carecapay->charges->get('txn_...');
$carecapay->charges->list(['status' => 'paid', 'limit' => 10]);
$carecapay->balance->get();          // ['available_cents' => ..., 'pending_cents' => ...]

// só no sandbox: baixa fake (dispara o webhook também)
$carecapay->charges->simulatePayment($charge['id']);
```

Os arrays devolvidos têm exatamente os shapes da API REST (snake_case).

## Webhooks (recomendado)

Configure sua URL no painel (**Webhooks**) e valide cada entrega com o corpo
**CRU** da requisição (`php://input`):

```php
use CarecaPay\Webhooks;
use CarecaPay\CarecaPayWebhookException;

try {
    $event = Webhooks::constructEvent(
        payload: file_get_contents('php://input'),          // corpo cru!
        header: $_SERVER['HTTP_X_CARECAPAY_SIGNATURE'] ?? '',
        secret: $_ENV['CARECAPAY_WEBHOOK_SECRET'],          // ccp_whsec_...
    );
} catch (CarecaPayWebhookException) {
    http_response_code(400);
    exit;
}

if ($event['type'] === 'charge.paid') {
    liberarPedido($event['data']['id']);  // deduplique pelo $event['id']
}
http_response_code(200);
```

`Webhooks::verifySignature($payload, $header, $secret)` devolve só o booleano.
Entregas com mais de 5 minutos são rejeitadas (`$toleranceSeconds` ajusta).

## Erros

```php
use CarecaPay\CarecaPayException;

try {
    $carecapay->charges->create(['amount_cents' => 0]);
} catch (CarecaPayException $err) {
    $err->code;    // "invalid_amount" (estável — programe contra ele)
    $err->status;  // 400 (0 em falha de rede, code "network_error")
}
```

## Desenvolvimento

```bash
composer install && composer test
```
