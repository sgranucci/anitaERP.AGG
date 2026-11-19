<?php

declare(strict_types=1);

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Contable\ConciliacionBancariaEjecucion;
use App\Support\Contable\PeriodoContableCierreSupport;
use Carbon\Carbon;
use RuntimeException;

/**
 * Lock operativo de conciliación:
 * - Cruce de pares / importar extracto: solo estado local (cerrado/aprobado).
 * - Agenda PeriodoCierreContable (alcance interbanking): para operaciones que
 *   contabilizan, p. ej. generar egreso/ICO de gastos bancarios.
 */
final class ConciliacionBancariaPeriodoLockSupport
{
    public static function assertMesOperable(int $empresaId, int $mes, int $anio, ?int $usuarioId = null): void
    {
        $desde = Carbon::createFromDate($anio, $mes, 1)->startOfDay();
        $hasta = $desde->copy()->endOfMonth();

        PeriodoContableCierreSupport::assertRangoOperacionPermitido(
            $empresaId,
            $desde->toDateString(),
            $hasta->toDateString(),
            PeriodoContableCierreSupport::ALCANCE_INTERBANKING,
            $usuarioId,
        );
    }

    public static function assertFechaOperable(int $empresaId, string $fechaYmd, ?int $usuarioId = null): void
    {
        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fechaYmd,
            PeriodoContableCierreSupport::ALCANCE_INTERBANKING,
            $usuarioId,
        );
    }

    /**
     * Bloquea altas/bajas de pares si el período local de conciliación está cerrado/aprobado.
     * No consulta la agenda de cierre contable: conciliar no genera asientos.
     */
    public static function assertEditable(
        int $empresaId,
        int $cuentacajaId,
        int $mes,
        int $anio,
        ?int $usuarioId = null,
    ): void {
        unset($usuarioId);

        $ejec = ConciliacionBancariaEjecucion::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacaja_id', $cuentacajaId)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->orderByDesc('id')
            ->first();

        $estado = (string) ($ejec->estado ?? 'borrador');
        if (in_array($estado, ['cerrado', 'aprobado'], true)) {
            throw new RuntimeException(
                'El período de conciliación está '.$estado.'. Reabrilo antes de conciliar o importar.'
            );
        }
    }

    public static function estaMesCerradoEnAgenda(int $empresaId, int $mes, int $anio): bool
    {
        $hasta = Carbon::createFromDate($anio, $mes, 1)->endOfMonth()->toDateString();

        return PeriodoContableCierreSupport::fechaEnPeriodoCerrado(
            $empresaId,
            $hasta,
            PeriodoContableCierreSupport::ALCANCE_INTERBANKING,
        );
    }
}
