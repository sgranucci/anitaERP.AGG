<?php

namespace App\Support\Caja\Echeq;

/**
 * Contrato multi-banco para eCheq.
 * Cada empresa/banco implementa su driver; el ERP solo orquesta.
 */
interface ChequeEcheqProviderInterface
{
    public function codigo(): string;

    /**
     * @return array{ok:bool, estado:?string, mensaje?:string, raw?:array<string,mixed>}
     */
    public function consultarEstado(string $nroEcheq, array $contexto = []): array;

    /**
     * @return array{ok:bool, estado:?string, mensaje?:string, raw?:array<string,mixed>}
     */
    public function depositar(string $nroEcheq, array $contexto = []): array;
}
