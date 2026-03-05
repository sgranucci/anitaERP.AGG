<?php

namespace App\Traits\Compras;

trait RetenciongananciaTrait {

	public static $enumFormaCalculo = [
		['id' => '1', 'valor' => 'S', 'nombre'  => 'Toma acumulados'],
		['id' => '2', 'valor' => 'N', 'nombre'  => 'No toma acumulados'],
		['id' => '3', 'valor' => 'M', 'nombre'  => 'Retención manual'],
		['id' => '4', 'valor' => 'E', 'nombre'  => 'No resta excedente'],
		['id' => '5', 'valor' => 'O', 'nombre'  => 'Acumula por períodos'],
		['id' => '6', 'valor' => 'G', 'nombre'  => 'Grossing up'],
		['id' => '7', 'valor' => 'B', 'nombre'  => 'Grossing up base manual']
			];
		
}

