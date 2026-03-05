<?php

namespace App\Queries\Configuracion;

use App\Models\Configuracion\Cotizacion;

class CotizacionQuery implements CotizacionQueryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
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

    public function leeCotizacion($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $cotizaciones = $this->model->select('cotizacion.id as id',
                                        'cotizacion.fecha as fecha')
                                ->with('cotizacion_monedas');

        if ($busqueda != null)
            $cotizaciones = $cotizaciones->where('cotizacion.fecha', $busqueda);

        $cotizaciones = $cotizaciones->orderby('fecha', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $cotizaciones = $cotizaciones->paginate(10);
            else
                $cotizaciones = $cotizaciones->get();
        }
        else
            $cotizaciones = $cotizaciones->get();

        return $cotizaciones;    
    }

    public function leeCotizacionDiaria($fecha)
    {
        $cotizaciones = $this->model->with('cotizacion_monedas')
                                    ->where('fecha', '<=', $fecha)
                                    ->orderby('fecha', 'DESC')
                                    ->first();

        return $cotizaciones;    
    }

}

