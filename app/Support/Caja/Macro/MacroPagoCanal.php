<?php

declare(strict_types=1);

namespace App\Support\Caja\Macro;

/**
 * Canal de envío de pagos Macro.
 * Hoy: archivo (diskette). Premium: webservice (mismo contrato).
 */
interface MacroPagoCanal
{
    public function codigo(): string;

    /**
     * @param  array{
     *   beneficiarios:list<array<string,mixed>>,
     *   ordenes:list<array<string,mixed>>,
     *   retenciones:list<array<string,mixed>>,
     *   meta:array<string,mixed>
     * }  $lote
     * @return array{
     *   ok:bool,
     *   mensaje:string,
     *   contenido:string,
     *   nombre:string,
     *   mime:string,
     *   archivos:array<string,string>
     * }
     */
    public function exportar(array $lote): array;
}
