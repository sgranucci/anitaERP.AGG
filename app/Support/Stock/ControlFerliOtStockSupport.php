<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Services\Stock\Articulo_MovimientoService;
use App\Services\Ventas\OrdentrabajoService;
use App\Support\Database\SqlDialectSupport;
use App\Support\Ventas\PedidoPickingFerliSupport;
use Illuminate\Support\Facades\DB;

/**
 * Control de solo lectura del stock Ferli por lote/OT y depósito.
 *
 * El saldo canónico de cada bucket es OtBucketSaldoSupport (suma de talles, sin la
 * factura ni la devolución de un compañero de OT). Se compara contra lo que muestran
 * o validan Stock por OT, el picking, el control de egresos, el consumo de OT stock
 * y el modal F1, y se listan los datos que dejan el saldo mal armado.
 */
final class ControlFerliOtStockSupport
{
    public const SEVERIDAD_ERROR = 'error';

    public const SEVERIDAD_AVISO = 'aviso';

    private const TOLERANCIA = 0.0001;

    private const CONTROLES = [
        'reporte_vs_canonico' => [self::SEVERIDAD_ERROR, 'Stock por OT distinto del saldo del bucket'],
        'picking_vs_canonico' => [self::SEVERIDAD_ERROR, 'Saldo del picking distinto del saldo del bucket'],
        'egreso_rechaza_saldo' => [self::SEVERIDAD_ERROR, 'Control de egreso rechaza pares que el bucket tiene'],
        'egreso_permite_exceso' => [self::SEVERIDAD_ERROR, 'Control de egreso acepta más pares que el bucket'],
        'consumo_ot_vs_canonico' => [self::SEVERIDAD_ERROR, 'Consumo de OT stock informa otro saldo o un depósito sin saldo'],
        'f1_vs_canonico' => [self::SEVERIDAD_ERROR, 'Modal F1 oculta o muestra otro saldo'],
        'movimiento_sin_talles' => [self::SEVERIDAD_ERROR, 'Movimiento de lote/OT sin numeración por talle'],
        'cabecera_distinta_talles' => [self::SEVERIDAD_ERROR, 'Movimiento de lote/OT con cantidad distinta de la suma de talles'],
        'bucket_negativo' => [self::SEVERIDAD_AVISO, 'Bucket con saldo negativo (oculto en Stock por OT)'],
        'talle_negativo_total_positivo' => [self::SEVERIDAD_AVISO, 'Bucket con saldo positivo y algún talle negativo'],
        'baja_previa_reemplazo' => [self::SEVERIDAD_ERROR, 'Movimiento ya descontado por el reemplazo de Excel, borrado o cambiado sin regrabar ni compensar'],
        'residuo_reemplazo_excel' => [self::SEVERIDAD_AVISO, 'Saldo previo al reemplazo de Excel que el CONOT no llevó a cero'],
        'devolucion_companero' => [self::SEVERIDAD_AVISO, 'Devolución del consumo de un compañero de OT: suma al saldo (pares devueltos)'],
        'tra_origen_no_descontado' => [self::SEVERIDAD_AVISO, 'TRA que descontó un bucket nuevo y dejó el lote original con saldo en el origen'],
    ];

    /** @var array<string, array{severidad: string, descripcion: string, cantidad: int, pares: float, ejemplos: list<array<string, mixed>>}> */
    private array $controles = [];

    private int $maxEjemplos = 20;

    /** @var array{articulo_id: int, combinacion_id: int, deposito_id: int} */
    private array $filtro = ['articulo_id' => 0, 'combinacion_id' => 0, 'deposito_id' => 0];

    public function __construct(
        private Articulo_MovimientoService $movimientos,
        private OrdentrabajoService $ordenes
    ) {}

    /**
     * @param  array{articulo_id?: int, combinacion_id?: int, deposito_id?: int, desde?: ?string, ejemplos?: int, validadores?: bool}  $opciones
     * @return array{controles: array<string, array{severidad: string, descripcion: string, cantidad: int, pares: float, ejemplos: list<array<string, mixed>>}>, divergencias: int, buckets: int}
     */
    public function ejecutar(array $opciones): array
    {
        $this->filtro = [
            'articulo_id' => (int) ($opciones['articulo_id'] ?? 0),
            'combinacion_id' => (int) ($opciones['combinacion_id'] ?? 0),
            'deposito_id' => (int) ($opciones['deposito_id'] ?? 0),
        ];
        $this->maxEjemplos = max(0, (int) ($opciones['ejemplos'] ?? 20));
        $this->controles = [];
        foreach (self::CONTROLES as $clave => [$severidad, $descripcion]) {
            $this->controles[$clave] = [
                'severidad' => $severidad,
                'descripcion' => $descripcion,
                'cantidad' => 0,
                'pares' => 0.0,
                'ejemplos' => [],
            ];
        }

        $buckets = $this->bucketsCanonicos();
        $this->controlarBuckets($buckets);
        $this->controlarTallesDeMovimientos($opciones['desde'] ?? null);
        $this->controlarBajasPreviasReemplazo($opciones['desde'] ?? null);
        $filas = $this->movimientosDeBuckets();
        $this->controlarResiduoReemplazoExcel($filas);
        $this->controlarTraOrigen($filas);
        $this->controlarDevolucionesCompanero();
        if ($opciones['validadores'] ?? true) {
            $this->controlarValidadores($buckets);
        }

        return [
            'controles' => $this->controles,
            'divergencias' => self::cantidadDivergencias($this->controles),
            'buckets' => count($buckets),
        ];
    }

    /**
     * @param  array<string, array{severidad: string, cantidad: int}>  $controles
     */
    public static function cantidadDivergencias(array $controles): int
    {
        $total = 0;
        foreach ($controles as $control) {
            if (($control['severidad'] ?? '') === self::SEVERIDAD_ERROR) {
                $total += (int) ($control['cantidad'] ?? 0);
            }
        }

        return $total;
    }

    /**
     * Residuo de un bucket con reemplazo de Excel: lo que había antes del reemplazo más
     * el CONOT (que debía llevarlo a cero). Las filas van en orden de id.
     *
     * @param  list<array{concepto: string, cantidad: float}>  $filas
     * @return array{tiene_reemplazo: bool, residuo: float, saldo: float}
     */
    public static function residuoReemplazoExcel(array $filas): array
    {
        $previo = 0.0;
        $conot = 0.0;
        $saldo = 0.0;
        $visto = false;
        foreach ($filas as $fila) {
            $cantidad = (float) $fila['cantidad'];
            $saldo += $cantidad;
            $esReemplazo = CorteReemplazoExcelFerliSupport::esReemplazo((string) $fila['concepto']);
            if ($esReemplazo) {
                $visto = true;
                if ($cantidad < 0) {
                    $conot += $cantidad;
                }

                continue;
            }
            if (! $visto) {
                $previo += $cantidad;
            }
        }

        return ['tiene_reemplazo' => $visto, 'residuo' => $previo + $conot, 'saldo' => $saldo];
    }

    /**
     * @return array<string, array{articulo_id: int, combinacion_id: int, clave: string, ordentrabajo_id: int, lote: string, deposito_id: int, saldo: float, talles: array<string, float>}>
     */
    private function bucketsCanonicos(): array
    {
        $q = DB::table('articulo_movimiento as am')
            ->join('articulo_movimiento_talle as amt', 'amt.articulo_movimiento_id', '=', 'am.id')
            ->join('talle as t', 't.id', '=', 'amt.talle_id')
            ->where('am.combinacion_id', '>', 0)
            ->where('am.deposito_id', '>', 0)
            ->where(fn ($w) => $w->where('am.lote', '>', 0)->orWhere('am.ordentrabajo_id', '>', 0));
        $this->aplicarFiltro($q);
        OtClienteCompartidoStockSupport::excluirConsumoFacturaCompanero($q, 'am');

        $rows = $q->groupBy('am.articulo_id', 'am.combinacion_id', 'am.lote', 'am.ordentrabajo_id', 'am.deposito_id', 't.nombre')
            ->select([
                'am.articulo_id',
                'am.combinacion_id',
                'am.lote',
                'am.ordentrabajo_id',
                'am.deposito_id',
                't.nombre as talle',
                DB::raw('SUM(amt.cantidad) as cantidad'),
            ])
            ->get();
        OtBucketStockSupport::precargar($rows);

        $buckets = [];
        foreach ($rows as $row) {
            $articuloId = (int) $row->articulo_id;
            $combinacionId = (int) $row->combinacion_id;
            $otBucket = OtBucketStockSupport::ordentrabajoIdDelBucket($row->lote, (int) ($row->ordentrabajo_id ?? 0), $articuloId, $combinacionId);
            $clave = OtBucketStockSupport::claveDesdeOtResuelta($row->lote, $otBucket);
            $k = $articuloId.'|'.$combinacionId.'|'.$clave.'|D:'.(int) $row->deposito_id;
            if (! isset($buckets[$k])) {
                $buckets[$k] = [
                    'articulo_id' => $articuloId,
                    'combinacion_id' => $combinacionId,
                    'clave' => $clave,
                    'ordentrabajo_id' => $otBucket,
                    'lote' => $otBucket > 0 ? '' : trim((string) $row->lote),
                    'deposito_id' => (int) $row->deposito_id,
                    'saldo' => 0.0,
                    'talles' => [],
                ];
            }
            $cantidad = (float) $row->cantidad;
            $buckets[$k]['saldo'] += $cantidad;
            $talle = (string) $row->talle;
            $buckets[$k]['talles'][$talle] = (float) ($buckets[$k]['talles'][$talle] ?? 0) + $cantidad;
        }

        $codigos = $this->codigosOt(array_column($buckets, 'ordentrabajo_id'));
        foreach ($buckets as $k => $bucket) {
            if ($bucket['ordentrabajo_id'] > 0) {
                $buckets[$k]['lote'] = $codigos[$bucket['ordentrabajo_id']] ?? (string) $bucket['ordentrabajo_id'];
            }
        }

        return $buckets;
    }

    /**
     * @param  array<string, array<string, mixed>>  $buckets
     */
    private function controlarBuckets(array $buckets): void
    {
        foreach ($buckets as $bucket) {
            $etiqueta = $this->etiqueta($bucket);
            if ($bucket['saldo'] < -self::TOLERANCIA) {
                $this->registrar('bucket_negativo', $bucket['saldo'], $etiqueta);

                continue;
            }
            if ($bucket['saldo'] > self::TOLERANCIA) {
                foreach ($bucket['talles'] as $cantidad) {
                    if ($cantidad < -self::TOLERANCIA) {
                        $this->registrar('talle_negativo_total_positivo', $bucket['saldo'], $etiqueta + ['talles' => $bucket['talles']]);
                        break;
                    }
                }
            }
        }
    }

    private function controlarTallesDeMovimientos(?string $desde): void
    {
        $talles = DB::table('articulo_movimiento_talle')
            ->groupBy('articulo_movimiento_id')
            ->select([
                'articulo_movimiento_id',
                DB::raw('SUM(ABS(cantidad)) as suma'),
            ]);

        $q = DB::table('articulo_movimiento as am')
            ->leftJoinSub($talles, 'tt', 'tt.articulo_movimiento_id', '=', 'am.id')
            ->where('am.combinacion_id', '>', 0)
            ->where(fn ($w) => $w->where('am.lote', '>', 0)->orWhere('am.ordentrabajo_id', '>', 0))
            ->whereRaw('ABS(ABS(am.cantidad) - '.SqlDialectSupport::coalesce('tt.suma', '0').') > '.self::TOLERANCIA);
        $this->aplicarFiltro($q);
        if ($desde !== null && $desde !== '') {
            $q->where('am.fecha', '>=', $desde);
        }

        $rows = $q->orderBy('am.id')
            ->get(['am.id', 'am.fecha', 'am.articulo_id', 'am.combinacion_id', 'am.lote', 'am.ordentrabajo_id', 'am.deposito_id', 'am.cantidad', 'am.concepto', 'tt.suma']);
        foreach ($rows as $row) {
            $suma = (float) ($row->suma ?? 0);
            $control = $suma <= self::TOLERANCIA ? 'movimiento_sin_talles' : 'cabecera_distinta_talles';
            $this->registrar($control, (float) $row->cantidad, [
                'id' => (int) $row->id,
                'fecha' => (string) $row->fecha,
                'articulo_id' => (int) $row->articulo_id,
                'combinacion_id' => (int) $row->combinacion_id,
                'lote' => (string) $row->lote,
                'ordentrabajo_id' => (int) ($row->ordentrabajo_id ?? 0),
                'deposito_id' => (int) ($row->deposito_id ?? 0),
                'cantidad' => (float) $row->cantidad,
                'suma_talles' => $suma,
                'concepto' => (string) $row->concepto,
            ]);
        }
    }

    /**
     * Borrados o cambios (según audits) de movimientos que el reemplazo de Excel ya había
     * descontado. Grabar el pedido o el mov. de stock borra y vuelve a crear el movimiento
     * con los mismos pares: eso no altera el corte. Sí lo altera una baja sin regrabación
     * ni movimiento de reemplazo que la compense (concepto con "#id").
     */
    private function controlarBajasPreviasReemplazo(?string $desde): void
    {
        $q = DB::table('audits')
            ->where('auditable_type', Articulo_Movimiento::class)
            ->whereIn('event', ['deleted', 'updated'])
            ->where('created_at', '>=', ($desde !== null && $desde !== '') ? $desde : now()->subDay()->toDateString());

        foreach ($q->orderBy('id')->cursor() as $audit) {
            $old = json_decode((string) $audit->old_values, true) ?: [];
            $new = json_decode((string) $audit->new_values, true) ?: [];
            if ($audit->event === 'updated' && array_intersect(array_keys($new), CorteReemplazoExcelFerliSupport::CAMPOS_BUCKET) === []) {
                continue;
            }

            $id = (int) $audit->auditable_id;
            $actual = (array) (DB::table('articulo_movimiento')->where('id', $id)->first() ?? []);
            $mov = ['id' => $id] + $old + $actual;
            if (CorteReemplazoExcelFerliSupport::esReemplazo($mov['concepto'] ?? null)
                || ! $this->pasaFiltro((int) ($mov['articulo_id'] ?? 0), (int) ($mov['combinacion_id'] ?? 0), (int) ($mov['deposito_id'] ?? 0))) {
                continue;
            }

            $reemplazoId = CorteReemplazoExcelFerliSupport::reemplazoQueLoAbsorbe($mov);
            if ($reemplazoId === null || $this->bajaRegrabadaOCompensada($mov, $audit->event === 'updated' ? $actual : null)) {
                continue;
            }

            $this->registrar('baja_previa_reemplazo', -(float) ($mov['cantidad'] ?? 0), [
                'id' => $id,
                'evento' => (string) $audit->event,
                'cuando' => (string) $audit->created_at,
                'origen' => (string) ($audit->url ?? ''),
                'articulo_id' => (int) ($mov['articulo_id'] ?? 0),
                'combinacion_id' => (int) ($mov['combinacion_id'] ?? 0),
                'lote' => (string) ($mov['lote'] ?? ''),
                'deposito_id' => (int) ($mov['deposito_id'] ?? 0),
                'cantidad' => (float) ($mov['cantidad'] ?? 0),
                'reemplazo_id' => $reemplazoId,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $mov  valores previos a la baja/cambio
     * @param  array<string, mixed>|null  $actual  fila actual si fue un cambio
     */
    private function bajaRegrabadaOCompensada(array $mov, ?array $actual): bool
    {
        $cantidad = (float) ($mov['cantidad'] ?? 0);
        if ($actual !== null
            && abs((float) ($actual['cantidad'] ?? 0) - $cantidad) <= self::TOLERANCIA
            && (int) ($actual['deposito_id'] ?? 0) === (int) ($mov['deposito_id'] ?? 0)
            && (string) ($actual['lote'] ?? '') === (string) ($mov['lote'] ?? '')) {
            return true;
        }

        $compensado = DB::table('articulo_movimiento')
            ->where('concepto', 'like', CorteReemplazoExcelFerliSupport::PREFIJO.'%#'.(int) $mov['id'].'%')
            ->exists();
        if ($compensado) {
            return true;
        }

        $origen = null;
        foreach (['pedido_combinacion_id', 'movimientostock_id', 'venta_id'] as $campo) {
            if ((int) ($mov[$campo] ?? 0) > 0) {
                $origen = $campo;
                break;
            }
        }
        if ($origen === null) {
            return false;
        }

        // El gemelo de un duplicado (id menor) no es una regrabación.
        return DB::table('articulo_movimiento')
            ->where('id', '>', (int) $mov['id'])
            ->where($origen, (int) $mov[$origen])
            ->where('articulo_id', (int) ($mov['articulo_id'] ?? 0))
            ->where('combinacion_id', (int) ($mov['combinacion_id'] ?? 0))
            ->where('deposito_id', (int) ($mov['deposito_id'] ?? 0))
            ->where('lote', $mov['lote'] ?? null)
            ->whereRaw('ABS(cantidad - ?) <= ?', [$cantidad, self::TOLERANCIA])
            ->exists();
    }

    /**
     * @return array<string, list<array{id: int, concepto: string, cantidad: float, tra: bool}>>
     */
    private function movimientosDeBuckets(): array
    {
        $tras = DB::table('transferencia_mercaderia')
            ->select('movimientostock_salida_id as ms')
            ->whereNotNull('movimientostock_salida_id')
            ->union(
                DB::table('transferencia_mercaderia')
                    ->select('movimientostock_entrada_id as ms')
                    ->whereNotNull('movimientostock_entrada_id')
            )
            ->pluck('ms')
            ->map(static fn ($id) => (int) $id)
            ->flip()
            ->all();

        $q = DB::table('articulo_movimiento as am')
            ->where('am.combinacion_id', '>', 0)
            ->where('am.deposito_id', '>', 0)
            ->where(fn ($w) => $w->where('am.lote', '>', 0)->orWhere('am.ordentrabajo_id', '>', 0));
        $this->aplicarFiltro($q);
        OtClienteCompartidoStockSupport::excluirConsumoFacturaCompanero($q, 'am');

        $out = [];
        foreach ($q->orderBy('am.id')->cursor() as $row) {
            $articuloId = (int) $row->articulo_id;
            $combinacionId = (int) $row->combinacion_id;
            $k = $articuloId.'|'.$combinacionId.'|'
                .OtBucketStockSupport::claveBucket($row->lote, (int) ($row->ordentrabajo_id ?? 0), $articuloId, $combinacionId)
                .'|D:'.(int) $row->deposito_id;
            $out[$k][] = [
                'id' => (int) $row->id,
                'concepto' => (string) $row->concepto,
                'cantidad' => (float) $row->cantidad,
                'tra' => isset($tras[(int) ($row->movimientostock_id ?? 0)]),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, list<array{concepto: string, cantidad: float}>>  $filas
     */
    private function controlarResiduoReemplazoExcel(array $filas): void
    {
        foreach ($filas as $k => $movs) {
            $r = self::residuoReemplazoExcel($movs);
            if ($r['tiene_reemplazo'] && $r['residuo'] > self::TOLERANCIA && $r['saldo'] > self::TOLERANCIA) {
                $this->registrar('residuo_reemplazo_excel', min($r['residuo'], $r['saldo']), [
                    'bucket' => $k,
                    'residuo' => $r['residuo'],
                    'saldo' => $r['saldo'],
                ]);
            }
        }
    }

    /**
     * @param  array<string, list<array{cantidad: float, tra: bool}>>  $filas
     */
    private function controlarTraOrigen(array $filas): void
    {
        /** @var array<string, array<string, array{saldo: float, solo_tra: bool}>> $porArticuloDeposito */
        $porArticuloDeposito = [];
        foreach ($filas as $k => $movs) {
            [$articuloId, $combinacionId, $clave, $deposito] = explode('|', $k);
            $grupo = $articuloId.'|'.$combinacionId.'|'.$deposito;
            $saldo = 0.0;
            $soloTra = true;
            foreach ($movs as $mov) {
                $saldo += $mov['cantidad'];
                $soloTra = $soloTra && $mov['tra'];
            }
            $porArticuloDeposito[$grupo][$clave] = ['saldo' => $saldo, 'solo_tra' => $soloTra];
        }

        foreach ($porArticuloDeposito as $grupo => $porClave) {
            foreach ($porClave as $clave => $info) {
                if (! $info['solo_tra'] || $info['saldo'] >= -self::TOLERANCIA) {
                    continue;
                }
                $otros = 0.0;
                foreach ($porClave as $claveOtra => $otra) {
                    if ($claveOtra !== $clave && $otra['saldo'] > self::TOLERANCIA) {
                        $otros += $otra['saldo'];
                    }
                }
                if ($otros > self::TOLERANCIA) {
                    $this->registrar('tra_origen_no_descontado', min(-$info['saldo'], $otros), [
                        'articulo_combinacion_deposito' => $grupo,
                        'bucket_tra' => $clave,
                        'saldo_tra' => $info['saldo'],
                        'otros_buckets_con_saldo' => $otros,
                    ]);
                }
            }
        }
    }

    private function controlarDevolucionesCompanero(): void
    {
        $q = DB::table('articulo_movimiento as am')->where('am.combinacion_id', '>', 0);
        $this->aplicarFiltro($q);
        OtClienteCompartidoStockSupport::soloDevolucionDeConsumoCompanero($q, 'am');

        foreach ($q->orderBy('am.id')->get(['am.id', 'am.ordentrabajo_id', 'am.deposito_id', 'am.cantidad', 'am.concepto']) as $row) {
            $this->registrar('devolucion_companero', (float) $row->cantidad, [
                'id' => (int) $row->id,
                'ordentrabajo_id' => (int) ($row->ordentrabajo_id ?? 0),
                'deposito_id' => (int) ($row->deposito_id ?? 0),
                'concepto' => (string) $row->concepto,
            ]);
        }
    }

    /**
     * @param  array<string, array{articulo_id: int, combinacion_id: int, clave: string, ordentrabajo_id: int, lote: string, deposito_id: int, saldo: float, talles: array<string, float>}>  $buckets
     */
    private function controlarValidadores(array $buckets): void
    {
        $reporte = $this->saldosReporte();
        foreach ($reporte as $k => $saldoReporte) {
            if (! isset($buckets[$k]) && $saldoReporte > self::TOLERANCIA) {
                $this->registrar('reporte_vs_canonico', $saldoReporte, ['bucket' => $k, 'reporte' => $saldoReporte, 'canonico' => 0.0]);
            }
        }

        $f1 = [];
        $consumoOt = [];
        foreach ($buckets as $k => $bucket) {
            if ($bucket['saldo'] <= self::TOLERANCIA) {
                continue;
            }
            $etiqueta = $this->etiqueta($bucket);
            $saldo = $bucket['saldo'];
            $articuloId = $bucket['articulo_id'];
            $combinacionId = $bucket['combinacion_id'];
            $depositoId = $bucket['deposito_id'];
            $lote = $bucket['lote'];

            $saldoReporte = $reporte[$k] ?? 0.0;
            if (abs($saldoReporte - $saldo) > self::TOLERANCIA) {
                $this->registrar('reporte_vs_canonico', $saldo - $saldoReporte, $etiqueta + ['reporte' => $saldoReporte]);
            }

            $picking = PedidoPickingFerliSupport::saldosNetosLotePorDeposito($articuloId, $combinacionId, $lote);
            $saldoPicking = (float) ($picking[$depositoId]['saldo'] ?? 0);
            if (abs($saldoPicking - $saldo) > self::TOLERANCIA) {
                $this->registrar('picking_vs_canonico', $saldo - $saldoPicking, $etiqueta + ['picking' => $saldoPicking]);
            }

            $modos = [[true, 0]];
            if ($bucket['ordentrabajo_id'] > 0) {
                $modos[] = [false, $bucket['ordentrabajo_id']];
            }
            foreach ($modos as [$grabaEnLote, $otId]) {
                $error = PedidoPickingFerliSupport::errorSiEgresoBucketSuperaSaldo($articuloId, $combinacionId, $lote, $depositoId, $saldo, [], $grabaEnLote, $otId);
                if ($error !== null) {
                    $this->registrar('egreso_rechaza_saldo', $saldo, $etiqueta + ['graba_en_lote' => $grabaEnLote, 'error' => $error]);
                }
                $exceso = PedidoPickingFerliSupport::errorSiEgresoBucketSuperaSaldo($articuloId, $combinacionId, $lote, $depositoId, $saldo + 1, [], $grabaEnLote, $otId);
                if ($exceso === null) {
                    $this->registrar('egreso_permite_exceso', 1.0, $etiqueta + ['graba_en_lote' => $grabaEnLote]);
                }
            }

            $kf = $articuloId.'|'.$combinacionId;
            if (! isset($f1[$kf])) {
                $f1[$kf] = [];
                foreach ($this->movimientos->leeLotesStockPendientes($articuloId, $combinacionId) as $fila) {
                    $kl = trim((string) $fila['lote']).'|'.(int) $fila['deposito_id'];
                    $f1[$kf][$kl] = (float) ($f1[$kf][$kl] ?? 0) + (float) $fila['saldo'];
                }
            }
            $saldoF1 = $f1[$kf][$lote.'|'.$depositoId] ?? 0.0;
            if (abs($saldoF1 - $saldo) > self::TOLERANCIA) {
                $this->registrar('f1_vs_canonico', $saldo - $saldoF1, $etiqueta + ['f1' => $saldoF1]);
            }

            if ($bucket['ordentrabajo_id'] > 0) {
                $kc = $articuloId.'|'.$combinacionId.'|'.$bucket['ordentrabajo_id'];
                $consumoOt[$kc]['lote'] = $lote;
                $consumoOt[$kc]['articulo_id'] = $articuloId;
                $consumoOt[$kc]['combinacion_id'] = $combinacionId;
                $consumoOt[$kc]['depositos'][$depositoId] = $saldo;
            }
        }

        foreach ($consumoOt as $kc => $info) {
            $control = $this->ordenes->controlaOtStock($info['lote'], $info['articulo_id'], $info['combinacion_id']);
            $depositoId = (int) ($control['deposito_id'] ?? 0);
            $saldoCanonico = (float) ($info['depositos'][$depositoId] ?? 0);
            $mayor = max($info['depositos']);
            if (abs((float) $control['saldo'] - $saldoCanonico) > self::TOLERANCIA || $saldoCanonico + self::TOLERANCIA < $mayor) {
                $this->registrar('consumo_ot_vs_canonico', $mayor - $saldoCanonico, [
                    'articulo_combinacion_ot' => $kc,
                    'lote' => $info['lote'],
                    'deposito_informado' => $depositoId,
                    'saldo_informado' => (float) $control['saldo'],
                    'saldos_por_deposito' => $info['depositos'],
                ]);
            }
        }
    }

    /**
     * @return array<string, float>
     */
    private function saldosReporte(): array
    {
        $aviso = null;
        $filas = $this->movimientos->generaDatosRepStockOt(
            'TODAS', 0, '', '', 0, 999999999, 0, 999999999, '', '',
            'TODAS', 'TOTALIZADO', 0, 'TODOS', $aviso
        );

        $out = [];
        foreach ($filas as $fila) {
            $depositoId = (int) ($fila['deposito_id'] ?? 0);
            $articuloId = (int) ($fila['articulo_id'] ?? 0);
            $combinacionId = (int) ($fila['combinacion_id'] ?? 0);
            if ($depositoId <= 0 || ! $this->pasaFiltro($articuloId, $combinacionId, $depositoId)) {
                continue;
            }
            $otId = (int) ($fila['ordentrabajo_id'] ?? 0);
            $clave = $otId > 0 ? 'OT:'.$otId : 'L:'.trim((string) ($fila['lote'] ?? ''));
            $k = $articuloId.'|'.$combinacionId.'|'.$clave.'|D:'.$depositoId;
            $out[$k] = (float) ($out[$k] ?? 0) + (float) ($fila['total_pares'] ?? 0);
        }

        return $out;
    }

    /**
     * @param  list<int>  $otIds
     * @return array<int, string>
     */
    private function codigosOt(array $otIds): array
    {
        $otIds = array_values(array_unique(array_filter(array_map('intval', $otIds), static fn (int $id) => $id > 0)));
        $out = [];
        foreach (array_chunk($otIds, 1000) as $lote) {
            foreach (DB::table('ordentrabajo')->whereIn('id', $lote)->get(['id', 'codigo']) as $row) {
                $out[(int) $row->id] = trim((string) $row->codigo);
            }
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $q
     */
    private function aplicarFiltro($q): void
    {
        if ($this->filtro['articulo_id'] > 0) {
            $q->where('am.articulo_id', $this->filtro['articulo_id']);
        }
        if ($this->filtro['combinacion_id'] > 0) {
            $q->where('am.combinacion_id', $this->filtro['combinacion_id']);
        }
        if ($this->filtro['deposito_id'] > 0) {
            $q->where('am.deposito_id', $this->filtro['deposito_id']);
        }
    }

    private function pasaFiltro(int $articuloId, int $combinacionId, int $depositoId): bool
    {
        return ($this->filtro['articulo_id'] <= 0 || $this->filtro['articulo_id'] === $articuloId)
            && ($this->filtro['combinacion_id'] <= 0 || $this->filtro['combinacion_id'] === $combinacionId)
            && ($this->filtro['deposito_id'] <= 0 || $this->filtro['deposito_id'] === $depositoId);
    }

    /**
     * @param  array<string, mixed>  $bucket
     * @return array<string, mixed>
     */
    private function etiqueta(array $bucket): array
    {
        return [
            'articulo_id' => $bucket['articulo_id'],
            'combinacion_id' => $bucket['combinacion_id'],
            'bucket' => $bucket['clave'],
            'lote_ot' => $bucket['lote'],
            'deposito_id' => $bucket['deposito_id'],
            'saldo' => $bucket['saldo'],
        ];
    }

    /**
     * @param  array<string, mixed>  $ejemplo
     */
    private function registrar(string $control, float $pares, array $ejemplo): void
    {
        $this->controles[$control]['cantidad']++;
        $this->controles[$control]['pares'] += $pares;
        if (count($this->controles[$control]['ejemplos']) < $this->maxEjemplos) {
            $this->controles[$control]['ejemplos'][] = $ejemplo;
        }
    }
}
