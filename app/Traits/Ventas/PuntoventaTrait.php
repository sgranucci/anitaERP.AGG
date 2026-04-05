<?php

namespace App\Traits\Ventas;

trait PuntoventaTrait {

	public static $enumModoFacturacion = [
		'M' => 'Manual',
		'C' => 'Factura electronica CAE',
		'A' => 'Factura electronica CAEA',
		'E' => 'Factura electronica de exportacion',
		'R' => 'Remito Factura',
		'L' => 'Remito Manual',
		'O' => 'Otros',
		'I' => 'Inactiva'
		];

	public static $enumEstado = [
			'A' => 'Activa',
			'S' => 'Suspendida',
			];
}
