<?php

namespace App\Traits\Produccion;

trait OperacionTrait {

	public static $enumTipoOperacion = [
		'I' => 'Inicio',
		'F' => 'Fin',
		'P' => 'Proceso',
		'A' => 'Anulacion',
		];
}
