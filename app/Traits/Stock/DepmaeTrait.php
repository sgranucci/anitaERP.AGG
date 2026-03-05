<?php

namespace App\Traits\Stock;

trait DepmaeTrait {

	public static $enumTipoDeposito = [
		['id' => 1, 'valor' => 'N', 'nombre' => 'Normal'],
		['id' => 2, 'valor' => 'E', 'nombre' => 'Excedente'],
		['id' => 3, 'valor' => 'C', 'nombre' => 'Consignacion'],
		['id' => 4, 'valor' => 'T', 'nombre' => 'Transito'],
		['id' => 5, 'valor' => 'P', 'nombre' => 'Temporal'],
		['id' => 6, 'valor' => 'M', 'nombre' => 'Centro de consumo'],
		['id' => 7, 'valor' => 'I', 'nombre' => 'Interno'],
		['id' => 8, 'valor' => 'F', 'nombre' => 'Formulas']
		];

}
