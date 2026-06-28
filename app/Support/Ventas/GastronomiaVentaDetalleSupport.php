<?php

namespace App\Support\Ventas;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cobranza;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use Illuminate\Support\Collection;

/**
 * Detalle de factura gastronomía: cobranzas directas e insumos descontados por venta.
 */
final class GastronomiaVentaDetalleSupport
{
    public const SUFIJO_CONCEPTO_INSUMO = ' - Insumo';

    /** @deprecated Solo lectura de movimientos grabados antes del cambio de etiqueta. */
    public const SUFIJO_CONCEPTO_INSUMO_LEGACY = ' — Ing.';

    public static function conceptoEsMovimientoInsumo(string $concepto): bool
    {
        return str_contains($concepto, self::SUFIJO_CONCEPTO_INSUMO)
            || str_contains($concepto, self::SUFIJO_CONCEPTO_INSUMO_LEGACY);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function aplicarWhereConceptoEsInsumo($query, string $column = 'concepto'): void
    {
        $query->where(function ($q) use ($column) {
            $q->where($column, 'like', '%'.self::SUFIJO_CONCEPTO_INSUMO)
                ->orWhere($column, 'like', '%'.self::SUFIJO_CONCEPTO_INSUMO_LEGACY);
        });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function aplicarWhereConceptoNoEsInsumo($query, string $column = 'concepto'): void
    {
        $query->where($column, 'not like', '%'.self::SUFIJO_CONCEPTO_INSUMO)
            ->where($column, 'not like', '%'.self::SUFIJO_CONCEPTO_INSUMO_LEGACY);
    }

    /**
     * Cobranzas vinculadas a la venta (venta_id directo o vía movimiento de caja).
     *
     * @return Collection<int, Cobranza>
     */
    public static function cobranzasDeVenta(Venta $venta): Collection
    {
        $venta->loadMissing(['cobranzasDirectas', 'caja_movimientos.cobranzas']);

        if ($venta->relationLoaded('cobranzasDirectas')) {
            $porVentaId = $venta->cobranzasDirectas;
        } elseif ((int) $venta->id > 0) {
            $porVentaId = Cobranza::query()->where('venta_id', $venta->id)->get();
        } else {
            $porVentaId = collect();
        }

        $porCaja = collect();
        foreach ($venta->caja_movimientos as $mov) {
            if ($mov->cobranzas) {
                $porCaja->push($mov->cobranzas);
            }
        }

        return $porVentaId
            ->merge($porCaja)
            ->unique('id')
            ->sortByDesc('id')
            ->values();
    }

    /**
     * Medios de cobro por cobranza (cuentas de caja del movimiento asociado).
     *
     * @param  Collection<int, Cobranza>  $cobranzas
     * @return array<int, list<object{cuenta:string,moneda:string,monto:float}>>
     */
    public static function mediosPagoPorCobranza(Collection $cobranzas): array
    {
        if ($cobranzas->isEmpty()) {
            return [];
        }

        $movimientos = Caja_Movimiento::query()
            ->whereIn('cobranza_id', $cobranzas->pluck('id'))
            ->with(['caja_movimiento_cuentacajas.cuentacajas', 'caja_movimiento_cuentacajas.monedas'])
            ->get();

        $out = [];
        foreach ($movimientos as $mov) {
            $lineas = [];
            foreach ($mov->caja_movimiento_cuentacajas as $cc) {
                $lineas[] = (object) [
                    'cuentacaja_id' => (int) ($cc->cuentacaja_id ?? 0),
                    'codigo' => (string) ($cc->cuentacajas->codigo ?? ''),
                    'nombre' => (string) ($cc->cuentacajas->nombre ?? ''),
                    'cuenta' => trim((string) (($cc->cuentacajas->codigo ?? '').' '.($cc->cuentacajas->nombre ?? ''))),
                    'moneda' => (string) ($cc->monedas->abreviatura ?? ''),
                    'monto' => (float) $cc->monto,
                ];
            }
            $out[(int) $mov->cobranza_id] = $lineas;
        }

        return $out;
    }

    /**
     * Ítems facturados con SKU para consulta gastronomía (incluye facturas previas sin articulo_id en emisión).
     *
     * @return Collection<int, object{articulo_id:int,sku:string,detalle:string,cantidad:float,precio:float}>
     */
    public static function itemsFacturadosParaDetalle(Venta $venta, ?CuentaGastronomia $cuenta = null): Collection
    {
        $cuenta?->loadMissing(['lineas.articulo']);
        $lineas = $cuenta?->lineas ?? collect();
        $lineaPorDescripcion = $lineas->filter(fn ($l) => $l->articulo)
            ->keyBy(fn ($l) => mb_strtolower(trim((string) $l->articulo->descripcion)));

        $lineaIdx = 0;

        return $venta->venta_emisiones
            ->sortBy('numeroitem')
            ->values()
            ->map(function (Venta_Emision $em) use ($lineas, $lineaPorDescripcion, &$lineaIdx) {
                $linea = null;
                $sku = $em->articulos?->sku;
                $articuloId = (int) ($em->articulo_id ?? 0);
                if (! $sku && $articuloId > 0) {
                    $sku = Articulo::query()->whereKey($articuloId)->value('sku');
                }
                if (! $sku) {
                    $detNorm = mb_strtolower(trim((string) $em->detalle));
                    $linea = $lineaPorDescripcion->get($detNorm);
                    if (! $linea && $lineaIdx < $lineas->count()) {
                        $linea = $lineas[$lineaIdx];
                        $lineaIdx++;
                    }
                    $sku = $linea?->articulo?->sku;
                    if ($articuloId <= 0 && $linea) {
                        $articuloId = (int) ($linea->articulo_id ?? 0);
                    }
                }

                return (object) [
                    'venta_emision_id' => (int) $em->id,
                    'articulo_id' => $articuloId,
                    'sku' => $sku ? (string) $sku : '—',
                    'detalle' => (string) ($em->articulos?->descripcion ?? $em->detalle ?? '—'),
                    'cantidad' => (float) $em->cantidad,
                    'precio' => (float) $em->precio,
                ];
            });
    }

    /**
     * Ítems facturados con insumos asociados (por venta_emision_id).
     *
     * @return Collection<int, object{
     *   venta_emision_id:int,
     *   articulo_id:int,
     *   sku:string,
     *   detalle:string,
     *   cantidad:float,
     *   precio:float,
     *   insumos:Collection<int, Articulo_Movimiento>
     * }>
     */
    public static function itemsFacturadosConInsumos(Venta $venta, ?CuentaGastronomia $cuenta = null): Collection
    {
        $items = self::itemsFacturadosParaDetalle($venta, $cuenta);
        $movimientos = self::movimientosStockVenta((int) $venta->id);

        return $items->map(function ($item) use ($movimientos) {
            $emId = (int) ($item->venta_emision_id ?? 0);
            $insumos = $emId > 0
                ? $movimientos->filter(
                    fn (Articulo_Movimiento $m) => (int) ($m->venta_emision_id ?? 0) === $emId
                        && self::esMovimientoInsumo($m),
                )->values()
                : collect();

            return (object) [
                'venta_emision_id' => $emId,
                'articulo_id' => (int) $item->articulo_id,
                'sku' => $item->sku,
                'detalle' => $item->detalle,
                'cantidad' => $item->cantidad,
                'precio' => $item->precio,
                'insumos' => $insumos,
            ];
        });
    }

    /**
     * Movimientos de stock de la venta gastronómica (ítems facturados e insumos).
     *
     * @return Collection<int, Articulo_Movimiento>
     */
    public static function movimientosStockVenta(int $ventaId): Collection
    {
        if ($ventaId <= 0) {
            return collect();
        }

        return Articulo_Movimiento::query()
            ->with(['articulos', 'depositos', 'venta_emisiones'])
            ->where('venta_id', $ventaId)
            ->whereNotNull('venta_emision_id')
            ->orderBy('venta_emision_id')
            ->orderBy('deposito_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * Movimientos de stock por consumo de fórmula (insumos).
     *
     * @return Collection<int, Articulo_Movimiento>
     */
    public static function movimientosInsumos(int $ventaId): Collection
    {
        $conEmision = self::movimientosStockVenta($ventaId)
            ->filter(fn (Articulo_Movimiento $m) => self::esMovimientoInsumo($m))
            ->values();

        if ($conEmision->isNotEmpty()) {
            return $conEmision;
        }

        // Facturas anteriores sin venta_emision_id en movimientos.
        if ($ventaId <= 0) {
            return collect();
        }

        return Articulo_Movimiento::query()
            ->with(['articulos', 'depositos', 'venta_emisiones.articulos'])
            ->where('venta_id', $ventaId)
            ->tap(fn ($q) => self::aplicarWhereConceptoEsInsumo($q))
            ->orderBy('deposito_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * Salidas del artículo facturado (no insumos de fórmula).
     *
     * @return Collection<int, Articulo_Movimiento>
     */
    public static function movimientosItemsFacturados(int $ventaId): Collection
    {
        return self::movimientosStockVenta($ventaId)
            ->reject(fn (Articulo_Movimiento $m) => self::esMovimientoInsumo($m))
            ->values();
    }

    /**
     * Insumos descontados para un ítem de factura (línea padre).
     *
     * @return Collection<int, Articulo_Movimiento>
     */
    public static function movimientosInsumosPorVentaEmision(int $ventaEmisionId): Collection
    {
        if ($ventaEmisionId <= 0) {
            return collect();
        }

        return Articulo_Movimiento::query()
            ->with(['articulos', 'depositos'])
            ->where('venta_emision_id', $ventaEmisionId)
            ->tap(fn ($q) => self::aplicarWhereConceptoEsInsumo($q))
            ->orderBy('id')
            ->get();
    }

    /**
     * Agrupa movimientos de stock por venta_emision_id (ítem facturado + sus insumos).
     *
     * @param  Collection<int, Articulo_Movimiento>  $movimientos
     * @return Collection<int, object{
     *   venta_emision_id:int,
     *   item:object|null,
     *   movimiento_item:Articulo_Movimiento|null,
     *   insumos:Collection<int, Articulo_Movimiento>
     * }>
     */
    public static function agruparPorItemFacturado(
        Collection $movimientos,
        Collection $itemsFacturados,
    ): Collection {
        $porEmision = $movimientos->groupBy(fn (Articulo_Movimiento $m) => (int) ($m->venta_emision_id ?? 0));

        $itemsPorArticulo = $itemsFacturados->keyBy('articulo_id');

        return $porEmision
            ->filter(fn ($grupo, $emId) => (int) $emId > 0)
            ->map(function (Collection $grupo, int $ventaEmisionId) use ($itemsPorArticulo) {
                $movItem = $grupo->first(fn (Articulo_Movimiento $m) => ! self::esMovimientoInsumo($m));
                $insumos = $grupo->filter(fn (Articulo_Movimiento $m) => self::esMovimientoInsumo($m))->values();
                $articuloId = (int) ($movItem?->articulo_id ?? $insumos->first()?->articulo_id ?? 0);

                return (object) [
                    'venta_emision_id' => $ventaEmisionId,
                    'item' => $itemsPorArticulo->get($articuloId),
                    'movimiento_item' => $movItem,
                    'insumos' => $insumos,
                ];
            })
            ->values();
    }

    public static function esMovimientoInsumo(Articulo_Movimiento $movimiento): bool
    {
        return self::conceptoEsMovimientoInsumo((string) ($movimiento->concepto ?? ''));
    }

    /**
     * @param  Collection<int, Articulo_Movimiento>  $movimientos
     * @return Collection<int, object{deposito_id:int,deposito_codigo:string,deposito_nombre:string,movimientos:Collection}>
     */
    public static function agruparPorDeposito(Collection $movimientos): Collection
    {
        return $movimientos
            ->groupBy(fn (Articulo_Movimiento $m) => (int) ($m->deposito_id ?? 0))
            ->map(function (Collection $grupo, int $depositoId) {
                $primero = $grupo->first();
                $dep = $primero?->depositos;

                return (object) [
                    'deposito_id' => $depositoId,
                    'deposito_codigo' => $dep ? (string) ($dep->codigo ?? '') : (string) $depositoId,
                    'deposito_nombre' => $dep ? (string) ($dep->nombre ?? '') : '—',
                    'movimientos' => $grupo->values(),
                ];
            })
            ->values();
    }

    /**
     * Resuelve artículo por ID numérico o SKU (consulta facturas del día).
     *
     * @return object{id:int,sku:string,descripcion:string}|null
     */
    public static function resolverArticuloFiltro(?int $articuloId, string $skuOBusqueda): ?object
    {
        if ($articuloId > 0) {
            $art = Articulo::query()->find($articuloId);
            if ($art) {
                return self::articuloFiltroDto($art);
            }
        }

        $texto = trim($skuOBusqueda);
        if ($texto === '') {
            return null;
        }

        if (ctype_digit($texto)) {
            $art = Articulo::query()->find((int) $texto);
            if ($art) {
                return self::articuloFiltroDto($art);
            }
        }

        $art = Articulo::query()->where('sku', $texto)->first();
        if ($art) {
            return self::articuloFiltroDto($art);
        }

        $like = '%'.addcslashes($texto, '%_\\').'%';

        return Articulo::query()
            ->where('sku', 'like', $like)
            ->orWhere('descripcion', 'like', $like)
            ->orderBy('sku')
            ->limit(1)
            ->get()
            ->map(fn (Articulo $a) => self::articuloFiltroDto($a))
            ->first();
    }

    /**
     * Insumos descontados por venta, para un artículo padre facturado (listado del día).
     *
     * @param  list<int>|Collection<int, int>  $ventaIds
     * @return array<int, Collection<int, Articulo_Movimiento>> venta_id => insumos
     */
    public static function mapInsumosPorVentaYArticuloPadre(array|Collection $ventaIds, int $articuloPadreId): array
    {
        $ids = $ventaIds instanceof Collection
            ? $ventaIds->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all()
            : array_values(array_filter(array_map('intval', $ventaIds), fn ($id) => $id > 0));

        if ($ids === [] || $articuloPadreId <= 0) {
            return [];
        }

        $movimientos = Articulo_Movimiento::query()
            ->with(['articulos', 'depositos', 'venta_emisiones'])
            ->whereIn('venta_id', $ids)
            ->tap(fn ($q) => self::aplicarWhereConceptoEsInsumo($q))
            ->where(function ($q) use ($articuloPadreId) {
                $q->whereHas(
                    'venta_emisiones',
                    fn ($e) => $e->where('articulo_id', $articuloPadreId),
                )->orWhere(function ($q2) use ($articuloPadreId, $ids) {
                    // Legacy: movimientos sin venta_emision_id pero venta con una sola línea del padre.
                    $q2->whereNull('venta_emision_id')
                        ->whereIn('venta_id', $ids)
                        ->whereIn('venta_id', function ($sub) use ($articuloPadreId) {
                            $sub->select('venta_id')
                                ->from('venta_emision')
                                ->where('articulo_id', $articuloPadreId)
                                ->groupBy('venta_id')
                                ->havingRaw('COUNT(*) = 1');
                        });
                });
            })
            ->orderBy('venta_id')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($movimientos->groupBy('venta_id') as $ventaId => $grupo) {
            $out[(int) $ventaId] = $grupo->values();
        }

        return $out;
    }

    /**
     * Cantidad facturada del artículo padre en una venta (suma venta_emision).
     */
    public static function cantidadItemFacturadoEnVenta(int $ventaId, int $articuloPadreId): float
    {
        if ($ventaId <= 0 || $articuloPadreId <= 0) {
            return 0.;
        }

        return (float) Venta_Emision::query()
            ->where('venta_id', $ventaId)
            ->where('articulo_id', $articuloPadreId)
            ->sum('cantidad');
    }

    private static function articuloFiltroDto(Articulo $art): object
    {
        return (object) [
            'id' => (int) $art->id,
            'sku' => (string) ($art->sku ?? ''),
            'descripcion' => (string) ($art->descripcion ?? ''),
        ];
    }

    /**
     * @deprecated Use {@see GastronomiaDepositoConfigSupport::depositoVentaDto()}
     */
    public static function depositoVentaGastronomia(?\App\Models\Ventas\ConfiguracionPuntoventaGastronomia $cfg = null): ?object
    {
        return GastronomiaDepositoConfigSupport::depositoVentaDto($cfg);
    }
}
