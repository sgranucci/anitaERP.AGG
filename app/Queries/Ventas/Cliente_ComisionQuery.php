<?php

namespace App\Queries\Ventas;

use App\Models\Ventas\Cliente_Comision;
use App\ApiAnita;

class Cliente_ComisionQuery implements Cliente_ComisionQueryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Comision $cliente_comision)
    {
        $this->model = $cliente_comision;
    }

    public function traeVendedor($cliente, $marca_id)
    {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "clico_vendedor, vend_nombre", 
            			'whereArmado' => " WHERE vend_codigo = clico_vendedor and clico_cliente = '".str_pad($cliente, 6, "0", STR_PAD_LEFT)."' AND clico_marca = '".$marca_id."' ", 
						'tabla' => 'clicomi, vendedor' );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		return $dataAnita;
    }
}

