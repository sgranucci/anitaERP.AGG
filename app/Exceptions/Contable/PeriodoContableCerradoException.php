<?php

namespace App\Exceptions\Contable;

use Exception;

class PeriodoContableCerradoException extends Exception
{
    public function __construct(
        string $message,
        private readonly ?string $fechaOperacion = null,
        private readonly ?string $fechaCierre = null,
        private readonly ?string $alcance = null,
    ) {
        parent::__construct($message);
    }

    public function fechaOperacion(): ?string
    {
        return $this->fechaOperacion;
    }

    public function fechaCierre(): ?string
    {
        return $this->fechaCierre;
    }

    public function alcance(): ?string
    {
        return $this->alcance;
    }
}
