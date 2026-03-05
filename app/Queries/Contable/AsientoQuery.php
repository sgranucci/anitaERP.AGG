<?php

namespace App\Queries\Contable;

use App\Models\Contable\Asiento;

class AsientoQuery implements AsientoQueryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Asiento $asiento)
    {
        $this->model = $asiento;
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

    public function leeAsiento($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $asientos = $this->model->select('asiento.id as id',
                                        'asiento.empresa_id as empresa',
                                        'empresa.nombre as nombreempresa',
                                        'asiento.numeroasiento as numeroasiento',
                                        'asiento.tipoasiento_id as tipoasiento_id',
                                        'tipoasiento.nombre as nombretipoasiento',
                                        'asiento.fecha as fecha',
                                        'asiento.observacion as observacion')
                                ->join('tipoasiento', 'tipoasiento.id', '=', 'asiento.tipoasiento_id')
                                ->join('empresa', 'empresa.id', '=', 'asiento.empresa_id')
                                ->with('asiento_movimientos')
                                ->where('asiento.numeroasiento', $busqueda)
                                ->orWhere('empresa.nombre', 'like', '%'.$busqueda.'%')  
                                ->orWhere('tipoasiento.nombre', 'like', '%'.$busqueda.'%')  
                                ->orWhere('asiento.fecha', $busqueda)  
                                ->orderby('id', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $asientos = $asientos->paginate(10);
            else
                $asientos = $asientos->get();
        }
        else
            $asientos = $asientos->get();

        return $asientos;
    }

}

