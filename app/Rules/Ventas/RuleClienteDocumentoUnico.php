<?php

namespace App\Rules\Ventas;

use App\Models\Ventas\Cliente;
use Illuminate\Contracts\Validation\Rule;

class RuleClienteDocumentoUnico implements Rule
{
    private ?Cliente $clienteDuplicado = null;

    public function __construct(private ?int $excluirClienteId = null)
    {
    }

    public function passes($attribute, $value): bool
    {
        $digitos = preg_replace('/\D+/', '', (string) $value);
        if ($digitos === '') {
            return true;
        }

        $query = Cliente::query()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(numerodocumento, '-', ''), '.', ''), ' ', '') = ?",
                [$digitos]
            );

        if ($this->excluirClienteId !== null && $this->excluirClienteId > 0) {
            $query->where('id', '!=', $this->excluirClienteId);
        }

        $this->clienteDuplicado = $query->first();

        return $this->clienteDuplicado === null;
    }

    public function message(): string
    {
        if ($this->clienteDuplicado !== null) {
            $codigo = trim((string) ($this->clienteDuplicado->codigo ?? ''));
            $nombre = trim((string) ($this->clienteDuplicado->nombre ?? ''));

            return 'El CUIT/documento ya está cargado en el cliente '
                .($codigo !== '' ? $codigo.' - ' : '')
                .$nombre
                .' (ID '.$this->clienteDuplicado->id.').';
        }

        return 'El CUIT/documento ya está registrado en otro cliente.';
    }
}
