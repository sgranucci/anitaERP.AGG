<?php

namespace App\Queries\Ventas;

use App\Models\Stock\Tipoarticulo;
use App\Support\Database\SqlDialectSupport;
use App\Support\Stock\ArticuloUsoDescartableSupport;
use App\Support\Stock\ArticuloUsoInsumoSupport;
use App\Support\Ventas\GastronomiaVentasArticulosReporteFiltros;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Totales del informe «Ventas de artículos» partidos como los usa la conciliación contable.
 * La venta externa es la misma definición del reporte: sin descuento de gastronomía.
 * La nota de crédito resta, con el signo del comprobante.
 */
final class GastronomiaVentasArticulosConciliacionQuery
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   importe_externa_informe: float,
     *   importe_externa_firmado: float,
     *   importe_externa_tabaco: float,
     *   facturas_externa: float,
     *   facturas_invitacion: float,
     *   facturas_staff: float,
     *   ventas_puntoventa_vending: int
     * }
     */
    public function resumen(array $filtros): array
    {
        $lineas = $this->resumenLineas($filtros);
        $facturas = $this->resumenFacturas($filtros);

        return [
            'importe_externa_informe' => $lineas['importe_externa_informe'],
            'importe_externa_firmado' => $lineas['importe_externa_firmado'],
            'importe_externa_tabaco' => $lineas['importe_externa_tabaco'],
            'facturas_externa' => $facturas['externa'],
            'facturas_invitacion' => $facturas['invitacion'],
            'facturas_staff' => $facturas['staff'],
            'ventas_puntoventa_vending' => $this->contarVentasPuntoventaVending($filtros),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{
     *   dia: string,
     *   importe_externa_informe: float,
     *   facturas_invitacion: float,
     *   facturas_staff: float
     * }>
     */
    public function porDia(array $filtros): array
    {
        $externa = [];
        foreach ($this->lineasExternasPorDia($filtros) as $dia => $importe) {
            $externa[$this->claveDia($dia)] = $importe;
        }

        $out = [];
        foreach ($this->facturasPorDia($filtros) as $row) {
            $dia = $this->claveDia($row->dia);
            if (! isset($out[$dia])) {
                $out[$dia] = [
                    'dia' => $dia,
                    'importe_externa_informe' => (float) ($externa[$dia] ?? 0),
                    'facturas_invitacion' => 0.0,
                    'facturas_staff' => 0.0,
                ];
                unset($externa[$dia]);
            }
            if ((string) $row->tipo === 'invitacion') {
                $out[$dia]['facturas_invitacion'] = round((float) $row->total, 2);
            } elseif ((string) $row->tipo === 'staff') {
                $out[$dia]['facturas_staff'] = round((float) $row->total, 2);
            }
        }

        foreach ($externa as $dia => $importe) {
            $out[$dia] = [
                'dia' => $dia,
                'importe_externa_informe' => $importe,
                'facturas_invitacion' => 0.0,
                'facturas_staff' => 0.0,
            ];
        }

        ksort($out);

        return array_values($out);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{importe_externa_informe: float, importe_externa_firmado: float, importe_externa_tabaco: float}
     */
    private function resumenLineas(array $filtros): array
    {
        $tipo = $this->sqlTipoVenta();
        $importe = $this->sqlImporteLinea();
        $cig = $this->sqlEsLineaCigarrillos();

        $row = $this->queryLineas($filtros)
            ->selectRaw("ROUND(SUM(CASE WHEN ({$tipo}) = 'externa' THEN ({$importe}) ELSE 0 END), 2) as informe")
            ->selectRaw("ROUND(SUM(CASE WHEN ({$tipo}) = 'externa' THEN ({$importe}) ELSE 0 END), 2) as firmado")
            ->selectRaw("ROUND(SUM(CASE WHEN ({$tipo}) = 'externa' AND ({$cig}) THEN ({$importe}) ELSE 0 END), 2) as tabaco")
            ->first();

        return [
            'importe_externa_informe' => round((float) ($row->informe ?? 0), 2),
            'importe_externa_firmado' => round((float) ($row->firmado ?? 0), 2),
            'importe_externa_tabaco' => round((float) ($row->tabaco ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, float>
     */
    private function lineasExternasPorDia(array $filtros): array
    {
        $tipo = $this->sqlTipoVenta();
        $importe = $this->sqlImporteLinea();
        $dia = SqlDialectSupport::fecha('v.fechajornada');

        $rows = $this->queryLineas($filtros)
            ->selectRaw("{$dia} as dia")
            ->selectRaw("ROUND(SUM(CASE WHEN ({$tipo}) = 'externa' THEN ({$importe}) ELSE 0 END), 2) as informe")
            ->groupByRaw($dia)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$this->claveDia($row->dia)] = round((float) ($row->informe ?? 0), 2);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{externa: float, invitacion: float, staff: float}
     */
    private function resumenFacturas(array $filtros): array
    {
        $tipo = $this->sqlTipoVenta();
        $total = GastronomiaVentaComprobanteSignoSupport::sqlTotalComprobante();

        $rows = $this->queryFacturas($filtros)
            ->selectRaw("({$tipo}) as tipo")
            ->selectRaw("ROUND(SUM({$total}), 2) as total")
            ->groupByRaw("({$tipo})")
            ->get();

        $out = ['externa' => 0.0, 'invitacion' => 0.0, 'staff' => 0.0];
        foreach ($rows as $row) {
            $clave = (string) $row->tipo;
            if (array_key_exists($clave, $out)) {
                $out[$clave] = round((float) $row->total, 2);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<object{dia: string, tipo: string, total: float}>
     */
    private function facturasPorDia(array $filtros): array
    {
        $tipo = $this->sqlTipoVenta();
        $total = GastronomiaVentaComprobanteSignoSupport::sqlTotalComprobante();
        $dia = SqlDialectSupport::fecha('v.fechajornada');

        return $this->queryFacturas($filtros)
            ->selectRaw("{$dia} as dia")
            ->selectRaw("({$tipo}) as tipo")
            ->selectRaw("ROUND(SUM({$total}), 2) as total")
            ->groupByRaw("{$dia}, ({$tipo})")
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function contarVentasPuntoventaVending(array $filtros): int
    {
        return (int) $this->queryFacturas($filtros)
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('maquinavending as mv')
                    ->whereColumn('mv.puntoventa_id', 'v.puntoventa_id');
            })
            ->distinct()
            ->count('v.id');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryLineas(array $filtros): Builder
    {
        $query = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->leftJoin('cuenta_gastronomia as cg', 'cg.id', '=', 'vge.cuenta_gastronomia_id')
            ->leftJoin('descuento_gastronomia as dg', 'dg.id', '=', 'cg.descuento_gastronomia_id');

        $query->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('usoarticulo as ua_excl')
                ->whereColumn('ua_excl.id', 'a.usoarticulo_id')
                ->whereRaw(
                    'UPPER(TRIM(ua_excl.nombre)) IN (?, ?)',
                    [
                        ArticuloUsoInsumoSupport::NOMBRE_USO_INSUMO,
                        ArticuloUsoDescartableSupport::NOMBRE_USO_DESCARTABLES,
                    ],
                );
        });

        $this->aplicarPeriodoEmpresa($query, $filtros);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryFacturas(array $filtros): Builder
    {
        $query = DB::table('venta as v')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->leftJoin('cuenta_gastronomia as cg', 'cg.id', '=', 'vge.cuenta_gastronomia_id')
            ->leftJoin('descuento_gastronomia as dg', 'dg.id', '=', 'cg.descuento_gastronomia_id');

        $this->aplicarPeriodoEmpresa($query, $filtros);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarPeriodoEmpresa(Builder $query, array $filtros): void
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('pv.empresa_id', $empresaId);
        }

        [$desde, $hasta] = GastronomiaVentasArticulosReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        if ($desde !== '') {
            $query->whereDate('v.fechajornada', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate('v.fechajornada', '<=', $hasta);
        }
    }

    private function sqlTipoVenta(): string
    {
        return "CASE
            WHEN cg.descuento_gastronomia_id IS NULL THEN 'externa'
            WHEN dg.tipo_consumo = 'staff' THEN 'staff'
            ELSE 'invitacion'
        END";
    }

    private function sqlImporteLinea(): string
    {
        return GastronomiaVentaComprobanteSignoSupport::sqlImporteLineaVenta();
    }

    /**
     * Misma idea que CierreJornadaVentasCigarrillosSupport::articuloEsLineaMenuCigarrillos:
     * la línea de menú (no el insumo) que el asiento manda a la cuenta de tabaco.
     */
    private function sqlEsLineaCigarrillos(): string
    {
        $tipoId = Tipoarticulo::idControlContableCigarrillos();
        $partes = ["UPPER(a.descripcion) LIKE '%CIGARRILLO%'"];

        if ($tipoId !== null && $tipoId > 0) {
            $partes[] = 'a.tipoarticulo_id = '.(int) $tipoId;
            $partes[] = 'EXISTS (
                SELECT 1 FROM formula_articulo_hijo fh
                INNER JOIN articulo ai ON ai.id = fh.articulo_id
                WHERE fh.formula_articulo_id = a.formula
                  AND ai.tipoarticulo_id = '.(int) $tipoId.'
            )';
            $partes[] = 'EXISTS (
                SELECT 1 FROM formula_articulo_hijo fh
                INNER JOIN formula_articulo_hijo fh2 ON fh2.formula_articulo_id = fh.formula_hija_id
                INNER JOIN articulo ai2 ON ai2.id = fh2.articulo_id
                WHERE fh.formula_articulo_id = a.formula
                  AND fh.formula_hija_id IS NOT NULL
                  AND ai2.tipoarticulo_id = '.(int) $tipoId.'
            )';
        }

        return '('.implode(' OR ', $partes).')';
    }

    private function claveDia(mixed $dia): string
    {
        $texto = trim((string) $dia);

        return strlen($texto) >= 10 ? substr($texto, 0, 10) : $texto;
    }
}
