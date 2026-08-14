<?php

namespace App\Services\Ventas\Gastronomia;

use App\Queries\Ventas\GastronomiaArticulosVendidosQuery;
use App\Support\Ventas\Gastronomia\GastronomiaInformeGerenteCostoListaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaStkpreAnitaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Top artículos del período para informe gerente con precio de venta y costos Anita (stkpre).
 */
final class GastronomiaInformeGerenteTopArticulosService
{
    private const TOP_LIMIT = 20;

    public function __construct(
        private readonly GastronomiaArticulosVendidosQuery $articulosVendidosQuery,
        private readonly GastronomiaStkpreAnitaSupport $stkpreAnita,
    ) {}

    /**
     * @return array{
     *   filas:list<array<string,mixed>>,
     *   listas:array<string,mixed>,
     *   error:?string
     * }
     */
    public function top20DelPeriodoConCostos(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        $listas = GastronomiaInformeGerenteCostoListaSupport::listasDesdeFechaJornada($fechaHasta);
        $top = $this->articulosVendidosQuery->topPorRango(
            $empresaId,
            $fechaDesde,
            $fechaHasta,
            'cantidad',
            self::TOP_LIMIT,
        );

        if ($top === []) {
            return [
                'filas' => [],
                'listas' => $listas,
                'error' => null,
            ];
        }

        $skus = array_values(array_filter(array_map(
            fn (array $f) => trim((string) ($f['sku'] ?? '')),
            $top,
        )));

        $preciosAnita = [];
        $error = null;
        try {
            $preciosAnita = $this->stkpreAnita->preciosPorSkusYListas(
                $skus,
                [$listas['lista_anterior'], $listas['lista_actual']],
            );
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::warning('gastronomia.informe_gerente.top_articulos_costo', [
                'empresa_id' => $empresaId,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'exception' => $e,
            ]);
        }

        $listaAnterior = (string) $listas['lista_anterior'];
        $listaActual = (string) $listas['lista_actual'];

        $filas = [];
        foreach ($top as $i => $fila) {
            $sku = trim((string) ($fila['sku'] ?? ''));
            $cantidad = round((float) ($fila['cantidad'] ?? 0), 4);
            $importe = round((float) ($fila['importe'] ?? 0), 2);
            $precioVenta = $cantidad > 0.0001 ? round($importe / $cantidad, 2) : null;

            $costosSku = $preciosAnita[$sku] ?? [];
            $costoAnterior = $costosSku[$listaAnterior] ?? null;
            $costoActual = $costosSku[$listaActual] ?? null;

            $filas[] = [
                'posicion' => $i + 1,
                'articulo_id' => (int) ($fila['articulo_id'] ?? 0),
                'sku' => $sku,
                'descripcion' => trim((string) ($fila['descripcion'] ?? '')),
                'cantidad' => $cantidad,
                'importe' => $importe,
                'precio_venta' => $precioVenta,
                'costo_mes_anterior' => $costoAnterior,
                'costo_mes_actual' => $costoActual,
                'pct_diferencia_costo' => GastronomiaInformeGerenteCostoListaSupport::porcentajeDiferenciaCosto(
                    $costoAnterior,
                    $costoActual,
                ),
            ];
        }

        return [
            'filas' => $filas,
            'listas' => $listas,
            'error' => $error,
        ];
    }

    /**
     * @deprecated Usar top20DelPeriodoConCostos
     * @return array{filas:list<array<string,mixed>>,listas:array<string,mixed>,error:?string}
     */
    public function top20DelDiaConCostos(int $empresaId, string $fechaJornada): array
    {
        return $this->top20DelPeriodoConCostos($empresaId, $fechaJornada, $fechaJornada);
    }
}
