<?php

declare(strict_types=1);

namespace App\Support\Caja\AnitaSync;

use App\Models\Caja\Bingo\BingoCarton;
use App\Models\Caja\Bingo\BingoConceptoRendicion;
use Carbon\Carbon;

/**
 * Mapeo Anita (rendbingo / rendcarton / rendpremio) → payload ERP.
 */
final class RendicionBingoAnitaImportMapper
{
    public const FUENTE_NRO_OPER = 'anita_nativo';

    /** Anita usa 60 para el premio 65%; el maestro ERP quedó en 55. */
    private const ALIAS_CODIGO_ANITA = [60 => 55];

    /**
     * @param  list<object>  $filas
     * @param  iterable<BingoCarton>  $cartones
     * @return list<array{carton_id: int, codigo: string, nombre: string, cantidad: int, precio_unitario: float}>
     */
    public static function lineasCarton(array $filas, iterable $cartones): array
    {
        $porCodigo = [];
        foreach ($cartones as $carton) {
            $codigoAnita = (int) ($carton->codigo_anita ?? 0);
            if ($codigoAnita > 0) {
                $porCodigo[$codigoAnita] = $carton;
            }
        }

        $out = [];
        foreach ($filas as $fila) {
            $codigoAnita = (int) ($fila->rendc_carton ?? 0);
            $cantidad = (int) ($fila->rendc_cantidad ?? 0);
            if ($codigoAnita <= 0 || $cantidad <= 0) {
                continue;
            }

            $carton = $porCodigo[$codigoAnita] ?? null;
            if ($carton === null) {
                continue;
            }

            $precio = round((float) ($fila->rendc_valor ?? $carton->precio_unitario ?? 0), 2);
            if ($precio <= 0) {
                $precio = round((float) ($carton->precio_unitario ?? 0), 2);
            }

            $out[] = [
                'carton_id' => (int) $carton->id,
                'codigo' => (string) $carton->codigo,
                'nombre' => (string) $carton->nombre,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
            ];
        }

        return $out;
    }

    /**
     * Premios Anita + ajustes de cabecera → montos manuales por concepto_id ERP.
     *
     * @param  list<object>  $filasPremio
     * @param  iterable<BingoConceptoRendicion>  $conceptos
     * @return array<int, float>
     */
    public static function montosManuales(array $filasPremio, iterable $conceptos, object $cabecera): array
    {
        $porCodigoAnita = [];
        $porCodigoErp = [];
        foreach ($conceptos as $concepto) {
            $porCodigoErp[strtoupper(trim((string) ($concepto->codigo ?? '')))] = $concepto;
            $codigoAnita = (int) ($concepto->codigo_anita ?? 0);
            if ($codigoAnita > 0) {
                $porCodigoAnita[$codigoAnita] = $concepto;
            }
        }

        $montos = [];
        foreach ($filasPremio as $fila) {
            $codigoAnita = self::normalizarCodigoAnita((int) ($fila->rendp_concepto ?? 0));
            $concepto = $porCodigoAnita[$codigoAnita] ?? null;
            if ($concepto === null || $concepto->es_saldo_rendicion) {
                continue;
            }
            if (($concepto->base_calculo ?? '') !== BingoConceptoRendicion::BASE_MANUAL) {
                continue;
            }

            $monto = self::montoPremio($fila);
            $montos[(int) $concepto->id] = round(
                ($montos[(int) $concepto->id] ?? 0) + $monto,
                2
            );
        }

        foreach ([
            'VALES' => (float) ($cabecera->rendb_vales ?? 0),
            'REFUERZO' => (float) ($cabecera->rendb_refuer_prest ?? 0),
        ] as $codigo => $monto) {
            $concepto = $porCodigoErp[$codigo] ?? null;
            if ($concepto === null || $concepto->es_saldo_rendicion) {
                continue;
            }
            $montos[(int) $concepto->id] = round(abs($monto), 2);
        }

        return $montos;
    }

    /**
     * Ajusta sobrante/redondeo para que el saldo coincida con el depósito Anita.
     *
     * @param  array<int, float>  $montos
     * @param  iterable<BingoConceptoRendicion>  $conceptos
     * @return array<int, float>
     */
    public static function aplicarAjusteDeposito(
        array $montos,
        iterable $conceptos,
        float $saldoSinAjuste,
        float $deposito,
    ): array {
        $porCodigo = [];
        foreach ($conceptos as $concepto) {
            $porCodigo[strtoupper(trim((string) ($concepto->codigo ?? '')))] = $concepto;
        }

        $diff = round($deposito - $saldoSinAjuste, 2);
        $sobrante = $porCodigo['SOBRANTE'] ?? null;
        $redondeo = $porCodigo['REDONDEO'] ?? null;

        if ($sobrante !== null) {
            $montos[(int) $sobrante->id] = $diff > 0.009 ? $diff : 0.0;
        }
        if ($redondeo !== null) {
            $montos[(int) $redondeo->id] = $diff < -0.009 ? abs($diff) : 0.0;
        }

        return $montos;
    }

    public static function fechaJornadaDesdeEntera(int $fechaEntera): string
    {
        $raw = sprintf('%08d', $fechaEntera);

        return sprintf(
            '%s-%s-%s',
            substr($raw, 0, 4),
            substr($raw, 4, 2),
            substr($raw, 6, 2),
        );
    }

    public static function fechaHoraRendicion(object $cabecera): Carbon
    {
        $fechaEntera = (int) ($cabecera->rendb_fecha ?? 0);
        $hora = trim((string) ($cabecera->rendb_hora ?? ''));
        $fecha = self::fechaJornadaDesdeEntera($fechaEntera > 0 ? $fechaEntera : (int) date('Ymd'));
        if ($hora === '' || ! preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $hora)) {
            $hora = '00:00:00';
        }
        if (substr_count($hora, ':') === 1) {
            $hora .= ':00';
        }

        return Carbon::parse($fecha.' '.$hora);
    }

    public static function empresaIdDesdeCodigoAnita(int $codigoAnita, iterable $empresas): ?int
    {
        if ($codigoAnita <= 0) {
            return null;
        }

        foreach ($empresas as $empresa) {
            $codigo = trim((string) ($empresa->codigo ?? ''));
            if ($codigo !== '' && ctype_digit($codigo) && (int) $codigo === $codigoAnita) {
                return (int) $empresa->id;
            }
        }

        foreach ($empresas as $empresa) {
            if ((int) $empresa->id === $codigoAnita) {
                return (int) $empresa->id;
            }
        }

        return null;
    }

    public static function normalizarCodigoAnita(int $codigoAnita): int
    {
        return self::ALIAS_CODIGO_ANITA[$codigoAnita] ?? $codigoAnita;
    }

    private static function montoPremio(object $fila): float
    {
        $real = round((float) ($fila->rendp_real ?? 0), 2);
        if (abs($real) >= 0.01) {
            return abs($real);
        }

        return abs(round((float) ($fila->rendp_pagado ?? 0), 2));
    }
}
