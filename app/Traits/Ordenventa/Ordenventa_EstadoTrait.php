<?php

namespace App\Traits\Ordenventa;

trait Ordenventa_EstadoTrait {

	public static $enumEstado = [
		['id' => '1', 'valor' => 'S', 'nombre'  => 'SOLICITADA'],
		['id' => '2', 'valor' => 'P', 'nombre'  => 'PENDIENTE'],
		['id' => '3', 'valor' => 'F', 'nombre'  => 'FACTURADA'],
		['id' => '4', 'valor' => 'C', 'nombre'  => 'COBRADA'],
		['id' => '5', 'valor' => 'R', 'nombre'  => 'RECHAZADA'],
			];
}

