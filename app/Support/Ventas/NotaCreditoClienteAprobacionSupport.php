<?php

namespace App\Support\Ventas;

use App\Repositories\Ventas\ClienteRepositoryInterface;
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
     * Config para el JS de facturación mostrador (validación al cargar cliente).
     *
     * @return array{exige: bool, mensaje: string, puede_abrir_abm: bool}
     */
    public static function payloadFrontend(): array
    {
        $puedeAbrirAbm = function_exists('can') && (
            can('editar-clientes', false)
            || can('listar-clientes', false)
            || can('actualizar-clientes', false)
        );

        return [
            'exige' => self::exigeAprobacionDelCliente(),
            'mensaje' => self::mensaje(),
            'puede_abrir_abm' => $puedeAbrirAbm,
        ];
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

    /**
     * El Bierzo: tras emitir una NC de mostrador/cobranza, el cliente deja de poder emitir otra
     * hasta que vuelvan a marcar «Emite NC» en la ficha.
     */
    public static function revocarTrasEmitirSiCorresponde(?int $clienteId): void
    {
        if (! self::exigeAprobacionDelCliente() || (int) $clienteId <= 0) {
            return;
        }

        app(ClienteRepositoryInterface::class)
            ->marcarNoEmiteNcTrasNotaCredito((int) $clienteId);
    }
}
