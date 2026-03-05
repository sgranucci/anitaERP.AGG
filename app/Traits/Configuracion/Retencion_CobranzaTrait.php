<?php

namespace App\Traits\Configuracion;

trait Retencion_CobranzaTrait {

	public static $enumTipoRetencion = [
		'B' => 'Ingresos Brutos',
		'G' => 'Ganancias',
		'I' => 'Iva',
		'S' => 'Seguridad Social',
		];

}
