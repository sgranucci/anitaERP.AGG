<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use InvalidArgumentException;

/**
 * Numeración CAEA (PV mod A) exclusivamente en anitaERP (tabla venta).
 *
 * Reserva bajo lock de PV; gastronomía/estacionamiento mantienen ese lock hasta
 * completar la emisión para evitar duplicados entre módulos en el mismo PV.
 */
final class CaeaEmisionNumeracionSupport
{
    public static function tipoAnitaDesdeTipotransaccion(Tipotransaccion $tipotransaccion): string
    {
        $codigo = (string) ($tipotransaccion->codigo ?? '');

        if ($codigo >= '200') {
            return substr((string) ($tipotransaccion->abreviatura ?? ''), 0, 1).'CE';
        }

        return (string) ($tipotransaccion->abreviatura ?? 'FAC');
    }

    public static function reservarSiguienteNumeroErp(
        int $puntoventaId,
        Tipotransaccion $tipotransaccion,
        string $letraComprobante,
        ?int $empresaId = null,
        ?string $modoFacturacionCliente = null,
        ?float $totalComprobante = null,
    ): int {
        if ($puntoventaId <= 0 || (int) ($tipotransaccion->id ?? 0) <= 0) {
            return 0;
        }

        return VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpDesdeTipotransaccion(
            $puntoventaId,
            (int) ($tipotransaccion->codigo ?? 0),
            $letraComprobante,
            $empresaId,
            $modoFacturacionCliente,
            $totalComprobante,
        ) + 1;
    }

    /**
     * Reserva el siguiente número ERP y lo aplica al payload de emisión.
     *
     * @param  array<string, mixed>  $payload
     * @return null si ok; mensaje de error si falla (solo PV mod A)
     */
    public static function aplicarReservaNumeracionAlPayload(
        array &$payload,
        Puntoventa $puntoventa,
        Tipotransaccion $tipotransaccion,
        string $letraComprobante = 'B',
        bool $lockYaAdquirido = false,
    ): ?string {
        if (($puntoventa->modofacturacion ?? '') !== 'A') {
            return null;
        }

        if (! empty($payload['numerocomprobante_forzado'])) {
            $payload['_omitir_numera_anita_fin'] = true;

            return null;
        }

        $lock = null;
        if (! $lockYaAdquirido) {
            try {
                $lock = PuntoventaEmisionLock::adquirir((int) $puntoventa->id);
            } catch (InvalidArgumentException $e) {
                return $e->getMessage();
            }
        }

        try {
            $empresaId = (int) ($puntoventa->empresa_id ?? 0);
            $modoCliente = isset($payload['modofacturacion_cliente'])
                ? (string) $payload['modofacturacion_cliente']
                : null;
            $totalComprobante = isset($payload['total_comprobante'])
                ? (float) $payload['total_comprobante']
                : null;

            $numero = self::reservarSiguienteNumeroErp(
                (int) $puntoventa->id,
                $tipotransaccion,
                $letraComprobante,
                $empresaId > 0 ? $empresaId : null,
                $modoCliente,
                $totalComprobante,
            );

            if ($numero <= 0) {
                return 'No pudo reservar número de comprobante CAEA en ERP (PV '.($puntoventa->codigo ?? '').').';
            }

            $payload['numerocomprobante_forzado'] = $numero;
            $payload['_omitir_numera_anita_fin'] = true;
        } finally {
            if (! $lockYaAdquirido) {
                PuntoventaEmisionLock::liberar($lock);
            }
        }

        return null;
    }

    /**
     * Asigna numerocomprobante_forzado en payload (sin lock; caller debe serializar emisión).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function marcarNumerocomprobanteForzadoEnPayload(array &$payload, int $numero): void
    {
        if ($numero <= 0) {
            return;
        }

        $payload['numerocomprobante_forzado'] = $numero;
        $payload['_omitir_numera_anita_fin'] = true;
    }
}
