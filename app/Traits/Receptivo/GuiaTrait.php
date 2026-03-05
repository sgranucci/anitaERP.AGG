<?php

namespace App\Traits\Receptivo;

use Illuminate\Support\Collection;

trait GuiaTrait {

	public static $enumManeja = [
		['id' => '1', 'valor' => 'S', 'nombre'  => 'Maneja'],
		['id' => '2', 'valor' => 'N', 'nombre'  => 'No maneja'],
			];

	public static $enumTipoGuia = [
		['id' => '1', 'valor' => 'G', 'nombre'  => 'Guia de agencia'],
		['id' => '2', 'valor' => 'C', 'nombre'  => 'Chofer de agencia'],
		['id' => '3', 'valor' => 'F', 'nombre'  => 'Guia freelance'],
		['id' => '4', 'valor' => 'H', 'nombre'  => 'Chofer freelance'],
		['id' => '5', 'valor' => 'O', 'nombre'  => 'Otro'],
		['id' => '6', 'valor' => 'R', 'nombre'  => 'Chofer'],
			];			
}

