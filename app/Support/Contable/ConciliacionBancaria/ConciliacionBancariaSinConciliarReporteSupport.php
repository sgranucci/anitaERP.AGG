<?php

declare(strict_types=1);

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Caja\Cheque;
use App\Models\Compras\Pagoproveedor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Armado del listado estilo Anita “Movimientos de Cuentas sin conciliar”.
 */
final class ConciliacionBancariaSinConciliarReporteSupport
{
    /**
     * @param  array<string, mixed>  $resultado  Salida de ConciliacionBancariaService::ejecutar / cache
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   totales: array{n: int, debitos: float, creditos: float},
     *   meta: array<string, mixed>
     * }
     */
    public static function armar(array $resultado): array
    {
        $filas = [];
        $vistos = [];

        foreach ($resultado['pendientes_cheques_anulas'] ?? [] as $ch) {
            if (! is_array($ch)) {
                continue;
            }
            $fila = self::desdeCheque($ch, 'ANULA');
            $clave = self::claveFila($fila);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $filas[] = $fila;
        }

        foreach ($resultado['pendientes_cheques_cpromae'] ?? [] as $ch) {
            if (! is_array($ch)) {
                continue;
            }
            $fila = self::desdeCheque($ch, (string) ($ch['tip'] ?? 'CHP'));
            $clave = self::claveFila($fila);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $filas[] = $fila;
        }

        foreach ($resultado['pendientes_contables_otros'] ?? [] as $mov) {
            if (! is_array($mov)) {
                continue;
            }
            $fila = self::desdeContable($mov);
            $clave = self::claveFila($fila);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $filas[] = $fila;
        }

        // Cheques del mayor pendientes que no estén en stock cpromae (p. ej. tip CH* sin cpromae).
        foreach ($resultado['pendientes_contables_cheques'] ?? [] as $mov) {
            if (! is_array($mov)) {
                continue;
            }
            $fila = self::desdeContable($mov);
            $clave = self::claveFila($fila);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $filas[] = $fila;
        }

        foreach ($resultado['pendientes_banco'] ?? [] as $mov) {
            if (! is_array($mov)) {
                continue;
            }
            $fila = self::desdeBanco($mov);
            $clave = self::claveFila($fila);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $filas[] = $fila;
        }

        $filas = ConciliacionBancariaChequeErpEnrichSupport::enriquecerFilasReporte(
            $filas,
            (int) ($resultado['empresa_id'] ?? 0),
            self::cuentacajaIdDesdeResultado($resultado),
        );
        $filas = self::enriquecerLinks($filas, $resultado);

        usort($filas, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['f_mov_ord'] ?? ''), (string) ($b['f_mov_ord'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['numero'] ?? ''), (string) ($b['numero'] ?? ''));
        });

        $debitos = 0.0;
        $creditos = 0.0;
        foreach ($filas as $f) {
            $debitos += (float) ($f['debito'] ?? 0);
            $creditos += (float) ($f['credito'] ?? 0);
        }

        $cc = $resultado['cuentacaja'] ?? null;
        $codigo = is_object($cc) ? (string) ($cc->codigo ?? '') : (string) (is_array($cc) ? ($cc['codigo'] ?? '') : '');
        $nombre = is_object($cc) ? (string) ($cc->nombre ?? '') : (string) (is_array($cc) ? ($cc['nombre'] ?? '') : '');

        return [
            'filas' => $filas,
            'totales' => [
                'n' => count($filas),
                'debitos' => round($debitos, 2),
                'creditos' => round($creditos, 2),
            ],
            'meta' => [
                'fecha_desde' => (string) ($resultado['fecha_desde'] ?? ''),
                'fecha_hasta' => (string) ($resultado['fecha_hasta'] ?? ''),
                'mes' => (int) ($resultado['mes'] ?? 0),
                'anio' => (int) ($resultado['anio'] ?? 0),
                'cuentacaja_codigo' => $codigo,
                'cuentacaja_nombre' => $nombre,
                'cuentacaja_label' => trim(
                    ($codigo !== '' ? '(' . str_pad(ltrim($codigo, '0') !== '' ? $codigo : '0', 8, '0', STR_PAD_LEFT) . ') ' : '')
                    .$nombre
                ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $ch
     * @return array<string, mixed>
     */
    private static function desdeCheque(array $ch, string $tip): array
    {
        $imp = (float) ($ch['importe'] ?? 0);
        $debe = $ch['debe'] ?? null;
        $haber = $ch['haber'] ?? null;
        if ($debe !== null && abs((float) $debe) < 0.005) {
            $debe = null;
        }
        if ($haber !== null && abs((float) $haber) < 0.005) {
            $haber = null;
        }
        if ($debe === null && $haber === null) {
            // Misma convención que Anita Bridge: importe (−) → Débitos, (+) → Créditos.
            // CHP emitido sin signo suele venir positivo (crédito pendiente de débito bancario).
            if (strcasecmp($tip, 'ANULA') === 0 || $imp < -0.005) {
                $debe = abs($imp) > 0.005 ? abs($imp) : null;
                $haber = null;
            } else {
                $debe = null;
                $haber = abs($imp) > 0.005 ? abs($imp) : null;
            }
        }

        $fMov = self::fmtFecha($ch['fecha_emision'] ?? null);
        $fDev = self::fmtFecha($ch['fecha_cheque'] ?? null);
        $fEntr = self::fmtFecha($ch['fecha_entrega'] ?? null);
        $fConc = self::fmtFecha($ch['fecha_conciliacion'] ?? null);

        $origen = is_array($ch['origen_json'] ?? null) ? $ch['origen_json'] : [];
        $chequeId = (int) ($origen['id'] ?? $ch['cheque_id'] ?? 0);

        return [
            'lado' => 'contable',
            'tip' => strtoupper(trim($tip)) !== '' ? strtoupper(trim($tip)) : 'CHP',
            'numero' => (string) ($ch['numero_cheque'] ?? ''),
            'n_orig' => (string) ($ch['nro_op'] ?? ''),
            'f_mov' => $fMov['txt'],
            'f_dev' => $fDev['txt'],
            'f_entr' => $fEntr['txt'],
            'f_conc' => $fConc['txt'],
            'f_mov_ord' => $fMov['ord'],
            'detalle' => (string) ($ch['entregado_a'] ?? ''),
            'debito' => $debe !== null ? round((float) $debe, 2) : 0.0,
            'credito' => $haber !== null ? round((float) $haber, 2) : 0.0,
            'n_con' => '',
            'detalle_concepto' => '',
            'asiento_id' => (int) ($ch['asiento_id'] ?? 0),
            'cheque_id' => $chequeId > 0 ? $chequeId : null,
            'pagoproveedor_id' => null,
            'concepto_id' => null,
            'hash' => (string) ($ch['hash'] ?? ''),
            'fuente' => 'cheque',
        ];
    }

    /**
     * @param  array<string, mixed>  $mov
     * @return array<string, mixed>
     */
    private static function desdeContable(array $mov): array
    {
        $tip = strtoupper(trim((string) ($mov['tipo_comp'] ?? '')));
        if ($tip === '') {
            $tip = 'ASI';
        }

        $nroCheque = ConciliacionBancariaReferenciaSupport::extraerChequeContable($mov);
        $nroOp = ConciliacionBancariaReferenciaSupport::extraerOrdenPagoContable($mov);
        $numero = $nroCheque !== null ? $nroCheque : (string) ($mov['nro'] ?? '');
        if ($numero === '' || $numero === '0') {
            $comp = preg_replace('/\D/', '', (string) ($mov['comprobante'] ?? '')) ?? '';
            $numero = $comp !== '' ? $comp : (string) ($mov['nro_asiento'] ?? '');
        }

        $debe = (float) ($mov['debe'] ?? 0);
        $haber = (float) ($mov['haber'] ?? 0);
        if (abs($debe) < 0.005 && abs($haber) < 0.005) {
            $firmado = ConciliacionBancariaHashSupport::importeFirmadoContable($mov);
            if ($firmado > 0.005) {
                $debe = abs($firmado);
            } elseif ($firmado < -0.005) {
                $haber = abs($firmado);
            }
        }

        $fMov = self::fmtFecha($mov['fecha'] ?? ($mov['fecha_fmt'] ?? null));

        return [
            'lado' => 'contable',
            'tip' => $tip,
            'numero' => ltrim((string) preg_replace('/\D/', '', (string) $numero), '0') ?: (string) $numero,
            'n_orig' => $nroOp && $nroOp !== $numero ? $nroOp : '',
            'f_mov' => $fMov['txt'],
            'f_dev' => $fMov['txt'],
            'f_entr' => '---',
            'f_conc' => '---',
            'f_mov_ord' => $fMov['ord'],
            'detalle' => (string) ($mov['descripcion'] ?? $mov['emisor'] ?? ''),
            'debito' => round($debe, 2),
            'credito' => round($haber, 2),
            'n_con' => (string) (($mov['concepto_id'] ?? '') ?: ''),
            'detalle_concepto' => (string) ($mov['concepto_nombre'] ?? ''),
            'asiento_id' => (int) ($mov['asiento_id'] ?? 0),
            'cheque_id' => null,
            'pagoproveedor_id' => (int) ($mov['pagoproveedor_id'] ?? 0) ?: null,
            'concepto_id' => (int) ($mov['concepto_id'] ?? 0) ?: null,
            'hash' => (string) ($mov['hash'] ?? ''),
            'fuente' => 'contable',
            'empresa_id' => (int) ($mov['empresa_id'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $mov
     * @return array<string, mixed>
     */
    private static function desdeBanco(array $mov): array
    {
        $tipo = strtoupper(trim((string) ($mov['debit_credit_type'] ?? '')));
        $imp = abs((float) ($mov['importe'] ?? $mov['amount'] ?? 0));
        $esDebito = str_starts_with($tipo, 'D') || (float) ($mov['importe'] ?? 0) < 0;
        $fMov = self::fmtFecha($mov['process_date'] ?? null);

        return [
            'lado' => 'banco',
            'tip' => 'IB',
            'numero' => (string) ($mov['voucher_number'] ?? $mov['id'] ?? ''),
            'n_orig' => '',
            'f_mov' => $fMov['txt'],
            'f_dev' => $fMov['txt'],
            'f_entr' => '---',
            'f_conc' => '---',
            'f_mov_ord' => $fMov['ord'],
            'detalle' => trim((string) ($mov['code_description_ib'] ?? '').' '.(string) ($mov['additional_info'] ?? '')),
            'debito' => $esDebito ? round($imp, 2) : 0.0,
            'credito' => ! $esDebito ? round($imp, 2) : 0.0,
            'n_con' => (string) ($mov['code'] ?? $mov['transaction_code'] ?? ''),
            'detalle_concepto' => (string) ($mov['code_description_ib'] ?? ''),
            'asiento_id' => 0,
            'cheque_id' => null,
            'pagoproveedor_id' => null,
            'concepto_id' => null,
            'interbanking_id' => (int) ($mov['id'] ?? 0) ?: null,
            'hash' => (string) ($mov['hash'] ?? ''),
            'fuente' => 'banco',
        ];
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private static function cuentacajaIdDesdeResultado(array $resultado): int
    {
        $cc = $resultado['cuentacaja'] ?? null;
        if (is_object($cc)) {
            return (int) ($cc->id ?? 0);
        }
        if (is_array($cc)) {
            return (int) ($cc['id'] ?? 0);
        }

        return (int) ($resultado['cuentacaja_id'] ?? 0);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    private static function enriquecerLinks(array $filas, array $resultado): array
    {
        $empresaId = (int) ($resultado['empresa_id'] ?? 0);
        $cuentacajaId = self::cuentacajaIdDesdeResultado($resultado);

        $nrosCheque = [];
        $nrosOp = [];
        foreach ($filas as $f) {
            $tip = strtoupper((string) ($f['tip'] ?? ''));
            $nro = preg_replace('/\D/', '', (string) ($f['numero'] ?? '')) ?? '';
            if ($nro !== '' && in_array($tip, ConciliacionBancariaChequeErpEnrichSupport::TIPS_CHEQUE, true) && empty($f['cheque_id'])) {
                $nrosCheque[$nro] = true;
            }
            if ($nro !== '' && in_array($tip, ['OPP', 'OPA'], true) && empty($f['pagoproveedor_id'])) {
                $nrosOp[(int) $nro] = true;
            }
            $nOrig = preg_replace('/\D/', '', (string) ($f['n_orig'] ?? '')) ?? '';
            if ($nOrig !== '' && empty($f['pagoproveedor_id'])) {
                $nrosOp[(int) $nOrig] = true;
            }
        }

        $mapCheque = [];
        if ($nrosCheque !== [] && Schema::hasTable('cheque')) {
            $q = Cheque::query()->whereIn('numerocheque', array_keys($nrosCheque));
            if ($cuentacajaId > 0 && Schema::hasColumn('cheque', 'cuentacaja_id')) {
                $q->where('cuentacaja_id', $cuentacajaId);
            }
            foreach ($q->get(['id', 'numerocheque']) as $ch) {
                $key = ltrim((string) preg_replace('/\D/', '', (string) $ch->numerocheque), '0');
                if ($key !== '') {
                    $mapCheque[$key] = (int) $ch->id;
                }
            }
        }

        $mapOp = [];
        if ($nrosOp !== [] && $empresaId > 0 && Schema::hasTable('pagoproveedor')) {
            $ops = Pagoproveedor::query()
                ->where('empresa_id', $empresaId)
                ->whereIn('numerotransaccion', array_keys($nrosOp))
                ->get(['id', 'numerotransaccion']);
            foreach ($ops as $op) {
                $mapOp[(int) $op->numerotransaccion] = (int) $op->id;
            }
        }

        foreach ($filas as &$f) {
            $tip = strtoupper((string) ($f['tip'] ?? ''));
            $nro = ltrim((string) preg_replace('/\D/', '', (string) ($f['numero'] ?? '')), '0');
            if (empty($f['cheque_id']) && $nro !== '' && isset($mapCheque[$nro])) {
                $f['cheque_id'] = $mapCheque[$nro];
            }
            if (empty($f['pagoproveedor_id'])) {
                $cand = (int) $nro;
                $nOrig = (int) ltrim((string) preg_replace('/\D/', '', (string) ($f['n_orig'] ?? '')), '0');
                if (in_array($tip, ['OPP', 'OPA'], true) && isset($mapOp[$cand])) {
                    $f['pagoproveedor_id'] = $mapOp[$cand];
                } elseif ($nOrig > 0 && isset($mapOp[$nOrig])) {
                    $f['pagoproveedor_id'] = $mapOp[$nOrig];
                }
            }

            $f['url_numero'] = self::urlNumero($f);
            $f['url_detalle'] = self::urlDetalle($f);
            $f['url_concepto'] = self::urlConcepto($f);
        }
        unset($f);

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private static function urlNumero(array $f): ?string
    {
        if (! empty($f['cheque_id']) && (int) $f['cheque_id'] > 0) {
            return route('editar_cheque', [
                'id' => (int) $f['cheque_id'],
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
        }
        if (! empty($f['pagoproveedor_id']) && (int) $f['pagoproveedor_id'] > 0) {
            return route('editar_pagoproveedor', [
                'id' => (int) $f['pagoproveedor_id'],
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
        }
        if (! empty($f['asiento_id']) && (int) $f['asiento_id'] > 0) {
            return route('editar_asiento', [
                'id' => (int) $f['asiento_id'],
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private static function urlDetalle(array $f): ?string
    {
        if (! empty($f['asiento_id']) && (int) $f['asiento_id'] > 0) {
            return route('editar_asiento', [
                'id' => (int) $f['asiento_id'],
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
        }
        if (! empty($f['pagoproveedor_id']) && (int) $f['pagoproveedor_id'] > 0) {
            return route('editar_pagoproveedor', [
                'id' => (int) $f['pagoproveedor_id'],
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
        }
        if (! empty($f['cheque_id']) && (int) $f['cheque_id'] > 0) {
            return route('editar_cheque', [
                'id' => (int) $f['cheque_id'],
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private static function urlConcepto(array $f): ?string
    {
        $id = (int) ($f['concepto_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        try {
            return route('editar_conceptogasto', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private static function claveFila(array $fila): string
    {
        $hash = (string) ($fila['hash'] ?? '');
        if ($hash !== '') {
            return $fila['fuente'].':'.$hash;
        }

        return implode('|', [
            $fila['fuente'] ?? '',
            $fila['tip'] ?? '',
            $fila['numero'] ?? '',
            $fila['f_mov_ord'] ?? '',
            number_format((float) ($fila['debito'] ?? 0), 2, '.', ''),
            number_format((float) ($fila['credito'] ?? 0), 2, '.', ''),
            mb_substr((string) ($fila['detalle'] ?? ''), 0, 40),
        ]);
    }

    /**
     * @return array{txt: string, ord: string}
     */
    private static function fmtFecha(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === '0' || $raw === 0) {
            return ['txt' => '---', 'ord' => '00000000'];
        }
        try {
            if (is_int($raw) || (is_string($raw) && preg_match('/^\d{8}$/', $raw))) {
                $c = Carbon::createFromFormat('Ymd', (string) $raw)->startOfDay();
            } else {
                $c = Carbon::parse((string) $raw)->startOfDay();
            }

            return [
                'txt' => $c->format('m/d/Y'),
                'ord' => $c->format('Ymd'),
            ];
        } catch (\Throwable) {
            return ['txt' => '---', 'ord' => '00000000'];
        }
    }
}
