<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Support\Database\DbContencionSupport;
use Throwable;

/**
 * Defensa ante colisión de numerocomprobante (índice único venta).
 *
 * AGG / CAEA gastro: unique (puntoventa_id, numerocomprobante) — no se toca.
 * El Bierzo: unique (codigo_afip, puntoventa_id, numerocomprobante).
 * codigo_afip es el tipo ARCA efectivo (001+letra, 002 ND, 003 NC, 201 FCE, 202 NDE, 203 NCE):
 * FAC A y FAG A (ambas 001) no pueden repetir sucursal+número; FAC A 10-1 y FAC B 10-1 sí (001 vs 006).
 */
final class VentaNumerocomprobanteUnicidadSupport
{
    public const UNIQUE_INDEX = 'venta_puntoventa_numerocomprobante_unique';

    public const UNIQUE_INDEX_ELBIERZO_TIPO = 'venta_puntoventa_tipotransaccion_numerocomprobante_unique';

    public const UNIQUE_INDEX_ELBIERZO_AFIP = 'venta_codigo_afip_puntoventa_numerocomprobante_unique';

    public static function esViolacionNumerocomprobante(Throwable $e): bool
    {
        return DbContencionSupport::esViolacionUnicidad(
            $e,
            self::UNIQUE_INDEX,
            self::UNIQUE_INDEX_ELBIERZO_TIPO,
            self::UNIQUE_INDEX_ELBIERZO_AFIP,
            'numerocomprobante',
            'puntoventa_numerocomprobante',
            'puntoventa_tipotransaccion_numerocomprobante',
            'codigo_afip_puntoventa_numerocomprobante',
        );
    }

    /**
     * Tras colisión en INSERT: descarta número reservado y pide el siguiente (lock PV ya adquirido).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function renumerarPayloadCaeaTrasColision(
        array &$payload,
        Puntoventa $puntoventa,
        Tipotransaccion $tipo,
        string $letraComprobante,
        bool $lockYaAdquirido = true,
    ): ?string {
        unset($payload['numerocomprobante_forzado'], $payload['_omitir_numera_anita_fin']);

        return CaeaEmisionNumeracionSupport::aplicarReservaNumeracionAlPayload(
            $payload,
            $puntoventa,
            $tipo,
            $letraComprobante,
            $lockYaAdquirido,
        );
    }
}
