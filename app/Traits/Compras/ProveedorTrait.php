<?php

namespace App\Traits\Compras;

use Illuminate\Support\Collection;

trait ProveedorTrait {

	public const ESTADO_ACTIVO = 'Activo';

	public const ESTADO_SUSPENDIDO = 'Suspendido';

	public const ESTADO_ALTA_PENDIENTE = 'Alta Pendiente';

	/** Proveedor con problemas ARCA/AFIP pero habilitado para operar (Anita: prom_estado_pro = 3). */
	public const ESTADO_REGULARIZADO = 'Regularizado';

	public static $enumEstado = [
		'0' => 'Activo',
		'1' => 'Suspendido',
		'2' => 'Alta Pendiente',
		'3' => 'Regularizado'
		];

	/**
	 * Estados de proveedor habilitados para elegir en compras (requisición, OC, modales).
	 * Literales (no self::CONST): en PHP 8.3+ una propiedad estática del trait con
	 * self::CONST revienta al leerse ("Cannot access trait constant self::… directly").
	 *
	 * @var list<string>
	 */
	public static $estadosHabilitadosOperacion = [
		'Activo',
		'Regularizado',
	];

	/** @return list<string> */
	public static function estadosHabilitadosOperacion(): array
	{
		return ['Activo', 'Regularizado'];
	}

	/** @return list<string> */
	public static function estadosHabilitadosOperacionCodigosYTextos(): array
	{
		return ['0', '3', 'Activo', 'Regularizado'];
	}

	public static function estaHabilitadoParaOperacion(?string $estado): bool
	{
		return in_array((string) ($estado ?? ''), self::estadosHabilitadosOperacionCodigosYTextos(), true);
	}

	public static function esRegularizado(?string $estado): bool
	{
		return in_array((string) ($estado ?? ''), ['3', 'Regularizado'], true);
	}

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

	public static $enumSemaforo = [
		'V' => 'Verde',
		'A' => 'Amarillo',
		'R' => 'Rojo'
		];
	
	public static $enumRegimenfacturacion = [
		'1' => 'RG 3419',
		'2' => 'FCE'
		];
}

