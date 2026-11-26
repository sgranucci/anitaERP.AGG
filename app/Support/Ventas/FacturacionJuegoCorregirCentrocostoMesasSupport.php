<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Caja\Flash\FlashCrown;
use App\Models\Contable\Asiento_Movimiento;
use App\Support\Contable\PeriodoContableCierreSupport;
use Illuminate\Support\Facades\DB;

/**
 * Corrige el centro de costo de los asientos de MESAS de facturación juego que
 * quedaron con el CC de SLOTS de la sala (incidente octubre 2026).
 *
 * Solo toca movimientos cuyo CC actual es el CC de slots de la sala (el valor
 * erróneo conocido). Ambas patas del asiento (juego y caja) pasan al CC de mesas.
 * Se escribe por Eloquent para que Asiento_MovimientoObserver mantenga
 * cuentacontable_saldo_mes.
 */
final class FacturacionJuegoCorregirCentrocostoMesasSupport
{
    /**
     * @return array{planes: list<array<string, mixed>>, sin_cc_mesa: list<string>}
     */
    public function planificar(string $fechaDesde, string $fechaHasta, ?int $salaId = null): array
    {
        $flashes = FlashCrown::query()
            ->with(['sala:id,codigo'])
            ->whereDate('fecha', '>=', $fechaDesde)
            ->whereDate('fecha', '<=', $fechaHasta)
            ->where('asiento_mesa_id', '>', 0)
            ->when($salaId !== null && $salaId > 0, fn ($q) => $q->where('sala_id', $salaId))
            ->orderBy('fecha')
            ->orderBy('sala_id')
            ->get();

        $planes = [];
        $sinCcMesa = [];
        $cfgPorSala = [];

        foreach ($flashes as $flash) {
            $sid = (int) $flash->sala_id;
            if (! array_key_exists($sid, $cfgPorSala)) {
                $cfgPorSala[$sid] = FacturacionJuegoConfigSupport::salaPorSalaId($sid);
            }
            $cfg = $cfgPorSala[$sid];
            $ccMesa = (int) ($cfg?->centrocosto_mesa_id ?? 0);
            $ccSala = (int) ($cfg?->centrocosto_id ?? 0);
            $codigoSala = (string) ($flash->sala->codigo ?? $sid);

            if ($ccMesa <= 0) {
                $sinCcMesa[$codigoSala] = $codigoSala;

                continue;
            }
            if ($ccSala <= 0 || $ccSala === $ccMesa) {
                continue;
            }

            $movimientos = Asiento_Movimiento::query()
                ->where('asiento_id', (int) $flash->asiento_mesa_id)
                ->orderBy('id')
                ->get(['id', 'asiento_id', 'cuentacontable_id', 'centrocosto_id', 'monto']);

            $aCorregir = [];
            foreach ($movimientos as $mov) {
                if ((int) ($mov->centrocosto_id ?? 0) !== $ccSala) {
                    continue;
                }
                $aCorregir[] = [
                    'id' => (int) $mov->id,
                    'cuentacontable_id' => (int) $mov->cuentacontable_id,
                    'monto' => (float) $mov->monto,
                ];
            }
            if ($aCorregir === []) {
                continue;
            }

            $empresaId = (int) $flash->empresa_id;
            $fecha = substr((string) $flash->fecha, 0, 10);

            $planes[] = [
                'flash_id' => (int) $flash->id,
                'fecha' => $fecha,
                'sala_id' => $sid,
                'sala_codigo' => $codigoSala,
                'empresa_id' => $empresaId,
                'asiento_id' => (int) $flash->asiento_mesa_id,
                'cc_actual' => $ccSala,
                'cc_nuevo' => $ccMesa,
                'movimientos' => $aCorregir,
                'periodo_cerrado' => PeriodoContableCierreSupport::fechaEnPeriodoCerrado(
                    $empresaId,
                    $fecha,
                    PeriodoContableCierreSupport::MODULO_VENTAS,
                ),
            ];
        }

        return [
            'planes' => $planes,
            'sin_cc_mesa' => array_values($sinCcMesa),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $planes
     * @return array{asientos:int, movimientos:int, errores:list<string>}
     */
    public function ejecutar(array $planes): array
    {
        $asientos = 0;
        $movimientos = 0;
        $errores = [];

        foreach ($planes as $plan) {
            try {
                $n = DB::transaction(function () use ($plan) {
                    $cambiados = 0;
                    foreach ($plan['movimientos'] as $m) {
                        /** @var Asiento_Movimiento|null $mov */
                        $mov = Asiento_Movimiento::query()
                            ->whereKey((int) $m['id'])
                            ->lockForUpdate()
                            ->first();
                        // Si cambió desde el dry-run, no tocar.
                        if ($mov === null || (int) ($mov->centrocosto_id ?? 0) !== (int) $plan['cc_actual']) {
                            continue;
                        }
                        $mov->centrocosto_id = (int) $plan['cc_nuevo'];
                        $mov->save();
                        $cambiados++;
                    }

                    return $cambiados;
                });
                if ($n > 0) {
                    $asientos++;
                    $movimientos += $n;
                }
            } catch (\Throwable $e) {
                $errores[] = 'Asiento #'.($plan['asiento_id'] ?? '?').': '.$e->getMessage();
            }
        }

        return compact('asientos', 'movimientos', 'errores');
    }
}
