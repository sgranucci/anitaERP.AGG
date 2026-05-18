<?php

namespace App\Traits\Ventas;

trait ClienteTrait {

	public const ESTADO_ACTIVO = '0';

	public static $enumRetieneiva = [
		'N' => 'Percibir Iva',
		'S' => 'No Percibir Iva',
		];

	public static $enumCondicioniibb = [
		'L' => 'Local',
		'C' => 'Convenio',
		'E' => 'Exento',
		'N' => 'No retener',
		];

	public static $enumVaweb = [
		'S' => 'Si va a web',
		'N' => 'No va a web',
		];

	public static $enumEstado = [
		'0' => 'Activo',
		'1' => 'Suspendido',
		];

	public static $enumModoFacturacion = [
		'N' => 'Normal',
		'C' => 'Factura de crédito FCE',
		];

	public static $enumCajaEspecial = [
		'N' => 'No lleva caja especial',
		'S' => 'Lleva caja especial',
		];

	public static $enumEmiteCertificado = [		
		'S' => "Emite Certificado",
		'N' => "No Emite Certificado"
		];

	public static $enumEmiteNotaDeCredito = [
		'S' => "Emite Nota de Credito",
		'N' => "No Emite Nota de Credito"
		];

	public static $enumAgregaBonificacion = [
		'S' => "Agrega Bonificacion",
		'N' => "No Agrega Bonificacion"
		];

	/**
	 * Clientes habilitados (estado activo, nombre cargado).
	 */
	public function scopeActivos($query)
	{
		$table = $query->getModel()->getTable();

		return $query->where($table.'.estado', self::ESTADO_ACTIVO)
			->where($table.'.nombre', '!=', ' ');
	}
}
