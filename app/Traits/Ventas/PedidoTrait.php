<?php

namespace App\Traits\Ventas;

trait PedidoTrait {

	public static $enumEstado = [
		'P' => 'Pendiente',
		'E' => 'Entregado',
		'F' => 'Facturado',
		'A' => 'Anulado',
		'C' => 'Cerrado',
		];

}
