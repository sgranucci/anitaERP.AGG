<?php

namespace App\Queries\Ventas;

use App\Models\Ventas\JornadaGastronomia;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Ventas\GastronomiaArticulosVendidosListadoFiltros;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GastronomiaArticulosVendidosQuery
{
    private const CANTIDAD_EXPR = 'CASE WHEN tt.signo = -1 THEN -ve.cantidad ELSE ve.cantidad END';

    private const IMPORTE_EXPR = 'CASE WHEN tt.signo = -1 THEN -ve.cantidad * ve.precio ELSE ve.cantidad * ve.precio END';

    private const DEPOSITO_EXPR = 'COALESCE(am_dep.deposito_id, am_dep_v.deposito_id, ve.deposito_id)';

    private const DEPOSITO_INSUMO_EXPR = 'COALESCE(am_ing.deposito_id, am_ing_v.deposito_id)';

    private const DEPOSITO_INSUMO_JOIN = 'COALESCE(am_ing.deposito_id, am_ing_v.deposito_id)';

    /**
     * @return LengthAwarePaginator<int, object>|Collection<int, object>
     */
    public function listado(array $filtros, bool $paginar = true, int $perPage = 50): LengthAwarePaginator|Collection
    {
        $query = $this->queryBase($filtros);

        $query->orderBy('a.sku')
            ->orderBy('pv.codigo')
            ->orderBy('d.codigo');

        if ($paginar) {
            return $query->paginate(max(10, min(200, $perPage)))
                ->through(fn ($row) => $this->enriquecerFila($row));
        }

        return $query->get()->map(fn ($row) => $this->enriquecerFila($row));
    }

    /**
     * Top N artículos vendidos en una jornada (líneas facturadas en venta_emision).
     * No incluye insumos de fórmula (articulo_movimiento con sufijo « — Ing.»).
     *
     * @return list<array{articulo_id:int,sku:string,descripcion:string,cantidad:float,importe:float}>
     */
    public function topPorJornada(
        int $empresaId,
        string $fechaJornada,
        string $orden = 'cantidad',
        int $limit = 10,
    ): array {
        if ($empresaId <= 0) {
            return [];
        }

        $orderCol = $orden === 'importe' ? 'importe_total' : 'cantidad_total';

        $rows = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->whereNull('v.deleted_at')
            ->where('pv.empresa_id', $empresaId)
            ->whereDate('v.fechajornada', $fechaJornada)
            ->select([
                've.articulo_id',
                'a.sku',
                'a.descripcion',
            ])
            ->selectRaw('SUM('.self::CANTIDAD_EXPR.') as cantidad_total')
            ->selectRaw('SUM('.self::IMPORTE_EXPR.') as importe_total')
            ->groupBy('ve.articulo_id', 'a.sku', 'a.descripcion')
            ->orderByDesc($orderCol)
            ->orderBy('a.sku')
            ->limit(max(1, min(50, $limit)))
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'articulo_id' => (int) $row->articulo_id,
                'sku' => trim((string) $row->sku),
                'descripcion' => trim((string) $row->descripcion),
                'cantidad' => round((float) $row->cantidad_total, 4),
                'importe' => round((float) $row->importe_total, 2),
            ];
        }

        return $out;
    }

    /**
     * Totales del listado filtrado.
     *
     * @return array{cantidad_articulos:int,cantidad_total:float,importe_total:float,cantidad_comprobantes:int}
     */
    public function totales(array $filtros): array
    {
        $sub = $this->queryBase($filtros);

        $row = DB::query()
            ->fromSub($sub, 't')
            ->selectRaw('COUNT(*) as cantidad_articulos')
            ->selectRaw('COALESCE(SUM(cantidad_total), 0) as cantidad_total')
            ->selectRaw('COALESCE(SUM(importe_total), 0) as importe_total')
            ->first();

        $comprobantes = DB::query()
            ->fromSub($this->queryComprobantesDistintos($filtros), 'c')
            ->count();

        return [
            'cantidad_articulos' => (int) ($row->cantidad_articulos ?? 0),
            'cantidad_total' => round((float) ($row->cantidad_total ?? 0), 4),
            'importe_total' => round((float) ($row->importe_total ?? 0), 2),
            'cantidad_comprobantes' => (int) $comprobantes,
        ];
    }

    private function queryComprobantesDistintos(array $filtros): Builder
    {
        $query = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->leftJoin('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->whereNull('v.deleted_at')
            ->select('v.id')
            ->distinct();

        $this->aplicarJoinsDeposito($query);

        $this->aplicarFiltrosEstructurales($query, $filtros);

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '' || ($filtros['operador'] ?? '') === 'vacio') {
            $this->aplicarFiltrosTexto($query, $filtros);
        }

        return $query;
    }

    /**
     * Comprobantes que incluyen un artículo (mismos filtros estructurales del listado).
     *
     * @return list<array{
     *   venta_id:int,
     *   codigo:string,
     *   fecha_jornada:?string,
     *   fecha_comprobante:?string,
     *   hora:?string,
     *   cantidad:float,
     *   importe:float,
     *   puntoventa_etiqueta:string,
     *   deposito_etiqueta:string,
     *   es_nota_credito:bool
     * }>
     */
    public function facturasPorArticulo(int $articuloId, array $filtros): array
    {
        if ($articuloId <= 0) {
            return [];
        }

        $query = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->leftJoin('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->whereNull('v.deleted_at')
            ->where('ve.articulo_id', $articuloId);

        $this->aplicarJoinsDeposito($query);

        $query
            ->select([
                'v.id as venta_id',
                'v.codigo',
                'v.fechajornada',
                'v.fecha',
                'v.created_at',
                'v.puntoventa_id',
                've.deposito_id',
                'vge.venta_factura_origen_id',
                'd.codigo as deposito_codigo',
                'd.nombre as deposito_nombre',
                'd_ing.codigo as deposito_insumos_codigo',
                'd_ing.nombre as deposito_insumos_nombre',
            ])
            ->selectRaw(self::DEPOSITO_EXPR.' as deposito_resuelto_id')
            ->selectRaw(self::DEPOSITO_INSUMO_EXPR.' as deposito_insumos_id')
            ->selectRaw(self::CANTIDAD_EXPR.' as cantidad')
            ->selectRaw(self::IMPORTE_EXPR.' as importe')
            ->selectRaw("TRIM(CONCAT(COALESCE(pv.codigo, ''), ' ', COALESCE(pv.nombre, ''))) as puntoventa_etiqueta");

        $this->aplicarFiltrosEstructurales($query, $filtros);

        if ((int) ($filtros['deposito_id'] ?? 0) > 0) {
            $this->aplicarFiltroDeposito($query, (int) $filtros['deposito_id']);
        }

        return $query
            ->orderByDesc('v.id')
            ->get()
            ->map(function ($row) {
                $fechaJornada = $row->fechajornada
                    ? \Illuminate\Support\Carbon::parse($row->fechajornada)->format('d/m/Y')
                    : null;
                $fechaComp = $row->fecha
                    ? \Illuminate\Support\Carbon::parse($row->fecha)->format('d/m/Y')
                    : null;
                $hora = $row->created_at
                    ? \Illuminate\Support\Carbon::parse($row->created_at)->format('H:i:s')
                    : null;

                return [
                    'venta_id' => (int) $row->venta_id,
                    'codigo' => (string) ($row->codigo ?? ''),
                    'fecha_jornada' => $fechaJornada,
                    'fecha_comprobante' => $fechaComp,
                    'hora' => $hora,
                    'cantidad' => round((float) $row->cantidad, 4),
                    'importe' => round((float) $row->importe, 2),
                    'puntoventa_etiqueta' => trim((string) ($row->puntoventa_etiqueta ?? '')),
                    'deposito_etiqueta' => $this->etiquetaDepositoFila($row),
                    'es_nota_credito' => $row->venta_factura_origen_id !== null,
                ];
            })
            ->values()
            ->all();
    }

    private function queryBase(array $filtros, bool $aplicarTexto = true): Builder
    {
        $query = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->leftJoin('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->whereNull('v.deleted_at');

        $this->aplicarJoinsDeposito($query);

        $query
            ->select([
                've.articulo_id',
                'a.sku',
                'a.descripcion',
            ])
            ->selectRaw(self::DEPOSITO_EXPR.' as deposito_id')
            ->addSelect([
                'd.codigo as deposito_codigo',
                'd.nombre as deposito_nombre',
                'd_ing.codigo as deposito_insumos_codigo',
                'd_ing.nombre as deposito_insumos_nombre',
                'v.puntoventa_id',
                'pv.codigo as pv_codigo',
                'pv.nombre as pv_nombre',
            ])
            ->selectRaw(self::DEPOSITO_INSUMO_EXPR.' as deposito_insumos_id')
            ->selectRaw('SUM('.self::CANTIDAD_EXPR.') as cantidad_total')
            ->selectRaw('SUM('.self::IMPORTE_EXPR.') as importe_total')
            ->selectRaw('COUNT(DISTINCT v.id) as cantidad_comprobantes')
            ->groupBy(
                've.articulo_id',
                'a.sku',
                'a.descripcion',
                DB::raw(self::DEPOSITO_EXPR),
                'd.codigo',
                'd.nombre',
                DB::raw(self::DEPOSITO_INSUMO_EXPR),
                'd_ing.codigo',
                'd_ing.nombre',
                'v.puntoventa_id',
                'pv.codigo',
                'pv.nombre',
            );

        $this->aplicarFiltrosEstructurales($query, $filtros);

        if ($aplicarTexto) {
            $valor = trim((string) ($filtros['valor'] ?? ''));
            if ($valor !== '' || ($filtros['operador'] ?? '') === 'vacio') {
                $this->aplicarFiltrosTexto($query, $filtros);
            }
        }

        return $query;
    }

    private function aplicarJoinsDeposito(Builder $query): void
    {
        $query
            ->leftJoinSub($this->subqueryDepositoMovimientoItem(), 'am_dep', function ($join) {
                $join->on('am_dep.venta_emision_id', '=', 've.id')
                    ->on('am_dep.articulo_id', '=', 've.articulo_id');
            })
            ->leftJoinSub($this->subqueryDepositoMovimientoPorVenta(), 'am_dep_v', function ($join) {
                $join->on('am_dep_v.venta_id', '=', 'v.id')
                    ->on('am_dep_v.articulo_id', '=', 've.articulo_id');
            })
            ->leftJoinSub($this->subqueryDepositoInsumosLinea(), 'am_ing', function ($join) {
                $join->on('am_ing.venta_emision_id', '=', 've.id');
            })
            ->leftJoinSub($this->subqueryDepositoInsumoPorVentaArticulo(), 'am_ing_v', function ($join) {
                $join->on('am_ing_v.venta_id', '=', 'v.id')
                    ->on('am_ing_v.articulo_id', '=', 've.articulo_id');
            })
            ->leftJoin('depmae as d', 'd.id', '=', DB::raw(self::DEPOSITO_EXPR))
            ->leftJoin('depmae as d_ing', function ($join) {
                $join->whereRaw('d_ing.id = '.self::DEPOSITO_INSUMO_JOIN);
            });
    }

    /**
     * Depósito del ítem facturado en la misma línea de emisión.
     */
    private function subqueryDepositoMovimientoItem(): Builder
    {
        $sufijo = GastronomiaVentaDetalleSupport::SUFIJO_CONCEPTO_INSUMO;

        return DB::table('articulo_movimiento')
            ->select('venta_emision_id', 'articulo_id')
            ->selectRaw('MIN(deposito_id) as deposito_id')
            ->whereNotNull('venta_emision_id')
            ->where('concepto', 'not like', '%'.$sufijo)
            ->groupBy('venta_emision_id', 'articulo_id');
    }

    /**
     * Depósito del artículo vendido buscando en toda la venta (líneas sin movimiento propio).
     */
    private function subqueryDepositoMovimientoPorVenta(): Builder
    {
        $sufijo = GastronomiaVentaDetalleSupport::SUFIJO_CONCEPTO_INSUMO;

        return DB::table('articulo_movimiento')
            ->select('venta_id', 'articulo_id')
            ->selectRaw('MIN(deposito_id) as deposito_id')
            ->whereNotNull('venta_id')
            ->where('concepto', 'not like', '%'.$sufijo)
            ->groupBy('venta_id', 'articulo_id');
    }

    /**
     * Depósito de insumos de fórmula descontados en la línea facturada.
     */
    private function subqueryDepositoInsumosLinea(): Builder
    {
        $sufijo = GastronomiaVentaDetalleSupport::SUFIJO_CONCEPTO_INSUMO;

        return DB::table('articulo_movimiento')
            ->select('venta_emision_id')
            ->selectRaw('MIN(deposito_id) as deposito_id')
            ->whereNotNull('venta_emision_id')
            ->where('concepto', 'like', '%'.$sufijo)
            ->groupBy('venta_emision_id');
    }

    /**
     * Depósito cuando el artículo vendido es el insumo (movimiento con sufijo Ing. en la venta).
     */
    private function subqueryDepositoInsumoPorVentaArticulo(): Builder
    {
        $sufijo = GastronomiaVentaDetalleSupport::SUFIJO_CONCEPTO_INSUMO;

        return DB::table('articulo_movimiento')
            ->select('venta_id', 'articulo_id')
            ->selectRaw('MIN(deposito_id) as deposito_id')
            ->whereNotNull('venta_id')
            ->where('concepto', 'like', '%'.$sufijo)
            ->groupBy('venta_id', 'articulo_id');
    }

    private function aplicarFiltroDeposito(Builder $query, int $depositoId): void
    {
        $query->where(function ($w) use ($depositoId) {
            $w->whereRaw(self::DEPOSITO_EXPR.' = ?', [$depositoId])
                ->orWhereRaw(self::DEPOSITO_INSUMO_EXPR.' = ?', [$depositoId]);
        });
    }

    private function aplicarFiltrosEstructurales(Builder $query, array $filtros): void
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('pv.empresa_id', $empresaId);
        }

        $puntoventaId = (int) ($filtros['puntoventa_id'] ?? 0);
        if ($puntoventaId > 0) {
            $query->where('v.puntoventa_id', $puntoventaId);
        }

        $depositoId = (int) ($filtros['deposito_id'] ?? 0);
        if ($depositoId > 0) {
            $this->aplicarFiltroDeposito($query, $depositoId);
        }

        $jornadaId = (int) ($filtros['jornada_id'] ?? 0);
        if ($jornadaId > 0) {
            $jornada = JornadaGastronomia::query()->find($jornadaId);
            if ($jornada !== null) {
                $query->whereDate('v.fechajornada', $jornada->fecha_jornada);
                if ($empresaId <= 0) {
                    $query->where('pv.empresa_id', (int) $jornada->empresa_id);
                }
            }
        } else {
            [$desde, $hasta] = GastronomiaArticulosVendidosListadoFiltros::normalizarRangoFechas(
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
    }

    private function aplicarFiltrosTexto(Builder $query, array $filtros): void
    {
        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = (string) ($filtros['operador'] ?? 'contiene');
        $modo = (string) ($filtros['modo'] ?? GastronomiaArticulosVendidosListadoFiltros::MODO_TODOS);

        if ($valor === '' && $operador !== 'vacio') {
            return;
        }

        if ($modo === GastronomiaArticulosVendidosListadoFiltros::MODO_CAMPO) {
            $campo = (string) ($filtros['campo'] ?? 'descripcion');
            $this->aplicarEnCampo($query, $campo, $operador, $valor);

            return;
        }

        $query->where(function ($w) use ($operador, $valor) {
            foreach (array_keys(GastronomiaArticulosVendidosListadoFiltros::CAMPOS) as $campo) {
                $w->orWhere(function ($sub) use ($campo, $operador, $valor) {
                    $this->aplicarEnCampo($sub, $campo, $operador, $valor);
                });
            }
        });
    }

    private function aplicarEnCampo(Builder $query, string $campo, string $operador, string $valor): void
    {
        $columna = match ($campo) {
            'sku' => 'a.sku',
            'descripcion' => 'a.descripcion',
            'deposito' => "TRIM(CONCAT(
                COALESCE(d.codigo, ''), ' ', COALESCE(d.nombre, ''),
                CASE WHEN ".self::DEPOSITO_INSUMO_JOIN." IS NOT NULL
                    AND ".self::DEPOSITO_INSUMO_JOIN." <> COALESCE(".self::DEPOSITO_EXPR.", 0)
                    THEN CONCAT(' ins. ', COALESCE(d_ing.codigo, ''), ' ', COALESCE(d_ing.nombre, ''))
                    ELSE '' END
            ))",
            'puntoventa' => "TRIM(CONCAT(COALESCE(pv.codigo, ''), ' ', COALESCE(pv.nombre, '')))",
            default => 'a.descripcion',
        };

        if ($operador === 'vacio') {
            $query->whereRaw('('.$columna.') IS NULL OR TRIM(('.$columna.')) = \'\'');

            return;
        }

        if ($valor === '') {
            return;
        }

        $expr = 'LOWER('.$columna.')';
        $bus = Str::lower($valor);

        if ($operador === 'igual') {
            $query->whereRaw($expr.' = ?', [$bus]);

            return;
        }

        if ($operador === 'distinto') {
            $query->whereRaw($expr.' <> ?', [$bus]);

            return;
        }

        $like = $this->patronLike($operador, $valor);

        $query->where(function ($w) use ($expr, $like, $operador, $valor, $columna) {
            $w->whereRaw($expr.' LIKE ?', [Str::lower($like)]);

            if ($operador === 'contiene' && in_array($columna, ['a.sku', 'a.descripcion'], true)) {
                $this->aplicarCoincidenciaFlexibleRaw($w, $columna, $valor);
            }
        });
    }

    private function aplicarCoincidenciaFlexibleRaw(Builder $query, string $columna, string $valor): void
    {
        $min = $columna === 'a.sku'
            ? CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
            : CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT;

        if (mb_strlen($valor) < $min) {
            return;
        }

        $pref = mb_strtolower(mb_substr($valor, 0, 3));
        $longitudSufijo = mb_strlen($valor) >= 8 ? 5 : 4;
        $suf = mb_strtolower(mb_substr($valor, -$longitudSufijo));

        if ($pref === '' || $suf === '' || $pref === $suf) {
            return;
        }

        $expr = 'LOWER('.$columna.')';
        $query->orWhere(function ($w) use ($expr, $pref, $suf) {
            $w->whereRaw($expr.' LIKE ?', ['%'.CoincidenciaFlexibleTexto::escapeLike($pref).'%'])
                ->whereRaw($expr.' LIKE ?', ['%'.CoincidenciaFlexibleTexto::escapeLike($suf).'%']);
        });
    }

    private function patronLike(string $operador, string $valor): string
    {
        $esc = addcslashes($valor, '%_\\');

        return match ($operador) {
            'empieza' => $esc.'%',
            'termina' => '%'.$esc,
            'igual' => $esc,
            'distinto' => $esc,
            default => '%'.$esc.'%',
        };
    }

    private function enriquecerFila(object $row): object
    {
        $venta = trim((string) ($row->deposito_codigo ?? '').' '.(string) ($row->deposito_nombre ?? ''));
        $insumos = trim((string) ($row->deposito_insumos_codigo ?? '').' '.(string) ($row->deposito_insumos_nombre ?? ''));
        $depVentaId = (int) ($row->deposito_id ?? 0);
        $depInsumosId = (int) ($row->deposito_insumos_id ?? 0);

        $row->deposito_venta_etiqueta = $venta;
        $row->deposito_insumos_etiqueta = ($depInsumosId > 0 && $insumos !== '' && $depInsumosId !== $depVentaId)
            ? $insumos
            : '';
        $row->deposito_etiqueta = $this->etiquetaDepositoFila($row);
        $row->puntoventa_etiqueta = trim(
            (string) ($row->pv_codigo ?? '').' '.(string) ($row->pv_nombre ?? '')
        );
        $row->cantidad_total = round((float) ($row->cantidad_total ?? 0), 4);
        $row->importe_total = round((float) ($row->importe_total ?? 0), 2);

        return $row;
    }

    private function etiquetaDepositoFila(object $row): string
    {
        $venta = trim((string) ($row->deposito_codigo ?? '').' '.(string) ($row->deposito_nombre ?? ''));
        $insumos = trim((string) ($row->deposito_insumos_codigo ?? '').' '.(string) ($row->deposito_insumos_nombre ?? ''));
        $depVentaId = (int) ($row->deposito_id ?? $row->deposito_resuelto_id ?? 0);
        $depInsumosId = (int) ($row->deposito_insumos_id ?? 0);

        if ($depInsumosId > 0 && $insumos !== '') {
            if ($depVentaId <= 0 || $depInsumosId !== $depVentaId) {
                return $venta !== '' ? $venta.' / ins. '.$insumos : $insumos;
            }
        }

        return $venta !== '' ? $venta : $insumos;
    }
}
