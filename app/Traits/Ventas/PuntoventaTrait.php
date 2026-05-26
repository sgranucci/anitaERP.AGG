<?php

namespace App\Traits\Ventas;

trait PuntoventaTrait {

	public static $enumModoFacturacion = [
		'M' => 'Manual',
		'C' => 'Factura electronica CAE',
		'A' => 'Factura electronica CAEA',
		'E' => 'Factura electronica de exportacion',
		'R' => 'Remito Factura',
		'L' => 'Remito Manual',
		'O' => 'Otros',
		'I' => 'Inactiva'
		];

	public static $enumEstado = [
			'A' => 'Activa',
			'S' => 'Suspendida',
			];

	/** Valores usados en puntoventa.webservice (FacturaelectronicaService / servicios ARCA SOAP). */
	public static $enumWebservice = [
		'wsfev1' => 'WSFE v1 — Comprobantes nacionales (ARCA SOAP)',
		'wsmtxca' => 'WSMTXCA — Factura con detalle / ítems (ARCA SOAP)',
		'wsfex_v1' => 'WSFEX v1 — Factura de exportación (módulo AFIP)',
	];

	/** Cantidad de dígitos del número de punto de venta ante ARCA/AFIP. */
	public const CODIGO_ARCA_DIGITOS = 5;

	/**
	 * Normaliza el código de punto de venta al formato ARCA (ceros a la izquierda).
	 */
	public static function normalizarCodigoArca(?string $codigo): ?string
	{
		$numerico = (int) preg_replace('/\D+/', '', (string) $codigo);
		if ($numerico < 1) {
			return null;
		}

		return str_pad((string) $numerico, self::CODIGO_ARCA_DIGITOS, '0', STR_PAD_LEFT);
	}
}
