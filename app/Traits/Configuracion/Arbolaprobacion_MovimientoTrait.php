<?php

namespace App\Traits\Configuracion;

trait Arbolaprobacion_MovimientoTrait {

	public static $enumEstado = [
		['id' => '1', 'valor' => 'P', 'nombre'  => 'Pendiente'],
		['id' => '2', 'valor' => 'A', 'nombre'  => 'Aprobado'],
		['id' => '3', 'valor' => 'R', 'nombre'  => 'Rechazado'],
			];

}

