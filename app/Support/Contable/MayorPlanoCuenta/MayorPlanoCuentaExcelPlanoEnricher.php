<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Support\Compras\ComprobanteProveedorEstados;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa el Excel plano con qué se compró (ítems/IA), proyecto CAPEX, facturas y usuario.
 * Las facturas de una misma OC van concatenadas en una sola celda (no se duplican filas).
 * COM/recepción no se listan como factura.
 */
class MayorPlanoCuentaExcelPlanoEnricher
{
    public function __construct(
        private readonly MayorPlanoCuentaOcCompraResumenSupport $ocResumenSupport = new MayorPlanoCuentaOcCompraResumenSupport(),
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function enriquecer(array $filas, bool $usarIa = false): array
    {
        if ($filas === []) {
            return $filas;
        }

        $ocIds = [];
        $nrosOc = [];
        $asientoIds = [];
        $cpIds = [];

        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }
            $ocId = (int) ($fila['ordencompra_id'] ?? 0);
            if ($ocId > 0) {
                $ocIds[$ocId] = $ocId;
            }
            $nroOc = (int) ($fila['nro_oc'] ?? 0);
            if ($nroOc > 0) {
                $nrosOc[$nroOc] = $nroOc;
            }
            $asientoId = (int) ($fila['asiento_id'] ?? 0);
            if ($asientoId > 0) {
                $asientoIds[$asientoId] = $asientoId;
            }
            $cpId = (int) ($fila['comprobante_proveedor_id'] ?? 0);
            if ($cpId > 0) {
                $cpIds[$cpId] = $cpId;
            }
        }

        $ocs = $this->cargarOrdenes($ocIds, $nrosOc);
        foreach ($ocs['por_id'] as $id => $oc) {
            $ocIds[(int) $id] = (int) $id;
        }

        $facturasPorOc = $this->cargarFacturasPorOc($ocIds);
        $facturasPorCp = $this->cargarFacturasPorId($cpIds);
        $capexPorOc = $this->cargarCapexPorOc($ocIds);
        $asientos = $this->cargarAsientos($asientoIds);
        $itemsPorOc = $this->cargarItemsPorOc($ocIds);
        $resumenesOc = $this->ocResumenSupport->resumirVarias($itemsPorOc, $usarIa);

        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $ocId = (int) ($fila['ordencompra_id'] ?? 0);
            $nroOc = (int) ($fila['nro_oc'] ?? 0);
            $oc = $ocId > 0
                ? ($ocs['por_id'][$ocId] ?? null)
                : ($nroOc > 0 ? ($ocs['por_numero'][$nroOc] ?? null) : null);
            if ($oc !== null && $ocId <= 0) {
                $ocId = (int) ($oc->id ?? 0);
            }

            $etiquetas = $facturasPorOc[$ocId] ?? [];
            $cpId = (int) ($fila['comprobante_proveedor_id'] ?? 0);
            if ($cpId > 0 && isset($facturasPorCp[$cpId])) {
                $etiquetas[] = $facturasPorCp[$cpId];
            }
            $propia = MayorPlanoCuentaExcelPlanoSupport::etiquetaFacturaDesdeMovimiento($fila);
            if ($propia !== '') {
                $etiquetas[] = $propia;
            }

            $asientoId = (int) ($fila['asiento_id'] ?? 0);
            $asiento = $asientos[$asientoId] ?? null;

            $resumen = trim((string) ($resumenesOc[$ocId] ?? ''));
            if ($resumen === '' && $oc !== null) {
                $resumen = MayorPlanoCuentaExcelPlanoSupport::observacionOc(
                    (string) ($oc->comentario ?? ''),
                    (string) ($oc->detalle ?? ''),
                );
            }
            $filas[$idx]['observacion_oc'] = $resumen;
            $filas[$idx]['numeros_facturas'] = MayorPlanoCuentaExcelPlanoSupport::concatenarEnUnaCelda($etiquetas);
            $filas[$idx]['proyecto_capex'] = MayorPlanoCuentaExcelPlanoSupport::proyectoCapexDesdeCodigos(
                $capexPorOc[$ocId] ?? [],
            );
            $filas[$idx]['usuario'] = trim((string) ($asiento['usuario'] ?? $fila['usuario'] ?? ''));
            $filas[$idx]['fecha_ult_mod'] = (string) ($asiento['fecha_ult_mod'] ?? $fila['fecha_ult_mod'] ?? '');
        }

        return $filas;
    }

    /**
     * @param  array<int, int>  $ocIds
     * @return array<int, list<array{sku:string,descripcion:string,detalle:string,cantidad:float,partida:string}>>
     */
    private function cargarItemsPorOc(array $ocIds): array
    {
        if ($ocIds === [] || ! Schema::hasTable('ordencompra_articulo')) {
            return [];
        }

        $query = DB::table('ordencompra_articulo as oa')
            ->whereIn('oa.ordencompra_id', array_values($ocIds))
            ->select(['oa.ordencompra_id', 'oa.cantidad', 'oa.detalle', 'oa.articulo_id', 'oa.partidagasto_id']);

        if (Schema::hasTable('articulo')) {
            $query->leftJoin('articulo as a', 'a.id', '=', 'oa.articulo_id')
                ->addSelect(['a.sku', 'a.descripcion']);
        }
        if (Schema::hasTable('partidagasto')) {
            $query->leftJoin('partidagasto as pg', 'pg.id', '=', 'oa.partidagasto_id')
                ->addSelect(['pg.codigo as partida_codigo', 'pg.detalle as partida_detalle']);
            if (Schema::hasTable('articulo')) {
                $query->leftJoin('articulo as pga', 'pga.id', '=', 'pg.articulo_id')
                    ->addSelect(['pga.descripcion as partida_articulo']);
            }
        }

        $out = [];
        foreach ($query->orderBy('oa.id')->get() as $row) {
            $ocId = (int) $row->ordencompra_id;
            $partida = trim((string) ($row->partida_articulo ?? ''));
            if ($partida === '') {
                $partida = trim((string) ($row->partida_detalle ?? ''));
            }
            if ($partida === '' && trim((string) ($row->partida_codigo ?? '')) !== '') {
                $partida = 'Partida '.$row->partida_codigo;
            }
            $out[$ocId][] = [
                'sku' => trim((string) ($row->sku ?? '')),
                'descripcion' => trim((string) ($row->descripcion ?? '')),
                'detalle' => trim((string) ($row->detalle ?? '')),
                'cantidad' => (float) ($row->cantidad ?? 0),
                'partida' => $partida,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $ocIds
     * @param  array<int, int>  $nrosOc
     * @return array{por_id: array<int, object>, por_numero: array<int, object>}
     */
    private function cargarOrdenes(array $ocIds, array $nrosOc): array
    {
        $vacio = ['por_id' => [], 'por_numero' => []];
        if (! Schema::hasTable('ordencompra')) {
            return $vacio;
        }

        $query = DB::table('ordencompra')->select(['id', 'numeroordencompra', 'comentario', 'detalle']);
        $ids = array_values($ocIds);
        $nros = array_values($nrosOc);
        if ($ids === [] && $nros === []) {
            return $vacio;
        }

        $query->where(function ($q) use ($ids, $nros) {
            if ($ids !== []) {
                $q->whereIn('id', $ids);
            }
            if ($nros !== []) {
                if ($ids !== []) {
                    $q->orWhereIn('numeroordencompra', $nros);
                } else {
                    $q->whereIn('numeroordencompra', $nros);
                }
            }
        });

        $porId = [];
        $porNumero = [];
        foreach ($query->get() as $oc) {
            $id = (int) $oc->id;
            $nro = (int) ($oc->numeroordencompra ?? 0);
            $porId[$id] = $oc;
            if ($nro > 0) {
                $porNumero[$nro] = $oc;
            }
        }

        return ['por_id' => $porId, 'por_numero' => $porNumero];
    }

    /**
     * @param  array<int, int>  $ocIds
     * @return array<int, list<string>>
     */
    private function cargarFacturasPorOc(array $ocIds): array
    {
        if ($ocIds === [] || ! Schema::hasTable('comprobante_proveedor')) {
            return [];
        }

        $query = DB::table('comprobante_proveedor as cp')
            ->leftJoin('tipotransaccion_compra as t', 't.id', '=', 'cp.tipotransaccion_compra_id')
            ->whereIn('cp.ordencompra_id', array_values($ocIds))
            ->select([
                'cp.ordencompra_id',
                't.abreviatura',
                'cp.letra',
                'cp.sucursal',
                'cp.numerocomprobante',
            ]);
        if (Schema::hasColumn('comprobante_proveedor', 'estado')) {
            $query->where('cp.estado', '!=', ComprobanteProveedorEstados::ANULADO);
        }

        $out = [];
        foreach ($query->orderBy('cp.fechacomprobante')->orderBy('cp.id')->get() as $row) {
            $ocId = (int) $row->ordencompra_id;
            $etiqueta = MayorPlanoCuentaExcelPlanoSupport::formatearNumeroFactura(
                (string) ($row->abreviatura ?? ''),
                (string) ($row->letra ?? ''),
                (int) ($row->sucursal ?? 0),
                (string) ($row->numerocomprobante ?? ''),
            );
            if ($etiqueta === '') {
                continue;
            }
            $out[$ocId][] = $etiqueta;
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $cpIds
     * @return array<int, string>
     */
    private function cargarFacturasPorId(array $cpIds): array
    {
        if ($cpIds === [] || ! Schema::hasTable('comprobante_proveedor')) {
            return [];
        }

        $query = DB::table('comprobante_proveedor as cp')
            ->leftJoin('tipotransaccion_compra as t', 't.id', '=', 'cp.tipotransaccion_compra_id')
            ->whereIn('cp.id', array_values($cpIds))
            ->select([
                'cp.id',
                't.abreviatura',
                'cp.letra',
                'cp.sucursal',
                'cp.numerocomprobante',
            ]);

        $out = [];
        foreach ($query->get() as $row) {
            $etiqueta = MayorPlanoCuentaExcelPlanoSupport::formatearNumeroFactura(
                (string) ($row->abreviatura ?? ''),
                (string) ($row->letra ?? ''),
                (int) ($row->sucursal ?? 0),
                (string) ($row->numerocomprobante ?? ''),
            );
            if ($etiqueta !== '') {
                $out[(int) $row->id] = $etiqueta;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $ocIds
     * @return array<int, list<string>>
     */
    private function cargarCapexPorOc(array $ocIds): array
    {
        if ($ocIds === []
            || ! Schema::hasTable('ordencompra_articulo')
            || ! Schema::hasTable('capex')
            || ! Schema::hasColumn('ordencompra_articulo', 'capex_id')
        ) {
            return [];
        }

        $rows = DB::table('ordencompra_articulo as oa')
            ->join('capex as c', 'c.id', '=', 'oa.capex_id')
            ->whereIn('oa.ordencompra_id', array_values($ocIds))
            ->where('oa.capex_id', '>', 0)
            ->select(['oa.ordencompra_id', 'c.codigoproyecto', 'c.codigo', 'c.nombre'])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $codigo = trim((string) ($row->codigoproyecto ?? ''));
            if ($codigo === '') {
                $codigo = trim((string) ($row->codigo ?? ''));
            }
            if ($codigo === '') {
                $codigo = trim((string) ($row->nombre ?? ''));
            }
            if ($codigo === '') {
                continue;
            }
            $out[(int) $row->ordencompra_id][] = $codigo;
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $asientoIds
     * @return array<int, array{usuario: string, fecha_ult_mod: string}>
     */
    private function cargarAsientos(array $asientoIds): array
    {
        if ($asientoIds === [] || ! Schema::hasTable('asiento')) {
            return [];
        }

        $cols = ['a.id'];
        $tieneUsuario = Schema::hasColumn('asiento', 'usuario_id');
        if ($tieneUsuario) {
            $cols[] = 'u.usuario';
        }
        $tieneUpdated = Schema::hasColumn('asiento', 'updated_at');
        if ($tieneUpdated) {
            $cols[] = 'a.updated_at';
        }
        $cols[] = 'a.fecha';

        $query = DB::table('asiento as a')->whereIn('a.id', array_values($asientoIds))->select($cols);
        if ($tieneUsuario && Schema::hasTable('usuario')) {
            $query->leftJoin('usuario as u', 'u.id', '=', 'a.usuario_id');
        }

        $out = [];
        foreach ($query->get() as $row) {
            $fecha = '';
            if ($tieneUpdated && ! empty($row->updated_at)) {
                $fecha = $this->formatearFechaMod((string) $row->updated_at);
            } elseif (! empty($row->fecha)) {
                $fecha = $this->formatearFechaMod((string) $row->fecha);
            }
            $out[(int) $row->id] = [
                'usuario' => trim((string) ($row->usuario ?? '')),
                'fecha_ult_mod' => $fecha,
            ];
        }

        return $out;
    }

    private function formatearFechaMod(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }

        $ymd = preg_replace('/\D/', '', substr($valor, 0, 10)) ?? '';
        if (strlen($ymd) === 8) {
            return substr($ymd, 6, 2).'/'.substr($ymd, 4, 2).'/'.substr($ymd, 0, 4);
        }

        return $valor;
    }
}
