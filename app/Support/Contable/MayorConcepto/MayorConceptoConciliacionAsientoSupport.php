<?php

namespace App\Support\Contable\MayorConcepto;

use Illuminate\Support\Facades\DB;

/**
 * Conciliación mayor analítico vs mayor por concepto — regla de control mensual.
 *
 * Por asiento: Neto_Analitico + Neto_Concepto = 0
 * (equivalente a Debe concepto = Haber analítico y Haber concepto = Debe analítico).
 *
 * Excluye asiento 0 (remanente mayor plano). Tolerancia default ±1,00.
 */
class MayorConceptoConciliacionAsientoSupport
{
    public const TOLERANCIA_DEFAULT = 1.0;

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public function conciliar(array $resultado, int $empresaId, float $tolerancia = self::TOLERANCIA_DEFAULT): array
    {
        $analitico = $this->normalizarAnaliticoPorAsiento($resultado['analitico_por_asiento'] ?? []);
        $concepto = $this->totalesConceptoPorAsiento($resultado);

        $numeros = array_unique(array_merge(array_keys($analitico), array_keys($concepto)));
        sort($numeros, SORT_NUMERIC);

        $filas = [];
        $descuadrados = 0;

        foreach ($numeros as $nro) {
            $a = $analitico[$nro] ?? ['debe' => 0.0, 'haber' => 0.0, 'fecha_min' => null, 'cuentas' => []];
            $c = $concepto[$nro] ?? ['debe' => 0.0, 'haber' => 0.0, 'fecha_min' => null, 'cuentas' => []];

            $debeA = (float) ($a['debe'] ?? 0);
            $haberA = (float) ($a['haber'] ?? 0);
            $debeC = (float) ($c['debe'] ?? 0);
            $haberC = (float) ($c['haber'] ?? 0);
            $netoA = round($debeA - $haberA, 2);
            $netoC = round($debeC - $haberC, 2);
            $diferencia = round($netoA + $netoC, 2);
            $cuadra = abs($diferencia) <= $tolerancia;

            if (! $cuadra) {
                $descuadrados++;
            }

            $enAnalitico = isset($analitico[$nro]);
            $enConcepto = isset($concepto[$nro]);
            $origen = match (true) {
                $enAnalitico && $enConcepto => 'En ambos reportes',
                $enAnalitico => 'Solo en analítico (sin contrapartida)',
                default => 'Solo en concepto (sin línea de tesorería)',
            };

            $fecha = $a['fecha_min'] ?? $c['fecha_min'] ?? null;

            $filas[] = [
                'nro_asiento' => (int) $nro,
                'fecha' => $fecha,
                'fecha_fmt' => $this->formatearFecha($fecha),
                'cuentas_analitico' => implode(', ', $a['cuentas'] ?? []),
                'cuentas_concepto' => implode(', ', $c['cuentas'] ?? []),
                'debe_analitico' => $debeA,
                'haber_analitico' => $haberA,
                'debe_concepto' => $debeC,
                'haber_concepto' => $haberC,
                'neto_analitico' => $netoA,
                'neto_concepto' => $netoC,
                'diferencia' => $diferencia,
                'cuadra' => $cuadra,
                'origen' => $origen,
                'asiento_id' => 0,
            ];
        }

        usort($filas, fn ($x, $y) => abs($y['diferencia']) <=> abs($x['diferencia']));

        $this->enriquecerAsientosErp($filas, $empresaId);

        $filasDescuadradas = array_values(array_filter($filas, fn ($f) => empty($f['cuadra'])));
        $filasCuadradas = array_values(array_filter($filas, fn ($f) => ! empty($f['cuadra'])));
        $analizados = count($filas);
        $cuadrados = $analizados - $descuadrados;

        return [
            'cuadra' => $descuadrados === 0,
            'tolerancia' => $tolerancia,
            'regla' => 'Neto analítico + Neto concepto = 0 por asiento',
            'asientos_analizados' => $analizados,
            'asientos_cuadrados' => $cuadrados,
            'asientos_descuadrados' => $descuadrados,
            'porcentaje_cuadrado' => $analizados > 0 ? round(100 * $cuadrados / $analizados, 1) : 100.0,
            'filas' => $filas,
            'filas_descuadradas' => $filasDescuadradas,
            'filas_cuadradas' => $filasCuadradas,
            'nota' => 'Analítico: cuentas ≤ límite de control (subdiario + ctamov). Concepto: imputaciones del reporte excepto remanente asiento 0.',
        ];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $analitico
     * @return array<int, array<string, mixed>>
     */
    private function normalizarAnaliticoPorAsiento(array $analitico): array
    {
        $normalizado = [];
        foreach ($analitico as $nro => $fila) {
            $n = (int) ($fila['nro_asiento'] ?? $nro);
            if ($n <= 0) {
                continue;
            }
            $normalizado[$n] = $fila;
        }

        return $normalizado;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<int, array{debe: float, haber: float, fecha_min: ?int, cuentas: list<string>}>
     */
    private function totalesConceptoPorAsiento(array $resultado): array
    {
        $porAsiento = [];

        foreach ($resultado['secciones'] ?? [] as $seccion) {
            foreach ($seccion['cuentas'] ?? [] as $cuentaBlock) {
                foreach ($cuentaBlock['lineas'] ?? [] as $ln) {
                    if (($ln['origen'] ?? '') === 'Remanente mayor plano') {
                        continue;
                    }

                    $nro = (int) ($ln['nro_asiento'] ?? 0);
                    if ($nro <= 0) {
                        continue;
                    }

                    if (! isset($porAsiento[$nro])) {
                        $porAsiento[$nro] = [
                            'debe' => 0.0,
                            'haber' => 0.0,
                            'fecha_min' => null,
                            'cuentas' => [],
                        ];
                    }

                    $porAsiento[$nro]['debe'] += (float) ($ln['debe'] ?? 0);
                    $porAsiento[$nro]['haber'] += (float) ($ln['haber'] ?? 0);

                    $fecha = (int) ($ln['fecha'] ?? 0);
                    if ($fecha > 0 && ($porAsiento[$nro]['fecha_min'] === null || $fecha < $porAsiento[$nro]['fecha_min'])) {
                        $porAsiento[$nro]['fecha_min'] = $fecha;
                    }

                    $codigo = trim((string) ($ln['cuenta_codigo'] ?? ''));
                    if ($codigo !== '') {
                        $porAsiento[$nro]['cuentas'][$codigo] = true;
                    }
                }
            }
        }

        foreach ($porAsiento as $nro => $row) {
            $cuentas = array_keys($row['cuentas']);
            sort($cuentas);
            $porAsiento[$nro] = [
                'debe' => round($row['debe'], 2),
                'haber' => round($row['haber'], 2),
                'fecha_min' => $row['fecha_min'],
                'cuentas' => $cuentas,
            ];
        }

        return $porAsiento;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function enriquecerAsientosErp(array &$filas, int $empresaId): void
    {
        if ($filas === [] || $empresaId <= 0) {
            return;
        }

        $numeros = array_values(array_unique(array_map(
            fn (array $f) => (int) ($f['nro_asiento'] ?? 0),
            $filas,
        )));
        $numeros = array_values(array_filter($numeros, fn (int $n) => $n > 0));

        if ($numeros === []) {
            return;
        }

        $mapa = DB::table('asiento')
            ->where('empresa_id', $empresaId)
            ->whereIn('numeroasiento', $numeros)
            ->pluck('id', 'numeroasiento')
            ->all();

        foreach ($filas as $idx => $fila) {
            $nro = (int) ($fila['nro_asiento'] ?? 0);
            $filas[$idx]['asiento_id'] = $nro > 0 ? (int) ($mapa[$nro] ?? 0) : 0;
        }
    }

    private function formatearFecha(mixed $fecha): string
    {
        $f = (int) $fecha;
        if ($f <= 0 || $f < 19000101) {
            return '';
        }

        $s = str_pad((string) $f, 8, '0', STR_PAD_LEFT);

        return substr($s, 6, 2).'/'.substr($s, 4, 2).'/'.substr($s, 0, 4);
    }
}
