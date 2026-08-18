<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Support\Database\DbContencionSupport;
use Throwable;

/**
 * Defensa ante colisión de numerocomprobante en el mismo punto de venta (índice único venta).
 */
final class VentaNumerocomprobanteUnicidadSupport
{
    public const UNIQUE_INDEX = 'venta_puntoventa_numerocomprobante_unique';

    public static function esViolacionNumerocomprobante(Throwable $e): bool
    {
        return DbContencionSupport::esViolacionUnicidad(
            $e,
            self::UNIQUE_INDEX,
            'numerocomprobante',
            'puntoventa_numerocomprobante',
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
