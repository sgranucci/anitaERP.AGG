<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Ventas\Canal;
use App\Models\Ventas\LocalVenta;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Movimientos de stock Ferli: grilla calzado vs insumos; canal según depósito.
 *
 * - Depósito ligado a local_venta → catálogo LOCAL (tiendas).
 * - Resto (Fábrica, Junín, Tal-*, etc.) → catálogo FÁBRICA (importaciones, insumos).
 * - TRA fábrica→local / local→fábrica: manda el depósito de origen (de dónde se saca).
 * - Calzado: combinaciones activas del canal, o activas en el otro canal (estado legacy).
 * - Insumos: sin combinaciones, igual seleccionables.
 * - Si el depósito tiene saldo distinto de cero, el artículo entra igual (ajuste de colores inactivos).
 */
final class MovimientoStockFerliSupport
{
    public static function esCalzadosFerli(): bool
    {
        return EntornoEmpresaSupport::esFerli();
    }

    public static function usaCanales(): bool
    {
        return self::esCalzadosFerli() && ArticuloEstadoCanalSupport::uiFerliActiva();
    }

    /**
     * Depósito operativo del formulario: origen en TRA, o depósito simple.
     */
    public static function depositoOperativoDesdeRequest(array $input): int
    {
        $salida = (int) ($input['deposito_salida_id'] ?? 0);
        if ($salida > 0) {
            return $salida;
        }

        return (int) ($input['deposito_id'] ?? 0);
    }

    public static function depositoEsDeLocalVenta(int $depositoId): bool
    {
        if ($depositoId <= 0 || ! Schema::hasTable('local_venta')) {
            return false;
        }

        return LocalVenta::query()
            ->where('deposito_id', $depositoId)
            ->where('activo', true)
            ->exists();
    }

    /**
     * Ámbito de artículos/combinaciones según depósito operativo.
     * Sin depósito aún: FÁBRICA (planta / importaciones / insumos por defecto).
     */
    public static function ambitoCatalogo(?int $depositoId = null): string
    {
        if (! self::usaCanales()) {
            return CombinacionEstadoCanalSupport::AMBITO_FABRICA;
        }

        if ($depositoId !== null && $depositoId > 0 && self::depositoEsDeLocalVenta($depositoId)) {
            return CombinacionEstadoCanalSupport::AMBITO_LOCAL;
        }

        return CombinacionEstadoCanalSupport::AMBITO_FABRICA;
    }

    public static function usaCatalogoLocal(?int $depositoId = null): bool
    {
        return self::ambitoCatalogo($depositoId) === CombinacionEstadoCanalSupport::AMBITO_LOCAL;
    }

    public static function codigoCanal(?int $depositoId = null): string
    {
        return self::usaCatalogoLocal($depositoId)
            ? Canal::CODIGO_LOCAL
            : Canal::CODIGO_FABRICA;
    }

    /**
     * Query de artículos elegibles en el canal del depósito:
     * activos en el estado del canal (en fábrica, sin exigir el pivote de canal)
     * + (combinación activa en canal OR color activo en el otro canal
     * OR sin ninguna combinación = insumo OR saldo distinto de cero en ese depósito).
     * Fuera de Ferli: solo activos operativos (sin filtro de canal/combinación).
     *
     * @param  Builder<Articulo>  $query
     * @return Builder<Articulo>
     */
    public static function aplicarFiltroCatalogo(Builder $query, ?int $depositoId = null): Builder
    {
        if (! self::usaCanales()) {
            return ArticuloSeleccionOperativaSupport::aplicarSoloActivosTablaArticulo($query);
        }

        $ambito = self::ambitoCatalogo($depositoId);
        $codigoCanal = self::codigoCanal($depositoId);

        // En movimientos no exigimos el pivote articulo_canal. Hay calzado de local
        // (GOBBY 11) con estado_fabrica ACTIVO y colores activos, pero el canal
        // cargado es solo LOCAL: el alta en Tal-E2 / Fábrica tiene que poder tomarlo.
        // Pedidos, POS y facturas siguen usando aplicarSoloActivosEnCanal.
        if ($codigoCanal === Canal::CODIGO_LOCAL) {
            ArticuloEstadoCanalSupport::aplicarSoloActivosEnCanal($query, $codigoCanal);
        } else {
            $query->where('articulo.estado', ArticuloSeleccionOperativaSupport::ESTADO_ACTIVO);
            if (ArticuloEstadoCanalSupport::columnasEstadoDisponibles()) {
                $query->where('articulo.estado_fabrica', ArticuloEstadoCanalSupport::ESTADO_ACTIVO);
            }
        }

        $depositoSaldo = ($depositoId !== null && $depositoId > 0) ? $depositoId : 0;

        return $query->where(function ($q) use ($ambito, $depositoSaldo) {
            $q->whereExists(function ($sub) use ($ambito) {
                $sub->selectRaw('1')
                    ->from('combinacion')
                    ->whereRaw(
                        'combinacion.articulo_id = articulo.id and '
                        .CombinacionEstadoCanalSupport::sqlColumnaActiva($ambito)
                    );
            })->orWhereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('combinacion')
                    ->whereRaw('combinacion.articulo_id = articulo.id');
            });
            if ($depositoSaldo > 0) {
                $q->orWhereExists(function ($sub) use ($depositoSaldo) {
                    $sub->from('articulo_movimiento')
                        ->select('articulo_movimiento.articulo_id')
                        ->whereColumn('articulo_movimiento.articulo_id', 'articulo.id')
                        ->where('articulo_movimiento.deposito_id', $depositoSaldo)
                        ->groupBy('articulo_movimiento.articulo_id')
                        ->havingRaw('SUM(articulo_movimiento.cantidad) <> 0');
                });
            }
            // Alta en planta/talles (Tal-E2, etc.): el artículo sigue en el canal,
            // pero el color solo está activo en el otro (estado legacy A).
            $q->orWhereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('combinacion')
                    ->whereColumn('combinacion.articulo_id', 'articulo.id')
                    ->where('combinacion.estado', CombinacionEstadoCanalSupport::ESTADO_ACTIVO);
            });
        });
    }

    /**
     * Listado compacto para el select de marca / data-articulo.
     * Solo activos del canal del depósito (calzado con comb. activa + insumos).
     *
     * @return Collection<int, object{id:int,sku:string,descripcion:string,mventa_id:?int}>
     */
    public static function listadoParaSelector(?int $depositoId = null, array $idsExtra = []): Collection
    {
        $idsExtra = array_values(array_filter(array_map('intval', $idsExtra)));

        $query = Articulo::query()
            ->select('id', 'sku', 'descripcion', 'mventa_id')
            ->orderBy('descripcion', 'ASC')
            ->where(function ($q) use ($depositoId, $idsExtra) {
                $q->where(function ($qActivos) use ($depositoId) {
                    self::aplicarFiltroCatalogo($qActivos, $depositoId);
                });
                if ($idsExtra !== []) {
                    $q->orWhereIn('id', $idsExtra);
                }
            });

        return $query->get();
    }

    /**
     * Listado amplio para el flag «Todos los artículos» (checkbox A):
     * activos operativos sin filtro de canal/combinación.
     *
     * @return Collection<int, object{id:int,sku:string,descripcion:string,mventa_id:?int}>
     */
    public static function listadoParaSelectorTodos(array $idsExtra = []): Collection
    {
        $idsExtra = array_values(array_filter(array_map('intval', $idsExtra)));

        $query = Articulo::query()
            ->select('id', 'sku', 'descripcion', 'mventa_id')
            ->orderBy('descripcion', 'ASC')
            ->where(function ($q) use ($idsExtra) {
                $q->where(function ($qActivos) {
                    ArticuloSeleccionOperativaSupport::aplicarSoloActivosTablaArticulo($qActivos);
                });
                if ($idsExtra !== []) {
                    $q->orWhereIn('id', $idsExtra);
                }
            });

        return $query->get();
    }
}
