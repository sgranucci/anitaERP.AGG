<?php

namespace App\Traits\Compras;

use Illuminate\Support\Collection;

trait Concepto_IvacompraTrait {

	public static $enumTipoConcepto = [
		['id' => '1', 'valor' => 'N', 'nombre'  => 'Importe no gravado'],
		['id' => '2', 'valor' => 'G', 'nombre'  => 'Importe gravado'],
		['id' => '3', 'valor' => 'E', 'nombre'  => 'Importe exento'],
		['id' => '4', 'valor' => 'I', 'nombre'  => 'Impuesto liquidado'],
		['id' => '5', 'valor' => 'P', 'nombre'  => 'Importe percepciones IVA'],
		['id' => '6', 'valor' => 'B', 'nombre'  => 'Importe percepciones IIBB'],
		['id' => '7', 'valor' => 'M', 'nombre'  => 'Importe percepciones municipales'],
		['id' => '8', 'valor' => 'T', 'nombre'  => 'Importe impuesto interno'],
		['id' => '9', 'valor' => 'S', 'nombre'  => 'Importe percepciones SIRCREB'],
		['id' => '10', 'valor' => 'A', 'nombre'  => 'Importe percepciones aduana'],
			];

	public static $enumRetiene = [
		['id' => '1', 'valor' => 'S', 'nombre' => 'Retiene'],
		['id' => '2', 'valor' => 'N', 'nombre' => 'No retiene'],
	];

}

