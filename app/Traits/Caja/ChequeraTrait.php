<?php

namespace App\Traits\Caja;

trait ChequeraTrait {

	public static $enumTipochequera = [
		['id' => '1', 'valor' => 'F', 'nombre'  => 'Física'],
		['id' => '2', 'valor' => 'E', 'nombre'  => 'Electrónica'],
			];

	public static $enumTipocheque = [
		['id' => '1', 'valor' => 'N', 'nombre'  => 'Normal'],
		['id' => '2', 'valor' => 'D', 'nombre'  => 'Diferido'],
			];
			
	public static $enumEstado = [
		['id' => '1', 'valor' => 'A', 'nombre'  => 'Activa'],
		['id' => '2', 'valor' => 'T', 'nombre'  => 'Terminada'],
			];			
}

