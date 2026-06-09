<?php

namespace App\Support\Ventas;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;

/**
 * Comentarios de ítem para comanda de cocina (KDS Waitry: orderItems[].notes).
 */
final class GastronomiaComentarioCocinaSupport
{
    public const LONGITUD_MAXIMA = 255;

    public static function normalizar(?string $texto): ?string
    {
        $txt = trim(preg_replace('/\s+/u', ' ', (string) $texto) ?? '');
        if ($txt === '') {
            return null;
        }

        return mb_substr($txt, 0, self::LONGITUD_MAXIMA);
    }

    /**
     * Copia comentarios de cuenta_gastronomia_linea a venta_emision tras facturar.
     */
    public static function persistirDesdeCuenta(Venta $venta, CuentaGastronomia $cuenta): void
    {
        $cuenta->loadMissing('lineas');
        $venta->loadMissing('venta_emisiones');

        if ($cuenta->lineas->isEmpty() || $venta->venta_emisiones->isEmpty()) {
            return;
        }

        $map = GastronomiaVentaEmisionMapSupport::mapLineasCuentaAVentaEmision($venta, $cuenta->lineas);

        foreach ($map as $lineaId => $emisionId) {
            $linea = $cuenta->lineas->firstWhere('id', (int) $lineaId);
            if ($linea === null) {
                continue;
            }

            $comentario = self::normalizar($linea->comentario_cocina ?? null);
            if ($comentario === null) {
                continue;
            }

            Venta_Emision::query()
                ->whereKey((int) $emisionId)
                ->where('venta_id', (int) $venta->id)
                ->update(['comentario_cocina' => $comentario]);
        }
    }
}
