<?php

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Caja\Cheque;

/**
 * Cruce Interbanking (débitos CH*) ↔ cheques propios del ERP.
 *
 * Sirve para Contaduría: misma referencia/importe y código 9 (CHEQUES),
 * con estado OP para responder “no aparece / otra codificación”.
 */
final class ConciliacionBancariaCruceChequesIbSupport
{
    public const MATCH_OK = 'OK_NRO_Y_IMPORTE';

    public const MATCH_IMPORTE = 'OK_NRO_IMPORTE_DISTINTO';

    public const MATCH_FALTA = 'NO_EN_ERP';

    /**
     * @param  list<array<string, mixed>>  $movimientosBanco
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   resumen: array{
     *     total: int,
     *     ok: int,
     *     importe_distinto: int,
     *     faltan: int,
     *     codigo_9: int,
     *     otros_codigos: int
     *   }
     * }
     */
    public static function armar(int $empresaId, int $cuentacajaId, array $movimientosBanco): array
    {
        $chequesIb = [];
        foreach ($movimientosBanco as $mov) {
            if (! is_array($mov)) {
                continue;
            }
            $concepto = (string) ($mov['code_description_ib'] ?? '');
            if (! ConciliacionBancariaReferenciaSupport::esConceptoCheque($concepto)) {
                continue;
            }
            $nro = ConciliacionBancariaExcelReferenciaSupport::soloDigitos((string) ($mov['voucher_number'] ?? ''));
            if ($nro === '' || $nro === '0') {
                continue;
            }
            $chequesIb[] = $mov;
        }

        $indice = self::indiceChequesErp(
            $empresaId,
            $cuentacajaId,
            array_values(array_unique(array_map(
                static fn (array $m): string => ConciliacionBancariaExcelReferenciaSupport::soloDigitos((string) ($m['voucher_number'] ?? '')),
                $chequesIb
            )))
        );

        return self::cruzar($chequesIb, $indice);
    }

    /**
     * @param  list<array<string, mixed>>  $movimientosChequeIb
     * @param  array<string, list<array<string, mixed>>>  $chequesPorNumero
     * @return array{filas: list<array<string, mixed>>, resumen: array<string, int>}
     */
    public static function cruzar(array $movimientosChequeIb, array $chequesPorNumero): array
    {
        $filas = [];
        $resumen = [
            'total' => 0,
            'ok' => 0,
            'importe_distinto' => 0,
            'faltan' => 0,
            'codigo_9' => 0,
            'otros_codigos' => 0,
        ];

        foreach ($movimientosChequeIb as $mov) {
            $nro = ConciliacionBancariaExcelReferenciaSupport::soloDigitos((string) ($mov['voucher_number'] ?? ''));
            if ($nro === '' || $nro === '0') {
                continue;
            }

            $importeIb = ConciliacionBancariaHashSupport::importeFirmadoBanco($mov);
            $clasif = ConciliacionBancariaCodificacionSupport::clasificarMovimientoBanco($mov);
            $candidatos = $chequesPorNumero[$nro] ?? [];
            $elegido = self::elegirCheque($candidatos, abs($importeIb));

            $match = self::MATCH_FALTA;
            if ($elegido !== null) {
                $match = abs((float) $elegido['monto'] - abs($importeIb)) < 0.05
                    ? self::MATCH_OK
                    : self::MATCH_IMPORTE;
            }

            $fila = [
                'fecha' => ConciliacionBancariaMovimientoBancoSupport::fechaMovimiento($mov),
                'referencia' => ConciliacionBancariaMovimientoBancoSupport::formatearReferencia($mov['voucher_number'] ?? null),
                'referencia_digits' => $nro,
                'concepto' => trim((string) ($mov['code_description_ib'] ?? '')),
                'importe' => $importeIb,
                'codigo' => $clasif['codigo'],
                'codigo_descripcion' => $clasif['descripcion'],
                'codigo_metodo' => $clasif['metodo'] ?? '',
                'pcc' => ConciliacionBancariaMovimientoBancoSupport::normalizarPcc($mov['grouping_code_ib'] ?? null),
                'match' => $match,
                'erp_cheque_id' => $elegido['id'] ?? null,
                'erp_numerocheque' => $elegido['numerocheque'] ?? '',
                'erp_monto' => $elegido['monto'] ?? null,
                'erp_estado' => $elegido['estado'] ?? '',
                'erp_estado_nombre' => $elegido['estado_nombre'] ?? '',
                'erp_origen' => $elegido['origen'] ?? '',
                'erp_fechapago' => $elegido['fechapago'] ?? null,
                'erp_fechaemision' => $elegido['fechaemision'] ?? null,
                'erp_chequera' => $elegido['chequera'] ?? '',
                'erp_tipocheque' => $elegido['tipocheque'] ?? '',
                'erp_op' => $elegido['op'] ?? '',
                'erp_op_estado' => $elegido['op_estado'] ?? '',
            ];

            $filas[] = $fila;
            $resumen['total']++;
            if ($match === self::MATCH_OK) {
                $resumen['ok']++;
            } elseif ($match === self::MATCH_IMPORTE) {
                $resumen['importe_distinto']++;
            } else {
                $resumen['faltan']++;
            }
            if ((string) $clasif['codigo'] === '9') {
                $resumen['codigo_9']++;
            } else {
                $resumen['otros_codigos']++;
            }
        }

        usort($filas, static function (array $a, array $b): int {
            $ord = [
                self::MATCH_FALTA => 0,
                self::MATCH_IMPORTE => 1,
                self::MATCH_OK => 2,
            ];

            return ($ord[$a['match']] ?? 9) <=> ($ord[$b['match']] ?? 9)
                ?: strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''))
                ?: abs((float) $a['importe']) <=> abs((float) $b['importe']);
        });

        return [
            'filas' => $filas,
            'resumen' => $resumen,
        ];
    }

    /**
     * @param  list<string>  $numeros
     * @return array<string, list<array<string, mixed>>>
     */
    public static function indiceChequesErp(int $empresaId, int $cuentacajaId, array $numeros): array
    {
        $numeros = array_values(array_filter(array_unique($numeros), static fn ($n) => $n !== '' && $n !== '0'));
        if ($numeros === [] || $empresaId <= 0 || $cuentacajaId <= 0) {
            return [];
        }

        $estados = [];
        foreach (Cheque::$enumEstado as $e) {
            $estados[(string) ($e['valor'] ?? '')] = (string) ($e['nombre'] ?? '');
        }

        $rows = Cheque::query()
            ->from('cheque as c')
            ->leftJoin('chequera as ch', 'ch.id', '=', 'c.chequera_id')
            ->leftJoin('pagoproveedor as pp', 'pp.id', '=', 'c.pagoproveedor_id')
            ->where('c.empresa_id', $empresaId)
            ->where('c.cuentacaja_id', $cuentacajaId)
            ->where('c.origen', 'E')
            ->where(function ($q) use ($numeros): void {
                foreach ($numeros as $n) {
                    $q->orWhere('c.numerocheque', $n)
                        ->orWhere('c.numerocheque', (int) $n)
                        ->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(CAST(c.numerocheque AS CHAR), '.', ''), '-', ''), ' ', '') = ?",
                            [$n]
                        );
                }
            })
            ->get([
                'c.id',
                'c.numerocheque',
                'c.monto',
                'c.estado',
                'c.origen',
                'c.fechapago',
                'c.fechaemision',
                'ch.codigo as chequera_codigo',
                'ch.tipocheque',
                'pp.numerotransaccion as op',
                'pp.estado as op_estado',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $nro = ConciliacionBancariaExcelReferenciaSupport::soloDigitos((string) $row->numerocheque);
            if ($nro === '' || $nro === '0') {
                continue;
            }
            $estadoRaw = (string) ($row->estado ?? '');
            $out[$nro][] = [
                'id' => (int) $row->id,
                'numerocheque' => (string) $row->numerocheque,
                'monto' => round((float) $row->monto, 2),
                'estado' => $estadoRaw,
                'estado_nombre' => $estados[$estadoRaw] ?? ($estadoRaw === '' ? '' : $estadoRaw),
                'origen' => (string) ($row->origen ?? ''),
                'fechapago' => self::ymd($row->fechapago),
                'fechaemision' => self::ymd($row->fechaemision),
                'chequera' => (string) ($row->chequera_codigo ?? ''),
                'tipocheque' => (string) ($row->tipocheque ?? ''),
                'op' => (string) ($row->op ?? ''),
                'op_estado' => (string) ($row->op_estado ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Completa cruce si el cache de conciliación es anterior a esta solapa.
     *
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public static function enriquecerResultado(array $resultado): array
    {
        if (isset($resultado['cruce_cheques_ib']['filas']) && is_array($resultado['cruce_cheques_ib']['filas'])) {
            return $resultado;
        }

        $empresaId = (int) ($resultado['empresa_id'] ?? 0);
        $cuentacaja = $resultado['cuentacaja'] ?? null;
        $cuentacajaId = 0;
        if (is_object($cuentacaja)) {
            $cuentacajaId = (int) ($cuentacaja->id ?? 0);
        } elseif (is_array($cuentacaja)) {
            $cuentacajaId = (int) ($cuentacaja['id'] ?? 0);
        }

        $movimientos = $resultado['movimientos_banco'] ?? [];
        if ($empresaId <= 0 || $cuentacajaId <= 0 || ! is_array($movimientos)) {
            $resultado['cruce_cheques_ib'] = [
                'filas' => [],
                'resumen' => [
                    'total' => 0,
                    'ok' => 0,
                    'importe_distinto' => 0,
                    'faltan' => 0,
                    'codigo_9' => 0,
                    'otros_codigos' => 0,
                ],
            ];

            return $resultado;
        }

        $resultado['cruce_cheques_ib'] = self::armar($empresaId, $cuentacajaId, $movimientos);

        return $resultado;
    }

    /**
     * @param  list<array<string, mixed>>  $candidatos
     * @return array<string, mixed>|null
     */
    private static function elegirCheque(array $candidatos, float $importeAbs): ?array
    {
        if ($candidatos === []) {
            return null;
        }

        foreach ($candidatos as $c) {
            if (abs((float) ($c['monto'] ?? 0) - $importeAbs) < 0.05) {
                return $c;
            }
        }

        return $candidatos[0];
    }

    private static function ymd(mixed $fecha): ?string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        $s = trim((string) $fecha);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) {
            return $m[1];
        }

        return null;
    }
}
