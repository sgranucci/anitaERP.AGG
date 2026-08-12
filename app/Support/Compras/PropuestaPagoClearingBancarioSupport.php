<?php

namespace App\Support\Compras;

use App\Models\Caja\InterbankingMovimiento;
use App\Models\Caja\InterbankingTransferencia;
use App\Models\Compras\ClearingBancarioSugerencia;
use App\Models\Compras\LoteBancarioLinea;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\PropuestaPago;
use App\Services\Compras\PagoproveedorService;
use App\Support\Caja\InterbankingTransferenciaComprobanteSupport;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Clearing bancario avanzado (estilo FEBAN light):
 * - scoring multi-criterio (CBU/CUIT/neto/bruto/fecha)
 * - exclusividad de voucher/movimiento
 * - sugerencias persistidas + auto-confirmación umbral alto
 * - match OP ↔ transferencia y OP ↔ movimiento (extracto)
 */
class PropuestaPagoClearingBancarioSupport
{
    public static function scoreAutoConfirmar(): int
    {
        return max(50, (int) config('propuesta_pago.clearing_score_auto', 90));
    }

    public static function scoreSugerir(): int
    {
        return max(30, (int) config('propuesta_pago.clearing_score_sugerir', 60));
    }

    public static function diasVentana(): int
    {
        return max(1, (int) config('propuesta_pago.clearing_dias_ventana', 7));
    }

    public static function toleranciaMonto(): float
    {
        return (float) config('propuesta_pago.clearing_tolerancia_monto', 0.05);
    }

    /**
     * Corre clearing sobre una propuesta (o todas recientes si id null vía command).
     *
     * @return array{
     *   ok:bool,mensaje:string,
     *   auto:list<int>,sugeridas:list<int>,excepciones:list<int>,omitidas:list<int>
     * }
     */
    public static function procesarPropuesta(int $propuestaPagoId, bool $autoConfirmar = true): array
    {
        if (! PropuestaPagoBridgeBancarioSupport::habilitado()) {
            return [
                'ok' => true,
                'mensaje' => 'Clearing deshabilitado (PROPUESTA_PAGO_BRIDGE_BANCARIO).',
                'auto' => [],
                'sugeridas' => [],
                'excepciones' => [],
                'omitidas' => [],
            ];
        }

        $propuesta = PropuestaPago::query()->findOrFail($propuestaPagoId);
        $ops = Pagoproveedor::query()
            ->with(['proveedores', 'pagoproveedor_retenciones'])
            ->where('propuesta_pago_id', $propuestaPagoId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        $usadasTransf = self::idsTransferenciasUsadas();
        $usadosMov = self::idsMovimientosUsados();

        $auto = [];
        $sugeridas = [];
        $excepciones = [];
        $omitidas = [];
        $svc = app(PagoproveedorService::class);
        $compSupport = app(InterbankingTransferenciaComprobanteSupport::class);

        foreach ($ops as $op) {
            $estado = (string) $op->estado;
            if (in_array($estado, ['CONCILIADA', 'REVERTIDA', 'BAJA'], true)) {
                $omitidas[] = (int) $op->id;
                continue;
            }
            if ($estado === 'PRE CARGA') {
                $omitidas[] = (int) $op->id;
                continue;
            }
            if (! empty($op->interbanking_transferencia_id) || ! empty($op->interbanking_movimiento_id)) {
                $omitidas[] = (int) $op->id;
                continue;
            }
            if (! in_array($estado, ['CONFIRMADA', 'PAGADA'], true)) {
                $omitidas[] = (int) $op->id;
                continue;
            }

            // Limpiar sugerencias abiertas previas de esta OP
            ClearingBancarioSugerencia::query()
                ->where('pagoproveedor_id', $op->id)
                ->whereIn('estado', ['SUGERIDO', 'EXCEPCION'])
                ->delete();

            $ctx = self::contextoOp($op);
            $candidatos = self::rankearCandidatos($op, $ctx, $usadasTransf, $usadosMov, $compSupport);

            if ($candidatos === []) {
                $excepciones[] = self::persistirExcepcion($op, $ctx, 'sin_candidato', 'Sin voucher/movimiento en ventana/monto');
                continue;
            }

            $top = $candidatos[0];
            $ambigua = isset($candidatos[1]) && (int) $candidatos[1]['score'] >= (int) $top['score'] - 5;

            if ($ambigua && (int) $top['score'] < self::scoreAutoConfirmar()) {
                foreach (array_slice($candidatos, 0, 3) as $c) {
                    self::persistirSugerencia($op, $ctx, $c, 'SUGERIDO', 'ambiguedad_N');
                }
                $excepciones[] = (int) $op->id;
                continue;
            }

            if ((int) $top['score'] >= self::scoreAutoConfirmar() && $autoConfirmar) {
                $ok = self::confirmarMatchInterno($svc, $op, $top, $usadasTransf, $usadosMov);
                if ($ok) {
                    self::persistirSugerencia($op, $ctx, $top, 'AUTO', $top['regla']);
                    $auto[] = (int) $op->id;
                } else {
                    $excepciones[] = self::persistirExcepcion($op, $ctx, 'error_vincular', 'Falló vínculo automático');
                }
                continue;
            }

            if ((int) $top['score'] >= self::scoreSugerir()) {
                self::persistirSugerencia($op, $ctx, $top, 'SUGERIDO', $top['regla']);
                foreach (array_slice($candidatos, 1, 2) as $c) {
                    if ((int) $c['score'] >= self::scoreSugerir()) {
                        self::persistirSugerencia($op, $ctx, $c, 'SUGERIDO', $c['regla']);
                    }
                }
                $sugeridas[] = (int) $op->id;
                continue;
            }

            $excepciones[] = self::persistirExcepcion(
                $op,
                $ctx,
                'score_bajo',
                'Mejor score '.$top['score'].' ('.$top['regla'].')'
            );
        }

        $msg = sprintf(
            'Clearing PP#%d: %d auto, %d sugeridas, %d excepciones, %d omitidas.',
            $propuestaPagoId,
            count($auto),
            count($sugeridas),
            count($excepciones),
            count($omitidas)
        );

        return [
            'ok' => true,
            'mensaje' => $msg,
            'auto' => $auto,
            'sugeridas' => $sugeridas,
            'excepciones' => $excepciones,
            'omitidas' => $omitidas,
        ];
    }

    /**
     * @return array{ok:bool,mensaje:string}
     */
    public static function confirmarSugerencia(int $sugerenciaId): array
    {
        $sug = ClearingBancarioSugerencia::query()->find($sugerenciaId);
        if (! $sug || ! in_array((string) $sug->estado, ['SUGERIDO', 'EXCEPCION'], true)) {
            return ['ok' => false, 'mensaje' => 'Sugerencia no disponible.'];
        }

        $op = Pagoproveedor::query()->find($sug->pagoproveedor_id);
        if (! $op) {
            return ['ok' => false, 'mensaje' => 'OP no encontrada.'];
        }

        $svc = app(PagoproveedorService::class);
        $top = [
            'lado' => $sug->lado_banco,
            'transferencia_id' => $sug->interbanking_transferencia_id,
            'movimiento_id' => $sug->interbanking_movimiento_id,
            'score' => (int) $sug->score,
            'regla' => (string) $sug->regla,
        ];

        $usadasTransf = self::idsTransferenciasUsadas();
        $usadosMov = self::idsMovimientosUsados();

        if (! self::confirmarMatchInterno($svc, $op, $top, $usadasTransf, $usadosMov)) {
            return ['ok' => false, 'mensaje' => 'No se pudo vincular (¿voucher ya usado o estado OP inválido?).'];
        }

        $sug->estado = 'CONFIRMADO';
        $sug->usuario_id = Auth::id();
        $sug->confirmado_at = now();
        $sug->save();

        ClearingBancarioSugerencia::query()
            ->where('pagoproveedor_id', $op->id)
            ->where('id', '!=', $sug->id)
            ->whereIn('estado', ['SUGERIDO', 'EXCEPCION'])
            ->update(['estado' => 'RECHAZADO', 'motivo' => 'Superseded por #'.$sug->id]);

        return ['ok' => true, 'mensaje' => 'Match confirmado OP #'.$op->id.'.'];
    }

    /**
     * @return array{ok:bool,mensaje:string}
     */
    public static function rechazarSugerencia(int $sugerenciaId, string $motivo = ''): array
    {
        $sug = ClearingBancarioSugerencia::query()->find($sugerenciaId);
        if (! $sug || ! in_array((string) $sug->estado, ['SUGERIDO', 'EXCEPCION'], true)) {
            return ['ok' => false, 'mensaje' => 'Sugerencia no disponible.'];
        }
        $sug->estado = 'RECHAZADO';
        $sug->motivo = $motivo !== '' ? mb_substr($motivo, 0, 255) : 'Rechazada manualmente';
        $sug->usuario_id = Auth::id();
        $sug->save();

        return ['ok' => true, 'mensaje' => 'Sugerencia rechazada.'];
    }

    /**
     * Match manual forzado OP ↔ transferencia o movimiento.
     *
     * @return array{ok:bool,mensaje:string}
     */
    public static function forzarMatch(int $pagoproveedorId, ?int $transferenciaId, ?int $movimientoId): array
    {
        $op = Pagoproveedor::query()->with(['proveedores', 'pagoproveedor_retenciones'])->find($pagoproveedorId);
        if (! $op) {
            return ['ok' => false, 'mensaje' => 'OP no encontrada.'];
        }
        if (! $transferenciaId && ! $movimientoId) {
            return ['ok' => false, 'mensaje' => 'Indique transferencia o movimiento.'];
        }

        $svc = app(PagoproveedorService::class);
        $top = [
            'lado' => $transferenciaId ? 'transferencia' : 'movimiento',
            'transferencia_id' => $transferenciaId,
            'movimiento_id' => $movimientoId,
            'score' => 100,
            'regla' => 'manual',
        ];
        $usadasTransf = self::idsTransferenciasUsadas();
        $usadosMov = self::idsMovimientosUsados();

        if (! self::confirmarMatchInterno($svc, $op, $top, $usadasTransf, $usadosMov)) {
            return ['ok' => false, 'mensaje' => 'No se pudo forzar el vínculo.'];
        }

        $ctx = self::contextoOp($op);
        self::persistirSugerencia($op, $ctx, $top, 'CONFIRMADO', 'manual');

        return ['ok' => true, 'mensaje' => 'Match manual OK OP #'.$op->id];
    }

    /**
     * Datos para workbench clearing.
     *
     * @return array<string, mixed>
     */
    public static function workbench(?int $empresaId = null, int $dias = 30): array
    {
        $desde = Carbon::today()->subDays($dias)->startOfDay();

        $opsPend = Pagoproveedor::query()
            ->with(['proveedores', 'empresas', 'pagoproveedor_retenciones'])
            ->whereNull('deleted_at')
            ->whereIn('estado', ['CONFIRMADA', 'PAGADA'])
            ->whereNull('interbanking_transferencia_id')
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('pagoproveedor', 'interbanking_movimiento_id'),
                fn ($q) => $q->whereNull('interbanking_movimiento_id')
            )
            ->when($empresaId && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->orderByDesc('fecha')
            ->limit(200)
            ->get()
            ->map(function ($op) {
                $ctx = self::contextoOp($op);

                return (object) [
                    'id' => $op->id,
                    'propuesta_pago_id' => $op->propuesta_pago_id,
                    'empresa' => $op->empresas->nombre ?? '',
                    'proveedor' => $op->proveedores->nombre ?? '',
                    'fecha' => optional($op->fecha)->format('Y-m-d'),
                    'estado' => $op->estado,
                    'bruto' => $ctx['bruto'],
                    'neto' => $ctx['neto'],
                    'cbu' => $ctx['cbu'],
                    'cuit' => $ctx['cuit'],
                ];
            });

        $usadas = self::idsTransferenciasUsadas();
        $transfLibres = InterbankingTransferencia::query()
            ->when($empresaId && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->where('request_date', '>=', $desde)
            ->when($usadas !== [], fn ($q) => $q->whereNotIn('id', $usadas))
            ->orderByDesc('request_date')
            ->limit(200)
            ->get();

        $comp = app(InterbankingTransferenciaComprobanteSupport::class);
        $banco = $transfLibres->map(function ($t) use ($comp) {
            $resumen = $comp->cuentaResumen($t->credit_account_json, $t->credit_account);

            return (object) [
                'id' => $t->id,
                'lado' => 'transferencia',
                'fecha' => optional($t->request_date)->format('Y-m-d'),
                'monto' => (float) $t->amount,
                'cbu' => preg_replace('/\D+/', '', (string) ($resumen['cbu'] ?? '')) ?: '',
                'cuit' => preg_replace('/\D+/', '', (string) ($resumen['cuit'] ?? '')) ?: '',
                'ref' => (string) ($t->transfer_id ?: $t->validation_code ?: $t->id),
            ];
        });

        $usadosMov = self::idsMovimientosUsados();
        $movs = InterbankingMovimiento::query()
            ->when($empresaId && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->where('process_date', '>=', $desde)
            ->where(function ($q) {
                $q->where('debit_credit_type', 'D')
                    ->orWhere('debit_credit_type', 'Debit')
                    ->orWhereRaw('UPPER(COALESCE(debit_credit_type,"")) LIKE ?', ['D%']);
            })
            ->when($usadosMov !== [], fn ($q) => $q->whereNotIn('id', $usadosMov))
            ->orderByDesc('process_date')
            ->limit(200)
            ->get()
            ->map(function ($m) {
                return (object) [
                    'id' => $m->id,
                    'lado' => 'movimiento',
                    'fecha' => optional($m->process_date)->format('Y-m-d'),
                    'monto' => abs((float) $m->amount),
                    'cbu' => preg_replace('/\D+/', '', (string) ($m->account_cbu ?? '')) ?: '',
                    'cuit' => preg_replace('/\D+/', '', (string) ($m->customer_cuit ?? '')) ?: '',
                    'ref' => (string) ($m->voucher_number ?: $m->id),
                    'desc' => trim(($m->code_description_bank ?? '').' '.($m->depositor_description ?? '')),
                ];
            });

        $sugerencias = ClearingBancarioSugerencia::query()
            ->with(['pagoproveedores.proveedores', 'empresas'])
            ->when($empresaId && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereIn('estado', ['SUGERIDO', 'EXCEPCION'])
            ->orderByDesc('score')
            ->orderByDesc('id')
            ->limit(150)
            ->get();

        return [
            'ops_pendientes' => $opsPend,
            'banco_transferencias' => $banco,
            'banco_movimientos' => $movs,
            'sugerencias' => $sugerencias,
            'contadores' => [
                'ops' => $opsPend->count(),
                'transferencias' => $banco->count(),
                'movimientos' => $movs->count(),
                'sugerencias' => $sugerencias->where('estado', 'SUGERIDO')->count(),
                'excepciones' => $sugerencias->where('estado', 'EXCEPCION')->count(),
            ],
        ];
    }

    /**
     * @return array{bruto:float,neto:float,ret:float,cbu:string,cuit:string,fecha:Carbon}
     */
    private static function contextoOp(Pagoproveedor $op): array
    {
        $op->loadMissing(['proveedores', 'pagoproveedor_retenciones']);
        $bruto = round((float) $op->monto, 2);
        $ret = round((float) $op->pagoproveedor_retenciones->sum('monto'), 2);
        $neto = round(max(0, $bruto - $ret), 2);

        $lineaLote = LoteBancarioLinea::query()
            ->where('pagoproveedor_id', $op->id)
            ->orderByDesc('id')
            ->first();

        $cbu = PropuestaPagoBridgeBancarioSupport::extraerCbuDesdeDetalle((string) ($op->detalle ?? ''));
        if ($cbu === '' && $lineaLote) {
            $cbu = CbuSupport::normalizar((string) $lineaLote->cbu);
        }
        if ($cbu === '') {
            $fp = PropuestaPagoInstrumentoSupport::resolverFormapagoProveedor((int) $op->proveedor_id, null);
            $cbu = CbuSupport::normalizar((string) ($fp->cbu ?? ''));
        }

        $cuit = preg_replace('/\D+/', '', (string) ($op->proveedores->nroinscripcion ?? '')) ?? '';
        if ($cuit === '' && $lineaLote) {
            $cuit = preg_replace('/\D+/', '', (string) ($lineaLote->cuit ?? '')) ?? '';
        }

        if ($lineaLote && (float) $lineaLote->monto_neto > 0) {
            $neto = round((float) $lineaLote->monto_neto, 2);
        }

        return [
            'bruto' => $bruto,
            'neto' => $neto,
            'ret' => $ret,
            'cbu' => $cbu,
            'cuit' => $cuit,
            'fecha' => $op->fecha ? Carbon::parse($op->fecha) : Carbon::today(),
        ];
    }

    /**
     * @param  array<int, true>  $usadasTransf
     * @param  array<int, true>  $usadosMov
     * @return list<array<string, mixed>>
     */
    private static function rankearCandidatos(
        Pagoproveedor $op,
        array $ctx,
        array $usadasTransf,
        array $usadosMov,
        InterbankingTransferenciaComprobanteSupport $compSupport
    ): array {
        $dias = self::diasVentana();
        $tol = self::toleranciaMonto();
        $desde = $ctx['fecha']->copy()->subDays($dias)->startOfDay();
        $hasta = $ctx['fecha']->copy()->addDays($dias)->endOfDay();
        $montos = array_values(array_unique(array_filter([
            $ctx['neto'],
            $ctx['bruto'],
        ], fn ($m) => $m > 0)));

        $out = [];

        $transfQuery = InterbankingTransferencia::query()
            ->where('empresa_id', (int) $op->empresa_id)
            ->whereBetween('request_date', [$desde, $hasta])
            ->where(function ($q) use ($montos, $tol) {
                foreach ($montos as $i => $m) {
                    $method = $i === 0 ? 'whereRaw' : 'orWhereRaw';
                    $q->{$method}('ABS(amount - ?) < ?', [$m, $tol]);
                }
            })
            ->orderByDesc('request_date')
            ->limit(80)
            ->get();

        foreach ($transfQuery as $t) {
            if (isset($usadasTransf[(int) $t->id])) {
                continue;
            }
            $resumen = $compSupport->cuentaResumen($t->credit_account_json, $t->credit_account);
            $cbuB = CbuSupport::normalizar((string) ($resumen['cbu'] ?? ''));
            $cuitB = preg_replace('/\D+/', '', (string) ($resumen['cuit'] ?? '')) ?? '';
            $montoB = round((float) $t->amount, 2);
            $fechaB = $t->request_date ? Carbon::parse($t->request_date) : null;

            $score = self::calcularScore($ctx, $montoB, $cbuB, $cuitB, $fechaB);
            if ($score['score'] <= 0) {
                continue;
            }
            $out[] = [
                'lado' => 'transferencia',
                'transferencia_id' => (int) $t->id,
                'movimiento_id' => null,
                'score' => $score['score'],
                'regla' => $score['regla'],
                'monto_banco' => $montoB,
                'cbu_banco' => $cbuB,
                'cuit_banco' => $cuitB,
                'fecha_banco' => $fechaB?->toDateString(),
            ];
        }

        $movQuery = InterbankingMovimiento::query()
            ->where('empresa_id', (int) $op->empresa_id)
            ->whereBetween('process_date', [$desde, $hasta])
            ->where(function ($q) use ($montos, $tol) {
                foreach ($montos as $i => $m) {
                    $method = $i === 0 ? 'whereRaw' : 'orWhereRaw';
                    $q->{$method}('ABS(ABS(amount) - ?) < ?', [$m, $tol]);
                }
            })
            ->limit(80)
            ->get();

        foreach ($movQuery as $m) {
            if (isset($usadosMov[(int) $m->id])) {
                continue;
            }
            $dc = strtoupper(substr((string) ($m->debit_credit_type ?? 'D'), 0, 1));
            if ($dc !== '' && $dc !== 'D') {
                continue;
            }
            $montoB = round(abs((float) $m->amount), 2);
            $cbuB = CbuSupport::normalizar((string) ($m->account_cbu ?? ''));
            $cuitB = preg_replace('/\D+/', '', (string) ($m->customer_cuit ?? '')) ?? '';
            $fechaB = $m->process_date ? Carbon::parse($m->process_date) : null;
            $score = self::calcularScore($ctx, $montoB, $cbuB, $cuitB, $fechaB, true);
            if ($score['score'] <= 0) {
                continue;
            }
            $out[] = [
                'lado' => 'movimiento',
                'transferencia_id' => null,
                'movimiento_id' => (int) $m->id,
                'score' => $score['score'],
                'regla' => 'mov_'.$score['regla'],
                'monto_banco' => $montoB,
                'cbu_banco' => $cbuB,
                'cuit_banco' => $cuitB,
                'fecha_banco' => $fechaB?->toDateString(),
            ];
        }

        usort($out, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return $out;
    }

    /**
     * @return array{score:int,regla:string}
     */
    private static function calcularScore(
        array $ctx,
        float $montoBanco,
        string $cbuBanco,
        string $cuitBanco,
        ?Carbon $fechaBanco,
        bool $esMovimiento = false
    ): array {
        $tol = self::toleranciaMonto();
        $score = 0;
        $reglas = [];

        $matchNeto = abs($montoBanco - $ctx['neto']) < $tol;
        $matchBruto = abs($montoBanco - $ctx['bruto']) < $tol;
        if (! $matchNeto && ! $matchBruto) {
            return ['score' => 0, 'regla' => 'sin_monto'];
        }

        if ($matchNeto) {
            $score += 40;
            $reglas[] = 'neto';
        } elseif ($matchBruto) {
            $score += 25;
            $reglas[] = 'bruto';
        }

        if ($ctx['cbu'] !== '' && $cbuBanco !== '' && $ctx['cbu'] === $cbuBanco) {
            $score += 40;
            $reglas[] = 'cbu';
        } elseif ($ctx['cuit'] !== '' && $cuitBanco !== '' && $ctx['cuit'] === $cuitBanco) {
            $score += 25;
            $reglas[] = 'cuit';
        } elseif ($ctx['cbu'] === '' && $ctx['cuit'] === '') {
            $score += 5;
            $reglas[] = 'sin_id';
        }

        if ($fechaBanco) {
            $diff = abs($ctx['fecha']->diffInDays($fechaBanco));
            if ($diff === 0) {
                $score += 20;
                $reglas[] = 'fecha0';
            } elseif ($diff <= 2) {
                $score += 12;
                $reglas[] = 'fecha2';
            } elseif ($diff <= self::diasVentana()) {
                $score += 5;
                $reglas[] = 'fechaN';
            } else {
                return ['score' => 0, 'regla' => 'fuera_ventana'];
            }
        }

        // Preferir transferencia sobre movimiento a igualdad
        if ($esMovimiento) {
            $score = max(0, $score - 3);
        }

        return ['score' => min(100, $score), 'regla' => implode('+', $reglas) ?: 'base'];
    }

    /**
     * @param  array<string, mixed>  $top
     * @param  array<int, true>  $usadasTransf
     * @param  array<int, true>  $usadosMov
     */
    private static function confirmarMatchInterno(
        PagoproveedorService $svc,
        Pagoproveedor $op,
        array $top,
        array &$usadasTransf,
        array &$usadosMov
    ): bool {
        if (($top['lado'] ?? '') === 'transferencia' && ! empty($top['transferencia_id'])) {
            $tid = (int) $top['transferencia_id'];
            if (isset($usadasTransf[$tid])) {
                return false;
            }
            $r = $svc->vincularTransferenciaInterbanking((int) $op->id, $tid);
            if (! empty($r['errores'])) {
                return false;
            }
            $usadasTransf[$tid] = true;

            return true;
        }

        if (($top['lado'] ?? '') === 'movimiento' && ! empty($top['movimiento_id'])) {
            $mid = (int) $top['movimiento_id'];
            if (isset($usadosMov[$mid])) {
                return false;
            }
            $r = $svc->vincularMovimientoInterbanking((int) $op->id, $mid);
            if (! empty($r['errores'])) {
                return false;
            }
            $usadosMov[$mid] = true;

            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $cand
     */
    private static function persistirSugerencia(
        Pagoproveedor $op,
        array $ctx,
        array $cand,
        string $estado,
        string $regla
    ): void {
        ClearingBancarioSugerencia::query()->create([
            'empresa_id' => (int) $op->empresa_id,
            'propuesta_pago_id' => $op->propuesta_pago_id,
            'pagoproveedor_id' => (int) $op->id,
            'interbanking_transferencia_id' => $cand['transferencia_id'] ?? null,
            'interbanking_movimiento_id' => $cand['movimiento_id'] ?? null,
            'lado_banco' => $cand['lado'] ?? 'transferencia',
            'score' => (int) ($cand['score'] ?? 0),
            'regla' => mb_substr($regla, 0, 60),
            'estado' => $estado,
            'motivo' => null,
            'monto_erp' => $ctx['neto'],
            'monto_banco' => $cand['monto_banco'] ?? null,
            'cbu_erp' => $ctx['cbu'] ?: null,
            'cbu_banco' => $cand['cbu_banco'] ?? null,
            'cuit_erp' => $ctx['cuit'] ?: null,
            'cuit_banco' => $cand['cuit_banco'] ?? null,
            'fecha_erp' => $ctx['fecha']->toDateString(),
            'fecha_banco' => $cand['fecha_banco'] ?? null,
            'detalle_json' => ['regla' => $regla, 'bruto' => $ctx['bruto'], 'ret' => $ctx['ret']],
            'usuario_id' => Auth::id(),
            'confirmado_at' => in_array($estado, ['AUTO', 'CONFIRMADO'], true) ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private static function persistirExcepcion(Pagoproveedor $op, array $ctx, string $codigo, string $motivo): int
    {
        ClearingBancarioSugerencia::query()->create([
            'empresa_id' => (int) $op->empresa_id,
            'propuesta_pago_id' => $op->propuesta_pago_id,
            'pagoproveedor_id' => (int) $op->id,
            'lado_banco' => 'transferencia',
            'score' => 0,
            'regla' => mb_substr($codigo, 0, 60),
            'estado' => 'EXCEPCION',
            'motivo' => mb_substr($motivo, 0, 255),
            'monto_erp' => $ctx['neto'],
            'cbu_erp' => $ctx['cbu'] ?: null,
            'cuit_erp' => $ctx['cuit'] ?: null,
            'fecha_erp' => $ctx['fecha']->toDateString(),
            'detalle_json' => ['bruto' => $ctx['bruto'], 'ret' => $ctx['ret']],
            'usuario_id' => Auth::id(),
        ]);

        return (int) $op->id;
    }

    /**
     * @return array<int, true>
     */
    private static function idsTransferenciasUsadas(): array
    {
        $ids = Pagoproveedor::query()
            ->whereNotNull('interbanking_transferencia_id')
            ->pluck('interbanking_transferencia_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_fill_keys($ids, true);
    }

    /**
     * @return array<int, true>
     */
    private static function idsMovimientosUsados(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('pagoproveedor', 'interbanking_movimiento_id')) {
            return [];
        }
        $ids = Pagoproveedor::query()
            ->whereNotNull('interbanking_movimiento_id')
            ->pluck('interbanking_movimiento_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_fill_keys($ids, true);
    }
}
