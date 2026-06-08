<?php

namespace App\Traits\Ventas;

trait TipotransaccionTrait {

	public static $enumOperacion = [
		'V' => 'Venta',
		'U' => 'Venta Bienes de Uso',
		'C' => 'Devolución de venta',
		];
	
	public static $enumSigno = [
			'S' => 'Suma',
			'R' => 'Resta',
			];

	public static $enumOperacionStock = [
		'S' => 'Salida',
		'E' => 'Entrada',
		'N' => 'Nulo',
		'O' => 'Sin operación sobre stock',
		];

	public static $enumEstado = [
			'A' => 'Activa',
			'S' => 'Suspendida',
			];
}
