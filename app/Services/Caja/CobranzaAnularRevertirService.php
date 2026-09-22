<?php

namespace App\Services\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cheque;
use App\Models\Caja\Cobranza;
use App\Models\Caja\Cobranza_Archivo;
use App\Models\Caja\Cobranza_Comprobante;
use App\Models\Caja\Cobranza_Descuento;
use App\Models\Caja\Cobranza_Estado;
use App\Models\Caja\Cobranza_Retencion;
use App\Models\Contable\Asiento;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Repositories\Caja\CobranzaRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Services\Ventas\ClienteCuentacorrienteAplicacionAnitaSyncService;
use App\Support\Caja\CajaMovimientoEloquentDeleteSupport;
use App\Support\Caja\ChequeOperacionActivaSupport;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Anulación física de cobranza: revierte CC/aplicaciones, asiento, caja, cheques y Anita.
 *
 * El delete histórico de CobranzaRepository solo borraba la cabecera y dejaba huérfanos
 * (incidente 22/sep/2026, cobranza 106601 / COB 77744).
 */
class CobranzaAnularRevertirService
{
    public function __construct(
        private CobranzaRepositoryInterface $cobranzaRepository,
        private AsientoRepositoryInterface $asientoRepository,
        private ClienteCuentacorrienteAplicacionAnitaSyncService $aplicacionAnitaSyncService,
        private CobranzaService $cobranzaService,
    ) {}

    /**
     * @return array{mensaje: string, cobranza_id: int}
     */
    public function anularFisicamente(int $id): array
    {
        $cobranza = $this->cobranzaRepository->findOrFail($id);

        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $cobranza->empresa_id,
            $this->fechaYmd($cobranza->fecha),
            PeriodoContableCierreSupport::ALCANCE_COBRANZA
        );

        return DB::transaction(function () use ($cobranza) {
            $actual = Cobranza::query()->whereKey((int) $cobranza->id)->lockForUpdate()->first();
            if ($actual === null) {
                throw new RuntimeException('La cobranza ya no existe.');
            }

            $this->limpiarCascada(
                cobranzaId: (int) $actual->id,
                empresaId: (int) $actual->empresa_id,
                numeroTransaccion: (string) ($actual->numerotransaccion ?? ''),
                borrarCabecera: $actual,
            );

            return [
                'mensaje' => 'ok',
                'cobranza_id' => (int) $actual->id,
            ];
        });
    }

    /**
     * Repara huérfanos cuando la cabecera ya se borró (bug histórico) y quedan CC/caja/asiento.
     *
     * @return array{mensaje: string, cobranza_id: int, limpio: bool}
     */
    public function limpiarHuerfanosSinCabecera(
        int $cobranzaId,
        int $empresaId,
        string $numeroTransaccion,
        string $fechaYmd,
    ): array {
        if (Cobranza::query()->whereKey($cobranzaId)->exists()) {
            throw new RuntimeException(
                'La cobranza '.$cobranzaId.' aún existe: use anularFisicamente().'
            );
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fechaYmd,
            PeriodoContableCierreSupport::ALCANCE_COBRANZA
        );

        return DB::transaction(function () use ($cobranzaId, $empresaId, $numeroTransaccion) {
            $this->limpiarCascada(
                cobranzaId: $cobranzaId,
                empresaId: $empresaId,
                numeroTransaccion: $numeroTransaccion,
                borrarCabecera: null,
            );

            return [
                'mensaje' => 'ok',
                'cobranza_id' => $cobranzaId,
                'limpio' => true,
            ];
        });
    }

    private function limpiarCascada(
        int $cobranzaId,
        int $empresaId,
        string $numeroTransaccion,
        ?Cobranza $borrarCabecera,
    ): void {
        $snapshots = [];
        $aplicacionesDeuda = Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cobranza_id', $cobranzaId)
            ->where('total', '<', 0)
            ->orderBy('id')
            ->get();
        foreach ($aplicacionesDeuda as $apl) {
            try {
                $snapshots[] = $this->aplicacionAnitaSyncService->snapshotDesdeAplicacion($apl);
            } catch (Throwable $e) {
                Log::warning('cobranza.anular.anita_cc_snapshot', [
                    'cobranza_id' => $cobranzaId,
                    'aplicacion_id' => $apl->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($snapshots as $snapshot) {
            try {
                $this->aplicacionAnitaSyncService->revertir($snapshot);
            } catch (Throwable $e) {
                Log::warning('cobranza.anular.anita_cc_revertir', [
                    'cobranza_id' => $cobranzaId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        EloquentAuditDeleteSupport::each(
            Cliente_Cuentacorriente_Aplicacion::query()->where('cobranza_id', $cobranzaId)
        );

        EloquentAuditDeleteSupport::each(
            Cliente_Cuentacorriente::query()->where('cobranza_id', $cobranzaId)
        );

        EloquentAuditDeleteSupport::each(
            Cobranza_Comprobante::query()->where('cobranza_id', $cobranzaId)
        );

        $asientos = Asiento::query()->where('cobranza_id', $cobranzaId)->get();
        foreach ($asientos as $asiento) {
            $this->asientoRepository->delete((int) $asiento->id);
        }

        $cajaMovimientos = Caja_Movimiento::query()
            ->where('cobranza_id', $cobranzaId)
            ->get();

        $tesoreriaBorrada = false;
        foreach ($cajaMovimientos as $mov) {
            IngresoEgresoAnitaTesmovSupport::eliminarDesdeMovimiento($mov);
            $tesoreriaBorrada = true;
        }

        if (! $tesoreriaBorrada && $numeroTransaccion !== '') {
            $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
            if ($empresaAnita <= 0) {
                $empresaAnita = $empresaId;
            }
            IngresoEgresoAnitaTesmovSupport::eliminarTesoreriaPorClave(
                'COB',
                (int) $numeroTransaccion,
                $empresaAnita,
                'cobranza anular '.$cobranzaId
            );
        }

        try {
            $this->cobranzaService->borraAnita('COB', 'X', 0, $numeroTransaccion, $empresaId);
        } catch (Throwable $e) {
            Log::warning('cobranza.anular.borra_anita_pago', [
                'cobranza_id' => $cobranzaId,
                'error' => $e->getMessage(),
            ]);
        }

        ChequeOperacionActivaSupport::anularPorCobranza($cobranzaId);
        EloquentAuditDeleteSupport::each(
            Cheque::query()->where('cobranza_id', $cobranzaId)
        );

        CajaMovimientoEloquentDeleteSupport::eliminarPorQuery(
            Caja_Movimiento::query()->where('cobranza_id', $cobranzaId)
        );

        EloquentAuditDeleteSupport::each(
            Cobranza_Descuento::query()->where('cobranza_id', $cobranzaId)
        );
        EloquentAuditDeleteSupport::each(
            Cobranza_Retencion::query()->where('cobranza_id', $cobranzaId)
        );
        EloquentAuditDeleteSupport::each(
            Cobranza_Archivo::query()->where('cobranza_id', $cobranzaId)
        );
        EloquentAuditDeleteSupport::each(
            Cobranza_Estado::query()->where('cobranza_id', $cobranzaId)
        );

        $borrarCabecera?->delete();
    }

    private function fechaYmd(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        $raw = trim((string) $fecha);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) === 1) {
            return substr($raw, 0, 10);
        }

        return date('Y-m-d');
    }
}
