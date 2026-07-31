<?php

namespace App\Queries\Configuracion;

use App\Models\Configuracion\Cotizacion;
use App\Support\Configuracion\CotizacionListadoFiltros;

class CotizacionQuery implements CotizacionQueryInterface
{
    protected $model;

    public function __construct(Cotizacion $cotizacion)
    {
        $this->model = $cotizacion;
    }

    public function first()
    {
        return $this->model->first();
    }

    public function all()
    {
        return $this->model->get();
    }

    public function allQuery(array $campos)
    {
        return $this->model->select($campos)->get();
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function leeCotizacion($filtros, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (is_string($filtros) || $filtros === null) {
            $legacy = trim((string) $filtros);
            $filtros = CotizacionListadoFiltros::filtrosVacios();
            if ($legacy !== '') {
                $filtros['modo'] = CotizacionListadoFiltros::MODO_CAMPO;
                $filtros['campo'] = 'fecha';
                $filtros['operador'] = 'igual';
                $filtros['valor'] = $legacy;
                $filtros['busqueda'] = $legacy;
            }
        }

        $cotizaciones = $this->model->select('cotizacion.id as id', 'cotizacion.fecha as fecha')
            ->with('cotizacion_monedas');

        if (is_array($filtros) && CotizacionListadoFiltros::tieneCriteriosAplicados($filtros)) {
            CotizacionListadoFiltros::aplicar($cotizaciones, $filtros);
        }

        $cotizaciones = $cotizaciones->orderBy('fecha', 'DESC');

        if (isset($flPaginando)) {
            if ($flPaginando) {
                return $cotizaciones->paginate(10);
            }

            return $cotizaciones->get();
        }

        return $cotizaciones->get();
    }

    public function leeCotizacionDiaria($fecha)
    {
        return $this->model->with('cotizacion_monedas')
            ->where('fecha', '<=', $fecha)
            ->orderBy('fecha', 'DESC')
            ->first();
    }
}
