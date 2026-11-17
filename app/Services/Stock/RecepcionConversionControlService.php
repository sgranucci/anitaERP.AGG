<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Support\Stock\RecepcionConversionControlFiltros;
use App\Support\Stock\RecepcionProveedorConversionSupport;
use App\Support\Stock\RecepcionProveedorDepositoSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\RecepcionProveedorVisibilidadSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class RecepcionConversionControlService
{
    /** @var array<int, Articulo> */
    private array $cacheInsumoCargado = [];

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array{filas: list<array<string, mixed>>, totales: array<string, mixed>}
     */
    public function generar(array $filtros, $empresaQuery): array
    {
        RecepcionProveedorDepositoSupport::reiniciarCache();
        $this->cacheInsumoCargado = [];

        $lineas = $this->consultarLineas($filtros);
        $filasDatos = [];
        $totales = [
            'lineas' => 0,
            'con_insumo' => 0,
            'convertidas_tra' => 0,
            'teoricas' => 0,
            'sin_insumo' => 0,
        ];

        foreach ($lineas as $row) {
            $fila = $this->mapearLinea($row);
            if (! empty($filtros['solo_con_insumo']) && empty($fila['tiene_insumo'])) {
                continue;
            }
            $filasDatos[] = $fila;
            $totales['lineas']++;
            if (! empty($fila['tiene_insumo'])) {
                $totales['con_insumo']++;
                if (($fila['origen_insumo'] ?? '') === 'tra') {
                    $totales['convertidas_tra']++;
                } else {
                    $totales['teoricas']++;
                }
            } else {
                $totales['sin_insumo']++;
            }
        }

        $filas = $this->aplicarConsolidacion($filasDatos, $filtros, $empresaQuery);

        return [
            'filas' => $filas,
            'totales' => $totales,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     */
    public function paginarFilas(array $filas, int $perPage, int $page): LengthAwarePaginator
    {
        $perPage = max(10, min(200, $perPage));
        $page = max(1, $page);
        $total = count($filas);
        $slice = array_slice($filas, ($page - 1) * $perPage, $perPage);

        return new Paginator($slice, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     */
    public function subtituloFiltros(array $filtros, $empresaQuery): string
    {
        $partes = [];
        $partes[] = 'Período '.RecepcionConversionControlFiltros::formatearPeriodoTexto($filtros);

        $ids = RecepcionConversionControlFiltros::empresaIds($filtros);
        if ($ids !== []) {
            $nombres = $empresaQuery->whereIn('id', $ids)->pluck('nombre')->filter()->values();
            if ($nombres->count() === 1) {
                $partes[] = 'Empresa: '.$nombres->first();
            } elseif ($nombres->count() > 1) {
                $partes[] = empty($filtros['consolidar_empresas'])
                    ? 'Empresas (por bloque): '.$nombres->implode(', ')
                    : 'Empresas (consolidadas): '.$nombres->implode(', ');
            }
        }

        if (! empty($filtros['solo_con_insumo'])) {
            $partes[] = 'Solo artículos con insumo asociado';
        }
        if (($filtros['proveedor'] ?? '') !== '') {
            $partes[] = 'Proveedor: '.$filtros['proveedor'];
        }
        if (($filtros['sku'] ?? '') !== '') {
            $partes[] = 'Artículo: '.$filtros['sku'];
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, object>
     */
    private function consultarLineas(array $filtros): Collection
    {
        $query = Recepcion_Proveedor_Articulo::query()
            ->from('recepcion_proveedor_articulo')
            ->join('recepcion_proveedor', 'recepcion_proveedor.id', '=', 'recepcion_proveedor_articulo.recepcion_proveedor_id')
            ->join('empresa', 'empresa.id', '=', 'recepcion_proveedor.empresa_id')
            ->join('proveedor', 'proveedor.id', '=', 'recepcion_proveedor.proveedor_id')
            ->leftJoin('articulo', 'articulo.id', '=', 'recepcion_proveedor_articulo.articulo_id')
            ->leftJoin('articulo as art_stock', 'art_stock.id', '=', 'recepcion_proveedor_articulo.articulo_stock_id')
            ->leftJoin('unidadmedida as um_linea', 'um_linea.id', '=', 'recepcion_proveedor_articulo.unidadmedida_id')
            ->leftJoin('unidadmedida as um_art', 'um_art.id', '=', 'articulo.unidadmedida_id')
            ->leftJoin('unidadmedida as um_insumo', 'um_insumo.id', '=', 'art_stock.unidadmedida_id')
            ->where('recepcion_proveedor.estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('recepcion_proveedor.tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->select([
                'recepcion_proveedor_articulo.id as linea_id',
                'recepcion_proveedor_articulo.articulo_id',
                'recepcion_proveedor_articulo.articulo_stock_id',
                'recepcion_proveedor_articulo.cantidad',
                'recepcion_proveedor_articulo.cantidad_stock',
                'recepcion_proveedor_articulo.precio',
                'recepcion_proveedor_articulo.precio_stock',
                'recepcion_proveedor_articulo.descuento',
                'recepcion_proveedor_articulo.coeficienteconversion',
                'recepcion_proveedor_articulo.peso_bulto',
                'recepcion_proveedor.id as recepcion_id',
                'recepcion_proveedor.numerorecepcion',
                'recepcion_proveedor.fecha',
                'recepcion_proveedor.tipo',
                'recepcion_proveedor.empresa_id',
                'recepcion_proveedor.proveedor_id',
                'empresa.nombre as nombreempresa',
                'proveedor.id as proveedor_pk',
                'proveedor.codigo as codigo_proveedor',
                'proveedor.nombre as nombreproveedor',
                'articulo.sku as sku_compra',
                'articulo.descripcion as descripcion_compra',
                'articulo.skualternativo',
                'articulo.coeficienteconversion as coef_maestro_articulo',
                'articulo.unidadmedida_id as um_stock_articulo_id',
                'art_stock.sku as sku_stock_grabado',
                'art_stock.descripcion as descripcion_stock_grabado',
                'art_stock.coeficienteconversion as coef_maestro_stock',
                'um_linea.abreviatura as um_compra_abrev',
                'um_linea.nombre as um_compra_nombre',
                'um_art.abreviatura as um_stock_abrev',
                'um_art.nombre as um_stock_nombre',
                'um_insumo.abreviatura as um_insumo_grabado_abrev',
                'um_insumo.nombre as um_insumo_grabado_nombre',
            ])
            ->orderBy('recepcion_proveedor.fecha')
            ->orderBy('recepcion_proveedor.numerorecepcion')
            ->orderBy('recepcion_proveedor_articulo.orden')
            ->orderBy('recepcion_proveedor_articulo.id');

        RecepcionProveedorVisibilidadSupport::aplicarFiltroListado($query);
        $this->aplicarFiltros($query, $filtros);

        return collect($query->toBase()->get());
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Stock\Recepcion_Proveedor_Articulo>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros($query, array $filtros): void
    {
        $empresaIds = RecepcionConversionControlFiltros::empresaIds($filtros);
        if ($empresaIds !== []) {
            $query->whereIn('recepcion_proveedor.empresa_id', $empresaIds);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('recepcion_proveedor.fecha', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('recepcion_proveedor.fecha', '<=', $filtros['fecha_hasta']);
        }

        if (! empty($filtros['solo_con_insumo'])) {
            $query->where(function ($q) {
                $q->whereNotNull('recepcion_proveedor_articulo.articulo_stock_id')
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('articulo.skualternativo')
                            ->where('articulo.skualternativo', '!=', '')
                            ->where('articulo.skualternativo', '!=', '0');
                    });
            });
        }

        $proveedor = trim((string) ($filtros['proveedor'] ?? ''));
        if ($proveedor !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $proveedor).'%';
            $query->where(function ($q) use ($like, $proveedor) {
                $q->where('proveedor.codigo', 'like', $like)
                    ->orWhere('proveedor.nombre', 'like', $like);
                if (ctype_digit($proveedor)) {
                    $q->orWhere('proveedor.codigo', $proveedor);
                }
            });
        }

        $sku = trim((string) ($filtros['sku'] ?? ''));
        if ($sku !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $sku).'%';
            $query->where(function ($q) use ($like) {
                $q->where('articulo.sku', 'like', $like)
                    ->orWhere('articulo.descripcion', 'like', $like)
                    ->orWhere('art_stock.sku', 'like', $like);
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearLinea(object $row): array
    {
        $cantidad = (float) ($row->cantidad ?? 0);
        $descuento = (float) ($row->descuento ?? 0);
        $precio = (float) ($row->precio ?? 0);
        $precioNeto = $precio * (1 - ($descuento / 100));
        $coefLinea = (float) ($row->coeficienteconversion ?? 0);
        if ($coefLinea <= 0) {
            $coefLinea = 1.0;
        }

        $umCompra = $this->etiquetaUm($row->um_compra_abrev ?? null, $row->um_compra_nombre ?? null);
        $umStockArt = $this->etiquetaUm($row->um_stock_abrev ?? null, $row->um_stock_nombre ?? null);

        $fecha = '';
        if (! empty($row->fecha)) {
            try {
                $fecha = \Carbon\Carbon::parse($row->fecha)->format('Y-m-d');
            } catch (\Throwable) {
                $fecha = (string) $row->fecha;
            }
        }

        $articuloStockId = (int) ($row->articulo_stock_id ?? 0);
        $cantidadStockGrabada = (float) ($row->cantidad_stock ?? 0);
        $precioStockGrabado = (float) ($row->precio_stock ?? 0);

        $insumo = null;
        $origenInsumo = '';
        $skuInsumo = '';
        $descInsumo = '';
        $umInsumo = '';
        $coefInsumo = null;
        $cantidadInsumo = null;
        $precioInsumo = null;
        $tieneInsumo = false;

        if ($articuloStockId > 0 && trim((string) ($row->sku_stock_grabado ?? '')) !== '') {
            $tieneInsumo = true;
            $origenInsumo = 'tra';
            $skuInsumo = (string) $row->sku_stock_grabado;
            $descInsumo = (string) ($row->descripcion_stock_grabado ?? '');
            $umInsumo = $this->etiquetaUm($row->um_insumo_grabado_abrev ?? null, $row->um_insumo_grabado_nombre ?? null);
            $coefInsumo = $coefLinea;
            $cantidadInsumo = $cantidadStockGrabada > 0
                ? $cantidadStockGrabada
                : RecepcionProveedorConversionSupport::cantidadStock($cantidad, $coefLinea);
            $precioInsumo = $precioStockGrabado > 0
                ? $precioStockGrabado
                : round($precioNeto / $coefLinea, 6);
        } else {
            $articuloId = (int) ($row->articulo_id ?? 0);
            $articulo = null;
            if ($articuloId > 0) {
                $articulo = $this->articuloCompraDesdeFila($row, $articuloId);
                $insumo = RecepcionProveedorDepositoSupport::resolverArticuloInsumo(
                    $articulo,
                    (int) ($row->empresa_id ?? 0) ?: null
                );
            }

            if ($insumo !== null && $articulo !== null) {
                $tieneInsumo = true;
                $origenInsumo = 'teorico';
                $insumo = $this->cargarInsumoCompleto((int) $insumo->id) ?? $insumo;
                if (! $insumo->relationLoaded('unidadesdemedidas')) {
                    $insumo->load('unidadesdemedidas');
                }
                $skuInsumo = (string) ($insumo->sku ?? '');
                $descInsumo = (string) ($insumo->descripcion ?? '');
                $umInsumo = $this->etiquetaUm(
                    optional($insumo->unidadesdemedidas)->abreviatura,
                    optional($insumo->unidadesdemedidas)->nombre
                );
                $coefInsumo = RecepcionProveedorDepositoSupport::coeficienteConversionFormula($articulo, $insumo);
                $cantidadInsumo = RecepcionProveedorConversionSupport::cantidadStock($cantidad, $coefInsumo);
                $precioInsumo = round($precioNeto / $coefInsumo, 6);
            }
        }

        return [
            'tipo_fila' => 'dato',
            'linea_id' => (int) ($row->linea_id ?? 0),
            'recepcion_id' => (int) ($row->recepcion_id ?? 0),
            'articulo_id' => (int) ($row->articulo_id ?? 0),
            'empresa_id' => (int) ($row->empresa_id ?? 0),
            'nombreempresa' => (string) ($row->nombreempresa ?? ''),
            'fecha' => $fecha,
            'fecha_fmt' => RecepcionConversionControlFiltros::formatearFechaPantalla($fecha),
            'tipo' => (string) ($row->tipo ?? Recepcion_Proveedor::TIPO_RECEPCION),
            'numerorecepcion' => (int) ($row->numerorecepcion ?? 0),
            'proveedor_id' => (int) ($row->proveedor_pk ?? $row->proveedor_id ?? 0),
            'codigo_proveedor' => (string) ($row->codigo_proveedor ?? ''),
            'nombreproveedor' => (string) ($row->nombreproveedor ?? ''),
            'sku_compra' => (string) ($row->sku_compra ?? ''),
            'descripcion_compra' => (string) ($row->descripcion_compra ?? ''),
            'cantidad' => $cantidad,
            'um_compra' => $umCompra,
            'um_stock' => $umStockArt,
            'coeficienteconversion' => $coefLinea,
            'coef_maestro_articulo' => (float) ($row->coef_maestro_articulo ?? 0) ?: null,
            'precio' => round($precioNeto, 6),
            'tiene_insumo' => $tieneInsumo,
            'origen_insumo' => $origenInsumo,
            'origen_insumo_etiqueta' => match ($origenInsumo) {
                'tra' => 'TRA / depósito fórmula',
                'teorico' => 'Teórico (maestro)',
                default => '',
            },
            'sku_insumo' => $skuInsumo,
            'descripcion_insumo' => $descInsumo,
            'cantidad_insumo' => $cantidadInsumo,
            'um_insumo' => $umInsumo,
            'coef_insumo' => $coefInsumo,
            'precio_insumo' => $precioInsumo,
            'articulo_stock_id' => $articuloStockId > 0 ? $articuloStockId : ($insumo?->id),
        ];
    }

    private function etiquetaUm(?string $abrev, ?string $nombre): string
    {
        $abr = trim((string) $abrev);
        if ($abr !== '') {
            return $abr;
        }

        return trim((string) $nombre);
    }

    private function cargarInsumoCompleto(int $insumoId): ?Articulo
    {
        if ($insumoId <= 0) {
            return null;
        }
        if (isset($this->cacheInsumoCargado[$insumoId])) {
            return $this->cacheInsumoCargado[$insumoId];
        }
        $insumo = Articulo::query()->with('unidadesdemedidas')->find($insumoId);
        if ($insumo !== null) {
            $this->cacheInsumoCargado[$insumoId] = $insumo;
        }

        return $insumo;
    }

    /**
     * Stub de compra sin mass assignment (id no está en fillable de Articulo).
     */
    private function articuloCompraDesdeFila(object $row, int $articuloId): Articulo
    {
        $articulo = new Articulo;
        $articulo->setRawAttributes([
            'id' => $articuloId,
            'sku' => (string) ($row->sku_compra ?? ''),
            'skualternativo' => (string) ($row->skualternativo ?? ''),
            'coeficienteconversion' => (float) ($row->coef_maestro_articulo ?? 0),
            'empresa_id' => (int) ($row->empresa_id ?? 0),
        ], true);
        $articulo->exists = true;

        return $articulo;
    }

    /**
     * @param  list<array<string, mixed>>  $filasDatos
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return list<array<string, mixed>>
     */
    private function aplicarConsolidacion(array $filasDatos, array $filtros, $empresaQuery): array
    {
        $empresaIds = RecepcionConversionControlFiltros::empresaIds($filtros);
        if (! empty($filtros['consolidar_empresas']) || count($empresaIds) <= 1) {
            return $filasDatos;
        }

        $nombres = $empresaQuery->keyBy(fn ($e) => (int) $e->id);
        $porEmpresa = [];
        foreach ($filasDatos as $fila) {
            $eid = (int) ($fila['empresa_id'] ?? 0);
            $porEmpresa[$eid][] = $fila;
        }

        $resultado = [];
        foreach ($empresaIds as $eid) {
            $grupo = $porEmpresa[$eid] ?? [];
            if ($grupo === []) {
                continue;
            }
            $nombre = (string) (optional($nombres->get($eid))->nombre ?? ('Empresa '.$eid));
            $resultado[] = [
                'tipo_fila' => 'header_empresa',
                'empresa_id' => $eid,
                'nombreempresa' => $nombre,
                'etiqueta' => $nombre,
            ];
            foreach ($grupo as $fila) {
                $resultado[] = $fila;
            }
        }

        return $resultado;
    }
}
