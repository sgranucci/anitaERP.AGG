<?php

namespace App\Traits\Compras;

trait Tipotransaccion_CompraTrait {

	public static $enumOperacion = [
		'L' => 'Mercado local',
		'I' => 'Importación',
		];
	
	public static $enumSigno = [
		'S' => 'Suma',
		'R' => 'Resta',
		'N' => 'Nulo',
		];

	public static $enumSubdiario = [
		'C' => 'Compras',
		'N' => 'No va al subdiario',
		];

	public static $enumAsientoContable = [
		'S' => 'Genera asiento contable',
		'N' => 'No genera asiento contable',
		];

	/** Separado de asientocontable: en Anita era el valor U de tcomp_genera_asi. */
	public static $enumGeneraBienDeUso = [
		'S' => 'Genera bien de uso',
		'N' => 'No genera bien de uso',
		];
	
	public static $enumEstado = [
		'A' => 'Activa',
		'S' => 'Suspendida',
		];

	public static $enumRetiene = [
		'S' => 'Retiene',
		'N' => 'No retiene',
		];
}
