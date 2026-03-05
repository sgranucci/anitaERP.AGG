<?php

namespace App\Queries\Caja;

use App\Models\Caja\Caja_Asignacion;
use DB;

class Caja_AsignacionQuery implements Caja_AsignacionQueryInterface
{
    protected $caja_asignacionModel;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Caja_Asignacion $caja_asignacionmodel)
    {
        $this->caja_asignacionModel = $caja_asignacionmodel;
    }

    public function first()
    {
        return $this->caja_asignacionModel->first();
    }

    public function all()
    {
        return $this->caja_asignacionModel->get();
    }

    public function allQuery(array $campos)
    {
        return $this->caja_asignacionModel->select($campos)->get();
    }

    public function leeAsignacionPorUsuario($usuario_id, $fecha)
    {
        return $this->caja_asignacionModel->where('usuario_id', $usuario_id)
                                          ->where('fecha', $fecha->format('Y-m-d'))
                                          ->with('usuarios')
                                          ->with('cajas')
                                          ->first();
    }

}

