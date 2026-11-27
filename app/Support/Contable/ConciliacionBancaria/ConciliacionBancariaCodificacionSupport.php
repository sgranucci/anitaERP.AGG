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
     * Clave fecha+importe+concepto para overrides Contaduría (solapa Saldo del Excel).
     */
    public static function claveOverrideSaldo(string $fechaYmd, float $importe, string $concepto = ''): string
    {
        $conc = strtoupper(trim(preg_replace('/\s+/', ' ', $concepto) ?? ''));

        return substr($fechaYmd, 0, 10).'|'.(int) round($importe * 100).'|'.$conc;
    }

    /**
     * @param  array<string, int|string>  $overridesSaldo  clave fecha|centavos|concepto → código Contaduría
     * @return array{codigo: int|string, descripcion: string, metodo: string}
     */
    public static function clasificarMovimientoBanco(array $movimiento, array $overridesSaldo = []): array
    {
        if ($overridesSaldo !== []) {
            $fecha = ConciliacionBancariaMovimientoBancoSupport::fechaMovimiento($movimiento);
            if ($fecha !== null && $fecha !== '') {
                $importe = ConciliacionBancariaHashSupport::importeFirmadoBanco($movimiento);
                $concepto = trim((string) ($movimiento['code_description_ib'] ?? ''));
                $clave = self::claveOverrideSaldo((string) $fecha, $importe, $concepto);
                if (isset($overridesSaldo[$clave])) {
                    $codigo = $overridesSaldo[$clave];

                    return self::respuestaCodigo(
                        $codigo,
                        self::descripcionCodigoNumerico($codigo),
                        'excel_saldo',
                    );
                }
            }
        }

        $concepto = strtoupper(trim((string) ($movimiento['code_description_ib'] ?? '')));
        $pcc = ConciliacionBancariaMovimientoBancoSupport::normalizarPcc($movimiento['grouping_code_ib'] ?? null);
        $codOp = strtoupper(trim((string) ($movimiento['operation_code_ib'] ?? '')));
        $textoDesc = strtoupper(implode(' ', array_filter([
            (string) ($movimiento['code_description_bank'] ?? ''),
            (string) ($movimiento['depositor_description'] ?? ''),
        ])));

        // Percepción IIBB BSAS: IB etiqueta concepto RET.IN.BRU; Contaduría la separa (código 4).
        // Si Contaduría tipificó distinto en Saldo (override), ya se aplicó arriba.
        $iibbPerc = self::clasificarPercepcionIibbSiCorresponde($concepto, $textoDesc);
        if ($iibbPerc !== null) {
            return $iibbPerc;
        }

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
            $codigo = ConciliacionBancariaReglasSupport::codigoGastoDefault();

            return [
                'codigo' => $codigo,
                'descripcion' => self::descripcionCodigoNumerico($codigo) ?: 'COMISIONES/INTERESES/SELLOS',
                'metodo' => 'default_gasto',
            ];
        }

        $codigo = ConciliacionBancariaReglasSupport::codigoTransferenciaDefault();

        return [
            'codigo' => $codigo,
            'descripcion' => self::descripcionCodigoNumerico($codigo) ?: 'TRANSFERENCIAS/PAGOS/DEPOSITOS',
            'metodo' => 'default_transferencia',
        ];
    }

    /**
     * Percepción IIBB provincia BSAS (no retención SIRCREB).
     * AGIP/CABA Contaduría lo deja en código 3; no se captura acá.
     *
     * @return array{codigo: int|string, descripcion: string, metodo: string}|null
     */
    private static function clasificarPercepcionIibbSiCorresponde(string $concepto, string $textoDesc): ?array
    {
        $blob = trim($concepto.' '.$textoDesc);
        if ($blob === '') {
            return null;
        }

        // AGIP = CABA → código 3 (convención Contaduría / ING-GTOS).
        foreach (['PER IIBB AGIP', 'AGIP'] as $agip) {
            if (str_contains($textoDesc, $agip)) {
                return self::respuestaCodigo(3, 'I BRUTOS RETENCION POR 3 ROS', 'iibb_agip');
            }
        }

        $esPercepcionBsas = false;
        foreach (['PERCIBBSAS', 'PER IIBB PBS', 'PERCEPCION BSAS', 'PERCEPCION IN', 'IIBBPERCEP'] as $pat) {
            if (str_contains($textoDesc, $pat) || str_contains($concepto, $pat)) {
                $esPercepcionBsas = true;
                break;
            }
        }
        // "PERCIB" solo si no es IVA u otro impuesto.
        if (! $esPercepcionBsas && str_contains($textoDesc, 'PERCIB') && ! str_contains($textoDesc, 'IVA')) {
            $esPercepcionBsas = true;
        }

        if (! $esPercepcionBsas) {
            return null;
        }

        return self::respuestaCodigo(4, 'II.BB. PERCEPCION', 'iibb_percepcion');
    }

    /**
     * @return array{codigo: int|string, descripcion: string, metodo: string}|null
     */
    private static function clasificarPorPcc(string $pcc, string $concepto, string $textoDesc): ?array
    {
        $estructuras = ConciliacionBancariaPccMapaSupport::estructuras();
        $mapa = $estructuras['mapa'];
        $pccAc = $estructuras['acumulado'];
        $ambiguos = $estructuras['ambiguos'];

        $acumulados = ConciliacionBancariaCodificacionMapasSupport::conceptosAcumulado();
        if (in_array($concepto, $acumulados, true)) {
            if (in_array($pcc, $pccAc, true) || (isset($mapa[$pcc]) && $mapa[$pcc] === 'AC')) {
                return self::codigoAcumulado($concepto);
            }
        }

        $ambiguo = $ambiguos[$pcc] ?? null;
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

        $mapa = ConciliacionBancariaCodificacionMapasSupport::conceptoMap();
        foreach ($mapa as $patron => $def) {
            if (str_contains($concepto, strtoupper((string) $patron))) {
                $codigo = $def['codigo'] ?? 10;

                return self::respuestaCodigo(
                    $codigo,
                    (string) ($def['descripcion'] ?? self::descripcionCodigoNumerico($codigo)),
                    'concepto',
                );
            }
        }

        if (in_array($concepto, ConciliacionBancariaCodificacionMapasSupport::conceptosAcumulado(), true)) {
            return self::codigoAcumulado($concepto);
        }

        return null;
    }

    /**
     * @return array{codigo: int|string, descripcion: string, metodo: string}|null
     */
    private static function clasificarPorCodigoOperacion(string $codOp): ?array
    {
        $mapa = ConciliacionBancariaCodificacionMapasSupport::codOpMap();
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

        foreach (ConciliacionBancariaCodificacionMapasSupport::codificacionGastos() as $codigo => $def) {
            foreach ($def['patrones'] ?? [] as $patron) {
                if ($patron !== '' && str_contains($texto, strtoupper((string) $patron))) {
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
        return ConciliacionBancariaCodificacionMapasSupport::descripcionCodigo($codigo);
    }

    /**
     * Resumen ING-GTOS DIARIOS: solo códigos numéricos de gasto (excluye AC y transferencias netas positivas).
     *
     * @param  list<array<string, mixed>>  $movimientos
     * @param  array<string, int|string>  $overridesSaldo
     * @return list<array<string, mixed>>
     */
    public static function resumirGastosDiarios(array $movimientos, array $overridesSaldo = [], ?array $hashesPendientes = null): array
    {
        $grupos = [];

        foreach ($movimientos as $mov) {
            $clasif = self::clasificarMovimientoBanco($mov, $overridesSaldo);
            $codigo = $clasif['codigo'];
            if ($codigo === 'AC' || (int) $codigo === ConciliacionBancariaReglasSupport::codigoTransferenciaDefault()) {
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
            if ($hashesPendientes !== null) {
                // Con el estado de conciliación del módulo: cuánto del gasto ya está conciliado y cuánto sigue pendiente.
                $hash = (string) ($mov['hash'] ?? '');
                $campo = ($hash !== '' && ! isset($hashesPendientes[$hash])) ? 'importe_conciliado' : 'importe_pendiente';
                $grupos[$key]['importe_conciliado'] = (float) ($grupos[$key]['importe_conciliado'] ?? 0.0);
                $grupos[$key]['importe_pendiente'] = (float) ($grupos[$key]['importe_pendiente'] ?? 0.0);
                $grupos[$key][$campo] += $importe;
            }
        }

        uksort($grupos, fn ($a, $b) => (int) $a <=> (int) $b);

        return array_values(array_map(function (array $g) {
            foreach (['importe', 'importe_conciliado', 'importe_pendiente'] as $campo) {
                if (isset($g[$campo])) {
                    $g[$campo] = round($g[$campo], 2);
                }
            }

            return $g;
        }, $grupos));
    }
}
