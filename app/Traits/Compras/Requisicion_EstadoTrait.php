<?php

namespace App\Traits\Compras;

trait Requisicion_EstadoTrait
{
	public static $enumEstado = [
		['id' => '1', 'valor' => 'P', 'nombre' => 'PENDIENTE'],
		['id' => '2', 'valor' => 'C', 'nombre' => 'CUMPLIDA'],
		['id' => '3', 'valor' => 'S', 'nombre' => 'SUSPENDIDA'],
		['id' => '4', 'valor' => 'K', 'nombre' => 'EN COMPRAS'],
		['id' => '5', 'valor' => 'R', 'nombre' => 'EN ARBOL APROBACION'],
		['id' => '6', 'valor' => 'A', 'nombre' => 'APROBADA'],
		['id' => '7', 'valor' => 'O', 'nombre' => 'GENERO ORDEN COMPRA'],
	];

	/** Estados disponibles en cada nivel del árbol de aprobación de requisiciones (opcional por nivel). */
	public static function estadosArbolRequisicionConfigurables(): array
	{
		$valores = ['P', 'K', 'R', 'A'];
		$salida = [];
		foreach (self::$enumEstado as $row) {
			if (in_array($row['valor'], $valores, true)) {
				$salida[] = $row;
			}
		}

		return $salida;
	}

	public static function esNombreEstadoValido(string $nombre): bool
	{
		foreach (self::$enumEstado as $row) {
			if ($row['nombre'] === $nombre) {
				return true;
			}
		}

		return false;
	}
}
