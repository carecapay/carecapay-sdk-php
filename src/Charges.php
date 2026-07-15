<?php

declare(strict_types=1);

namespace CarecaPay;

/** Cobranças Pix da conta. */
class Charges
{
    public function __construct(private CarecaPay $client)
    {
    }

    /**
     * Cria uma cobrança Pix; a resposta traz o qr_code copia e cola e o
     * qr_code_base64 (PNG já renderizado, pronto pra exibir).
     *
     * `method` e `currency` são opcionais — hoje só "pix"/"BRL" existem (padrão
     * se omitidos). `external_reference` é o seu id do pedido: a CarecaPay só
     * guarda e devolve de volta (aqui, no get/list e no webhook), não interpreta.
     *
     * @param array{amount_cents: int, description?: string, method?: string, currency?: string, external_reference?: string} $input
     */
    public function create(array $input): array
    {
        return $this->client->request('POST', '/v1/charges', $input);
    }

    /** Busca uma cobrança pelo id (txn_...). */
    public function get(string $id): array
    {
        return $this->client->request('GET', '/v1/charges/' . rawurlencode($id));
    }

    /**
     * Lista as cobranças da conta (mais recentes primeiro).
     *
     * @param array{status?: string, limit?: int} $filters
     */
    public function list(array $filters = []): array
    {
        $query = http_build_query($filters);
        $suffix = $query !== '' ? "?$query" : '';
        return $this->client->request('GET', "/v1/charges$suffix");
    }

    /** Baixa fake (só sandbox): marca como paga e dispara o webhook. */
    public function simulatePayment(string $id): array
    {
        return $this->client->request(
            'POST',
            '/v1/charges/' . rawurlencode($id) . '/simulate-payment',
        );
    }
}
