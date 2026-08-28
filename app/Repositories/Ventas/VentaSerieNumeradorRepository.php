<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Venta_Serie_Numerador;
use App\Support\Ventas\VentaSerieNumeradorListadoFiltros;
use Illuminate\Support\Facades\DB;

class VentaSerieNumeradorRepository implements VentaSerieNumeradorRepositoryInterface
{
    public function __construct(
        private readonly Venta_Serie_Numerador $model,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, Venta_Serie_Numerador>
     */
    public function leeVentaSerieNumerador($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => VentaSerieNumeradorListadoFiltros::MODO_TODOS,
                'campo' => 'codigo_pv',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = VentaSerieNumeradorListadoFiltros::filtrosVacios();
        }

        $maxVenta = DB::table('venta')
            ->select('puntoventa_id', 'codigo_afip', DB::raw('MAX(numerocomprobante) as max_venta'))
            ->whereNotNull('codigo_afip')
            ->where('codigo_afip', '>', 0)
            ->groupBy('puntoventa_id', 'codigo_afip');

        $query = $this->model->newQuery()
            ->select('venta_serie_numerador.*', 'mv.max_venta')
            ->leftJoin('puntoventa', 'puntoventa.id', '=', 'venta_serie_numerador.puntoventa_id')
            ->leftJoinSub($maxVenta, 'mv', function ($join): void {
                $join->on('mv.puntoventa_id', '=', 'venta_serie_numerador.puntoventa_id')
                    ->on('mv.codigo_afip', '=', 'venta_serie_numerador.codigo_afip');
            })
            ->with(['empresa:id,nombre', 'puntoventa:id,codigo,nombre,modofacturacion,empresa_id']);

        if (VentaSerieNumeradorListadoFiltros::tieneCriteriosAplicados($filtros)) {
            VentaSerieNumeradorListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('puntoventa.codigo')
            ->orderBy('venta_serie_numerador.codigo_afip');

        return $paginar
            ? $query->paginate(10)->appends(VentaSerieNumeradorListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }
}
