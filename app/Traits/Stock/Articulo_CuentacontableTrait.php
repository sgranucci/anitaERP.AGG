<?php

namespace App\Traits\Stock;

trait Articulo_CuentacontableTrait {

	public static $enumTipoImputacion = [
		['id' => '1', 'valor' => 'V', 'nombre'  => 'VENTAS'],
		['id' => '2', 'valor' => 'C', 'nombre'  => 'COMPRAS'],
		['id' => '3', 'valor' => 'G', 'nombre'  => 'GASTOS'],
		['id' => '4', 'valor' => 'I', 'nombre'  => 'IMPUESTOS INTERNOS'],
			];
}

