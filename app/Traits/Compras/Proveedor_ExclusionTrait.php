<?php

namespace App\Traits\Compras;

use Illuminate\Support\Collection;

trait Proveedor_ExclusionTrait {

	public static $enumTipoRetencion = [
		'G' => 'Ganancias',
		'I' => 'Iva',
		'S' => 'SUSS',
		'B' => 'Ingresos Brutos'
		];

}

