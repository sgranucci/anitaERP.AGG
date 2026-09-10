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

	/** Anita cpro_para_dep (pago.c CHP usa E). Independiente del carácter O/N. */
	public static $enumParaDep = [
		['id' => '1', 'valor' => 'E', 'nombre'  => 'E (CHP / e-cheq)'],
		['id' => '2', 'valor' => 'N', 'nombre'  => 'N'],
		['id' => '3', 'valor' => 'S', 'nombre'  => 'S'],
		['id' => '4', 'valor' => 'O', 'nombre'  => 'O'],
			];

	public static $enumNegociable = [
		['id' => '1', 'valor' => 'N', 'nombre'  => 'Físico'],
		['id' => '2', 'valor' => 'E', 'nombre'  => 'Electrónico'],
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

