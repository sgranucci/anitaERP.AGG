<?php

namespace App\Traits\Receptivo;

use Illuminate\Support\Collection;

trait ServicioterrestreTrait {

	public static $enumUbicacion = [
		['id' => '1', 'valor' => 'A', 'nombre'  => 'Argentina'],
		['id' => '2', 'valor' => 'E', 'nombre'  => 'Extranjera'],
		['id' => '3', 'valor' => 'O', 'nombre'  => 'Otros'],
			];

	public static $enumModoExento = [
		['id' => '1', 'valor' => 'N', 'nombre'  => 'No calcula'],
		['id' => '2', 'valor' => 'P', 'nombre'  => 'Porcentual'],
		['id' => '3', 'valor' => 'M', 'nombre'  => 'Monto fijo'],
			];
		
	public static $enumPrepago = [
		['id' => '1', 'valor' => 'S', 'nombre'  => 'Con prepago'],
		['id' => '2', 'valor' => 'N', 'nombre'  => 'Sin prepago'],
			];
	
}

