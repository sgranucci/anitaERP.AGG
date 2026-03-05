<?php

namespace App\Traits\Ventas;

trait DescuentoventaTrait {

	public static $enumTipoDescuento = [
		'P' => 'POR PORCENTAJE',
		'M' => 'POR MONTO FIJO',
		'C' => 'POR CANTIDAD VENDIDA',
		];

	public static $enumEstado = [
		'A' => 'ACTIVO',
		'S' => 'SUSPENDIDO',
		];

}
