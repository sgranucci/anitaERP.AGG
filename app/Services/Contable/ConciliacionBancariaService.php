<?php

namespace App\Services\Contable;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\InterbankingMovimiento;
use App\Support\Caja\InterbankingSaldoResolverSupport;
use App\Models\Contable\ConciliacionBancariaChequePendiente;
use App\Models\Contable\ConciliacionBancariaEjecucion;
use App\Models\Contable\ConciliacionBancariaPar;
use App\Services\Ai\AiPolicy;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillRegistry;
use App\Services\Contable\Ai\SugerirParesConciliacionBancariaSkill;
use App\Support\Ai\AiAgenteEventoDispatcherSupport;
use App\Support\Ai\AiAgenteOperativoSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaAnomaliaSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaCodificacionSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaExcelReferenciaSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaHashSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaMatcher;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaMovimientoBancoSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaPendienteSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaPendientesCpromaeSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaReferenciaSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaRuntimeSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConciliacionBancariaService
{
    public function __construct(
        private readonly MayorPlanoCuentaReporteService $mayorPlanoService,
        private readonly AiSkillRegistry $skillRegistry,
        private readonly AiPolicy $aiPolicy,
    ) {
    }

    /**
     * @param  array{pendientes_excel?: string, persistir_pendientes?: bool}  $opciones
     * @return array<string, mixed>
     */
    public function ejecutar(
        int $empresaId,
        int $cuentacajaId,
        int $mes,
        int $anio,
        ?int $usuarioId = null,
        bool $persistirPares = true,
        array $opciones = [],
    ): array {
        MayorPlanoCuentaRuntimeSupport::elevarLimites();

        $cuentacaja = Cuentacaja::query()
            ->with(['cuentacontables', 'empresas', 'bancos'])
            ->findOrFail($cuentacajaId);

        if (! $cuentacaja->perteneceAEmpresa($empresaId)) {
            throw new RuntimeException('La cuenta de caja no pertenece a la empresa indicada.');
        }

        $cuentaInterbanking = trim((string) ($cuentacaja->cuenta_interbanking ?? ''));
        if ($cuentaInterbanking === '') {
            throw new RuntimeException('La cuenta de caja no tiene configurada la cuenta Interbanking.');
        }

        $cuentaContable = $cuentacaja->cuentacontables;
        if (! $cuentaContable) {
            throw new RuntimeException('La cuenta de caja no tiene cuenta contable asociada.');
        }

        $codigoCuenta = MayorPlanoCuentaSupport::parsearCodigoCuenta((string) $cuentaContable->codigo);
        if ($codigoCuenta <= 0) {
            throw new RuntimeException('Código de cuenta contable inválido.');
        }

        $fechaDesde = Carbon::createFromDate($anio, $mes, 1)->startOfDay();
        $fechaHasta = $fechaDesde->copy()->endOfMonth();

        $filtrosMayor = [
            'empresa_ids' => [$empresaId],
            'moneda_id' => (int) ($cuentacaja->moneda_id ?? 1),
            'modo_periodo' => 'mes',
            'mes' => $mes,
            'anio' => $anio,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'solo_moneda_origen' => false,
            'incluye_subdiario' => true,
            'modo_inclusion_asientos' => 'sin_cierre_ni_inflacion',
            'cuenta_desde' => $codigoCuenta,
            'cuenta_hasta' => $codigoCuenta,
            'filtro_texto' => '',
        ];

        $resultadoMayor = $this->mayorPlanoService->generarDesdeFiltros($filtrosMayor);
        $filasMayor = $this->mayorPlanoService->aplanarFilas($resultadoMayor, [], false);
        $contablesPeriodo = $this->extraerMovimientosContables($filasMayor, $cuentacajaId);

        // Histórico: desde cobertura IB (o 18 meses) — desde 2000 provoca OOM y no hay extracto para matchear.
        // Se mantienen cheques previos a IB como pendientes de carátula (estilo Excel Contaduría).
        $fechaMinIb = $this->resolverFechaMinimaInterbanking($empresaId, $cuentaInterbanking);
        $diasCheque = max(30, (int) config('conciliacion_bancaria.dias_tolerancia_fecha_cheque', 30));
        $lookbackMeses = max(6, (int) config('conciliacion_bancaria.historico_lookback_meses', 18));
        $fechaDesdeHistorico = $fechaDesde->copy()->subMonths($lookbackMeses)->startOfDay();
        if ($fechaMinIb) {
            $desdeIb = $fechaMinIb->copy()->subDays($diasCheque)->startOfDay();
            if ($desdeIb->lt($fechaDesdeHistorico)) {
                $fechaDesdeHistorico = $desdeIb;
            }
        }
        @ini_set('memory_limit', (string) config('conciliacion_bancaria.memory_limit', '2048M'));

        $filtrosHistorico = $filtrosMayor;
        $filtrosHistorico['modo_periodo'] = 'rango';
        $filtrosHistorico['fecha_desde'] = $fechaDesdeHistorico->toDateString();
        $filtrosHistorico['fecha_hasta'] = $fechaHasta->copy()->subMonth()->endOfMonth()->format('Y-m-d');
        if ($filtrosHistorico['fecha_hasta'] < $filtrosHistorico['fecha_desde']) {
            $filtrosHistorico['fecha_hasta'] = $fechaDesde->copy()->subDay()->toDateString();
        }

        $contablesHistoricos = [];
        if ($filtrosHistorico['fecha_hasta'] >= $filtrosHistorico['fecha_desde']) {
            $resHist = $this->mayorPlanoService->generarDesdeFiltros($filtrosHistorico);
            $filasHist = $this->mayorPlanoService->aplanarFilas($resHist, [], false);
            $contablesHistoricos = $this->extraerMovimientosContables($filasHist, $cuentacajaId);
        }

        $movimientosBanco = $this->cargarMovimientosInterbanking(
            $empresaId,
            $cuentaInterbanking,
            $fechaDesde,
            $fechaHasta,
            $cuentacajaId,
        );

        $movimientosBancoHistoricos = $this->cargarMovimientosInterbanking(
            $empresaId,
            $cuentaInterbanking,
            $fechaDesdeHistorico,
            $fechaDesde->copy()->subDay(),
            $cuentacajaId,
        );

        $paresPrevios = ConciliacionBancariaPar::query()
            ->where('cuentacaja_id', $cuentacajaId)
            ->get();

        $hashContConc = [];
        $hashBancoConc = [];
        foreach ($paresPrevios as $par) {
            $hashContConc[$par->hash_contable] = true;
            $hashBancoConc[$par->hash_banco] = true;
        }

        $todosContables = array_merge($contablesHistoricos, $contablesPeriodo);
        $todosBanco = array_merge($movimientosBancoHistoricos, $movimientosBanco);

        $matchPeriodo = ConciliacionBancariaMatcher::emparejar(
            $contablesPeriodo,
            $movimientosBanco,
            $hashContConc,
            $hashBancoConc,
        );

        $matchHistorico = ConciliacionBancariaMatcher::emparejar(
            $contablesHistoricos,
            $movimientosBancoHistoricos,
            $hashContConc,
            $hashBancoConc,
        );

        $nuevosPares = array_merge($matchPeriodo['pares'], $matchHistorico['pares']);

        if ($persistirPares && $nuevosPares !== []) {
            $this->persistirPares($empresaId, $cuentacajaId, $nuevosPares, $usuarioId);
            foreach ($nuevosPares as $par) {
                $hashContConc[(string) $par['contable']['hash']] = true;
                $hashBancoConc[(string) $par['banco']['hash']] = true;
            }
        }

        $pendientesContables = ConciliacionBancariaMatcher::emparejar(
            $todosContables,
            $todosBanco,
            $hashContConc,
            $hashBancoConc,
        )['contables_pendientes'];

        $pendientesBanco = ConciliacionBancariaMatcher::emparejar(
            $todosContables,
            $todosBanco,
            $hashContConc,
            $hashBancoConc,
        )['banco_pendientes'];

        $partPend = ConciliacionBancariaPendienteSupport::particionarContables(
            $pendientesContables,
            $fechaMinIb?->copy()->subDays($diasCheque),
        );
        $partBanco = ConciliacionBancariaPendienteSupport::particionarBanco($pendientesBanco);

        // Pendientes Contaduría: cpromae (semilla Excel o Ch: del mayor + snapshot previo).
        $armPendientes = $this->armarPendientesCpromae(
            (string) $cuentacaja->codigo,
            $empresaId,
            $cuentacajaId,
            $fechaHasta,
            $pendientesContables,
            $opciones,
        );
        // Firmado carátula (cheques restan): -suma vencimientos del mes.
        $sumaPendContCaratula = round(-1 * (float) $armPendientes['suma_caratula'], 2);
        $sumaPendBancoCaratula = $partBanco['suma_caratula'];

        $saldoBanco = $this->resolverSaldoBanco($empresaId, $cuentaInterbanking, $fechaHasta);
        $saldoContable = $this->resolverSaldoContable($resultadoMayor, $codigoCuenta);

        $saldoBancoAjustado = round($saldoBanco + $sumaPendContCaratula - $sumaPendBancoCaratula, 2);
        $diferencia = round($saldoContable - $saldoBancoAjustado, 2);

        $gastosResumen = ConciliacionBancariaCodificacionSupport::resumirGastosDiarios($movimientosBanco);

        $saldoInicialPeriodo = $this->resolverSaldoBanco(
            $empresaId,
            $cuentaInterbanking,
            $fechaDesde->copy()->subDay(),
        );
        $bancoProcesado = ConciliacionBancariaMovimientoBancoSupport::procesarListado(
            $movimientosBanco,
            $saldoInicialPeriodo,
        );

        $caratula = $this->armarCaratula(
            $cuentacaja,
            $cuentaContable,
            $fechaHasta,
            $saldoBanco,
            $sumaPendContCaratula,
            $sumaPendBancoCaratula,
            $saldoBancoAjustado,
            $saldoContable,
            $diferencia,
        );
        $caratula['cheques_modo'] = (string) ($armPendientes['fuente'] ?? 'cpromae');
        $caratula['cheques_pendientes_n'] = count($armPendientes['pendientes'] ?? []);
        $caratula['cheques_caratula_n'] = count($armPendientes['caratula'] ?? []);

        $resultado = [
            'empresa_id' => $empresaId,
            'cuentacaja' => $cuentacaja,
            'cuenta_contable' => $cuentaContable,
            'mes' => $mes,
            'anio' => $anio,
            'fecha_desde' => $fechaDesde->toDateString(),
            'fecha_hasta' => $fechaHasta->toDateString(),
            'mayor' => $filasMayor,
            'movimientos_banco' => $movimientosBanco,
            'movimientos_extracto' => $bancoProcesado['extracto'],
            'movimientos_saldo' => $bancoProcesado['saldo'],
            'saldo_inicial_periodo' => $bancoProcesado['saldo_inicial'],
            'saldo_final_periodo' => $bancoProcesado['saldo_final'],
            'pares_nuevos' => $nuevosPares,
            'pares_conciliados_total' => $paresPrevios->count() + count($nuevosPares),
            'pendientes_contables' => $pendientesContables,
            'pendientes_contables_cheques' => $partPend['cheques'],
            'pendientes_contables_otros' => $partPend['otros'],
            'pendientes_cheques_cpromae' => $armPendientes['pendientes'],
            'pendientes_cheques_caratula' => $armPendientes['caratula'],
            'pendientes_cheques_fuente' => $armPendientes['fuente'],
            'suma_pendientes_cheques' => $armPendientes['suma_pendientes'],
            'suma_pendientes_cheques_caratula' => $armPendientes['suma_caratula'],
            'pendientes_banco' => $pendientesBanco,
            'pendientes_banco_creditos' => $partBanco['creditos'],
            'pendientes_banco_debitos' => $partBanco['debitos'],
            'pendientes_banco_caratula' => $partBanco['caratula'],
            'saldo_banco' => $saldoBanco,
            'saldo_contable' => $saldoContable,
            'suma_pendientes_contables' => $sumaPendContCaratula,
            'suma_pendientes_contables_otros' => $partPend['suma_otros'],
            'suma_pendientes_banco' => $sumaPendBancoCaratula,
            'suma_pendientes_banco_creditos' => $partBanco['suma_creditos'],
            'suma_pendientes_banco_debitos' => $partBanco['suma_debitos'],
            'saldo_banco_ajustado' => $saldoBancoAjustado,
            'diferencia' => $diferencia,
            'gastos_resumen' => $gastosResumen,
            'caratula' => $caratula,
        ];

        $ejecucion = ConciliacionBancariaEjecucion::query()->create([
            'empresa_id' => $empresaId,
            'cuentacaja_id' => $cuentacajaId,
            'mes' => $mes,
            'anio' => $anio,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'saldo_banco' => $saldoBanco,
            'saldo_contable' => $saldoContable,
            'diferencia' => $diferencia,
            'resumen_json' => [
                'pares_nuevos' => count($nuevosPares),
                'pendientes_contables' => count($pendientesContables),
                'pendientes_banco' => count($pendientesBanco),
                'pendientes_cheques_cpromae' => count($armPendientes['pendientes']),
                'pendientes_cheques_caratula' => count($armPendientes['caratula']),
                'pendientes_cheques_fuente' => $armPendientes['fuente'],
                'suma_cheques_caratula' => $armPendientes['suma_caratula'],
            ],
            'usuario_id' => $usuarioId,
        ]);
        $resultado['ejecucion_id'] = $ejecucion->id;

        if (($opciones['persistir_pendientes'] ?? true) && $persistirPares) {
            $this->persistirPendientesCheques(
                (int) $ejecucion->id,
                $empresaId,
                $cuentacajaId,
                $armPendientes['pendientes'],
            );
        }

        $resultado = $this->enriquecerConGobernanzaIa($resultado, $empresaId, $cuentacajaId, $mes, $anio);

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    private function enriquecerConGobernanzaIa(
        array $resultado,
        int $empresaId,
        int $cuentacajaId,
        int $mes,
        int $anio,
    ): array {
        $skill = SugerirParesConciliacionBancariaSkill::NOMBRE;
        if (! $this->skillRegistry->tiene($skill) || ! $this->aiPolicy->puedeEjecutar($skill)) {
            return $resultado;
        }

        $snapshot = [
            'pares_nuevos' => $resultado['pares_nuevos'] ?? [],
            'pendientes_contables' => $resultado['pendientes_contables_cheques']
                ?? $resultado['pendientes_contables']
                ?? [],
            'pendientes_contables_todos' => $resultado['pendientes_contables'] ?? [],
            'pendientes_contables_otros' => $resultado['pendientes_contables_otros'] ?? [],
            'pendientes_banco' => $resultado['pendientes_banco'] ?? [],
            'diferencia' => $resultado['diferencia'] ?? 0,
            'suma_pendientes_contables' => $resultado['suma_pendientes_contables'] ?? 0,
            'suma_pendientes_contables_otros' => $resultado['suma_pendientes_contables_otros'] ?? 0,
            'suma_pendientes_banco' => $resultado['suma_pendientes_banco'] ?? 0,
            'excel_comparacion' => $resultado['excel_comparacion'] ?? null,
        ];

        $skillResult = $this->skillRegistry->ejecutar($skill, new AiSkillContext(
            entradas: [
                'snapshot' => $snapshot,
                'cuentacaja_id' => $cuentacajaId,
                'mes' => $mes,
                'anio' => $anio,
            ],
            empresaId: $empresaId,
            entidadTipo: SugerirParesConciliacionBancariaSkill::ENTIDAD,
        ));

        if (! $skillResult->ok) {
            $resultado['ai_anomalias'] = [];
            $resultado['ai_score'] = null;
            $resultado['ai_advertencias'] = [$skillResult->error ?? 'No se pudo evaluar anomalías IA.'];

            return $resultado;
        }

        $resultado['ai_decision_id'] = $skillResult->decisionId;
        $resultado['ai_score'] = $skillResult->score;
        $resultado['ai_anomalias'] = $skillResult->datos['anomalias'] ?? [];
        $resultado['ai_resumen'] = $skillResult->datos['resumen'] ?? [];
        $resultado['ai_advertencias'] = $skillResult->advertencias;

        $anomalias = is_array($resultado['ai_anomalias']) ? $resultado['ai_anomalias'] : [];
        if ($anomalias !== []) {
            $resultado['ai_plan'] = AiAgenteOperativoSupport::planDesdeAnomaliasConciliacion(
                $anomalias,
                [
                    'mes' => $mes,
                    'anio' => $anio,
                    'cuentacaja_id' => $cuentacajaId,
                ]
            );
            $evento = AiAgenteEventoDispatcherSupport::registrar([
                'evento' => AiAgenteOperativoSupport::EVENTO_DESVIO_CONCILIACION,
                'origen' => 'contable.conciliacion_bancaria',
                'severidad' => count(array_filter($anomalias, static fn ($a) => ($a['severidad'] ?? '') === 'alta')) > 0
                    ? 'alta'
                    : 'media',
                'entidad_tipo' => 'cuentacaja',
                'entidad_id' => $cuentacajaId,
                'empresa_id' => $empresaId,
                'resumen' => $resultado['ai_plan']['resumen'] ?? (count($anomalias).' anomalía(s) de conciliación'),
                'payload' => [
                    'mes' => $mes,
                    'anio' => $anio,
                    'anomalias' => count($anomalias),
                    'ai_decision_id' => $skillResult->decisionId,
                ],
                'plan_params' => [
                    'fecha_desde' => sprintf('%04d-%02d-01', $anio, $mes),
                    'fecha_hasta' => date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio, $mes))),
                ],
            ]);
            if ($evento) {
                $resultado['ai_agente_evento_id'] = $evento->id;
            }
        }

        return $resultado;
    }

    /**
     * @param  list<array<string, mixed>>  $filasMayor
     * @return list<array<string, mixed>>
     */
    private function extraerMovimientosContables(array $filasMayor, int $cuentacajaId): array
    {
        $movs = [];

        foreach ($filasMayor as $fila) {
            if (($fila['tipo_fila'] ?? '') !== 'detalle') {
                continue;
            }

            $fila['origen'] = 'mayor';
            $fila['hash'] = ConciliacionBancariaHashSupport::hashContable($cuentacajaId, $fila);
            $movs[] = $fila;
        }

        return $movs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cargarMovimientosInterbanking(
        int $empresaId,
        string $cuentaInterbanking,
        Carbon $desde,
        Carbon $hasta,
        int $cuentacajaId,
    ): array {
        if ($hasta->lt($desde)) {
            return [];
        }

        $rows = InterbankingMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->where('account_number', $cuentaInterbanking)
            ->whereDate('process_date', '>=', $desde->toDateString())
            ->whereDate('process_date', '<=', $hasta->toDateString())
            ->orderBy('process_date')
            ->orderBy('id')
            ->get();

        $movs = [];
        foreach ($rows as $row) {
            $arr = $row->toArray();
            $arr['hash'] = ConciliacionBancariaHashSupport::hashBanco($cuentacajaId, (string) $row->dedupe_hash);
            $movs[] = $arr;
        }

        return $movs;
    }

    private function resolverSaldoBanco(int $empresaId, string $cuentaInterbanking, Carbon $fechaHasta): float
    {
        return InterbankingSaldoResolverSupport::saldoEnFecha($empresaId, $cuentaInterbanking, $fechaHasta);
    }

    private function resolverFechaMinimaInterbanking(int $empresaId, string $cuentaInterbanking): ?Carbon
    {
        $min = InterbankingMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->where('account_number', $cuentaInterbanking)
            ->min('process_date');

        if ($min === null || $min === '') {
            return null;
        }

        try {
            return Carbon::parse($min)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $resultadoMayor
     */
    private function resolverSaldoContable(array $resultadoMayor, int $codigoCuenta): float
    {
        foreach ($resultadoMayor['secciones'] ?? [] as $seccion) {
            if ((int) ($seccion['cuenta'] ?? 0) !== $codigoCuenta) {
                continue;
            }

            $saldoInicial = (float) ($seccion['saldo_ejercicio_inicial'] ?? $seccion['saldo_inicial'] ?? 0);
            $delta = (float) ($seccion['total_debe'] ?? 0) - (float) ($seccion['total_haber'] ?? 0);

            return round($saldoInicial + $delta, 2);
        }

        return 0.0;
    }

    /**
     * @param  list<array{contable: array<string,mixed>, banco: array<string,mixed>}>  $pares
     */
    private function persistirPares(int $empresaId, int $cuentacajaId, array $pares, ?int $usuarioId): void
    {
        DB::transaction(function () use ($empresaId, $cuentacajaId, $pares, $usuarioId) {
            foreach ($pares as $par) {
                $cont = $par['contable'];
                $banco = $par['banco'];
                $hashC = (string) ($cont['hash'] ?? '');
                $hashB = (string) ($banco['hash'] ?? '');

                if ($hashC === '' || $hashB === '') {
                    continue;
                }

                if (ConciliacionBancariaPar::query()->where('cuentacaja_id', $cuentacajaId)->where('hash_contable', $hashC)->exists()) {
                    continue;
                }
                if (ConciliacionBancariaPar::query()->where('cuentacaja_id', $cuentacajaId)->where('hash_banco', $hashB)->exists()) {
                    continue;
                }

                ConciliacionBancariaPar::query()->create([
                    'empresa_id' => $empresaId,
                    'cuentacaja_id' => $cuentacajaId,
                    'hash_contable' => $hashC,
                    'hash_banco' => $hashB,
                    'contable_json' => $cont,
                    'banco_json' => $banco,
                    'fecha_contable' => self::fechaDesdeYmdOString($cont['fecha'] ?? null),
                    'fecha_banco' => self::fechaDesdeYmdOString($banco['process_date'] ?? null),
                    'importe' => ConciliacionBancariaHashSupport::importeFirmadoContable($cont),
                    'conciliado_por_usuario_id' => $usuarioId,
                    'conciliado_at' => now(),
                ]);
            }
        });
    }

    private static function fechaDesdeYmdOString(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_int($valor) && $valor > 0) {
            $s = str_pad((string) $valor, 8, '0', STR_PAD_LEFT);

            return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
        }

        try {
            return Carbon::parse((string) $valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function armarCaratula(
        Cuentacaja $cuentacaja,
        $cuentaContable,
        Carbon $fechaHasta,
        float $saldoBanco,
        float $sumaPendCont,
        float $sumaPendBanco,
        float $saldoBancoAjustado,
        float $saldoContable,
        float $diferencia,
    ): array {
        return [
            'empresa' => $cuentacaja->empresas?->nombre ?? '',
            'cuenta_codigo' => MayorPlanoCuentaSupport::formatearCodigoCuenta(
                MayorPlanoCuentaSupport::parsearCodigoCuenta((string) $cuentaContable->codigo),
            ),
            'cuenta_nombre' => $cuentaContable->nombre ?? '',
            'cuentacaja_codigo' => $cuentacaja->codigo,
            'cuentacaja_nombre' => $cuentacaja->nombre,
            'cbu' => $cuentacaja->cbu ?? '',
            'cuenta_interbanking' => $cuentacaja->cuenta_interbanking ?? '',
            'fecha_corte' => $fechaHasta->format('d.m.y'),
            'saldo_banco_extracto' => $saldoBanco,
            // Firmado contable (debe−haber): cheques emitidos quedan negativos, igual que Excel Contaduría.
            'cheques_no_acreditados' => round($sumaPendCont, 2),
            // Firmado banco invertido en display: créditos IB no contabilizados restan al extracto.
            'movimientos_pendientes_banco' => round(-$sumaPendBanco, 2),
            'saldo_banco_ajustado' => $saldoBancoAjustado,
            'saldo_contable' => $saldoContable,
            'diferencia' => $diferencia,
        ];
    }

    /**
     * Compara la carátula ERP contra el Excel de Contaduría.
     * Si la corrida no usó semilla Excel, rearma Pendientes/carátula desde la solapa Pendientes.
     *
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public function compararContraExcel(array $resultado, string $rutaExcel): array
    {
        $excel = ConciliacionBancariaExcelReferenciaSupport::leerCaratula($rutaExcel);
        $detalle = ConciliacionBancariaExcelReferenciaSupport::leerPendientesDetalle($rutaExcel);

        $fuenteActual = (string) ($resultado['pendientes_cheques_fuente'] ?? '');
        if ($fuenteActual !== 'cpromae_semilla_excel') {
            $cc = $resultado['cuentacaja'] ?? null;
            $fechaHasta = Carbon::parse((string) ($resultado['fecha_hasta'] ?? 'now'));
            $semilla = ConciliacionBancariaPendientesCpromaeSupport::semillaDesdeExcelDetalle($detalle['cheques']);
            $arm = (new ConciliacionBancariaPendientesCpromaeSupport())->armar(
                (string) ($cc->codigo ?? ''),
                (int) ($resultado['empresa_id'] ?? 0),
                $fechaHasta,
                array_keys($semilla),
                $semilla,
                false,
            );
            $resultado['pendientes_cheques_cpromae'] = $arm['pendientes'];
            $resultado['pendientes_cheques_caratula'] = $arm['caratula'];
            $resultado['pendientes_cheques_fuente'] = $arm['fuente'];
            $resultado['suma_pendientes_cheques'] = $arm['suma_pendientes'];
            $resultado['suma_pendientes_cheques_caratula'] = $arm['suma_caratula'];

            $sumaCheques = round(-1 * (float) $arm['suma_caratula'], 2);
            $sumaBancoCar = (float) ($resultado['suma_pendientes_banco'] ?? 0);
            $saldoBanco = (float) ($resultado['saldo_banco'] ?? 0);
            $saldoContable = (float) ($resultado['saldo_contable'] ?? 0);
            $saldoAjustado = round($saldoBanco + $sumaCheques - $sumaBancoCar, 2);
            $diferencia = round($saldoContable - $saldoAjustado, 2);

            $resultado['suma_pendientes_contables'] = $sumaCheques;
            $resultado['saldo_banco_ajustado'] = $saldoAjustado;
            $resultado['diferencia'] = $diferencia;
            if (is_array($resultado['caratula'] ?? null)) {
                $resultado['caratula']['cheques_no_acreditados'] = $sumaCheques;
                $resultado['caratula']['saldo_banco_ajustado'] = $saldoAjustado;
                $resultado['caratula']['diferencia'] = $diferencia;
                $resultado['caratula']['cheques_modo'] = 'cpromae_semilla_excel';
                $resultado['caratula']['cheques_pendientes_n'] = count($arm['pendientes']);
                $resultado['caratula']['cheques_caratula_n'] = count($arm['caratula']);
            }
        }

        $numsExcel = [];
        foreach ($detalle['cheques'] as $ch) {
            if ($ch['numero'] !== '') {
                $numsExcel[$ch['numero']] = true;
            }
        }
        $erpNums = [];
        foreach ($resultado['pendientes_cheques_cpromae'] ?? [] as $ch) {
            $n = (string) ($ch['numero_cheque'] ?? '');
            if ($n !== '') {
                $erpNums[$n] = true;
            }
        }
        $inter = array_intersect_key($erpNums, $numsExcel);
        $resultado['excel_pendientes_cobertura'] = [
            'excel_n' => count($numsExcel),
            'erp_n' => count($erpNums),
            'interseccion_n' => count($inter),
            'solo_excel' => array_values(array_diff_key($numsExcel, $erpNums)),
            'solo_erp' => array_values(array_diff_key($erpNums, $numsExcel)),
        ];
        $resultado['excel_pendientes_detalle'] = $detalle;
        $resultado['pendientes_contables_cheques_excel'] = $resultado['pendientes_cheques_caratula'] ?? [];

        $cmp = ConciliacionBancariaExcelReferenciaSupport::comparar(
            is_array($resultado['caratula'] ?? null) ? $resultado['caratula'] : [],
            $excel,
        );

        $resultado['excel_referencia'] = $excel;
        $resultado['excel_comparacion'] = $cmp;

        $eval = ConciliacionBancariaAnomaliaSupport::evaluar([
            'pares_nuevos' => $resultado['pares_nuevos'] ?? [],
            'pendientes_contables' => $resultado['pendientes_cheques_caratula'] ?? [],
            'pendientes_contables_otros' => $resultado['pendientes_contables_otros'] ?? [],
            'pendientes_banco' => $resultado['pendientes_banco'] ?? [],
            'diferencia' => (float) ($resultado['diferencia'] ?? 0),
            'suma_pendientes_contables' => (float) ($resultado['suma_pendientes_contables'] ?? 0),
            'suma_pendientes_contables_otros' => $resultado['suma_pendientes_contables_otros'] ?? 0,
            'suma_pendientes_banco' => (float) ($resultado['suma_pendientes_banco'] ?? 0),
            'excel_comparacion' => $cmp,
        ]);
        $resultado['ai_score'] = $eval['score'];
        $resultado['ai_anomalias'] = $eval['anomalias'];
        $resultado['ai_resumen'] = $eval['resumen'];

        return $resultado;
    }

    /**
     * @param  list<array<string,mixed>>  $pendientesContables
     * @param  array<string, mixed>  $opciones
     * @return array{
     *   pendientes: list<array<string,mixed>>,
     *   caratula: list<array<string,mixed>>,
     *   suma_pendientes: float,
     *   suma_caratula: float,
     *   fuente: string
     * }
     */
    private function armarPendientesCpromae(
        string $codigoCuentacaja,
        int $empresaId,
        int $cuentacajaId,
        Carbon $fechaCorte,
        array $pendientesContables,
        array $opciones,
    ): array {
        $support = new ConciliacionBancariaPendientesCpromaeSupport();
        $semilla = [];
        $rutaExcel = trim((string) ($opciones['pendientes_excel'] ?? ''));
        if ($rutaExcel !== '') {
            $detalle = ConciliacionBancariaExcelReferenciaSupport::leerPendientesDetalle($rutaExcel);
            $semilla = ConciliacionBancariaPendientesCpromaeSupport::semillaDesdeExcelDetalle($detalle['cheques']);
        }

        if ($semilla === []) {
            $semilla = $this->semillaDesdeSnapshotPrevio($empresaId, $cuentacajaId);
        }

        $numeros = array_keys($semilla);
        if ($numeros === []) {
            foreach ($pendientesContables as $mov) {
                $n = ConciliacionBancariaReferenciaSupport::extraerChequeContable($mov);
                if ($n !== null) {
                    $numeros[] = $n;
                }
            }
            $numeros = array_values(array_unique($numeros));
        }

        return $support->armar(
            $codigoCuentacaja,
            $empresaId,
            $fechaCorte,
            $numeros,
            $semilla,
            $semilla === [],
        );
    }

    /**
     * @return array<string, array{tip: string, importe: float, fecha_emision?: string|null, fecha_cheque?: string|null}>
     */
    private function semillaDesdeSnapshotPrevio(int $empresaId, int $cuentacajaId): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('conciliacion_bancaria_cheque_pendiente')) {
            return [];
        }

        $ejecucionId = ConciliacionBancariaEjecucion::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacaja_id', $cuentacajaId)
            ->orderByDesc('id')
            ->value('id');
        if (! $ejecucionId) {
            return [];
        }

        $rows = ConciliacionBancariaChequePendiente::query()
            ->where('ejecucion_id', $ejecucionId)
            ->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $n = ltrim((string) preg_replace('/\D/', '', (string) $row->numero_cheque), '0');
            if ($n === '' || $n === '0') {
                continue;
            }
            $out[$n] = [
                'tip' => (string) ($row->tip ?: 'CHP'),
                'importe' => abs((float) $row->importe),
                'fecha_emision' => optional($row->fecha_emision)?->toDateString(),
                'fecha_cheque' => optional($row->fecha_cheque)?->toDateString(),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string,mixed>>  $pendientes
     */
    private function persistirPendientesCheques(
        int $ejecucionId,
        int $empresaId,
        int $cuentacajaId,
        array $pendientes,
    ): void {
        if (! \Illuminate\Support\Facades\Schema::hasTable('conciliacion_bancaria_cheque_pendiente')) {
            return;
        }

        $now = now();
        $chunks = array_chunk($pendientes, 200);
        foreach ($chunks as $chunk) {
            $rows = [];
            foreach ($chunk as $p) {
                $rows[] = [
                    'ejecucion_id' => $ejecucionId,
                    'empresa_id' => $empresaId,
                    'cuentacaja_id' => $cuentacajaId,
                    'tip' => (string) ($p['tip'] ?? 'CHP'),
                    'numero_cheque' => (string) ($p['numero_cheque'] ?? ''),
                    'fecha_emision' => $p['fecha_emision'] ?? null,
                    'fecha_cheque' => $p['fecha_cheque'] ?? null,
                    'fecha_entrega' => $p['fecha_entrega'] ?? null,
                    'fecha_conciliacion' => $p['fecha_conciliacion'] ?? null,
                    'importe' => (float) ($p['importe'] ?? 0),
                    'estado' => $p['estado'] ?? null,
                    'estado_banco' => $p['estado_banco'] ?? null,
                    'entregado_a' => $p['entregado_a'] ?? null,
                    'proveedor_codigo' => $p['proveedor_codigo'] ?? null,
                    'nro_op' => $p['nro_op'] ?? null,
                    'para_dep' => $p['para_dep'] ?? null,
                    'incluye_caratula' => ! empty($p['incluye_caratula']),
                    'origen_json' => isset($p['origen_json']) ? json_encode($p['origen_json']) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            ConciliacionBancariaChequePendiente::query()->insert($rows);
        }
    }
}
