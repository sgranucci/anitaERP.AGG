<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use Throwable;

/**
 * Defensa ante colisión de numerocomprobante en el mismo punto de venta (índice único venta).
 */
final class VentaNumerocomprobanteUnicidadSupport
{
    public const UNIQUE_INDEX = 'venta_puntoventa_numerocomprobante_unique';

    public static function esViolacionNumerocomprobante(Throwable $e): bool
    {
        $mensaje = $e->getMessage();

        if (str_contains($mensaje, self::UNIQUE_INDEX)) {
            return true;
        }

        return str_contains($mensaje, 'Duplicate entry')
            && (
                str_contains($mensaje, 'numerocomprobante')
                || str_contains($mensaje, 'puntoventa_numerocomprobante')
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
