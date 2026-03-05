<?php

namespace App\Traits\Caja;

trait Caja_Movimiento_EstadoTrait {

	public static $enumEstado = [
		['id' => '1', 'valor' => 'A', 'nombre'  => 'Activo'],
		['id' => '2', 'valor' => 'R', 'nombre'  => 'Revertido'],
		['id' => '3', 'valor' => 'S', 'nombre'  => 'Suspendido'],
		['id' => '4', 'valor' => 'B', 'nombre'  => 'Baja'],
			];
}

