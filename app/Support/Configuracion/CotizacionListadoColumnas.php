<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\Cotizacion;
use App\Models\Configuracion\Moneda;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Columnas dinámicas del listado de cotizaciones (Compra/Venta por moneda).
 */
final class CotizacionListadoColumnas
{
    /**
     * Monedas con cotización (excluye moneda default / pesos).
     *
     * @return Collection<int, Moneda>
     */
    public static function monedasParaColumnas(): Collection
    {
        $defaultId = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);

        return Moneda::query()
            ->where('id', '!=', $defaultId)
            ->orderBy('id')
            ->get(['id', 'codigo', 'nombre']);
    }

    /**
     * @return array<int, array{compra: float|null, venta: float|null}>
     */
    public static function mapaPorMoneda(Cotizacion $cotizacion): array
    {
        $mapa = [];
        foreach ($cotizacion->cotizacion_monedas ?? [] as $detalle) {
            $mapa[(int) $detalle->moneda_id] = [
                'compra' => $detalle->cotizacioncompra !== null ? (float) $detalle->cotizacioncompra : null,
                'venta' => $detalle->cotizacionventa !== null ? (float) $detalle->cotizacionventa : null,
            ];
        }

        return $mapa;
    }

    public static function formatear(?float $valor): string
    {
        if ($valor === null) {
            return '';
        }

        return number_format($valor, 4, ',', '.');
    }

    public static function totalColumnasDatos(int $cantidadMonedas): int
    {
        return 2 + ($cantidadMonedas * 2);
    }

    public static function letraUltimaColumna(int $cantidadMonedas): string
    {
        return Coordinate::stringFromColumnIndex(self::totalColumnasDatos($cantidadMonedas));
    }
}
