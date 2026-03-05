<?php

namespace App\Traits\Compras;

use Illuminate\Support\Collection;

trait CondicionpagoTrait {

	public static $enumTipoPlazo = [
		['id' => '1', 'valor' => 'D', 'nombre'  => 'Dias'],
		['id' => '2', 'valor' => 'F', 'nombre'  => 'Vto. fijo'],
		['id' => '3', 'valor' => 'O', 'nombre'  => 'Vto. por operacion'],
		['id' => '4', 'valor' => 'R', 'nombre'  => 'Vto. por rangos']
			];

}

