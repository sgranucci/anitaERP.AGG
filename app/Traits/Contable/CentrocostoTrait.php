<?php

namespace App\Traits\Contable;

use Illuminate\Support\Collection;

trait CentrocostoTrait {

	public static $enumTipoIva = [
		['id' => '1', 'valor' => 'D', 'nombre'  => 'Directo'],
		['id' => '2', 'valor' => 'I', 'nombre'  => 'Indirecto'],
		['id' => '2', 'valor' => 'N', 'nombre'  => 'No computable'],
			];
}

