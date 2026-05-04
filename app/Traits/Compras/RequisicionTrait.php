<?php

namespace App\Traits\Compras;

trait RequisicionTrait
{
	public static $enumTratamiento = [
		['id' => '1', 'valor' => 'N', 'nombre'  => 'Normal'],
		['id' => '2', 'valor' => 'U', 'nombre'  => 'Urgente'],
	];

	public static $enumContratacionDirecta = [
		['id' => '1', 'valor' => 'N', 'nombre'  => 'No es contratación directa'],
		['id' => '2', 'valor' => 'S', 'nombre'  => 'Es contratación directa'],
	];	
}
