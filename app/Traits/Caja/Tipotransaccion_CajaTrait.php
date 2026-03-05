<?php

namespace App\Traits\Caja;

trait Tipotransaccion_CajaTrait {

	public static $enumOperacion = [
		'I' => 'Ingreso',
		'E' => 'Egreso',
		'P' => 'Pago a proveedores',
		'C' => 'Cobranza de clientes'
		];
	
	public static $enumSigno = [
			'I' => 'Ingreso',
			'E' => 'Egreso',
			];

	public static $enumEstado = [
			'A' => 'Activa',
			'S' => 'Suspendida',
			];
}
