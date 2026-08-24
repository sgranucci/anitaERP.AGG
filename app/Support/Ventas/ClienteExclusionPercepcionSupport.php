<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente_Exclusion_Percepcion;
use Carbon\Carbon;

/**
 * Exclusiones / atenuaciones de percepción cargadas en el cliente (solo ERP).
 * El porcentaje es la alícuota que se cobra durante la vigencia (0 = no percibe).
 */
final class ClienteExclusionPercepcionSupport
{
    /** @var array<int, list<array{tipo: string, provincia_id: int|null, porcentaje: float, desde: string, hasta: string}>> */
    private static array $cachePorCliente = [];

    public static function tasaIva(?int $clienteId, mixed $fecha): ?float
    {
        return self::tasa($clienteId, 'IVA', null, $fecha);
    }

    public static function tasaIibb(?int $clienteId, ?int $provinciaId, mixed $fecha): ?float
    {
        if ($provinciaId === null || $provinciaId <= 0) {
            return null;
        }

        return self::tasa($clienteId, 'IIBB', $provinciaId, $fecha);
    }

    public static function resetCache(): void
    {
        self::$cachePorCliente = [];
    }

    private static function tasa(?int $clienteId, string $tipo, ?int $provinciaId, mixed $fecha): ?float
    {
        if ($clienteId === null || $clienteId <= 0) {
            return null;
        }

        $fechaYmd = self::normalizarFecha($fecha) ?? Carbon::today()->format('Y-m-d');
        $elegida = null;

        foreach (self::filasDelCliente($clienteId) as $fila) {
            if ($fila['tipo'] !== $tipo) {
                continue;
            }
            if ($tipo === 'IVA') {
                if ($fila['provincia_id'] !== null) {
                    continue;
                }
            } elseif ((int) $fila['provincia_id'] !== (int) $provinciaId) {
                continue;
            }
            if ($fila['desde'] !== '' && $fila['desde'] > $fechaYmd) {
                continue;
            }
            if ($fila['hasta'] !== '' && $fila['hasta'] < $fechaYmd) {
                continue;
            }
            if ($elegida === null || $fila['desde'] >= $elegida['desde']) {
                $elegida = $fila;
            }
        }

        return $elegida === null ? null : $elegida['porcentaje'];
    }

    /**
     * @return list<array{tipo: string, provincia_id: int|null, porcentaje: float, desde: string, hasta: string}>
     */
    private static function filasDelCliente(int $clienteId): array
    {
        if (array_key_exists($clienteId, self::$cachePorCliente)) {
            return self::$cachePorCliente[$clienteId];
        }

        $filas = [];
        $registros = Cliente_Exclusion_Percepcion::query()
            ->where('cliente_id', $clienteId)
            ->get(['tipo', 'provincia_id', 'porcentaje', 'desdefecha', 'hastafecha']);

        foreach ($registros as $registro) {
            $filas[] = [
                'tipo' => strtoupper((string) $registro->tipo),
                'provincia_id' => $registro->provincia_id !== null ? (int) $registro->provincia_id : null,
                'porcentaje' => (float) $registro->porcentaje,
                'desde' => self::normalizarFecha($registro->desdefecha) ?? '',
                'hasta' => self::normalizarFecha($registro->hastafecha) ?? '',
            ];
        }

        self::$cachePorCliente[$clienteId] = $filas;

        return $filas;
    }

    private static function normalizarFecha(mixed $fecha): ?string
    {
        if ($fecha === null || $fecha === '' || $fecha === '0000-00-00') {
            return null;
        }

        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $fecha)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
