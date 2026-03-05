<?php

namespace App\Traits\Presupuesto;

trait Capex_EstadoTrait {

	public static $enumEstado = [
		['id' => '1', 'valor' => 'A', 'nombre' => 'ACTIVO'],
		['id' => '2', 'valor' => 'C', 'nombre' => 'CERRADO'],
		['id' => '3', 'valor' => 'B', 'nombre' => 'ANULADO'],
			];
		
}
