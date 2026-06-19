<?php

namespace App\Support\Contable\ConciliacionBancaria;

/**
 * Codificación de movimientos bancarios (solapa Saldo / ING-GTOS DIARIOS).
 *
 * Prioridad de detección (validada contra extracto Macro cuenta 127):
 * 1. P.C.C. ({@see grouping_code_ib}) — clasificador principal del banco
 * 2. Concepto IB ({@see code_description_ib}) — desambiguación y acumulados AC
 * 3. Código operación IB ({@see operation_code_ib})
 * 4. Patrones en descripción / config Codificacion bcos.xlsx
 */
final class ConciliacionBancariaCodificacionSupport
{
    /**
     * @return array{codigo: int|string, descripcion: string, metodo: string}
     */
    public static function clasificarMovimientoBanco(array $movimiento): array
    {
        $concepto = strtoupper(trim((string) ($movimiento['code_description_ib'] ?? '')));
        $pcc = ConciliacionBancariaMovimientoBancoSupport::normalizarPcc($movimiento['grouping_code_ib'] ?? null);
        $codOp = strtoupper(trim((string) ($movimiento['operation_code_ib'] ?? '')));
        $textoDesc = strtoupper(implode(' ', array_filter([
            (string) ($movimiento['code_description_bank'] ?? ''),
            (string) ($movimiento['depositor_description'] ?? ''),
        ])));

        if ($pcc !== '') {
            $porPcc = self::clasificarPorPcc($pcc, $concepto, $textoDesc);
            if ($porPcc !== null) {
                return $porPcc;
            }
        }

        $porConcepto = self::clasificarPorConcepto($concepto);
        if ($porConcepto !== null) {
            return $porConcepto;
        }

        if ($codOp !== '') {
            $porOp = self::clasificarPorCodigoOperacion($codOp);
            if ($porOp !== null) {
                return $porOp;
            }
        }

        $porPatron = self::clasificarPorPatronesConfig($concepto, $textoDesc);
        if ($porPatron !== null) {
            return $porPatron;
        }

        $importe = ConciliacionBancariaHashSupport::importeFirmadoBanco($movimiento);

        if ($importe < 0) {
            return [
                'codigo' => (int) config('conciliacion_bancaria.codigo_gasto_default', 7),
                'descripcion' => 'COMISIONES/INTERESES/SELLOS',
                'metodo' => 'default_gasto',
            ];
        }

        return [
            'codigo' => (int) config('conciliacion_bancaria.codigo_transferencia_default', 10),
            'descripcion' => 'TRANSFERENCIAS/PAGOS/DEPOSITOS',
            'metodo' => 'default_transferencia',
        ];
    }

    /**
     * @return array{codigo: int|string, descripcion: string, metodo: string}|null
     */
    private static function clasificarPorPcc(string $pcc, string $concepto, string $textoDesc): ?array
    {
        $acumulados = config('conciliacion_bancaria.conceptos_acumulado', []);
        if (in_array($concepto, $acumulados, true)) {
            $pccAc = config('conciliacion_bancaria.pcc_acumulado', []);
            if (in_array($pcc, $pccAc, true) || isset(config('conciliacion_bancaria.pcc_map', [])[$pcc]) && config('conciliacion_bancaria.pcc_map')[$pcc] === 'AC') {
                return self::codigoAcumulado($concepto);
            }
        }

        $ambiguo = config('conciliacion_bancaria.pcc_ambiguos.'.$pcc);
        if (is_array($ambiguo)) {
            foreach ($ambiguo as $regla) {
                $patrones = $regla['patrones_concepto'] ?? [];
                foreach ($patrones as $pat) {
                    if ($pat !== '' && str_contains($concepto, strtoupper($pat))) {
                        return self::respuestaCodigo($regla['codigo'], $regla['descripcion'] ?? '', 'pcc_ambiguo');
                    }
                }
                foreach ($regla['patrones_descripcion'] ?? [] as $pat) {
                    if ($pat !== '' && str_contains($textoDesc, strtoupper($pat))) {
                        return self::respuestaCodigo($regla['codigo'], $regla['descripcion'] ?? '', 'pcc_ambiguo_desc');
                    }
                }
            }
        }

        $mapa = config('conciliacion_bancaria.pcc_map', []);
        if (array_key_exists($pcc, $mapa)) {
            $codigo = $mapa[$pcc];
            if ($codigo === 'AC') {
                return self::codigoAcumulado($concepto);
            }

            return self::respuestaCodigo($codigo, self::descripcionCodigoNumerico($codigo), 'pcc');
        }

        return null;
    }

    /**
     * @return array{codigo: int|string, descripcion: string, metodo: string}|null
     */
    private static function clasificarPorConcepto(string $concepto): ?array
    {
        if ($concepto === '') {
            return null;
        }

        $mapa = config('conciliacion_bancaria.concepto_map', []);
        foreach ($mapa as $patron => $def) {
            if (str_contains($concepto, strtoupper($patron))) {
                $codigo = $def['codigo'] ?? 10;

                return self::respuestaCodigo(
                    $codigo,
                    (string) ($def['descripcion'] ?? self::descripcionCodigoNumerico($codigo)),
                    'concepto',
                );
            }
        }

        if (in_array($concepto, config('conciliacion_bancaria.conceptos_acumulado', []), true)) {
            return self::codigoAcumulado($concepto);
        }

        return null;
    }

    /**
     * @return array{codigo: int|string, descripcion: string, metodo: string}|null
     */
    private static function clasificarPorCodigoOperacion(string $codOp): ?array
    {
        $mapa = config('conciliacion_bancaria.cod_op_map', []);
        if (! array_key_exists($codOp, $mapa)) {
            return null;
        }

        $codigo = $mapa[$codOp];

        return self::respuestaCodigo($codigo, self::descripcionCodigoNumerico($codigo), 'cod_op');
    }

    /**
     * @return array{codigo: int|string, descripcion: string, metodo: string}|null
     */
    private static function clasificarPorPatronesConfig(string $concepto, string $textoDesc): ?array
    {
        $texto = trim($concepto.' '.$textoDesc);
        if ($texto === '') {
            return null;
        }

        foreach (config('conciliacion_bancaria.codificacion_gastos', []) as $codigo => $def) {
            foreach ($def['patrones'] ?? [] as $patron) {
                if ($patron !== '' && str_contains($texto, strtoupper($patron))) {
                    return self::respuestaCodigo($codigo, (string) ($def['descripcion'] ?? ''), 'patron');
                }
            }
        }

        return null;
    }

    /**
     * @return array{codigo: int|string, descripcion: string, metodo: string}
     */
    private static function codigoAcumulado(string $concepto): array
    {
        $desc = match (true) {
            str_contains($concepto, 'SELL') => 'SELLOS (acumulado diario)',
            str_contains($concepto, 'IVA.10') || str_contains($concepto, 'IVA 10') => 'IVA 10,5% op. financieras (acumulado)',
            str_contains($concepto, 'INT.S') => 'INTERESES ACUERDO (acumulado)',
            default => 'ACUMULADO DIARIO',
        };

        return [
            'codigo' => 'AC',
            'descripcion' => $desc,
            'metodo' => 'acumulado',
        ];
    }

    /**
     * @return array{codigo: int|string, descripcion: string, metodo: string}
     */
    private static function respuestaCodigo(int|string $codigo, string $descripcion, string $metodo): array
    {
        if ($descripcion === '' && is_int($codigo)) {
            $descripcion = self::descripcionCodigoNumerico($codigo);
        }

        return [
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'metodo' => $metodo,
        ];
    }

    private static function descripcionCodigoNumerico(int|string $codigo): string
    {
        if ($codigo === 'AC') {
            return 'ACUMULADO DIARIO';
        }

        $def = config('conciliacion_bancaria.codificacion_gastos.'.(int) $codigo, []);

        return (string) ($def['descripcion'] ?? '');
    }

    /**
     * Resumen ING-GTOS DIARIOS: solo códigos numéricos de gasto (excluye AC y transferencias netas positivas).
     *
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    public static function resumirGastosDiarios(array $movimientos): array
    {
        $grupos = [];

        foreach ($movimientos as $mov) {
            $clasif = self::clasificarMovimientoBanco($mov);
            $codigo = $clasif['codigo'];
            if ($codigo === 'AC' || (int) $codigo === (int) config('conciliacion_bancaria.codigo_transferencia_default', 10)) {
                continue;
            }

            $importe = ConciliacionBancariaHashSupport::importeFirmadoBanco($mov);
            if ($importe >= 0) {
                continue;
            }

            $key = (string) $codigo;
            if (! isset($grupos[$key])) {
                $grupos[$key] = [
                    'codigo' => is_numeric($codigo) ? (int) $codigo : $codigo,
                    'descripcion' => $clasif['descripcion'],
                    'importe' => 0.0,
                ];
            }

            $grupos[$key]['importe'] += $importe;
        }

        uksort($grupos, fn ($a, $b) => (int) $a <=> (int) $b);

        return array_values(array_map(function (array $g) {
            $g['importe'] = round($g['importe'], 2);

            return $g;
        }, $grupos));
    }
}
