<?php

namespace App\Support\Contable\ConciliacionBancaria;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

/**
 * Lee la carátula del Excel de Contaduría (Macro Gerli / cuenta 127) para benchmark.
 */
final class ConciliacionBancariaExcelReferenciaSupport
{
    /**
     * @return array{
     *   archivo: string,
     *   saldo_banco_extracto: float|null,
     *   cheques_no_acreditados: float|null,
     *   movimientos_pendientes_banco: float|null,
     *   saldo_banco_ajustado: float|null,
     *   saldo_contable: float|null,
     *   diferencia: float|null,
     *   fuente: string
     * }
     */
    public static function leerCaratula(string $ruta): array
    {
        if (! is_readable($ruta)) {
            throw new RuntimeException('No se puede leer el Excel de referencia: '.$ruta);
        }

        try {
            $libro = IOFactory::load($ruta);
        } catch (Throwable $e) {
            throw new RuntimeException('Error abriendo Excel de referencia: '.$e->getMessage(), 0, $e);
        }

        $vals = self::extraerDesdeHoja($libro, 'CARATULA');
        $fuente = 'CARATULA';
        if ($vals['saldo_banco_extracto'] === null) {
            $vals = self::extraerDesdeHoja($libro, 'MAYO');
            $fuente = 'MAYO';
        }

        return [
            'archivo' => basename($ruta),
            'saldo_banco_extracto' => $vals['saldo_banco_extracto'],
            'cheques_no_acreditados' => $vals['cheques_no_acreditados'],
            'movimientos_pendientes_banco' => $vals['movimientos_pendientes_banco'],
            'saldo_banco_ajustado' => $vals['saldo_banco_ajustado'],
            'saldo_contable' => $vals['saldo_contable'],
            'diferencia' => $vals['diferencia'],
            'fuente' => $fuente,
        ];
    }

    /**
     * @param  array<string, mixed>  $caratulaErp
     * @param  array<string, mixed>  $caratulaExcel
     * @return array{
     *   filas: list<array{concepto: string, excel: float|null, erp: float|null, delta: float|null, ok: bool}>,
     *   ok: bool,
     *   tolerancia: float
     * }
     */
    public static function comparar(array $caratulaErp, array $caratulaExcel, ?float $tolerancia = null): array
    {
        $tol = $tolerancia ?? (float) config('conciliacion_bancaria.excel_tolerancia_importe', 1.0);
        $mapa = [
            'Saldo banco (extracto)' => ['saldo_banco_extracto', 'saldo_banco_extracto'],
            'Cheques no acreditados' => ['cheques_no_acreditados', 'cheques_no_acreditados'],
            'Pendientes banco' => ['movimientos_pendientes_banco', 'movimientos_pendientes_banco'],
            'Saldo banco ajustado' => ['saldo_banco_ajustado', 'saldo_banco_ajustado'],
            'Saldo contable' => ['saldo_contable', 'saldo_contable'],
            'Diferencia' => ['diferencia', 'diferencia'],
        ];

        $filas = [];
        $ok = true;
        foreach ($mapa as $label => [$kExcel, $kErp]) {
            $ex = self::floatOrNull($caratulaExcel[$kExcel] ?? null);
            $erp = self::floatOrNull($caratulaErp[$kErp] ?? null);
            $delta = ($ex === null || $erp === null) ? null : round($erp - $ex, 2);
            $filaOk = $delta !== null && abs($delta) <= $tol;
            if (! $filaOk) {
                $ok = false;
            }
            $filas[] = [
                'concepto' => $label,
                'excel' => $ex,
                'erp' => $erp,
                'delta' => $delta,
                'ok' => $filaOk,
            ];
        }

        return [
            'filas' => $filas,
            'ok' => $ok,
            'tolerancia' => $tol,
        ];
    }

    /**
     * @return array{
     *   saldo_banco_extracto: float|null,
     *   cheques_no_acreditados: float|null,
     *   movimientos_pendientes_banco: float|null,
     *   saldo_banco_ajustado: float|null,
     *   saldo_contable: float|null,
     *   diferencia: float|null
     * }
     */
    private static function extraerDesdeHoja($libro, string $nombre): array
    {
        $out = [
            'saldo_banco_extracto' => null,
            'cheques_no_acreditados' => null,
            'movimientos_pendientes_banco' => null,
            'saldo_banco_ajustado' => null,
            'saldo_contable' => null,
            'diferencia' => null,
        ];

        $hoja = $libro->getSheetByName($nombre);
        if ($hoja === null) {
            return $out;
        }
        $max = min(80, (int) $hoja->getHighestRow());
        for ($r = 1; $r <= $max; $r++) {
            $textos = [];
            $numeros = [];
            for ($c = 1; $c <= 12; $c++) {
                $v = $hoja->getCellByColumnAndRow($c, $r)->getCalculatedValue();
                if ($v === null || $v === '') {
                    continue;
                }
                if (is_numeric($v)) {
                    $numeros[] = (float) $v;
                } else {
                    $textos[] = self::normTexto((string) $v);
                }
            }
            if ($textos === [] || $numeros === []) {
                continue;
            }
            $blob = implode(' | ', $textos);
            $num = $numeros[count($numeros) - 1];

            if (str_contains($blob, 'saldo del banco segun extracto')
                || (str_contains($blob, 'saldo del banco al') && str_contains($blob, 'extracto'))) {
                $out['saldo_banco_extracto'] ??= $num;
            } elseif (str_contains($blob, 'cheques emitidos') && (str_contains($blob, 'no acredit') || str_contains($blob, 'no ingresaron'))) {
                $out['cheques_no_acreditados'] ??= $num;
            } elseif (str_contains($blob, 'pendiente a conciliar por soporte')
                || str_contains($blob, 'movimientos pendiente a conciliar')) {
                $out['movimientos_pendientes_banco'] ??= $num;
            } elseif (str_contains($blob, 'saldo del banco al') && ! str_contains($blob, 'extracto') && ! str_contains($blob, 'segun')) {
                // Carátula: línea "Saldo del Banco al DD.MM.YY" = ajustado
                if ($out['saldo_banco_extracto'] !== null) {
                    $out['saldo_banco_ajustado'] ??= $num;
                } else {
                    $out['saldo_banco_extracto'] ??= $num;
                }
            } elseif (str_contains($blob, 'saldo contable') && ! str_contains($blob, 'diferencia')) {
                $out['saldo_contable'] ??= $num;
            } elseif (str_contains($blob, 'diferencia')) {
                $out['diferencia'] ??= $num;
            } elseif (str_contains($blob, 'saldo contable por diferencia')) {
                $out['saldo_banco_ajustado'] ??= $num;
            }
        }

        // MAYO: primera cifra grande ~ saldo extracto en fila "Saldo del Banco al"
        if ($nombre === 'MAYO' && $out['saldo_banco_extracto'] === null) {
            $out['saldo_banco_extracto'] = self::primerNumeroFilaConTexto($hoja, 'saldo del banco al');
        }
        if ($nombre === 'MAYO' && $out['cheques_no_acreditados'] === null) {
            $out['cheques_no_acreditados'] = self::primerNumeroFilaConTexto($hoja, 'cheques emitidos');
        }

        return $out;
    }

    private static function primerNumeroFilaConTexto($hoja, string $needle): ?float
    {
        $max = min(80, (int) $hoja->getHighestRow());
        for ($r = 1; $r <= $max; $r++) {
            $blob = '';
            $nums = [];
            for ($c = 1; $c <= 12; $c++) {
                $v = $hoja->getCellByColumnAndRow($c, $r)->getCalculatedValue();
                if ($v === null || $v === '') {
                    continue;
                }
                if (is_numeric($v)) {
                    $nums[] = (float) $v;
                } else {
                    $blob .= ' '.self::normTexto((string) $v);
                }
            }
            if (str_contains($blob, $needle) && $nums !== []) {
                return $nums[count($nums) - 1];
            }
        }

        return null;
    }

    private static function normTexto(string $t): string
    {
        $t = mb_strtolower($t, 'UTF-8');
        $t = strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', ' ', trim($t)) ?? '';
    }

    /**
     * @return array{
     *   cheques: list<array{tip: string, numero: string, importe: float, detalle: string, fecha_emision: string|null, fecha_cheque: string|null}>,
     *   suma_cheques: float,
     *   banco_pendientes: list<array{fecha: string|null, referencia: string, concepto: string, importe: float}>,
     *   suma_banco_pendientes: float
     * }
     */
    public static function leerPendientesDetalle(string $ruta): array
    {
        if (! is_readable($ruta)) {
            throw new RuntimeException('No se puede leer el Excel de referencia: '.$ruta);
        }

        $libro = IOFactory::load($ruta);
        $cheques = [];
        $sumaCh = 0.0;

        $hoja = $libro->getSheetByName('Pendientes');
        if ($hoja !== null) {
            $max = (int) $hoja->getHighestRow();
            for ($r = 6; $r <= $max; $r++) {
                $tip = trim((string) $hoja->getCellByColumnAndRow(1, $r)->getCalculatedValue());
                if ($tip === '' || strcasecmp($tip, 'Tip') === 0 || str_starts_with(mb_strtolower($tip), 'total')) {
                    continue;
                }
                $numero = self::soloDigitos((string) $hoja->getCellByColumnAndRow(2, $r)->getCalculatedValue());
                $fechaEmision = self::celdaFechaAYmd($hoja->getCellByColumnAndRow(4, $r)->getCalculatedValue());
                $fechaCheque = self::celdaFechaAYmd($hoja->getCellByColumnAndRow(5, $r)->getCalculatedValue());
                $detalle = trim((string) $hoja->getCellByColumnAndRow(8, $r)->getCalculatedValue());
                $deb = self::floatOrNull($hoja->getCellByColumnAndRow(9, $r)->getCalculatedValue()) ?? 0.0;
                $cred = self::floatOrNull($hoja->getCellByColumnAndRow(10, $r)->getCalculatedValue()) ?? 0.0;
                $importe = $cred > 0 ? $cred : -$deb;
                if ($numero === '' && abs($importe) < 0.005) {
                    continue;
                }
                $cheques[] = [
                    'tip' => $tip,
                    'numero' => $numero,
                    'importe' => round($importe, 2),
                    'detalle' => $detalle,
                    'fecha_emision' => $fechaEmision,
                    'fecha_cheque' => $fechaCheque,
                ];
                $sumaCh += $importe;
            }
        }

        $bancoPend = [];
        $sumaB = 0.0;
        $hojaMayo = $libro->getSheetByName('MAYO');
        if ($hojaMayo !== null) {
            $enBloque = false;
            $max = min(80, (int) $hojaMayo->getHighestRow());
            for ($r = 1; $r <= $max; $r++) {
                $blob = '';
                for ($c = 1; $c <= 8; $c++) {
                    $v = $hojaMayo->getCellByColumnAndRow($c, $r)->getCalculatedValue();
                    if (is_string($v)) {
                        $blob .= ' '.self::normTexto($v);
                    }
                }
                if (str_contains($blob, 'movimientos bancarios pendientes')) {
                    $enBloque = true;
                    continue;
                }
                if (! $enBloque) {
                    continue;
                }
                if (str_contains($blob, 'saldo contable') || str_contains($blob, 'partidas conciliatorias')) {
                    break;
                }
                // Layout MAYO Contaduría: B=Fecha, C=Ref, D=Codigo, E=Concepto, G=Importe
                $fecha = $hojaMayo->getCellByColumnAndRow(2, $r)->getCalculatedValue();
                $ref = $hojaMayo->getCellByColumnAndRow(3, $r)->getCalculatedValue();
                $concepto = trim((string) $hojaMayo->getCellByColumnAndRow(5, $r)->getCalculatedValue());
                $importe = self::floatOrNull($hojaMayo->getCellByColumnAndRow(7, $r)->getCalculatedValue());
                if ($importe === null || abs($importe) < 0.005) {
                    continue;
                }
                if ($concepto === '' || str_contains(self::normTexto($concepto), 'gastos bancarios')) {
                    continue;
                }
                $refS = is_scalar($ref) ? (string) $ref : '';
                $fechaS = null;
                if ($fecha instanceof \DateTimeInterface) {
                    $fechaS = $fecha->format('Y-m-d');
                } elseif (is_numeric($fecha)) {
                    try {
                        $fechaS = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $fecha)->format('Y-m-d');
                    } catch (\Throwable) {
                        $fechaS = null;
                    }
                } elseif (is_string($fecha) && $fecha !== '') {
                    $ts = strtotime($fecha);
                    $fechaS = $ts ? date('Y-m-d', $ts) : null;
                }
                $bancoPend[] = [
                    'fecha' => $fechaS,
                    'referencia' => $refS,
                    'concepto' => $concepto,
                    'importe' => round($importe, 2),
                ];
                $sumaB += $importe;
            }
        }

        return [
            'cheques' => $cheques,
            'suma_cheques' => round($sumaCh, 2),
            'banco_pendientes' => $bancoPend,
            'suma_banco_pendientes' => round($sumaB, 2),
        ];
    }

    public static function soloDigitos(string $valor): string
    {
        $d = preg_replace('/\D/', '', $valor) ?? '';

        return ltrim($d, '0') !== '' ? ltrim($d, '0') : ($d !== '' ? '0' : '');
    }

    private static function floatOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return round((float) $v, 2);
    }

    private static function celdaFechaAYmd(mixed $v): ?string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_numeric($v)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }
        if (is_string($v) && trim($v) !== '' && ! str_contains($v, '--')) {
            $ts = strtotime($v);

            return $ts ? date('Y-m-d', $ts) : null;
        }

        return null;
    }
}
