<?php

declare(strict_types=1);

namespace CarecaPay;

/** Assinatura de webhook ausente, malformada, inválida ou expirada. */
class CarecaPayWebhookException extends \RuntimeException
{
}
