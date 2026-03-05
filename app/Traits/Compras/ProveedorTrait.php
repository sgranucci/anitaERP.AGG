<?php

namespace App\Traits\Compras;

use Illuminate\Support\Collection;

trait ProveedorTrait {

	public static $enumEstado = [
		'0' => 'Activo',
		'1' => 'Suspendido',
		];

	public static $enumTipoAlta = [
		['id' => '1', 'valor' => 'D', 'nombre'  => 'DEFINITIVO'],
		['id' => '2', 'valor' => 'P', 'nombre'  => 'PROVISORIO'],
			];

	public static $enumRetieneiva = [
		'N' => 'No retiene iva',
		'S' => 'Retiene iva',
		];

	public static $enumRetieneganancia = [
		'S' => 'Retiene ganancias',
		'N' => 'No retiene ganancias',
		];

	public static $enumCondicionganancia = [
		'I' => 'Inscripto',
		'N' => 'No inscripto',
		'C' => 'Condominio/SH'
		];

	public static $enumRetienesuss = [
		'N' => 'No retiene suss',
		'S' => 'Retiene suss',
		];

	public static $enumAgentePercepcioniva = [
		'0' => 'No informa',
		'S' => 'Percibe iva',
		'N' => 'No percibe iva'
		];
	
	public static $enumAgentePercepcionIIBB = [
		'0' => 'No informa',
		'S' => 'Percibe IIBB',
		'N' => 'No percibe IIBB'
		];

}

