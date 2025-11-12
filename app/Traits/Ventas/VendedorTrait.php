<?php

namespace App\Traits\Ventas;

trait VendedorTrait {

	public static $enumAplicaSobre = [
		['id' => '1', 'valor' => 'N', 'nombre'  => 'Sobre Neto'],
		['id' => '2', 'valor' => 'B', 'nombre'  => 'Sobre Bruto'],
			];		


	public static $enumEstado = [
		['id' => '1', 'valor' => ' ', 'nombre'  => 'Activo'],
		['id' => '2', 'valor' => 'N', 'nombre'  => 'No Carga Clientes'],
			];					

}
