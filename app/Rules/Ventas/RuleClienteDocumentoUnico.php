<?php

namespace App\Rules\Ventas;

use App\Models\Ventas\Cliente;
use App\Support\Ventas\ClienteDocumentoUnicoSupport;
use Illuminate\Contracts\Validation\Rule;

class RuleClienteDocumentoUnico implements Rule
{
    private ?Cliente $clienteDuplicado = null;

    public function __construct(
        private ?int $excluirClienteId = null,
        private ?int $paisId = null,
    ) {
    }

    public function passes($attribute, $value): bool
    {
        if (! ClienteDocumentoUnicoSupport::bloqueaDocumentoDuplicado($this->paisId)) {
            $this->clienteDuplicado = null;

            return true;
        }

        $this->clienteDuplicado = ClienteDocumentoUnicoSupport::findOtroCliente(
            (string) $value,
            $this->excluirClienteId
        );

        return $this->clienteDuplicado === null;
    }

    public function message(): string
    {
        if ($this->clienteDuplicado !== null) {
            return ClienteDocumentoUnicoSupport::mensajeDuplicado($this->clienteDuplicado);
        }

        return 'El CUIT/documento ya está registrado en otro cliente.';
    }
}
