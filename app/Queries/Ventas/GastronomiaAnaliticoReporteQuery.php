<?php

namespace App\Queries\Ventas;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Stock\ArticuloUsoDescartableSupport;
use App\Support\Stock\ArticuloUsoInsumoSupport;
use App\Support\Ventas\GastronomiaAnaliticoReporteFiltros;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Líneas de venta gastronomía desde tablas ERP (sin Anita bridge).
 */
final class GastronomiaAnaliticoReporteQuery
{
    private static function cantidadExpr(): string
    {
        return GastronomiaVentaComprobanteSignoSupport::sqlCantidadLineaVenta();
    }

    private static function importeExpr(): string
    {
        return GastronomiaVentaComprobanteSignoSupport::sqlImporteLineaVenta();
    }

    private static function tipoVentaExpr(): string
    {
        return "CASE
            WHEN cg.descuento_gastronomia_id IS NULL THEN 'venta'
            ELSE 'invitacion'
        END";
    }

    private static function clienteExpr(): string
    {
        return "COALESCE(
            NULLIF(TRIM(cli_fact.nombre), ''),
            NULLIF(TRIM(cli_int.nombre), ''),
            NULLIF(TRIM(CONCAT(COALESCE(cv.nombre, ''), ' ', COALESCE(cv.apellido, ''))), ''),
            ''
        )";
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, object>|Collection<int, object>
     */
    public function listado(array $filtros, bool $paginar = true, int $perPage = 25): LengthAwarePaginator|Collection
    {
        $query = $this->queryBase($filtros)
            ->orderByDesc('v.fechajornada')
            ->orderByDesc('v.id')
            ->orderBy('ve.id');

        if ($paginar) {
            return $query->paginate(max(10, min(200, $perPage)));
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   cantidad_filas: int,
     *   cantidad_total: float,
     *   total_importe: float
     * }
     */
    public function totales(array $filtros): array
    {
        $cantidad = self::cantidadExpr();
        $importe = self::importeExpr();

        $row = $this->queryBaseSinSelect($filtros)
            ->selectRaw('COUNT(*) as cantidad_filas')
            ->selectRaw("COALESCE(SUM({$cantidad}), 0) as cantidad_total")
            ->selectRaw("COALESCE(SUM({$importe}), 0) as total_importe")
            ->first();

        return [
            'cantidad_filas' => (int) ($row->cantidad_filas ?? 0),
            'cantidad_total' => round((float) ($row->cantidad_total ?? 0), 4),
            'total_importe' => round((float) ($row->total_importe ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryBase(array $filtros): Builder
    {
        $cantidad = self::cantidadExpr();
        $importe = self::importeExpr();
        $tipoVenta = self::tipoVentaExpr();
        $cliente = self::clienteExpr();

        return $this->queryBaseSinSelect($filtros)->select([
            've.id',
            've.articulo_id',
            've.precio as precio_unitario',
            'v.id as venta_id',
            'v.fechajornada',
            'v.fecha as fecha_real',
            'v.created_at as venta_created_at',
            'v.numerocomprobante as numero_comprobante',
            'v.codigo as venta_codigo',
            'v.cliente_id',
            'tt.id as tipotransaccion_id',
            'tt.abreviatura as tipo_comprobante',
            'tt.nombre as tipo_comprobante_nombre',
            'pv.id as puntoventa_id',
            'pv.codigo as punto_venta',
            'pv.nombre as punto_venta_nombre',
            'e.id as empresa_id',
            'e.nombre as sala',
            'e.nombre as nombreempresa',
            'm.id as mozo_id',
            'm.nombre as nombre_mozo',
            'm.codigo as legajo_mozo',
            'a.sku as codigo_articulo',
            'a.descripcion as descripcion_articulo',
            'a.categoria_id',
            'cat.nombre as categoria_articulo',
            'dg.id as descuento_gastronomia_id',
            'dg.nombre as tipo_descuento',
            'cg.cliente_interno_descuento_id',
            'cg.cliente_vip_gastronomia_id',
        ])
            ->selectRaw("{$cantidad} as cantidad")
            ->selectRaw("{$importe} as total")
            ->selectRaw("{$tipoVenta} as tipo_venta")
            ->selectRaw("{$cliente} as cliente");
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryBaseSinSelect(array $filtros): Builder
    {
        $query = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('empresa as e', 'e.id', '=', 'pv.empresa_id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->leftJoin('categoria as cat', 'cat.id', '=', 'a.categoria_id')
            ->leftJoin('cuenta_gastronomia as cg', 'cg.id', '=', 'vge.cuenta_gastronomia_id')
            ->leftJoin('descuento_gastronomia as dg', 'dg.id', '=', 'cg.descuento_gastronomia_id')
            ->leftJoin('mozo_gastronomia as m', 'm.id', '=', 'cg.mozo_gastronomia_id')
            ->leftJoin('cliente as cli_fact', 'cli_fact.id', '=', 'v.cliente_id')
            ->leftJoin('cliente as cli_int', 'cli_int.id', '=', 'cg.cliente_interno_descuento_id')
            ->leftJoin('cliente_vip_gastronomia as cv', 'cv.id', '=', 'cg.cliente_vip_gastronomia_id')
            ->whereNull('v.deleted_at')
            ->whereNull('vge.venta_factura_origen_id');

        $this->aplicarExclusionInsumosYDescartables($query);
        $this->aplicarFiltrosEstructurales($query, $filtros);
        $this->aplicarFiltrosTexto($query, $filtros);

        return $query;
    }

    private function aplicarExclusionInsumosYDescartables(Builder $query): void
    {
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
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosEstructurales(Builder $query, array $filtros): void
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('pv.empresa_id', $empresaId);
        }

        [$desde, $hasta] = GastronomiaAnaliticoReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        if ($desde !== '') {
            $query->whereDate('v.fechajornada', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate('v.fechajornada', '<=', $hasta);
        }

        $tipoVenta = trim((string) ($filtros['tipo_venta'] ?? ''));
        if ($tipoVenta === GastronomiaAnaliticoReporteFiltros::TIPO_VENTA_VENTA) {
            $query->whereNull('cg.descuento_gastronomia_id');
        } elseif ($tipoVenta === GastronomiaAnaliticoReporteFiltros::TIPO_VENTA_INVITACION) {
            $query->whereNotNull('cg.descuento_gastronomia_id');
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosTexto(Builder $query, array $filtros): void
    {
        $operador = (string) ($filtros['operador'] ?? 'contiene');
        $valor = trim((string) ($filtros['valor'] ?? ''));
        $modo = (string) ($filtros['modo'] ?? GastronomiaAnaliticoReporteFiltros::MODO_TODOS);

        if ($operador !== 'vacio' && $valor === '') {
            return;
        }

        if ($modo === GastronomiaAnaliticoReporteFiltros::MODO_CAMPO) {
            $campo = (string) ($filtros['campo'] ?? 'descripcion_articulo');
            $this->aplicarEnCampo($query, $campo, $operador, $valor);

            return;
        }

        $query->where(function (Builder $w) use ($operador, $valor) {
            foreach (array_keys(GastronomiaAnaliticoReporteFiltros::CAMPOS) as $campo) {
                $w->orWhere(function (Builder $sub) use ($campo, $operador, $valor) {
                    $this->aplicarEnCampo($sub, $campo, $operador, $valor);
                });
            }
        });
    }

    private function aplicarEnCampo(Builder $query, string $campo, string $operador, string $valor): void
    {
        $columna = match ($campo) {
            'codigo_articulo' => 'a.sku',
            'descripcion_articulo' => 'a.descripcion',
            'categoria_articulo' => 'cat.nombre',
            'nombre_mozo' => 'm.nombre',
            'legajo_mozo' => 'm.codigo',
            'tipo_comprobante' => "COALESCE(tt.abreviatura, tt.nombre)",
            'numero_comprobante' => "CAST(v.numerocomprobante AS CHAR)",
            'punto_venta' => 'pv.codigo',
            'cliente' => self::clienteExpr(),
            'tipo_descuento' => 'dg.nombre',
            'tipo_venta' => self::tipoVentaExpr(),
            'sala' => 'e.nombre',
            default => 'a.descripcion',
        };

        if ($operador === 'vacio') {
            $query->whereRaw('('.$columna.') IS NULL OR TRIM(CAST(('.$columna.') AS CHAR)) = \'\'');

            return;
        }

        if ($valor === '') {
            return;
        }

        $expr = 'LOWER(CAST(('.$columna.') AS CHAR))';
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

        $query->where(function (Builder $w) use ($expr, $like, $operador, $valor, $campo, $columna) {
            $w->whereRaw($expr.' LIKE ?', [Str::lower($like)]);

            if ($operador === 'contiene'
                && in_array($campo, GastronomiaAnaliticoReporteFiltros::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)
            ) {
                $this->aplicarCoincidenciaFlexibleRaw($w, $columna, $valor, $campo);
            }
        });
    }

    private function aplicarCoincidenciaFlexibleRaw(
        Builder $query,
        string $columna,
        string $valor,
        string $campo,
    ): void {
        $min = $campo === 'codigo_articulo'
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

        $expr = 'LOWER(CAST(('.$columna.') AS CHAR))';
        $query->orWhere(function (Builder $w) use ($expr, $pref, $suf) {
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
            default => '%'.$esc.'%',
        };
    }
}
