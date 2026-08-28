<?php

namespace App\Traits\Ventas;

trait ClienteTrait {

	public const ESTADO_ACTIVO = '0';

	public const ESTADO_SUSPENDIDO = '1';

	/** Cliente con problemas ARCA/AFIP pero habilitado para facturar (Anita: clim_estado_cli = R). */
	public const ESTADO_REGULARIZADO = 'R';

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
		'R' => 'Regularizado',
		];

	/** @return list<string> */
	public static function estadosHabilitadosFacturacion(): array
	{
		return [self::ESTADO_ACTIVO, self::ESTADO_REGULARIZADO];
	}

	public static function estaHabilitadoParaFacturacion(?string $estado): bool
	{
		return in_array((string) ($estado ?? ''), self::estadosHabilitadosFacturacion(), true);
	}

	public static $enumModoFacturacion = [
		'N' => 'Normal',
		'C' => 'Factura de crédito FCE',
		];

	/**
	 * Alias de la columna `modofacturacion`.
	 * La facturación usaba `$cliente->modoFacturacion` (camelCase) y Eloquent devolvía null.
	 */
	public function getModoFacturacionAttribute(): ?string
	{
		$valor = $this->attributes['modofacturacion'] ?? null;

		return $valor !== null && $valor !== '' ? (string) $valor : null;
	}

	public function esReceptorFacturaCreditoFce(): bool
	{
		return ($this->modofacturacion ?? '') === 'C';
	}

	public static $enumCajaEspecial = [
		'N' => 'No lleva caja especial',
		'S' => 'Lleva caja especial',
		];

	public static $enumEmiteCertificado = [
		'S' => 'Sí',
		'N' => 'No',
		];

	public static function normalizarEmiteCertificado(mixed $valor): string
	{
		$v = mb_strtolower(trim((string) ($valor ?? '')));
		if (in_array($v, ['s', 'si', 'sí', 'emite certificado'], true)) {
			return 'S';
		}

		return 'N';
	}

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

		return $query->whereIn($table.'.estado', self::estadosHabilitadosFacturacion())
			->where($table.'.nombre', '!=', ' ');
	}
}
