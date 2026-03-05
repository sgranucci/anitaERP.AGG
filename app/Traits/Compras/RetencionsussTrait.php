<?php

namespace App\Traits\Compras;

use Illuminate\Support\Collection;

trait RetencionsussTrait {

	public static $enumFormaCalculo = [
		['id' => '1', 'valor' => 'P', 'nombre'  => 'Porcentaje'],
		['id' => '2', 'valor' => 'I', 'nombre'  => 'Importe'],
		['id' => '3', 'valor' => 'A', 'nombre'  => 'Acumulado mensual'],
		['id' => '4', 'valor' => 'M', 'nombre'  => 'Acumulado anual'],
			];
}

