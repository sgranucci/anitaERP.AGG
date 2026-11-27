<?php

declare(strict_types=1);

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Caja\Cheque;
use App\Models\Compras\Pagoproveedor;
use App\Models\Contable\ConciliacionBancariaPar;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Armado del listado estilo Anita “Movimientos de Cuentas sin conciliar”.
 *
 * Anita Tesorería lista cheques / OP / cobranzas / transferencias tesmov
 * (CH*, ACP, ANULA, OPP/OPA, COB/COA/COC, TED/TEH, EGR). La TRA del ERP sale como TED.
 * Un cheque sale una sola vez (CHP), no además como OPP.
 * No mete ASI ni movimientos Interbanking (IB): esos van a la carátula /
 * solapas de conciliación, no a este reporte.
 */
final class ConciliacionBancariaSinConciliarReporteSupport
{
    /** Tips que Anita incluye en “Movimientos de Cuentas sin conciliar”. */
    public const TIPS_ANITA = [
        'CHP', 'CHD', 'CHQ', 'CHE', 'ACP', 'ANULA', 'OPP', 'OPA',
        'COB', 'COA', 'COC', 'TED', 'TEH', 'EGR',
    ];

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
        $vistosNegocio = [];

        foreach ($resultado['pendientes_cheques_anulas'] ?? [] as $ch) {
            if (! is_array($ch)) {
                continue;
            }
            self::agregarFila($filas, $vistos, $vistosNegocio, self::desdeCheque($ch, 'ANULA'));
        }

        foreach ($resultado['pendientes_cheques_cpromae'] ?? [] as $ch) {
            if (! is_array($ch)) {
                continue;
            }
            self::agregarFila(
                $filas,
                $vistos,
                $vistosNegocio,
                self::desdeCheque($ch, (string) ($ch['tip'] ?? 'CHP')),
            );
        }

        // OPP/OPA (y tip Anita) del mayor sin stock cpromae. Excluye ASI/TRA/etc.
        foreach ($resultado['pendientes_contables_otros'] ?? [] as $mov) {
            if (! is_array($mov)) {
                continue;
            }
            $fila = self::desdeContable($mov);
            if (! self::tipEsAnita((string) ($fila['tip'] ?? ''))) {
                continue;
            }
            self::agregarFila($filas, $vistos, $vistosNegocio, $fila);
        }

        // Cheques del mayor pendientes que no estén en stock cpromae (p. ej. tip CH* sin cpromae).
        foreach ($resultado['pendientes_contables_cheques'] ?? [] as $mov) {
            if (! is_array($mov)) {
                continue;
            }
            self::agregarFila($filas, $vistos, $vistosNegocio, self::desdeContable($mov));
        }

        // No incluir pendientes_banco (IB): Anita no los lista en este reporte.

        // La anulación del cheque queda en el mayor como "ANULA OPP" y Anita la lista
        // aparte, con tip ACP, el mismo número de cheque y la fecha de la anulación.
        foreach ($resultado['pendientes_contables_anulas'] ?? [] as $mov) {
            if (! is_array($mov) || ! ConciliacionBancariaPendienteSupport::esAnulaChequeContable($mov)) {
                continue;
            }
            self::agregarFila($filas, $vistos, $vistosNegocio, self::desdeAnulacionCheque($mov));
        }

        // Anita lista el cheque como CHP (aunque después se anule). El OPP del mayor con
        // "Ch: N" no se muestra otra vez. Un cheque ya conciliado contra el banco deja de
        // figurar como CHP; la ACP de la anulación sigue, porque es otro movimiento.
        $empresaId = (int) ($resultado['empresa_id'] ?? 0);
        $cuentacajaId = self::cuentacajaIdDesdeResultado($resultado);
        $filas = self::unificarChequeYOrdenPago($filas, $empresaId, $cuentacajaId);
        // OPP "cheque a descontar" sin "Ch: N": Anita la lista como CHP, con el número del cheque.
        $filas = self::mostrarOppDeChequeComoChp($filas, $empresaId, $cuentacajaId);
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
            $cmp = strcmp((string) ($a['numero'] ?? ''), (string) ($b['numero'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            $ordenTip = static fn (string $tip): int => $tip === 'ACP' ? 1 : 0;

            return $ordenTip(strtoupper((string) ($a['tip'] ?? ''))) <=> $ordenTip(strtoupper((string) ($b['tip'] ?? '')));
        });

        $debitos = 0.0;
        $creditos = 0.0;
        foreach ($filas as $f) {
            $debitos += (float) ($f['debito'] ?? 0);
            $creditos += (float) ($f['credito'] ?? 0);
        }

        return [
            'filas' => $filas,
            'totales' => [
                'n' => count($filas),
                'debitos' => round($debitos, 2),
                'creditos' => round($creditos, 2),
            ],
            'meta' => self::metaDesdeResultado($resultado),
        ];
    }

    /**
     * Mismo listado “Movimientos de Cuentas sin conciliar”, pero del lado Interbanking:
     * movimientos del extracto bancario que no se emparejaron con el mayor (`pendientes_banco`).
     *
     * Misma estructura de filas que {@see armar()} para reutilizar Excel, PDF y CSV.
     * Débitos = movimientos que el banco debitó (importe negativo); Créditos = acreditados.
     *
     * @param  array<string, mixed>  $resultado  Salida de ConciliacionBancariaService::ejecutar / cache
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   totales: array{n: int, debitos: float, creditos: float},
     *   meta: array<string, mixed>
     * }
     */
    public static function armarInterbanking(array $resultado): array
    {
        $filas = [];
        $debitos = 0.0;
        $creditos = 0.0;

        foreach ($resultado['pendientes_banco'] ?? [] as $mov) {
            $mov = is_object($mov) ? (array) $mov : $mov;
            if (! is_array($mov)) {
                continue;
            }

            $importe = round((float) ($mov['amount'] ?? $mov['importe'] ?? 0), 2);
            $esDebito = $importe < 0
                || ($importe == 0.0 && strtoupper((string) ($mov['debit_credit_type'] ?? '')) === 'D');
            $debito = $esDebito ? abs($importe) : 0.0;
            $credito = $esDebito ? 0.0 : abs($importe);
            $debitos += $debito;
            $creditos += $credito;

            $fMov = self::fmtFechaInterbanking($mov['process_date'] ?? null);
            $voucher = $mov['voucher_number'] ?? null;
            $depositante = trim((string) ($mov['depositor_description'] ?? ''));
            $cuit = trim((string) ($mov['customer_cuit'] ?? ''));

            $filas[] = [
                'lado' => 'banco',
                'tip' => trim((string) ($mov['code_description_ib'] ?? '')),
                'numero' => ($voucher !== null && $voucher !== '' && (string) $voucher !== '0') ? (string) $voucher : '',
                'n_orig' => '',
                'f_mov' => $fMov['txt'],
                'f_dev' => '',
                'f_entr' => '',
                'f_conc' => '',
                'f_mov_ord' => $fMov['ord'],
                'detalle' => trim((string) ($mov['code_description_bank'] ?? '')),
                'debito' => $debito,
                'credito' => $credito,
                'n_con' => '',
                'detalle_concepto' => $depositante !== '' ? $depositante : ($cuit !== '' ? 'CUIT '.$cuit : ''),
                'asiento_id' => 0,
                'cheque_id' => null,
                'pagoproveedor_id' => null,
                'concepto_id' => null,
                'hash' => (string) ($mov['hash'] ?? $mov['dedupe_hash'] ?? ''),
                'fuente' => 'interbanking',
            ];
        }

        usort($filas, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['f_mov_ord'] ?? ''), (string) ($b['f_mov_ord'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['numero'] ?? ''), (string) ($b['numero'] ?? ''));
        });

        return [
            'filas' => $filas,
            'totales' => [
                'n' => count($filas),
                'debitos' => round($debitos, 2),
                'creditos' => round($creditos, 2),
            ],
            'meta' => array_merge(self::metaDesdeResultado($resultado), [
                'lado' => 'interbanking',
                'titulo' => 'Movimientos de Interbanking sin conciliar',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    private static function metaDesdeResultado(array $resultado): array
    {
        $cc = $resultado['cuentacaja'] ?? null;
        $codigo = is_object($cc) ? (string) ($cc->codigo ?? '') : (string) (is_array($cc) ? ($cc['codigo'] ?? '') : '');
        $nombre = is_object($cc) ? (string) ($cc->nombre ?? '') : (string) (is_array($cc) ? ($cc['nombre'] ?? '') : '');

        return [
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
        ];
    }

    /**
     * `process_date` del extracto llega como ISO en UTC (03:00Z = 00:00 en Argentina): se pasa a la
     * zona de la app antes de tomar el día. Mismo formato de salida que el resto del reporte.
     *
     * @return array{txt: string, ord: string}
     */
    private static function fmtFechaInterbanking(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return ['txt' => '---', 'ord' => '00000000'];
        }
        try {
            $c = Carbon::parse((string) $raw)->setTimezone((string) config('app.timezone'))->startOfDay();

            return [
                'txt' => $c->format('m/d/Y'),
                'ord' => $c->format('Ymd'),
            ];
        } catch (\Throwable) {
            return ['txt' => '---', 'ord' => '00000000'];
        }
    }

    /**
     * Lo pendiente del ERP es lo mismo que muestra la pantalla de conciliación: movimientos del mayor
     * que no están en ningún cruce. Sin filtro por tipo ni stock de pendientes de Anita.
     *
     * @param  list<array<string, mixed>>  $contablesPendientes  Movimientos del mayor (con `hash`) sin cruce
     * @param  array<string, mixed>  $resultado  Cabecera: empresa_id, cuentacaja, mes, anio, fecha_desde, fecha_hasta
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   totales: array{n: int, debitos: float, creditos: float},
     *   meta: array<string, mixed>
     * }
     */
    public static function armarDesdeTablero(array $contablesPendientes, array $resultado): array
    {
        $filas = [];
        foreach ($contablesPendientes as $mov) {
            if (! is_array($mov)) {
                continue;
            }
            $fila = self::desdeContable($mov);
            $tipo = strtoupper(trim((string) ($mov['tipo_comp'] ?? '')));
            $fila['tip'] = $tipo !== '' ? $tipo : 'ASI';
            // Pago con cheque (OPP/OPA con "Ch: N"): se lista como cheque, con el link al cheque.
            if (in_array($fila['tip'], ['OPP', 'OPA'], true) && (string) ($fila['cheque_ref'] ?? '') !== '') {
                $fila['tip'] = 'CHP';
            }
            $fila['detalle'] = (string) ($mov['descripcion'] ?? $mov['emisor'] ?? '');
            $filas[] = $fila;
        }
        $filas = self::enriquecerLinks($filas, $resultado);

        usort($filas, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['f_mov_ord'] ?? ''), (string) ($b['f_mov_ord'] ?? ''));

            return $cmp !== 0 ? $cmp : strcmp((string) ($a['numero'] ?? ''), (string) ($b['numero'] ?? ''));
        });

        $debitos = 0.0;
        $creditos = 0.0;
        foreach ($filas as $f) {
            $debitos += (float) ($f['debito'] ?? 0);
            $creditos += (float) ($f['credito'] ?? 0);
        }

        return [
            'filas' => $filas,
            'totales' => [
                'n' => count($filas),
                'debitos' => round($debitos, 2),
                'creditos' => round($creditos, 2),
            ],
            'meta' => self::metaDesdeResultado($resultado),
        ];
    }

    /**
     * Deja solo los movimientos con fecha de movimiento hasta la fecha pedida (inclusive) y recalcula
     * contador y totales. Sirve para pedir el reporte "al día" sin lo que se está cargando ahora.
     *
     * @param  array<string, mixed>  $reporte  salida de armar()
     * @param  string  $hastaYmd  Y-m-d
     * @return array<string, mixed>
     */
    public static function limitarHasta(array $reporte, string $hastaYmd): array
    {
        $corte = preg_replace('/\D/', '', $hastaYmd);
        if (strlen((string) $corte) !== 8) {
            return $reporte;
        }

        $filas = array_values(array_filter(
            $reporte['filas'] ?? [],
            static fn (array $f): bool => (string) ($f['f_mov_ord'] ?? '') <= $corte,
        ));

        $debitos = 0.0;
        $creditos = 0.0;
        foreach ($filas as $f) {
            $debitos += (float) ($f['debito'] ?? 0);
            $creditos += (float) ($f['credito'] ?? 0);
        }

        $reporte['filas'] = $filas;
        $reporte['totales'] = [
            'n' => count($filas),
            'debitos' => round($debitos, 2),
            'creditos' => round($creditos, 2),
        ];
        $reporte['meta']['fecha_hasta'] = substr($corte, 0, 4).'-'.substr($corte, 4, 2).'-'.substr($corte, 6, 2);

        return $reporte;
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
     * Una anulación de OP no concilia el cheque: la OPP y su "ANULA OPP" siguen sin
     * movimiento de banco. El reporte las deja pendientes.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private static function unificarChequeYOrdenPago(array $filas, int $empresaId, int $cuentacajaId): array
    {
        $conciliados = self::chequesConciliadosContraBanco($empresaId, $cuentacajaId);
        $esTipCheque = static fn (string $tip): bool => in_array(
            $tip,
            ConciliacionBancariaChequeErpEnrichSupport::TIPS_CHEQUE,
            true,
        );

        $salida = [];
        $chpListados = [];
        foreach ($filas as $f) {
            $tip = strtoupper((string) ($f['tip'] ?? ''));
            $nro = ltrim((string) ($f['numero'] ?? ''), '0');
            if (($f['fuente'] ?? '') === 'cheque' && $esTipCheque($tip)) {
                if ($nro !== '' && isset($conciliados[$nro])) {
                    continue;
                }
                $chpListados[$nro] = true;
            }
            $salida[] = $f;
        }

        // OPP/OPA con "Ch: N": el cheque ya está listado (o conciliado) → se omite el OPP.
        $porConvertir = [];
        $resto = [];
        foreach ($salida as $f) {
            $ref = (string) ($f['cheque_ref'] ?? '');
            $tip = strtoupper((string) ($f['tip'] ?? ''));
            if ($ref === '' || ! in_array($tip, ['OPP', 'OPA'], true) || ($f['fuente'] ?? '') !== 'contable') {
                $resto[] = $f;
                continue;
            }
            if (isset($conciliados[$ref]) || isset($chpListados[$ref])) {
                continue;
            }
            $porConvertir[$ref][] = $f;
            $resto[] = $f;
        }

        if ($porConvertir === [] || ! Schema::hasTable('cheque')) {
            return $resto;
        }

        $q = Cheque::query()
            ->with(['proveedores:id,codigo,nombre'])
            ->whereIn('numerocheque', array_keys($porConvertir));
        if ($cuentacajaId > 0 && Schema::hasColumn('cheque', 'cuentacaja_id')) {
            $q->where('cuentacaja_id', $cuentacajaId);
        }
        $cheques = [];
        foreach ($q->get(['id', 'numerocheque', 'monto', 'fechaemision', 'fechapago', 'anombrede', 'proveedor_id']) as $ch) {
            $key = ltrim((string) preg_replace('/\D/', '', (string) $ch->numerocheque), '0');
            if ($key !== '') {
                $cheques[$key] = $ch;
            }
        }

        $fecha = static function (mixed $v): ?string {
            if ($v instanceof \DateTimeInterface) {
                return $v->format('Y-m-d');
            }

            return $v ? substr((string) $v, 0, 10) : null;
        };

        $final = [];
        $convertidos = [];
        foreach ($resto as $f) {
            $ref = (string) ($f['cheque_ref'] ?? '');
            $tipFila = strtoupper((string) ($f['tip'] ?? ''));
            if ($ref === '' || ! isset($porConvertir[$ref]) || ($f['fuente'] ?? '') !== 'contable'
                || ! in_array($tipFila, ['OPP', 'OPA'], true)) {
                $final[] = $f;
                continue;
            }
            $ch = $cheques[$ref] ?? null;
            if ($ch === null) {
                $final[] = $f;
                continue;
            }
            if (isset($convertidos[$ref])) {
                continue;
            }
            $convertidos[$ref] = true;
            $anombre = trim((string) ($ch->anombrede ?? ''));
            $nombre = trim((string) ($ch->proveedores->nombre ?? ''));
            $fila = self::desdeCheque([
                'numero_cheque' => $ref,
                'fecha_emision' => $fecha($ch->fechaemision),
                'fecha_cheque' => $fecha($ch->fechapago),
                'fecha_entrega' => null,
                'fecha_conciliacion' => null,
                'importe' => abs((float) $ch->monto),
                'entregado_a' => $anombre !== '' ? $anombre : $nombre,
                'nro_op' => '',
                'cheque_id' => (int) $ch->id,
                'asiento_id' => (int) ($f['asiento_id'] ?? 0),
                'hash' => (string) ($f['hash'] ?? ''),
            ], 'CHP');
            $final[] = $fila;
        }

        return $final;
    }

    /**
     * Una OPP cuyo texto habla de un cheque y no trae "Ch: N" se muestra como CHP
     * cuando hay un cheque de la misma cuenta, emitido el mismo día y por el mismo importe.
     * Si hay más de un cheque para esa fecha e importe, la OPP queda como está.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private static function mostrarOppDeChequeComoChp(array $filas, int $empresaId, int $cuentacajaId): array
    {
        if ($empresaId <= 0 || $cuentacajaId <= 0 || ! Schema::hasTable('cheque')) {
            return $filas;
        }

        $candidatos = [];
        $fechas = [];
        foreach ($filas as $i => $f) {
            $tip = strtoupper((string) ($f['tip'] ?? ''));
            if (! in_array($tip, ['OPP', 'OPA'], true) || ($f['fuente'] ?? '') !== 'contable') {
                continue;
            }
            if ((string) ($f['cheque_ref'] ?? '') !== '') {
                continue;
            }
            if (! preg_match('/\bcheques?\b/i', (string) ($f['detalle'] ?? ''))) {
                continue;
            }
            $fecha = (string) ($f['f_mov_ord'] ?? '');
            $importe = round(max((float) ($f['credito'] ?? 0), (float) ($f['debito'] ?? 0)), 2);
            if (! preg_match('/^\d{8}$/', $fecha) || $fecha === '00000000' || $importe < 0.005) {
                continue;
            }
            $candidatos[$i] = $fecha.'|'.number_format($importe, 2, '.', '');
            $fechas[$fecha] = true;
        }
        if ($candidatos === []) {
            return $filas;
        }

        $desde = Carbon::createFromFormat('Ymd', min(array_keys($fechas)))->toDateString();
        $hasta = Carbon::createFromFormat('Ymd', max(array_keys($fechas)))->toDateString();
        $porClave = [];
        $rows = Cheque::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacaja_id', $cuentacajaId)
            ->where(function ($q): void {
                $q->whereNull('estado')->orWhere('estado', '<>', 'A');
            })
            ->whereDate('fechaemision', '>=', $desde)
            ->whereDate('fechaemision', '<=', $hasta)
            ->get(['id', 'numerocheque', 'monto', 'fechaemision', 'fechapago', 'anombrede']);
        foreach ($rows as $ch) {
            $emision = $ch->fechaemision instanceof \DateTimeInterface
                ? $ch->fechaemision->format('Ymd')
                : ($ch->fechaemision ? Carbon::parse((string) $ch->fechaemision)->format('Ymd') : '');
            if ($emision === '') {
                continue;
            }
            $clave = $emision.'|'.number_format(abs((float) $ch->monto), 2, '.', '');
            $porClave[$clave][] = $ch;
        }

        $pedidos = array_count_values($candidatos);
        $yaListado = [];
        foreach ($filas as $f) {
            $tip = strtoupper((string) ($f['tip'] ?? ''));
            if (($f['fuente'] ?? '') === 'cheque' || in_array($tip, ['CHP', 'CHD', 'CHQ', 'CHE'], true)) {
                $nro = ltrim((string) preg_replace('/\D/', '', (string) ($f['numero'] ?? '')), '0');
                if ($nro !== '') {
                    $yaListado[$nro] = true;
                }
            }
        }
        $conciliados = self::chequesConciliadosContraBanco($empresaId, $cuentacajaId);

        $usados = [];
        $final = [];
        foreach ($filas as $i => $f) {
            if (! isset($candidatos[$i])) {
                $final[] = $f;
                continue;
            }
            $clave = $candidatos[$i];
            $chs = $porClave[$clave] ?? [];
            if (count($chs) !== ($pedidos[$clave] ?? 0)) {
                $final[] = $f;
                continue;
            }
            $ch = null;
            foreach ($chs as $candidato) {
                $nro = ltrim((string) preg_replace('/\D/', '', (string) $candidato->numerocheque), '0');
                if ($nro === '' || isset($usados[$nro])) {
                    continue;
                }
                $usados[$nro] = true;
                $ch = $candidato;
                break;
            }
            if ($ch === null) {
                $final[] = $f;
                continue;
            }
            $nro = ltrim((string) preg_replace('/\D/', '', (string) $ch->numerocheque), '0');
            if (isset($yaListado[$nro]) || isset($conciliados[$nro])) {
                continue;
            }
            $pago = $ch->fechapago instanceof \DateTimeInterface
                ? $ch->fechapago->format('Y-m-d')
                : ($ch->fechapago ? substr((string) $ch->fechapago, 0, 10) : null);
            $emision = $ch->fechaemision instanceof \DateTimeInterface
                ? $ch->fechaemision->format('Y-m-d')
                : ($ch->fechaemision ? substr((string) $ch->fechaemision, 0, 10) : null);
            $final[] = self::desdeCheque([
                'numero_cheque' => $nro,
                'fecha_emision' => $emision,
                'fecha_cheque' => $pago,
                'importe' => abs((float) $ch->monto),
                'entregado_a' => trim((string) ($ch->anombrede ?? '')),
                'nro_op' => '',
                'cheque_id' => (int) $ch->id,
                'asiento_id' => (int) ($f['asiento_id'] ?? 0),
                'hash' => (string) ($f['hash'] ?? ''),
            ], 'CHP');
        }

        return $final;
    }

    /**
     * Cheques cuyo OPP quedó conciliado contra una línea real del banco.
     * Los pares de “cierre Excel” (contable y banco inventados) no cuentan.
     *
     * @return array<string, true>
     */
    private static function chequesConciliadosContraBanco(int $empresaId, int $cuentacajaId): array
    {
        if ($empresaId <= 0 || $cuentacajaId <= 0 || ! Schema::hasTable('conciliacion_bancaria_par')) {
            return [];
        }

        $out = [];
        ConciliacionBancariaPar::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacaja_id', $cuentacajaId)
            ->where('contable_json', 'like', '%Ch:%')
            ->select(['id', 'hash_contable', 'hash_banco', 'contable_json'])
            ->chunkById(2000, static function ($pares) use (&$out): void {
                foreach ($pares as $par) {
                    if ((string) $par->hash_banco === hash('sha256', 'cierre_excel|banco|'.$par->hash_contable)) {
                        continue;
                    }
                    $contable = is_array($par->contable_json) ? $par->contable_json : [];
                    $nro = ConciliacionBancariaReferenciaSupport::extraerChequeContable($contable);
                    if ($nro !== null) {
                        $out[ltrim($nro, '0')] = true;
                    }
                }
            });

        return $out;
    }

    /**
     * Anulación de cheque pendiente: tip ACP, número de cheque, fecha de la anulación
     * en F.Mov y F.Dev, detalle "ANULA OPP nnnnn". El importe va a Débitos.
     *
     * @param  array<string, mixed>  $mov
     * @return array<string, mixed>
     */
    private static function desdeAnulacionCheque(array $mov): array
    {
        $fila = self::desdeContable($mov);
        $fila['tip'] = 'ACP';
        $desc = trim((string) ($mov['descripcion'] ?? ''));
        if (preg_match('/ANULA\s+OPP\s+(\d+)/i', $desc, $m)) {
            $fila['detalle'] = 'ANULA OPP '.ltrim($m[1], '0');
        }
        $fila['f_dev'] = (string) ($fila['f_mov'] ?? '---');

        return $fila;
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
        // La transferencia entre cuentas del ERP (TRA) es el TED de Anita.
        if ($tip === 'TRA') {
            $tip = 'TED';
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

        $detalle = (string) ($mov['descripcion'] ?? $mov['emisor'] ?? '');
        if (strtoupper(trim((string) ($mov['tipo_comp'] ?? ''))) === 'TRA' && $numero !== '') {
            // Anita antepone el número de la transferencia: "TRA 4080 Trf bi bank a Macro".
            $detalle = trim('TRA '.ltrim((string) preg_replace('/\D/', '', (string) $numero), '0').' '.$detalle);
        }

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
            'detalle' => $detalle,
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
            'cheque_ref' => $nroCheque !== null ? ltrim($nroCheque, '0') : '',
            'empresa_id' => (int) ($mov['empresa_id'] ?? 0),
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

    public static function tipEsAnita(string $tip): bool
    {
        return in_array(strtoupper(trim($tip)), self::TIPS_ANITA, true);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, true>  $vistos
     * @param  array<string, true>  $vistosNegocio
     * @param  array<string, mixed>  $fila
     */
    private static function agregarFila(array &$filas, array &$vistos, array &$vistosNegocio, array $fila): void
    {
        if (! self::tipEsAnita((string) ($fila['tip'] ?? ''))) {
            return;
        }

        $clave = self::claveFila($fila);
        if (isset($vistos[$clave])) {
            return;
        }

        // Mismo tip+número+importe con otro hash/detalle (p. ej. OPP en cpromae
        // como "SUELDOS" y otra vez en el mayor como "NETOS…"): Anita lista una sola.
        $negocio = self::claveNegocio($fila);
        if ($negocio !== '' && isset($vistosNegocio[$negocio])) {
            return;
        }

        $vistos[$clave] = true;
        if ($negocio !== '') {
            $vistosNegocio[$negocio] = true;
        }
        $filas[] = $fila;
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
     * Clave de negocio tip|numero|importes (sin detalle ni hash).
     *
     * @param  array<string, mixed>  $fila
     */
    private static function claveNegocio(array $fila): string
    {
        $tip = strtoupper(trim((string) ($fila['tip'] ?? '')));
        $numero = ltrim((string) preg_replace('/\D/', '', (string) ($fila['numero'] ?? '')), '0');
        if ($tip === '' || $numero === '') {
            return '';
        }

        return implode('|', [
            $tip,
            $numero,
            number_format((float) ($fila['debito'] ?? 0), 2, '.', ''),
            number_format((float) ($fila['credito'] ?? 0), 2, '.', ''),
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
