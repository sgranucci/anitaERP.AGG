<?php

namespace App\Traits\Uif;

trait Cliente_UifTrait {

	public static $enumEstado = [
		['id' => '1', 'valor' => 'A', 'nombre' => "ACTIVO"],
		['id' => '2', 'valor' => 'B', 'nombre' => "DADO DE BAJA"],
	];

	public static $enumSexo = [
		['id' => '1', 'valor' => 'M', 'nombre'  => 'MASCULINO'],
		['id' => '2', 'valor' => 'F', 'nombre'  => 'FEMENINO'],
			];

	public static $enumResideParaisoFiscal = [
		['id' => '1', 'valor' => 'N', 'nombre'  => 'NO RESIDE'],
		['id' => '2', 'valor' => 'S', 'nombre'  => 'RESIDE'],
			];

	public static $enumResideExterior = [
		['id' => '1', 'valor' => 'N', 'nombre'  => 'NO RESIDE'],
		['id' => '2', 'valor' => 'S', 'nombre'  => 'RESIDE'],
			];

	public static $enumCumpleNormativaSo = [
		['id' => '1', 'valor' => 'S', 'nombre'  => 'CUMPLE'],
		['id' => '2', 'valor' => 'N', 'nombre'  => 'NO CUMPLE'],
			];

	public static $enumFirmoDeclaracionJurada = [
		['id' => '1', 'valor' => 'N', 'nombre'  => 'NO FIRMO'],
		['id' => '2', 'valor' => 'S', 'nombre'  => 'FIRMO'],
			];

	public static $enumRiesgoPep = [
		['id' => '1', 'valor' => 'B', 'nombre'  => 'BAJO'],
		['id' => '2', 'valor' => 'M', 'nombre'  => 'MEDIO'],
		['id' => '3', 'valor' => 'A', 'nombre'  => 'ALTO'],
			];

	public static $enumExpuesto = [
		['id' => '1', 'valor' => 'S', 'nombre'  => 'EXPUESTO'],
		['id' => '2', 'valor' => 'N', 'nombre'  => 'NO EXPUESTO'],
			];			

}

