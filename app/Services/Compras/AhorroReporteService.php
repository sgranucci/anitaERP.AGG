<?php

namespace App\Services\Compras;

use App\Support\Compras\AhorroLineaSupport;
use App\Support\Compras\OrdencompraEstados;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Reporte de ahorros: una fila por línea de OC con ahorro cargado, OC no suspendidas.
 * % de ahorro total = ahorro / (gasto + ahorro), donde gasto + ahorro = precio anterior × cantidad (en pesos).
 */
class AhorroReporteService
{
    /**
     * @param  array<string, mixed>  $filtros  {@see \App\Support\Compras\AhorroReporteFiltros}
     * @return array{filas: Collection<int, object>, resumen: array{ahorro_pesos: float, base_pesos: float, porcentaje: float|null, lineas_con_ahorro: int}}
     */
    public function generar(array $filtros): array
    {
        $filas = $this->consulta($filtros)->get();

        return [
            'filas' => $filas,
            'resumen' => AhorroLineaSupport::resumen($filas),
        ];
    }

    /**
     * Texto de los filtros activos para el encabezado de PDF / Excel.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function subtituloFiltros(array $filtros): string
    {
        $partes = [
            'Período OC: '.date('d/m/Y', strtotime($filtros['desde'])).' al '.date('d/m/Y', strtotime($filtros['hasta'])),
        ];

        $asignadas = collect($this->empresasAsignadas());
        $pedidas = $filtros['empresa_ids'] ?? [];
        if ($pedidas !== [] && $asignadas->isNotEmpty()) {
            $partes[] = 'Empresas: '.$asignadas->whereIn('id', $pedidas)->pluck('nombre')->implode(', ');
        }

        $buscar = [
            'comprador_id' => ['Comprador', 'usuario', 'nombre'],
            'proveedor_id' => ['Proveedor', 'proveedor', 'nombre'],
            'centrocosto_id' => ['C. costo destino', 'centrocosto', 'nombre'],
            'usoarticulo_id' => ['Uso de artículo', 'usoarticulo', 'nombre'],
            'tipoahorro_id' => ['Tipo de ahorro', 'tipo_ahorro', 'nombre'],
        ];
        foreach ($buscar as $clave => [$etiqueta, $tabla, $columna]) {
            $id = (int) ($filtros[$clave] ?? 0);
            if ($id > 0) {
                $valor = DB::table($tabla)->where('id', $id)->value($columna);
                if ($valor !== null && $valor !== '') {
                    $partes[] = $etiqueta.': '.$valor;
                }
            }
        }

        return implode(' · ', $partes);
    }

    /** Empresas que el usuario puede ver en el reporte. */
    public function empresasAsignadas(): array
    {
        return collect(Session::get('usuario_empresas'))->map(fn ($e) => [
            'id' => (int) ($e['id'] ?? 0),
            'nombre' => (string) ($e['nombre'] ?? ''),
        ])->filter(fn ($e) => $e['id'] > 0)->values()->all();
    }

    /**
     * Empresas pedidas que además estén asignadas al usuario; sin pedido = todas las asignadas.
     *
     * Sin empresas asignadas al usuario (acceso total) devuelve null = sin filtro de empresa.
     *
     * @param  array<int, int>  $pedidas
     * @return array<int, int>|null
     */
    private function empresasResueltas(array $pedidas): ?array
    {
        $asignadas = collect($this->empresasAsignadas())->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($asignadas === []) {
            return null;
        }
        if ($pedidas === []) {
            return $asignadas;
        }

        $resueltas = array_values(array_intersect($pedidas, $asignadas));

        return $resueltas !== [] ? $resueltas : [-1];
    }

    private function consulta(array $filtros)
    {
        $q = DB::table('ordencompra_articulo as oa')
            ->join('ordencompra as o', 'o.id', '=', 'oa.ordencompra_id')
            ->leftJoin('requisicion as r', 'r.id', '=', 'o.requisicion_id')
            ->leftJoin('empresa as e', 'e.id', '=', 'o.empresa_id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'o.proveedor_id')
            ->leftJoin('articulo as a', 'a.id', '=', 'oa.articulo_id')
            ->leftJoin('centrocosto as cc', 'cc.id', '=', 'oa.centrocostodestino_id')
            ->leftJoin('usuario as u', 'u.id', '=', 'o.creousuario_id')
            ->leftJoin('tipo_ahorro as ta', 'ta.id', '=', 'oa.tipoahorro_id')
            ->leftJoin('moneda as m', 'm.id', '=', 'oa.moneda_id')
            ->leftJoin('moneda as ma', 'ma.id', '=', 'oa.moneda_anterior_id')
            ->whereBetween('o.fecha', [$filtros['desde'], $filtros['hasta']])
            ->where('o.estadoordencompra', '!=', OrdencompraEstados::SUSPENDIDA)
            ->whereNotNull('oa.ahorro_total_pesos');

        $empresas = $this->empresasResueltas($filtros['empresa_ids'] ?? []);
        if ($empresas !== null) {
            $q->whereIn('o.empresa_id', $empresas);
        }
        if ($filtros['comprador_id'] > 0) {
            $q->where('o.creousuario_id', $filtros['comprador_id']);
        }
        if ($filtros['proveedor_id'] > 0) {
            $q->where('o.proveedor_id', $filtros['proveedor_id']);
        }
        if ($filtros['centrocosto_id'] > 0) {
            $q->where('oa.centrocostodestino_id', $filtros['centrocosto_id']);
        }
        if (($filtros['usoarticulo_id'] ?? 0) > 0) {
            $q->where('a.usoarticulo_id', $filtros['usoarticulo_id']);
        }
        if (($filtros['tipoahorro_id'] ?? 0) > 0) {
            $q->where('oa.tipoahorro_id', $filtros['tipoahorro_id']);
        }

        return $q->orderBy('o.fecha')->orderBy('o.id')->orderBy('oa.id')
            ->select([
                'o.id as ordencompra_id',
                'o.numeroordencompra',
                'o.fecha',
                'o.estadoordencompra',
                'e.nombre as nombreempresa',
                'r.numerorequisicion',
                'p.codigo as proveedor_codigo',
                'p.nombre as proveedor_nombre',
                'a.sku',
                'a.descripcion as articulo_descripcion',
                'cc.codigo as centrocosto_codigo',
                'cc.nombre as centrocosto_nombre',
                'u.nombre as comprador_nombre',
                'ta.nombre as tipoahorro_nombre',
                'm.abreviatura as moneda_abrev',
                'ma.abreviatura as moneda_anterior_abrev',
                'oa.cantidad',
                'oa.precio',
                'oa.preciooriginal',
                'oa.ahorro_unitario',
                'oa.ahorro_total',
                'oa.ahorro_total_pesos',
                'oa.tc_ahorro',
                'oa.porc_ahorro',
                'oa.origen_anterior',
                'oa.referencia_anterior',
                'oa.motivoahorro',
            ]);
    }

    /** Etiqueta de origen para la exportación. */
    public static function origenTexto(?string $origen): string
    {
        return AhorroLineaSupport::etiquetaOrigen($origen);
    }
}
