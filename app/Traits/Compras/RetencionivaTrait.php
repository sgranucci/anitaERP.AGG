<?php

namespace App\Traits\Compras;

use Illuminate\Support\Collection;

trait RetencionivaTrait {

	public static $enumFormaCalculo = [
		['id' => '1', 'valor' => 'I', 'nombre'  => 'Aplica sobre iva'],
		['id' => '2', 'valor' => 'N', 'nombre'  => 'Aplica sobre neto'],
		['id' => '3', 'valor' => 'O', 'nombre'  => 'Acumula por períodos'],
			];
}

