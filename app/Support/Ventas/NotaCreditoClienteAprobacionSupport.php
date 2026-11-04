<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * El Bierzo: emitir nota de crédito exige la marca del cliente («Emite NC»),
 * además del permiso generar-nota-de-credito.
 */
class NotaCreditoClienteAprobacionSupport
{
    public const EMITE = 'Emite Nota de Credito';

    public const NO_EMITE = 'No Emite Nota de Credito';

    public static function exigeAprobacionDelCliente(): bool
    {
        return EntornoEmpresaSupport::esElBierzo();
    }

    public static function clienteAutoriza(mixed $valor): bool
    {
        $texto = mb_strtolower(trim((string) ($valor ?? '')));

        return in_array($texto, ['emite nota de credito', 's'], true);
    }

    /**
     * Fuera de El Bierzo no hay marca de cliente: alcanza el permiso.
     */
    public static function habilitadaParaCliente(mixed $valor): bool
    {
        if (! self::exigeAprobacionDelCliente()) {
            return true;
        }

        return self::clienteAutoriza($valor);
    }

    public static function mensaje(): string
    {
        return 'Este cliente no está autorizado a nota de crédito. Hay que habilitar «Emite NC» en la ficha del cliente.';
    }

    /**
     * @return array{error: string}|null
     */
    public static function errorSiNoAutoriza(mixed $cliente): ?array
    {
        if (! self::exigeAprobacionDelCliente()) {
            return null;
        }

        $valor = is_object($cliente) ? ($cliente->emitenotadecredito ?? null) : $cliente;
        if (self::clienteAutoriza($valor)) {
            return null;
        }

        return ['error' => self::mensaje()];
    }
}
