<?php

namespace App\Traits\Receptivo;

use Illuminate\Support\Collection;

trait Comision_ServicioterrestreTrait {

	public static $enumTipoComision = [
		['id' => '1', 'valor' => 'VH', 'nombre'  => 'Vende y Hace'],
		['id' => '2', 'valor' => 'V', 'nombre'  => 'Vende'],
		['id' => '3', 'valor' => 'H', 'nombre'  => 'Hace'],
		['id' => '4', 'valor' => 'O', 'nombre'  => 'Otros'],
			];
	
}

