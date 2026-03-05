<?php

namespace App\Traits\Caja;

trait ChequeTrait {

	public static $enumOrigen = [
		['id' => '1', 'valor' => 'E', 'nombre'  => 'Emitido'],
		['id' => '2', 'valor' => 'R', 'nombre'  => 'Recibido'],
			];

	public static $enumCaracter = [
		['id' => '1', 'valor' => 'O', 'nombre'  => 'A la orden'],
		['id' => '2', 'valor' => 'N', 'nombre'  => 'No a la orden'],
		['id' => '3', 'valor' => 'R', 'nombre'  => 'Recibido']
			];
			
	public static $enumEstado = [
		['id' => '1', 'valor' => ' ', 'nombre'  => 'DIFERIDO'],
		['id' => '2', 'valor' => '*', 'nombre'  => 'DEBITADO'],
		['id' => '3', 'valor' => 'C', 'nombre'  => 'CIERRE'],
		['id' => '4', 'valor' => 'A', 'nombre'  => 'ANULADO'],
		['id' => '5', 'valor' => 'R', 'nombre'  => 'RECHAZADO'],
		['id' => '6', 'valor' => 'N', 'nombre'  => 'NO_PRESENTADO'],
			];			
}

