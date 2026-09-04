<?php

namespace App\Support\Compras;

use App\Models\Compras\Configuracion_ComprobanteProveedorTolerancia;
use App\Models\Compras\Ordencompra;

/**
 * Tolerancia % de importe factura vs provisión COM, por empresa y centro de costo destino de la OC.
 */
final class ComprobanteProveedorToleranciaImporteSupport
{
    /** Default de negocio si no hay fila en configuración (sin CC / sin registro). */
    public const PCT_DEFAULT = 5.0;

    public static function porcentajeDesdeOc(object $ordencompra): float
    {
        if ($ordencompra instanceof Ordencompra) {
            $ordencompra->loadMissing('ordencompra_articulos');
        }
        $ccId = ComprobanteProveedorCentrocostoSupport::resolverDesdeOc($ordencompra);

        return self::porcentajeParaOc(
            (int) ($ordencompra->empresa_id ?? 0),
            $ccId > 0 ? $ccId : null
        );
    }

    public static function porcentajeParaOc(int $empresaId, ?int $centrocostoId): float
    {
        if ($empresaId <= 0) {
            return self::PCT_DEFAULT;
        }

        if ($centrocostoId !== null && $centrocostoId > 0) {
            $especifica = Configuracion_ComprobanteProveedorTolerancia::query()
                ->where('empresa_id', $empresaId)
                ->where('centrocosto_id', $centrocostoId)
                ->where('activo', true)
                ->value('tolerancia_importe_pct');

            if ($especifica !== null) {
                return (float) $especifica;
            }
        }

        $default = Configuracion_ComprobanteProveedorTolerancia::query()
            ->where('empresa_id', $empresaId)
            ->whereNull('centrocosto_id')
            ->where('activo', true)
            ->value('tolerancia_importe_pct');

        return $default !== null ? (float) $default : self::PCT_DEFAULT;
    }

    /**
     * True si la diferencia relativa (respecto del importe COM) supera la tolerancia %.
     */
    public static function excedeTolerancia(float $importeComprobante, float $importeCom, float $toleranciaPct): bool
    {
        $base = abs($importeCom);
        $diff = abs($importeComprobante - $importeCom);

        // Centavos: siempre permitir hasta 0.05 de diferencia absoluta.
        if ($diff <= ComprobanteProveedorImporteComparacionComSupport::tolerancia()) {
            return false;
        }

        if ($base <= 0.00001) {
            return $diff > ComprobanteProveedorImporteComparacionComSupport::tolerancia();
        }

        $pct = ($diff / $base) * 100.0;

        return $pct > $toleranciaPct + 0.0001;
    }
}
