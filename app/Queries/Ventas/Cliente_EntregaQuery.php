<?php

namespace App\Queries\Ventas;

use App\Models\Ventas\Cliente_Entrega;

class Cliente_EntregaQuery implements Cliente_EntregaQueryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Entrega $cliente_entrega)
    {
        $this->model = $cliente_entrega;
    }

    public function traeCliente_EntregaporCliente_id($cliente_id)
    {
        return $this->model
            ->select('id', 'nombre', 'domicilio', 'localidad_id', 'provincia_id', 'codigopostal')
            ->where('cliente_id', $cliente_id)
            ->orderBy('nombre')
            ->get()
            ->map(function ($entrega) {
                return [
                    'id' => $entrega->id,
                    'nombre' => $entrega->nombre,
                    'domicilio' => $entrega->domicilio,
                    'localidad_id' => $entrega->localidad_id,
                    'provincia_id' => $entrega->provincia_id,
                    'codigopostal' => $entrega->codigopostal,
                    'localidad' => $entrega->desc_localidades,
                    'provincia' => $entrega->desc_provincias,
                ];
            })
            ->values();
    }

}

