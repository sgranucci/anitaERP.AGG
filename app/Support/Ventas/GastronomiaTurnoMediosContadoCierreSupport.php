<?php

namespace App\Support\Ventas;

use App\Models\Caja\Cuentacaja;
use InvalidArgumentException;

/**
 * Montos contados por el cajero al cierre definitivo de turno (arqueo por medio de pago).
 */
final class GastronomiaTurnoMediosContadoCierreSupport
{
    /**
     * @param  mixed  $input  Request medios_contado: list<array{cuentacaja_id:int,monto?:float,contado?:float}>
     * @param  array<string, mixed>  $totalesTurno
     * @return list<array{cuentacaja_id:int, codigo:string, nombre:string, esperado:float, contado:float}>|null
     */
    public static function normalizarParaGuardar(mixed $input, array $totalesTurno, int $empresaId): ?array
    {
        if (! is_array($input) || $input === []) {
            return null;
        }

        /** @var array<int, array{cuentacaja_id:int, codigo:string, nombre:string, total:float}> $esperados */
        $esperados = [];
        foreach ($totalesTurno['por_medio_pago'] ?? [] as $p) {
            if (! is_array($p)) {
                continue;
            }
            $ccId = (int) ($p['cuentacaja_id'] ?? 0);
            if ($ccId <= 0) {
                continue;
            }
            $esperados[$ccId] = $p;
        }

        if ($esperados === []) {
            return null;
        }

        /** @var array<int, float> $contados */
        $contados = [];
        foreach ($input as $row) {
            if (! is_array($row)) {
                continue;
            }
            $ccId = (int) ($row['cuentacaja_id'] ?? 0);
            if ($ccId <= 0) {
                continue;
            }
            if (! Cuentacaja::existeParaEmpresa($ccId, $empresaId)) {
                throw new InvalidArgumentException(
                    'La cuenta de caja #'.$ccId.' no existe o no pertenece a la empresa del turno.'
                );
            }
            $contados[$ccId] = round((float) ($row['monto'] ?? $row['contado'] ?? 0), 2);
        }

        $salida = [];
        foreach ($esperados as $ccId => $p) {
            $nombre = trim((string) ($p['nombre'] ?? ''));
            $codigo = trim((string) ($p['codigo'] ?? ''));
            $salida[] = [
                'cuentacaja_id' => $ccId,
                'codigo' => $codigo,
                'nombre' => $nombre !== '' ? $nombre : $codigo,
                'esperado' => round((float) ($p['total'] ?? 0), 2),
                'contado' => array_key_exists($ccId, $contados)
                    ? $contados[$ccId]
                    : round((float) ($p['total'] ?? 0), 2),
            ];
        }

        usort($salida, fn ($a, $b) => strcmp((string) $a['nombre'], (string) $b['nombre']));

        return $salida;
    }

    /**
     * @param  mixed  $json
     * @return list<array{cuentacaja_id:int, codigo:string, nombre:string, esperado:float, contado:float}>|null
     */
    public static function desdeAlmacenado(mixed $json): ?array
    {
        if (! is_array($json) || $json === []) {
            return null;
        }

        $salida = [];
        foreach ($json as $row) {
            if (! is_array($row)) {
                continue;
            }
            $ccId = (int) ($row['cuentacaja_id'] ?? 0);
            if ($ccId <= 0) {
                continue;
            }
            $nombre = trim((string) ($row['nombre'] ?? ''));
            $codigo = trim((string) ($row['codigo'] ?? ''));
            $salida[] = [
                'cuentacaja_id' => $ccId,
                'codigo' => $codigo,
                'nombre' => $nombre !== '' ? $nombre : $codigo,
                'esperado' => round((float) ($row['esperado'] ?? $row['total'] ?? 0), 2),
                'contado' => round((float) ($row['contado'] ?? $row['monto'] ?? 0), 2),
            ];
        }

        return $salida === [] ? null : $salida;
    }

    /**
     * @param  array<string, mixed>  $totalesTurno
     * @param  list<array{cuentacaja_id:int, codigo:string, nombre:string, esperado:float, contado:float}>|null  $mediosContado
     * @return array<string, mixed>
     */
    public static function enriquecerTotalesConContado(array $totalesTurno, ?array $mediosContado): array
    {
        if ($mediosContado === null || $mediosContado === []) {
            return $totalesTurno;
        }

        $porContado = [];
        foreach ($mediosContado as $m) {
            $porContado[(int) $m['cuentacaja_id']] = $m;
        }

        $porMedio = $totalesTurno['por_medio_pago'] ?? [];
        if (! is_array($porMedio)) {
            $porMedio = [];
        }

        $enriquecidos = [];
        foreach ($porMedio as $p) {
            if (! is_array($p)) {
                continue;
            }
            $ccId = (int) ($p['cuentacaja_id'] ?? 0);
            $copia = $p;
            if ($ccId > 0 && isset($porContado[$ccId])) {
                $copia['esperado'] = (float) $porContado[$ccId]['esperado'];
                $copia['contado'] = (float) $porContado[$ccId]['contado'];
            }
            $enriquecidos[] = $copia;
        }

        if ($enriquecidos === []) {
            foreach ($mediosContado as $m) {
                $enriquecidos[] = [
                    'cuentacaja_id' => (int) $m['cuentacaja_id'],
                    'codigo' => (string) $m['codigo'],
                    'nombre' => (string) $m['nombre'],
                    'total' => (float) $m['esperado'],
                    'esperado' => (float) $m['esperado'],
                    'contado' => (float) $m['contado'],
                ];
            }
        }

        $totalesTurno['por_medio_pago'] = $enriquecidos;
        $totalesTurno['arqueo_medios_cierre'] = true;

        return $totalesTurno;
    }
}
