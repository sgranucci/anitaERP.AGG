<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Stock\Articulo;
use App\Models\Ventas\Canal;
use App\Support\Stock\ArticuloEstadoCanalSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalCostoFabricaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalCostosLocalReporteFiltros;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FacturacionLocalCostosLocalReporteService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas:list<array<string,mixed>>,
     *   totales:array{sku:int,sin_precio:int,con_precio:int},
     *   sin_precio:int,
     *   costo_formula:string,
     *   factor:float,
     *   descuento_pct:float,
     *   fecha_vigencia:string,
     *   texto_filtros:string
     * }
     */
    public function generar(array $filtros): array
    {
        $fecha = now()->toDateString();
        $canal = (string) ($filtros['canal'] ?? FacturacionLocalCostosLocalReporteFiltros::CANAL_TODOS);
        $articulos = $this->articulos($filtros);
        $ids = [];
        foreach ($articulos as $row) {
            $ids[] = (int) $row->id;
        }

        $preciosFabrica = FacturacionLocalCostoFabricaSupport::mapaPrecioVentaFabrica($ids, $fecha);
        $factor = FacturacionLocalCostoFabricaSupport::factorCosto();
        $canales = $this->canalesPorArticulo($ids);

        $filas = [];
        $sinPrecio = 0;
        $soloCero = ! empty($filtros['solo_precio_cero']);
        $estadosPorCanal = ArticuloEstadoCanalSupport::columnasEstadoDisponibles();

        foreach ($articulos as $row) {
            $id = (int) $row->id;
            $precioFabrica = round((float) ($preciosFabrica[$id] ?? 0), 4);
            $costo = $precioFabrica > 0 ? round($precioFabrica * $factor, 4) : 0.0;
            $sin = $precioFabrica <= 0;
            if ($sin) {
                $sinPrecio++;
            }
            if ($soloCero && ! $sin) {
                continue;
            }

            $codigosCanal = $canales[$id] ?? [];
            $filas[] = [
                'articulo_id' => $id,
                'sku' => (string) ($row->sku ?? ''),
                'descripcion' => (string) ($row->descripcion ?? ''),
                'marca' => $this->etiquetaMarca($row),
                'canal' => $this->etiquetaCanales($codigosCanal),
                'estado' => $this->etiquetaEstado($row, $canal, $estadosPorCanal),
                'precio_fabrica' => $precioFabrica,
                'costo' => $costo,
                'sin_precio' => $sin,
            ];
        }

        $totalBase = count($articulos);

        return [
            'filas' => $filas,
            'totales' => [
                'sku' => $totalBase,
                'sin_precio' => $sinPrecio,
                'con_precio' => max(0, $totalBase - $sinPrecio),
            ],
            'sin_precio' => $sinPrecio,
            'costo_formula' => FacturacionLocalCostoFabricaSupport::etiquetaFormula(),
            'factor' => FacturacionLocalCostoFabricaSupport::factorCosto(),
            'descuento_pct' => FacturacionLocalCostoFabricaSupport::descuentoPct(),
            'fecha_vigencia' => $fecha,
            'texto_filtros' => FacturacionLocalCostosLocalReporteFiltros::textoFiltros($filtros),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
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

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<object>
     */
    private function articulos(array $filtros): array
    {
        $query = Articulo::query()
            ->leftJoin('mventa', 'mventa.id', '=', 'articulo.mventa_id');

        $columnas = [
            'articulo.id',
            'articulo.sku',
            'articulo.descripcion',
            'articulo.mventa_id',
            'mventa.codigo as marca_codigo',
            'mventa.nombre as marca_nombre',
        ];
        if (Schema::hasColumn('articulo', 'estado')) {
            $columnas[] = 'articulo.estado';
        }
        if (ArticuloEstadoCanalSupport::columnasEstadoDisponibles()) {
            $columnas[] = 'articulo.estado_fabrica';
            $columnas[] = 'articulo.estado_local';
        }
        $query->select($columnas);

        $mventaId = (int) ($filtros['mventa_id'] ?? 0);
        if ($mventaId > 0) {
            $query->where('articulo.mventa_id', $mventaId);
        }

        $canal = (string) ($filtros['canal'] ?? FacturacionLocalCostosLocalReporteFiltros::CANAL_TODOS);
        if (in_array($canal, [Canal::CODIGO_FABRICA, Canal::CODIGO_LOCAL], true)) {
            $query->whereExists(function ($q) use ($canal) {
                $q->selectRaw('1')
                    ->from('articulo_canal')
                    ->join('canal', 'canal.id', '=', 'articulo_canal.canal_id')
                    ->whereColumn('articulo_canal.articulo_id', 'articulo.id')
                    ->where('canal.codigo', $canal)
                    ->where('canal.activo', true);
            });
        }

        $estado = (string) ($filtros['estado'] ?? FacturacionLocalCostosLocalReporteFiltros::ESTADO_ACTIVOS);
        if ($estado === FacturacionLocalCostosLocalReporteFiltros::ESTADO_ACTIVOS) {
            ArticuloEstadoCanalSupport::aplicarFiltroEstadoListado(
                $query,
                ArticuloEstadoCanalSupport::ESTADO_ACTIVO,
                $canal === FacturacionLocalCostosLocalReporteFiltros::CANAL_TODOS ? '' : $canal,
            );
        } elseif ($estado === FacturacionLocalCostosLocalReporteFiltros::ESTADO_INACTIVOS) {
            ArticuloEstadoCanalSupport::aplicarFiltroEstadoListado(
                $query,
                ArticuloEstadoCanalSupport::ESTADO_INACTIVO,
                $canal === FacturacionLocalCostosLocalReporteFiltros::CANAL_TODOS ? '' : $canal,
            );
        }

        return $query
            ->orderBy('articulo.sku')
            ->orderBy('articulo.id')
            ->get()
            ->all();
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, list<string>>
     */
    private function canalesPorArticulo(array $articuloIds): array
    {
        $mapa = [];
        if ($articuloIds === [] || ! Schema::hasTable('articulo_canal')) {
            return $mapa;
        }

        foreach (array_chunk($articuloIds, 800) as $chunk) {
            $rows = DB::table('articulo_canal as ac')
                ->join('canal as c', 'c.id', '=', 'ac.canal_id')
                ->whereIn('ac.articulo_id', $chunk)
                ->whereIn('c.codigo', [Canal::CODIGO_FABRICA, Canal::CODIGO_LOCAL])
                ->where('c.activo', true)
                ->get(['ac.articulo_id', 'c.codigo']);
            foreach ($rows as $row) {
                $id = (int) $row->articulo_id;
                $codigo = (string) $row->codigo;
                $mapa[$id][$codigo] = $codigo;
            }
        }

        $out = [];
        foreach ($mapa as $id => $codigos) {
            $ordenados = [];
            if (isset($codigos[Canal::CODIGO_FABRICA])) {
                $ordenados[] = Canal::CODIGO_FABRICA;
            }
            if (isset($codigos[Canal::CODIGO_LOCAL])) {
                $ordenados[] = Canal::CODIGO_LOCAL;
            }
            $out[$id] = $ordenados;
        }

        return $out;
    }

    private function etiquetaMarca(object $row): string
    {
        $codigo = trim((string) ($row->marca_codigo ?? ''));
        $nombre = trim((string) ($row->marca_nombre ?? ''));
        if ($codigo !== '' && $nombre !== '') {
            return $codigo.' — '.$nombre;
        }

        return $nombre !== '' ? $nombre : $codigo;
    }

    /**
     * @param  list<string>  $codigos
     */
    private function etiquetaCanales(array $codigos): string
    {
        $nombres = [];
        if (in_array(Canal::CODIGO_FABRICA, $codigos, true)) {
            $nombres[] = 'Fábrica';
        }
        if (in_array(Canal::CODIGO_LOCAL, $codigos, true)) {
            $nombres[] = 'Local';
        }

        return $nombres !== [] ? implode(' / ', $nombres) : 'Sin canal';
    }

    private function etiquetaEstado(object $row, string $canal, bool $estadosPorCanal): string
    {
        if (! $estadosPorCanal) {
            return (string) ($row->estado ?? '');
        }

        $fab = (string) ($row->estado_fabrica ?? '');
        $loc = (string) ($row->estado_local ?? '');
        if ($canal === Canal::CODIGO_FABRICA) {
            return $fab;
        }
        if ($canal === Canal::CODIGO_LOCAL) {
            return $loc;
        }

        return 'Fáb: '.$fab.' · Loc: '.$loc;
    }
}
