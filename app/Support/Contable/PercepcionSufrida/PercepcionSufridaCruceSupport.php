<?php

declare(strict_types=1);

namespace App\Support\Contable\PercepcionSufrida;

/**
 * Cruza cada línea del mayor contra el reporte por comprobante e importe.
 * Lo que está en el reporte y no en el mayor no se lista ni se informa.
 */
final class PercepcionSufridaCruceSupport
{
    /**
     * @param  list<array<string, mixed>>  $mayor
     * @param  list<array<string, mixed>>  $reporte
     * @return array{
     *     cruzados: list<array<string, mixed>>,
     *     diferencias: list<array<string, mixed>>,
     *     totales: array<string, float|int>
     * }
     */
    public static function cruzar(array $mayor, array $reporte, bool $porJurisdiccion): array
    {
        $pool = [];
        foreach ($reporte as $linea) {
            $clave = (string) ($linea['clave_comprobante'] ?? '');
            if ($clave === '') {
                continue;
            }
            $pool[$clave][] = $linea;
        }

        $gruposMayor = [];
        foreach ($mayor as $linea) {
            $gruposMayor[(string) ($linea['clave_comprobante'] ?? '')][] = $linea;
        }

        $cruzados = [];
        $diferencias = [];

        foreach ($gruposMayor as $clave => $lineasMayor) {
            $disponibles = $pool[$clave] ?? [];
            $usados = [];
            $sinMatch = [];

            foreach ($lineasMayor as $lineaMayor) {
                $importeMayor = round((float) ($lineaMayor['importe'] ?? 0), 2);
                $idx = self::indiceImporteExacto($disponibles, $usados, $importeMayor);
                if ($idx === null) {
                    $sinMatch[] = $lineaMayor;
                    continue;
                }
                $usados[$idx] = true;
                $cruzados[] = self::lineaCruzada($lineaMayor, $disponibles[$idx]);
            }

            $restantes = [];
            foreach ($disponibles as $idx => $linea) {
                if (! isset($usados[$idx])) {
                    $restantes[] = $linea;
                }
            }

            foreach ($sinMatch as $lineaMayor) {
                if ($porJurisdiccion && $restantes !== [] && self::sumaNativa($restantes, (float) ($lineaMayor['importe'] ?? 0))) {
                    foreach ($restantes as $reporteLinea) {
                        $cruzados[] = self::lineaCruzada($lineaMayor, $reporteLinea);
                    }
                    $restantes = [];
                    continue;
                }
                $importeMayor = self::pesos($lineaMayor);
                $reporteLinea = array_shift($restantes);
                $importeReporte = $reporteLinea === null ? 0.0 : self::pesosDeParte($lineaMayor, $reporteLinea);
                $diferencias[] = self::filaDiferencia($lineaMayor, $reporteLinea, $importeMayor, $importeReporte);
            }
        }

        $totalMayor = round(array_sum(array_map(
            static fn (array $l) => self::pesos($l),
            $mayor,
        )), 2);
        $totalDif = round(array_sum(array_map(
            static fn (array $l) => (float) ($l['diferencia'] ?? 0),
            $diferencias,
        )), 2);

        $cruzado901 = 0.0;
        $cruzado902 = 0.0;
        $cruzado = 0.0;
        foreach ($cruzados as $linea) {
            $importe = self::pesos($linea);
            $cruzado += $importe;
            if (! $porJurisdiccion) {
                continue;
            }
            $jur = (int) ($linea['jurisdiccion'] ?? 0);
            if ($jur === 901) {
                $cruzado901 += $importe;
            } elseif ($jur === 902) {
                $cruzado902 += $importe;
            }
        }

        $cruzado = round($cruzado, 2);
        $cruzado901 = round($cruzado901, 2);
        $cruzado902 = round($cruzado902, 2);
        $desvioCierre = $porJurisdiccion
            ? round($totalMayor - $cruzado901 - $cruzado902 - $totalDif, 2)
            : round($totalMayor - $cruzado - $totalDif, 2);

        return [
            'cruzados' => $cruzados,
            'diferencias' => $diferencias,
            'totales' => [
                'mayor' => $totalMayor,
                'cruzado' => $cruzado,
                'cruzado_901' => $cruzado901,
                'cruzado_902' => $cruzado902,
                'diferencias' => $totalDif,
                'desvio_cierre' => $desvioCierre,
                'lineas_mayor' => count($mayor),
                'lineas_cruzadas' => count($cruzados),
                'lineas_diferencia' => count($diferencias),
            ],
        ];
    }

    /**
     * El importe nativo del mayor es la suma de las partes que quedaron sin cruzar.
     *
     * @param  list<array<string, mixed>>  $partes
     */
    private static function sumaNativa(array $partes, float $importeMayor): bool
    {
        if (count($partes) < 2) {
            return false;
        }
        $suma = 0.0;
        foreach ($partes as $parte) {
            $suma += round((float) ($parte['importe'] ?? 0), 2);
        }

        return abs(round($suma, 2) - round($importeMayor, 2)) < 0.02;
    }

    /**
     * @param  array<string, mixed>  $mayor
     * @param  array<string, mixed>  $reporte
     * @return array<string, mixed>
     */
    private static function lineaCruzada(array $mayor, array $reporte): array
    {
        $pesos = self::pesosDeParte($mayor, $reporte);

        return array_merge($mayor, [
            'jurisdiccion' => (int) ($reporte['jurisdiccion'] ?? $mayor['jurisdiccion'] ?? 0),
            'importe' => $pesos,
            'importe_pesos' => $pesos,
            'importe_reporte' => $pesos,
            'cuit' => self::primeroNoVacio($mayor, $reporte, 'cuit'),
            'emisor' => self::primeroNoVacio($mayor, $reporte, 'emisor'),
            'emisor_nombre' => self::primeroNoVacio($mayor, $reporte, 'emisor_nombre'),
            'descripcion' => self::primeroNoVacio($reporte, $mayor, 'descripcion'),
            'letra' => self::primeroNoVacio($reporte, $mayor, 'letra') !== ''
                ? self::primeroNoVacio($reporte, $mayor, 'letra')
                : ($mayor['letra'] ?? ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    private static function pesos(array $linea): float
    {
        if (array_key_exists('importe_pesos', $linea)) {
            return round((float) $linea['importe_pesos'], 2);
        }

        return round((float) ($linea['importe'] ?? 0), 2);
    }

    /**
     * La parte del reporte, en pesos. Si el mayor está en moneda extranjera,
     * la cotización del movimiento manda sobre la del comprobante.
     *
     * @param  array<string, mixed>  $mayor
     * @param  array<string, mixed>  $reporte
     */
    private static function pesosDeParte(array $mayor, array $reporte): float
    {
        $nativo = round((float) ($reporte['importe'] ?? 0), 2);
        $cotizacion = self::cotizacionEnPesos($mayor);
        if ($cotizacion <= 1.0001) {
            $cotizacion = self::cotizacionEnPesos($reporte);
        }
        if ($cotizacion <= 1.0001) {
            return self::pesos($reporte) !== 0.0 && abs(self::pesos($reporte) - $nativo) > 0.009
                ? self::pesos($reporte)
                : $nativo;
        }

        return round($nativo * $cotizacion, 2);
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    private static function cotizacionEnPesos(array $linea): float
    {
        if ((int) ($linea['moneda_id'] ?? 1) <= 1) {
            return 1.0;
        }

        return (float) ($linea['cotizacion'] ?? 0);
    }

    /**
     * @param  list<array<string, mixed>>  $pool
     * @param  array<int, true>  $usados
     */
    private static function indiceImporteExacto(array $pool, array $usados, float $importe): ?int
    {
        foreach ($pool as $idx => $linea) {
            if (isset($usados[$idx])) {
                continue;
            }
            $otro = round((float) ($linea['importe'] ?? 0), 2);
            if (abs($otro - $importe) < 0.009) {
                return (int) $idx;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mayor
     * @param  array<string, mixed>|null  $reporte
     * @return array<string, mixed>
     */
    private static function filaDiferencia(array $mayor, ?array $reporte, float $importeMayor, float $importeReporte): array
    {
        return [
            'fecha' => (string) ($mayor['fecha'] ?? ''),
            'tipo' => (string) ($mayor['tipo'] ?? ''),
            'comprobante' => (string) ($mayor['comprobante'] ?? ''),
            'emisor' => (string) ($mayor['emisor'] ?? ''),
            'emisor_nombre' => (string) ($mayor['emisor_nombre'] ?? ''),
            'cuit' => (string) ($mayor['cuit'] ?? ''),
            'descripcion' => (string) ($mayor['descripcion'] ?? ''),
            'importe_mayor' => $importeMayor,
            'importe_reporte' => $importeReporte,
            'diferencia' => round($importeMayor - $importeReporte, 2),
            'jurisdiccion' => (int) ($reporte['jurisdiccion'] ?? $mayor['jurisdiccion'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function primeroNoVacio(array $a, array $b, string $campo): string
    {
        $va = trim((string) ($a[$campo] ?? ''));
        if ($va !== '') {
            return $va;
        }

        return trim((string) ($b[$campo] ?? ''));
    }
}
