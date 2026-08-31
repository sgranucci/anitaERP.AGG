<?php

namespace App\Services\Sueldos;

use App\Models\Contable\Asiento;
use App\Models\Sueldos\Liquidacion_Asiento_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Contable\AsientoReversoSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Sueldos\SueldosAsientoCalidadCierreSupport;
use App\Support\Sueldos\SueldosAsientoCuadreSupport;
use App\Support\Sueldos\SueldosAsientoModoSupport;
use App\Support\Sueldos\SueldosAsientoSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Motor del asiento de devengamiento de una corrida.
 * El modo (ERP / Anita) lo elige la empresa. Anita: solo ctamov vía
 * AsientoRepository (omitir_anita + sync). No escribe subdiario.
 * El período cerrado lo valida AsientoRepository (alcance contable).
 * El pago del neto no vive acá: sale por solicitud de pago + Ingreso/Egreso (TES).
 */
class LiquidacionAsientoService
{
    public function __construct(
        private AsientoRepositoryInterface $asientoRepository,
        private Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private TipoasientoRepositoryInterface $tipoasientoRepository,
        private AsientoReversoSupport $asientoReverso,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(Liquidacion_Sueldos $liq): array
    {
        $this->assertPuedePrevisualizar($liq);

        return SueldosAsientoCalidadCierreSupport::evaluar($liq);
    }

    public function generar(Liquidacion_Sueldos $liq): int
    {
        $this->assertPuedeContabilizar($liq);

        $preview = SueldosAsientoCalidadCierreSupport::evaluar($liq);
        SueldosAsientoCalidadCierreSupport::assertListoParaContabilizar($preview);

        $modo = SueldosAsientoModoSupport::normalizar((string) ($preview['modo'] ?? ''));
        $abrev = SueldosAsientoModoSupport::abrevTipoasiento($modo);
        $tipo = $this->tipoasientoRepository->findPorAbreviatura($abrev);
        if ($tipo === null) {
            throw new RuntimeException('No existe el tipo de asiento '.$abrev.'.');
        }

        $fecha = $liq->periodo_hasta?->format('Y-m-d')
            ?? $liq->fecha_liquidacion?->format('Y-m-d')
            ?? date('Y-m-d');
        $periodoLabel = $liq->periodo_mes
            ? sprintf('%02d/%04d', (int) $liq->periodo_mes, (int) $liq->periodo_anio)
            : (string) ($liq->periodo ?? '');
        $obsBase = SueldosAsientoSupport::observacionCabecera(
            (int) $liq->numero,
            (string) ($liq->descripcion ?? ''),
            $periodoLabel
        );

        $grupos = $preview['grupos'] ?? [];
        if ($grupos === []) {
            $grupos = [[
                'centrocosto_id' => null,
                'etiqueta' => 'Corrida',
                'lineas' => $preview['lineas'] ?? [],
                'total_debe' => $preview['total_debe'] ?? 0,
                'total_haber' => $preview['total_haber'] ?? 0,
            ]];
        }

        return (int) DB::transaction(function () use ($liq, $tipo, $fecha, $obsBase, $modo, $grupos) {
            $primerId = 0;
            foreach ($grupos as $grupo) {
                if (round((float) ($grupo['total_debe'] ?? 0), 2) < 0.01) {
                    continue;
                }
                $obs = $obsBase;
                if ($modo === SueldosAsientoModoSupport::ANITA) {
                    $etiq = trim((string) ($grupo['etiqueta'] ?? ''));
                    if ($etiq !== '') {
                        $obs .= ' — '.$etiq;
                    }
                }
                $payload = $this->payloadDesdeLineas(
                    $liq,
                    (int) $tipo->id,
                    $fecha,
                    $obs,
                    $grupo['lineas'] ?? []
                );
                $asiento = $this->asientoRepository->create($payload);
                if ($asiento === 'Error' || ! $asiento) {
                    throw new RuntimeException('Error al grabar el asiento de sueldos.');
                }
                $asientoId = (int) $asiento->id;
                $this->asientoMovimientoRepository->create($payload, $asientoId);
                SueldosAsientoCuadreSupport::assertPersistido(
                    $asientoId,
                    $grupo,
                    $this->asientoMovimientoRepository
                );
                $this->sincronizarCtamov($asiento->fresh(), $liq, $payload);

                Liquidacion_Asiento_Sueldos::query()->create([
                    'liquidacion_id' => (int) $liq->id,
                    'asiento_id' => $asientoId,
                    'centrocosto_id' => ! empty($grupo['centrocosto_id'])
                        ? (int) $grupo['centrocosto_id']
                        : null,
                ]);

                if ($primerId === 0) {
                    $primerId = $asientoId;
                }
            }

            if ($primerId <= 0) {
                throw new RuntimeException('No se generó ningún asiento de sueldos.');
            }

            $liq->update([
                'asiento_id' => $primerId,
                'contabilizado' => true,
                'fecha_contabilizacion' => now(),
                'estado' => 'contabilizada',
            ]);

            return $primerId;
        });
    }

    public function revertir(Liquidacion_Sueldos $liq): int
    {
        $this->assertPuedeRevertir($liq);
        app(LiquidacionSolicitudpagoService::class)->assertSinSolicitudActiva($liq);

        $ids = Liquidacion_Asiento_Sueldos::query()
            ->where('liquidacion_id', (int) $liq->id)
            ->pluck('asiento_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
        if ($ids->isEmpty() && (int) ($liq->asiento_id ?? 0) > 0) {
            $ids = collect([(int) $liq->asiento_id]);
        }

        return (int) DB::transaction(function () use ($liq, $ids) {
            $ultimoReverso = 0;
            foreach ($ids as $asientoId) {
                $asiento = Asiento::query()->with('asiento_movimientos')->find((int) $asientoId);
                if ($asiento === null) {
                    continue;
                }
                $reverso = $this->asientoReverso->generarDesdeAsiento(
                    $asiento,
                    $asiento->fecha instanceof \DateTimeInterface
                        ? $asiento->fecha->format('Y-m-d')
                        : (string) $asiento->fecha,
                    null,
                    'Revierte sueldos corrida '.$liq->numero,
                    true,
                    null,
                    $this->referenciaCtamov($liq),
                    PeriodoContableCierreSupport::ALCANCE_CONTABLE
                );
                $reversoId = (int) ($reverso['asiento_id'] ?? 0);
                if ($reversoId > 0) {
                    Asiento::query()->whereKey($reversoId)->update([
                        'liquidacion_sueldos_id' => (int) $liq->id,
                    ]);
                    $reversoModelo = Asiento::query()->with('asiento_movimientos')->find($reversoId);
                    if ($reversoModelo !== null) {
                        $this->sincronizarCtamov($reversoModelo, $liq);
                    }
                    $ultimoReverso = $reversoId;
                }
            }

            EloquentAuditDeleteSupport::each(
                Liquidacion_Asiento_Sueldos::query()->where('liquidacion_id', (int) $liq->id)
            );

            $liq->update([
                'asiento_id' => null,
                'contabilizado' => false,
                'fecha_contabilizacion' => null,
                'estado' => 'cerrada',
            ]);

            return $ultimoReverso;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, mixed>
     */
    private function payloadDesdeLineas(
        Liquidacion_Sueldos $liq,
        int $tipoasientoId,
        string $fecha,
        string $observacion,
        array $lineas
    ): array {
        $payload = [
            'empresa_id' => (int) $liq->empresa_id,
            'tipoasiento_id' => $tipoasientoId,
            'fecha' => $fecha,
            'observacion' => $observacion,
            'usuario_id' => (int) (Auth::id() ?: $liq->usuario_id),
            'liquidacion_sueldos_id' => (int) $liq->id,
            'omitir_anita' => true,
            'alcance_cierre_contable' => PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            'sistema_ctav' => SueldosAsientoSupport::CTAMOV_SISTEMA,
            'cuentacontable_ids' => [],
            'moneda_ids' => [],
            'centrocosto_ids' => [],
            'debes' => [],
            'haberes' => [],
            'cotizaciones' => [],
            'observaciones' => [],
        ];

        foreach ($lineas as $linea) {
            $payload['cuentacontable_ids'][] = $linea['cuentacontable_id'];
            $payload['moneda_ids'][] = SueldosAsientoSupport::MONEDA_LOCAL_ID;
            $payload['centrocosto_ids'][] = (int) ($linea['centrocosto_id'] ?? 0);
            $payload['debes'][] = ($linea['debe'] ?? 0) > 0 ? $linea['debe'] : '';
            $payload['haberes'][] = ($linea['haber'] ?? 0) > 0 ? $linea['haber'] : '';
            $payload['cotizaciones'][] = 1;
            $payload['observaciones'][] = $linea['observacion'] ?? '';
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $payloadLineas
     */
    private function sincronizarCtamov(Asiento $asiento, Liquidacion_Sueldos $liq, ?array $payloadLineas = null): void
    {
        $payload = $payloadLineas !== null
            ? array_merge($payloadLineas, [
                'numeroasiento' => (string) $asiento->numeroasiento,
                'tipoasiento_id' => (int) $asiento->tipoasiento_id,
                'empresa_id' => (int) $asiento->empresa_id,
                'fecha' => $asiento->fecha instanceof \DateTimeInterface
                    ? $asiento->fecha->format('Y-m-d')
                    : (string) $asiento->fecha,
            ])
            : $this->asientoRepository->armarPayloadAnitaDesdeModelo($asiento);

        $payload = array_merge($payload, $this->referenciaCtamov($liq), [
            'numeroasiento' => (string) $asiento->numeroasiento,
            'alcance_cierre_contable' => PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            'sistema_ctav' => SueldosAsientoSupport::CTAMOV_SISTEMA,
        ]);

        $this->asientoRepository->sincronizarCtamovAnita($payload);
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    private function referenciaCtamov(Liquidacion_Sueldos $liq): array
    {
        return [
            'tipo' => SueldosAsientoSupport::CTAMOV_TIPO,
            'letra' => ' ',
            'sucursal' => 0,
            'nro' => (int) $liq->numero,
        ];
    }

    private function assertPuedePrevisualizar(Liquidacion_Sueldos $liq): void
    {
        if ((int) $liq->empresa_id <= 0) {
            throw new RuntimeException('La corrida no tiene empresa.');
        }
        if (! empty($liq->simulacion)) {
            throw new RuntimeException('Una simulación no genera asiento contable.');
        }
    }

    private function assertPuedeContabilizar(Liquidacion_Sueldos $liq): void
    {
        $this->assertPuedePrevisualizar($liq);
        if ((string) $liq->estado !== 'cerrada') {
            throw new RuntimeException('Solo se contabiliza una corrida cerrada.');
        }
        if (! empty($liq->contabilizado) || (int) ($liq->asiento_id ?? 0) > 0) {
            throw new RuntimeException('La corrida ya está contabilizada.');
        }
    }

    private function assertPuedeRevertir(Liquidacion_Sueldos $liq): void
    {
        if ((string) $liq->estado !== 'contabilizada' && empty($liq->contabilizado)) {
            throw new RuntimeException('La corrida no está contabilizada.');
        }
        $tieneHijos = Liquidacion_Asiento_Sueldos::query()
            ->where('liquidacion_id', (int) $liq->id)
            ->exists();
        if ((int) ($liq->asiento_id ?? 0) <= 0 && ! $tieneHijos) {
            throw new RuntimeException('La corrida no tiene asiento asociado.');
        }
    }
}
