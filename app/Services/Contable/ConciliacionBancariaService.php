<?php

namespace App\Services\Contable;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\InterbankingMovimiento;
use App\Models\Caja\InterbankingSaldoDiario;
use App\Models\Contable\ConciliacionBancariaEjecucion;
use App\Models\Contable\ConciliacionBancariaPar;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaCodificacionSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaHashSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaMatcher;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaMovimientoBancoSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaRuntimeSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConciliacionBancariaService
{
    public function __construct(
        private readonly MayorPlanoCuentaReporteService $mayorPlanoService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        int $empresaId,
        int $cuentacajaId,
        int $mes,
        int $anio,
        ?int $usuarioId = null,
        bool $persistirPares = true,
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

        $filtrosHistorico = $filtrosMayor;
        $filtrosHistorico['modo_periodo'] = 'rango';
        $filtrosHistorico['fecha_desde'] = '2000-01-01';
        $filtrosHistorico['fecha_hasta'] = $fechaHasta->copy()->subMonth()->endOfMonth()->format('Y-m-d');
        if ($filtrosHistorico['fecha_hasta'] < '2000-01-01') {
            $filtrosHistorico['fecha_hasta'] = $fechaDesde->format('Y-m-d');
        }

        $contablesHistoricos = [];
        if ($filtrosHistorico['fecha_hasta'] >= '2000-01-01') {
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
            Carbon::parse('2000-01-01'),
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

        $saldoBanco = $this->resolverSaldoBanco($empresaId, $cuentaInterbanking, $fechaHasta);
        $saldoContable = $this->resolverSaldoContable($resultadoMayor, $codigoCuenta);
        $sumaPendCont = array_sum(array_map(
            fn (array $m) => ConciliacionBancariaHashSupport::importeFirmadoContable($m),
            $pendientesContables,
        ));
        $sumaPendBanco = array_sum(array_map(
            fn (array $m) => ConciliacionBancariaHashSupport::importeFirmadoBanco($m),
            $pendientesBanco,
        ));

        $saldoBancoAjustado = round($saldoBanco - $sumaPendCont + $sumaPendBanco, 2);
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
            'pendientes_banco' => $pendientesBanco,
            'saldo_banco' => $saldoBanco,
            'saldo_contable' => $saldoContable,
            'suma_pendientes_contables' => round($sumaPendCont, 2),
            'suma_pendientes_banco' => round($sumaPendBanco, 2),
            'saldo_banco_ajustado' => $saldoBancoAjustado,
            'diferencia' => $diferencia,
            'gastos_resumen' => $gastosResumen,
            'caratula' => $this->armarCaratula(
                $cuentacaja,
                $cuentaContable,
                $fechaHasta,
                $saldoBanco,
                $sumaPendCont,
                $sumaPendBanco,
                $saldoBancoAjustado,
                $saldoContable,
                $diferencia,
            ),
        ];

        ConciliacionBancariaEjecucion::query()->create([
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
            ],
            'usuario_id' => $usuarioId,
        ]);

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
        $saldo = InterbankingSaldoDiario::query()
            ->where('empresa_id', $empresaId)
            ->where('account_number', $cuentaInterbanking)
            ->whereDate('fecha', '<=', $fechaHasta->toDateString())
            ->orderByDesc('fecha')
            ->value('countable_balance');

        if ($saldo !== null) {
            return round((float) $saldo, 2);
        }

        $saldoAlt = InterbankingSaldoDiario::query()
            ->where('empresa_id', $empresaId)
            ->where('account_number', $cuentaInterbanking)
            ->whereDate('fecha', '<=', $fechaHasta->toDateString())
            ->orderByDesc('fecha')
            ->value('current_operating_balance');

        return round((float) ($saldoAlt ?? 0), 2);
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
            'cheques_no_acreditados' => round(-$sumaPendCont, 2),
            'movimientos_pendientes_banco' => round($sumaPendBanco, 2),
            'saldo_banco_ajustado' => $saldoBancoAjustado,
            'saldo_contable' => $saldoContable,
            'diferencia' => $diferencia,
        ];
    }
}
