<?php

namespace App\Traits\Ventas;

trait Cliente_Cm05Trait {

	public static $enumTipoPercepcion = [
		'P' => 'Percibe por padrón',
		'C' => 'Percibe por coeficiente',
		'N' => 'No percibe'
		];

	public static $enumCertificadoNoRetencion = [
		'N' => 'No tiene certificado',
		'S' => 'Si tiene certificado',
		];

}
