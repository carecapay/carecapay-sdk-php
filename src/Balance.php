<?php

declare(strict_types=1);

namespace CarecaPay;

/** Saldo da conta no ambiente da credencial. */
class Balance
{
    public function __construct(private CarecaPay $client)
    {
    }

    /** Saldo disponível (pago) e pendente, em centavos. */
    public function get(): array
    {
        return $this->client->request('GET', '/v1/balance');
    }
}
