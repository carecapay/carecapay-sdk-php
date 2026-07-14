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
     * Cria uma cobrança Pix; a resposta traz o qr_code copia e cola.
     *
     * `method` e `currency` são opcionais — hoje só "pix"/"BRL" existem (padrão
     * se omitidos).
     *
     * @param array{amount_cents: int, description?: string, method?: string, currency?: string} $input
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
