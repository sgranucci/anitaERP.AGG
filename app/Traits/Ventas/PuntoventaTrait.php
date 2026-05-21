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
}
