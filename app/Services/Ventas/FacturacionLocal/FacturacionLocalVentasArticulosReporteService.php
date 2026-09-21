<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Ventas\Puntoventa;
use App\Queries\Ventas\FacturacionLocal\FacturacionLocalVentasArticulosReporteQuery;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalCostoFabricaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalVentasArticulosReporteFiltros;
use Illuminate\Pagination\LengthAwarePaginator;

final class FacturacionLocalVentasArticulosReporteService
{
    public function __construct(
        private readonly FacturacionLocalVentasArticulosReporteQuery $query,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas:list<array<string,mixed>>,
     *   totales:array<string,float>,
     *   periodo_texto:string,
     *   modo_texto:string,
     *   puntoventa_texto:string,
     *   nombreempresa:string,
     *   abierto_talle:bool,
     *   incluir_costo:bool,
     *   costo_formula:string
     * }
     */
    public function generar(array $filtros): array
    {
        $abiertoTalle = FacturacionLocalVentasArticulosReporteFiltros::esAbiertoPorTalle($filtros);
        $incluirCosto = FacturacionLocalVentasArticulosReporteFiltros::incluirCosto($filtros);
        $fechaCosto = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($fechaCosto === '') {
            $fechaCosto = now()->toDateString();
        }

        $metaPv = $this->metaPuntoventa((int) ($filtros['puntoventa_id'] ?? 0));
        $filas = [];
        $totales = [
            'cantidad' => 0.0,
            'importe' => 0.0,
            'importe_costo' => 0.0,
        ];
        $cacheCosto = [];
        $sinPrecioFabrica = 0;

        foreach ($this->query->filasAgregadas($filtros) as $row) {
            $combTxt = $this->etiquetaCombinacionOColor(
                (string) $row->combinacion_codigo,
                (string) $row->combinacion_nombre,
                (string) $row->color_codigo,
                (string) $row->color_nombre,
            );
            $talleTxt = '';
            if ($abiertoTalle) {
                $talleTxt = trim(($row->talle_codigo !== '' ? $row->talle_codigo : '').' '.$row->talle_nombre);
                $talleTxt = trim($talleTxt);
            }

            $cantidad = (float) $row->cantidad;
            $importe = (float) $row->importe;
            $precioVenta = abs($cantidad) > 0.0001
                ? round($importe / $cantidad, 4)
                : 0.0;

            $item = [
                'articulo_id' => (int) $row->articulo_id,
                'sku' => $row->sku,
                'descripcion' => $row->descripcion,
                'combinacion_id' => $row->combinacion_id,
                'color_id' => $row->color_id,
                'combinacion_color' => $combTxt !== '' ? $combTxt : '—',
                'talle_id' => $row->talle_id,
                'talle' => $abiertoTalle ? ($talleTxt !== '' ? $talleTxt : '—') : null,
                'cantidad' => $cantidad,
                'precio_venta' => $precioVenta,
                'importe' => $importe,
                'nombreempresa' => $metaPv['nombreempresa'],
            ];

            if ($incluirCosto) {
                $precioFabrica = FacturacionLocalCostoFabricaSupport::precioVentaFabrica(
                    (int) $row->articulo_id,
                    $abiertoTalle ? $row->talle_id : null,
                    $fechaCosto,
                    $cacheCosto,
                    $row->combinacion_id,
                );
                $precioCosto = FacturacionLocalCostoFabricaSupport::precioCosto(
                    (int) $row->articulo_id,
                    $abiertoTalle ? $row->talle_id : null,
                    $fechaCosto,
                    $cacheCosto,
                    $row->combinacion_id,
                );
                $importeCosto = round($cantidad * $precioCosto, 2);
                $item['precio_fabrica'] = $precioFabrica;
                $item['precio_costo'] = $precioCosto;
                $item['importe_costo'] = $importeCosto;
                $item['sin_precio_fabrica'] = $precioFabrica <= 0;
                if ($precioFabrica <= 0) {
                    $sinPrecioFabrica++;
                }
                $totales['importe_costo'] += $importeCosto;
            }

            $filas[] = $item;
            $totales['cantidad'] += $cantidad;
            $totales['importe'] += $importe;
        }

        $totales['cantidad'] = round($totales['cantidad'], 4);
        $totales['importe'] = round($totales['importe'], 2);
        $totales['importe_costo'] = round($totales['importe_costo'], 2);

        return [
            'filas' => $filas,
            'totales' => $totales,
            'periodo_texto' => FacturacionLocalVentasArticulosReporteFiltros::formatearPeriodoTexto($filtros),
            'modo_texto' => FacturacionLocalVentasArticulosReporteFiltros::etiquetaModo($filtros),
            'puntoventa_texto' => $metaPv['puntoventa_texto'],
            'local_texto' => $metaPv['puntoventa_texto'],
            'nombreempresa' => $metaPv['nombreempresa'],
            'abierto_talle' => $abiertoTalle,
            'incluir_costo' => $incluirCosto,
            'costo_formula' => $incluirCosto
                ? FacturacionLocalCostoFabricaSupport::etiquetaFormula()
                : '',
            'sin_precio_fabrica' => $sinPrecioFabrica,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $filas
     */
    public function paginarFilas(array $filas, int $perPage, int $page): LengthAwarePaginator
    {
        $total = count($filas);
        $offset = max(0, ($page - 1) * $perPage);
        $slice = array_slice($filas, $offset, $perPage);

        return new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);
    }

    private function etiquetaCombinacionOColor(
        string $combCodigo,
        string $combNombre,
        string $colorCodigo,
        string $colorNombre,
    ): string {
        $comb = trim($combCodigo.' '.$combNombre);
        if ($comb !== '') {
            return $comb;
        }

        return trim($colorCodigo.' '.$colorNombre);
    }

    /**
     * @return array{puntoventa_texto:string,nombreempresa:string}
     */
    private function metaPuntoventa(int $puntoventaId): array
    {
        if ($puntoventaId <= 0) {
            return ['puntoventa_texto' => '', 'nombreempresa' => ''];
        }
        $pv = Puntoventa::query()
            ->with(['empresas:id,nombre'])
            ->whereKey($puntoventaId)
            ->first(['id', 'codigo', 'nombre', 'empresa_id']);
        if (! $pv) {
            return ['puntoventa_texto' => '', 'nombreempresa' => ''];
        }

        return [
            'puntoventa_texto' => trim(($pv->codigo ?? '').' — '.($pv->nombre ?? '')),
            'nombreempresa' => trim((string) ($pv->empresas->nombre ?? '')),
        ];
    }
}
