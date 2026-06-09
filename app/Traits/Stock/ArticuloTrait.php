<?php

namespace App\Traits\Stock;

trait ArticuloTrait {

	public static $enumDivide = [
		['id' => '1', 'valor' => 'S', 'nombre'  => 'DIVIDE'],
		['id' => '2', 'valor' => 'N', 'nombre'  => 'NO DIVIDE'],
			];

	public static $enumEnviaAlarma = [
		['nombre' => 'Envia Alarma'],
		['nombre' => 'No Envia Alarma'],
	];

}

