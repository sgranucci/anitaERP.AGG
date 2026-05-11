<?php

namespace App\Support\Compras;

use App\Models\Configuracion\Cotizacion;
use App\Models\Compras\Requisicion;
use App\Queries\Configuracion\CotizacionQueryInterface;

/**
 * Total de requisición en la moneda del primer ítem (por id de línea), con conversión vía cotización diaria.
 * No persiste nada en cabecera.
 */
final class RequisicionTotalesCabecera
{
    /**
     * @return array{monto: float, moneda_id: int, monedacabecera_abreviatura: string}
     */
    public static function desdeModelo(Requisicion $req, CotizacionQueryInterface $cotizacionQuery): array
    {
        $req->loadMissing(['requisicion_articulos.monedas']);

        $lineas = collect($req->requisicion_articulos ?? [])->sortBy('id');

        if ($lineas->isEmpty()) {
            return ['monto' => 0.0, 'moneda_id' => 1, 'monedacabecera_abreviatura' => ''];
        }

        $primer = $lineas->first();
        $monedaBaseId = (int) ($primer->moneda_id ?: 1);
        $abrev = (string) (optional($primer->monedas)->abreviatura ?? '');

        $fechaCab = $req->fecha ? substr((string) $req->fecha, 0, 10) : date('Y-m-d');
        $cotVenta = self::cotizacionVentaParaConversionEnFecha($cotizacionQuery, $fechaCab, $monedaBaseId);

        $suma = 0.0;
        foreach ($lineas as $lin) {
            $lineMoneda = (int) ($lin->moneda_id ?: $monedaBaseId ?: 1);
            $coef = calculaCoeficienteMoneda(
                $monedaBaseId,
                $lineMoneda,
                ['cotizacionventa' => $cotVenta],
            );
            $suma += $coef * (float) $lin->cantidad * (float) $lin->precio;
        }

        return [
            'monto' => round($suma, 4),
            'moneda_id' => $monedaBaseId,
            'monedacabecera_abreviatura' => $abrev,
        ];
    }

    public static function aplicarAtributosVirtuales(Requisicion $req, CotizacionQueryInterface $cotizacionQuery): void
    {
        $t = self::desdeModelo($req, $cotizacionQuery);
        $req->setAttribute('monto', $t['monto']);
        $req->setAttribute('monedacabecera_abreviatura', $t['monedacabecera_abreviatura']);
    }

    /**
     * Mismo criterio que desdeModelo pero desde el request de alta/edición (orden de índices de línea).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: float, 1: int}
     */
    public static function montoYMonedaDesdeRequest(array $data, CotizacionQueryInterface $cotizacionQuery): array
    {
        $articulo_ids = $data['articulo_ids'] ?? [];
        if (! is_array($articulo_ids)) {
            return [0.0, 1];
        }

        $n = count($articulo_ids);
        $fecha = isset($data['fecha']) ? substr((string) $data['fecha'], 0, 10) : date('Y-m-d');

        $monedaBaseId = 1;
        $foundFirst = false;

        for ($i = 0; $i < $n; $i++) {
            $aid = $articulo_ids[$i] ?? null;
            if ($aid === null || $aid === '') {
                continue;
            }
            $cant = (float) ($data['cantidades'][$i] ?? 0);
            if ($cant <= 0) {
                continue;
            }
            if (! $foundFirst) {
                $foundFirst = true;
                $monedaBaseId = (int) ($data['moneda_linea_ids'][$i] ?? 1);

                break;
            }
        }

        if (! $foundFirst) {
            return [0.0, 1];
        }

        $cotVenta = self::cotizacionVentaParaConversionEnFecha($cotizacionQuery, $fecha, $monedaBaseId);

        $suma = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $aid = $articulo_ids[$i] ?? null;
            if ($aid === null || $aid === '') {
                continue;
            }
            $cant = (float) ($data['cantidades'][$i] ?? 0);
            if ($cant <= 0) {
                continue;
            }

            $lineMoneda = (int) ($data['moneda_linea_ids'][$i] ?? $monedaBaseId);
            $precio = (float) ($data['precios'][$i] ?? 0);
            $coef = calculaCoeficienteMoneda(
                $monedaBaseId,
                $lineMoneda,
                ['cotizacionventa' => $cotVenta],
            );
            $suma += $coef * $cant * $precio;
        }

        return [round($suma, 4), $monedaBaseId];
    }

    public static function cotizacionVentaParaConversionEnFecha(
        CotizacionQueryInterface $cotizacionQuery,
        string $fechaYmd,
        int $monedaIdBaseParaRef,
    ): float {
        $cotRecord = $cotizacionQuery->leeCotizacionDiaria($fechaYmd);

        if (! $cotRecord instanceof Cotizacion) {
            return 1.0;
        }

        $cotRecord->loadMissing('cotizacion_monedas');

        foreach ($cotRecord->cotizacion_monedas as $cotizacionMoneda) {
            if ($monedaIdBaseParaRef === 1) {
                $refMoneda = 2;
            } else {
                $refMoneda = $monedaIdBaseParaRef;
            }
            if ((int) $cotizacionMoneda->moneda_id === $refMoneda) {
                $valor = (float) $cotizacionMoneda->cotizacionventa;

                return $valor > 0 ? $valor : 1.0;
            }
        }

        return 1.0;
    }
}
