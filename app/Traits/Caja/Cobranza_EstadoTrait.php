<?php

namespace App\Traits\Caja;

trait Cobranza_EstadoTrait {

	public static $enumEstado = [
		['id' => '1', 'valor' => 'C', 'nombre'  => 'CONFIRMADA'],
		['id' => '2', 'valor' => 'P', 'nombre'  => 'PRE CARGA'],
		['id' => '3', 'valor' => 'R', 'nombre'  => 'REVERTIDA'],
		['id' => '4', 'valor' => 'B', 'nombre'  => 'BAJA'],
			];
}

