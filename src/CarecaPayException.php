<?php

declare(strict_types=1);

namespace CarecaPay;

/**
 * Erro da API (ou de rede). `code` é estável — programe contra ele.
 * `status` é o HTTP da resposta (0 em falha de rede/configuração).
 */
class CarecaPayException extends \RuntimeException
{
    /**
     * Código estável do erro (ex.: "invalid_amount"). Sobrescreve o $code
     * herdado de \Exception como string — mesmo padrão do PDOException.
     *
     * @var string
     */
    public $code;

    public readonly int $status;

    public function __construct(string $code, string $message, int $status = 0)
    {
        parent::__construct($message);
        $this->code = $code;
        $this->status = $status;
    }
}
