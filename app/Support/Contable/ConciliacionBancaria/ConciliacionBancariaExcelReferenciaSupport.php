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
            $libro = self::cargarLibroReferencia($ruta);
        } catch (Throwable $e) {
            throw new RuntimeException('Error abriendo Excel de referencia: '.$e->getMessage(), 0, $e);
        }

        $vals = self::extraerDesdeHoja($libro, 'CARATULA');
        $fuente = 'CARATULA';
        if ($vals['saldo_banco_extracto'] === null) {
            $vals = self::extraerDesdeHoja($libro, 'MAYO');
            $fuente = 'MAYO';
        }

        // Algunas carátulas dejan "pendientes soporte" en 0 aunque el saldo ajustado
        // ya descuenta el TOTAL de MAYO (caso Rebisco mayo/2026).
        $vals = self::completarPendientesBancoDesdeIdentidad($vals);

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
                $v = self::valorCelda($hoja->getCellByColumnAndRow($c, $r));
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
            // Contaduría a veces deja un "1" de nota al lado del importe (KSA/RSA).
            $num = self::importeSignificativoDeFila($numeros);

            if (str_contains($blob, 'saldo del banco segun extracto')
                || (str_contains($blob, 'saldo del banco al') && str_contains($blob, 'extracto'))) {
                $out['saldo_banco_extracto'] ??= $num;
            } elseif (str_contains($blob, 'cheques emitidos') && (str_contains($blob, 'no acredit') || str_contains($blob, 'no ingresaron'))) {
                $out['cheques_no_acreditados'] ??= $num;
            } elseif (str_contains($blob, 'pendiente a conciliar por soporte')
                || str_contains($blob, 'movimientos pendiente a conciliar')
                || str_contains($blob, 'acreditacion no registradas')
                || str_contains($blob, 'movimientos pendiente a conciliar por soporte')) {
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
                $v = self::valorCelda($hoja->getCellByColumnAndRow($c, $r));
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

        $libro = self::cargarLibroReferencia($ruta);
        $cheques = [];
        $sumaCh = 0.0;

        $hoja = self::hojaPorNombres($libro, ['Pendientes', 'PENDIENTES']);
        if ($hoja !== null) {
            $max = (int) $hoja->getHighestRow();
            for ($r = 6; $r <= $max; $r++) {
                $tip = trim((string) self::valorCelda($hoja->getCellByColumnAndRow(1, $r)));
                if ($tip === '' || strcasecmp($tip, 'Tip') === 0 || str_starts_with(mb_strtolower($tip), 'total')) {
                    continue;
                }
                $numero = self::soloDigitos((string) self::valorCelda($hoja->getCellByColumnAndRow(2, $r)));
                $fechaEmision = self::celdaFechaAYmd(self::valorCelda($hoja->getCellByColumnAndRow(4, $r)));
                $fechaCheque = self::celdaFechaAYmd(self::valorCelda($hoja->getCellByColumnAndRow(5, $r)));
                $detalle = trim((string) self::valorCelda($hoja->getCellByColumnAndRow(8, $r)));
                $deb = self::floatOrNull(self::valorCelda($hoja->getCellByColumnAndRow(9, $r))) ?? 0.0;
                $cred = self::floatOrNull(self::valorCelda($hoja->getCellByColumnAndRow(10, $r))) ?? 0.0;
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
            $max = min(120, (int) $hojaMayo->getHighestRow());
            for ($r = 1; $r <= $max; $r++) {
                $blob = '';
                for ($c = 1; $c <= 8; $c++) {
                    $v = self::valorCelda($hojaMayo->getCellByColumnAndRow($c, $r));
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
                if (str_contains($blob, 'saldo contable')
                    || str_contains($blob, 'partidas conciliatorias')
                    || str_contains($blob, 'movimientos pendientes de registracion')) {
                    break;
                }
                // BSA: B=Fecha…G=Importe. KSA/RSA: A=Fecha, B=Ref, C=Cod, D=Concepto, E=Importe.
                $fila = self::filaMayoPendienteBanco($hojaMayo, $r);
                if ($fila === null) {
                    continue;
                }
                $bancoPend[] = $fila;
                $sumaB += $fila['importe'];
            }
        }

        return [
            'cheques' => $cheques,
            'suma_cheques' => round($sumaCh, 2),
            'banco_pendientes' => $bancoPend,
            'suma_banco_pendientes' => round($sumaB, 2),
        ];
    }

    /**
     * Códigos Contaduría de la solapa Saldo (fecha+importe → código).
     * Sirve para alinear gastos (p.ej. RET.IN.BRU / PERCIBBSAS: algunos en 3, otros en 4).
     *
     * @return array<string, int> clave {@see ConciliacionBancariaCodificacionSupport::claveOverrideSaldo}
     */
    public static function mapaCodigosDesdeSaldo(string $ruta): array
    {
        if (! is_readable($ruta)) {
            throw new RuntimeException('No se puede leer el Excel de referencia: '.$ruta);
        }

        try {
            $libro = self::cargarLibroReferencia($ruta);
        } catch (Throwable $e) {
            throw new RuntimeException('Error abriendo Excel de referencia: '.$e->getMessage(), 0, $e);
        }

        $hoja = $libro->getSheetByName('Saldo');
        if ($hoja === null) {
            return [];
        }

        $out = [];
        $max = (int) $hoja->getHighestRow();
        for ($r = 1; $r <= $max; $r++) {
            $codRaw = self::valorCelda($hoja->getCellByColumnAndRow(3, $r));
            if (! is_numeric($codRaw)) {
                continue;
            }
            $codigo = (int) $codRaw;
            if ($codigo <= 0) {
                continue;
            }
            $importe = self::floatOrNull(self::valorCelda($hoja->getCellByColumnAndRow(5, $r)));
            if ($importe === null || abs($importe) < 0.005) {
                continue;
            }
            $fecha = self::celdaFechaAYmd(self::valorCelda($hoja->getCellByColumnAndRow(1, $r)));
            if ($fecha === null) {
                continue;
            }
            $concepto = trim((string) self::valorCelda($hoja->getCellByColumnAndRow(4, $r)));
            $clave = ConciliacionBancariaCodificacionSupport::claveOverrideSaldo($fecha, $importe, $concepto);
            // Primera aparición gana (evitar pisar si hay colisión rara).
            $out[$clave] ??= $codigo;
        }

        return $out;
    }

    public static function soloDigitos(string $valor): string
    {
        $d = preg_replace('/\D/', '', $valor) ?? '';

        return ltrim($d, '0') !== '' ? ltrim($d, '0') : ($d !== '' ? '0' : '');
    }

    /**
     * Evita OOM en Excels Contaduría con hojas fantasma (EXTRACTO NUEVO ~1M filas).
     */
    private static function cargarLibroReferencia(string $ruta)
    {
        $reader = IOFactory::createReaderForFile($ruta);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly([
                'CARATULA',
                'Saldo',
                'MAYO',
                'Pendientes',
                'PENDIENTES',
                'EXTRACTO',
                'Extracto.',
                'ING-GTOS DIARIOS',
            ]);
        }

        return $reader->load($ruta);
    }

    /**
     * @param  list<string>  $nombres
     */
    private static function hojaPorNombres($libro, array $nombres)
    {
        foreach ($nombres as $nombre) {
            $hoja = $libro->getSheetByName($nombre);
            if ($hoja !== null) {
                return $hoja;
            }
        }

        return null;
    }

    /**
     * @param  list<float>  $numeros
     */
    private static function importeSignificativoDeFila(array $numeros): float
    {
        // Ignora marcas de nota (1) / flags chicos al lado del importe Contaduría.
        $candidatos = array_values(array_filter(
            $numeros,
            static fn (float $n) => abs($n) >= 10.0
        ));
        if ($candidatos === []) {
            $candidatos = $numeros;
        }
        if ($candidatos === []) {
            return 0.0;
        }
        usort($candidatos, static fn (float $a, float $b) => abs($b) <=> abs($a));

        return $candidatos[0];
    }

    /**
     * @return array{fecha: string|null, referencia: string, concepto: string, importe: float}|null
     */
    private static function filaMayoPendienteBanco($hoja, int $r): ?array
    {
        $layouts = [
            // BSA (columnas corridas)
            ['fecha' => 2, 'ref' => 3, 'concepto' => 5, 'importe' => 7],
            // KSA / RSA
            ['fecha' => 1, 'ref' => 2, 'concepto' => 4, 'importe' => 5],
        ];

        foreach ($layouts as $layout) {
            $concepto = trim((string) self::valorCelda($hoja->getCellByColumnAndRow($layout['concepto'], $r)));
            $importe = self::floatOrNull(self::valorCelda($hoja->getCellByColumnAndRow($layout['importe'], $r)));
            if ($importe === null || abs($importe) < 0.005) {
                continue;
            }
            if ($concepto === '' || str_contains(self::normTexto($concepto), 'gastos bancarios')) {
                continue;
            }
            if (is_numeric($concepto)) {
                continue;
            }
            $fecha = self::valorCelda($hoja->getCellByColumnAndRow($layout['fecha'], $r));
            $ref = self::valorCelda($hoja->getCellByColumnAndRow($layout['ref'], $r));
            $fechaS = self::celdaFechaAYmd($fecha);
            if ($fechaS === null && ! ($fecha instanceof \DateTimeInterface) && ! is_numeric($fecha)) {
                // Layout incorrecto (p.ej. "34.075.628" como fecha).
                continue;
            }

            return [
                'fecha' => $fechaS,
                'referencia' => is_scalar($ref) ? (string) $ref : '',
                'concepto' => $concepto,
                'importe' => round($importe, 2),
            ];
        }

        return null;
    }

    /**
     * Identidad Contaduría: ajustado = extracto + cheques + pendientes_banco.
     *
     * @param  array<string, float|null>  $vals
     * @return array<string, float|null>
     */
    private static function completarPendientesBancoDesdeIdentidad(array $vals): array
    {
        $extracto = self::floatOrNull($vals['saldo_banco_extracto'] ?? null);
        $cheques = self::floatOrNull($vals['cheques_no_acreditados'] ?? null);
        $ajustado = self::floatOrNull($vals['saldo_banco_ajustado'] ?? null);
        $pend = self::floatOrNull($vals['movimientos_pendientes_banco'] ?? null);

        if ($extracto === null || $cheques === null || $ajustado === null) {
            return $vals;
        }

        $implicito = round($ajustado - $extracto - $cheques, 2);
        if ($pend === null || (abs($pend) < 0.005 && abs($implicito) >= 0.005)) {
            $vals['movimientos_pendientes_banco'] = $implicito;
        }

        return $vals;
    }

    /**
     * Prefiere valor cacheado del Excel cuando la fórmula no resuelve (#REF! al cargar hojas parciales).
     */
    private static function valorCelda($cell): mixed
    {
        $calc = $cell->getCalculatedValue();
        if (is_string($calc) && (str_starts_with($calc, '#') || $calc === '')) {
            $calc = null;
        }
        if ($calc !== null && $calc !== '') {
            return $calc;
        }
        if (method_exists($cell, 'getOldCalculatedValue')) {
            $old = $cell->getOldCalculatedValue();
            if ($old !== null && $old !== '' && !(is_string($old) && str_starts_with($old, '#'))) {
                return $old;
            }
        }
        $raw = $cell->getValue();
        if (is_string($raw) && str_starts_with($raw, '=')) {
            return null;
        }

        return $raw;
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
