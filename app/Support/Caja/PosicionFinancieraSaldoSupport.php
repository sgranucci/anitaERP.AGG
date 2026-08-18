<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Models\Caja\PosicionFinancieraSaldo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PosicionFinancieraSaldoSupport
{
    public static function ultimoConfirmadoAnterior(int $empresaId, string $fechaExclusiva): ?PosicionFinancieraSaldo
    {
        if ($empresaId <= 0 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaExclusiva)) {
            return null;
        }

        return PosicionFinancieraSaldo::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_cierre', '<', $fechaExclusiva)
            ->whereNull('anulado_at')
            ->orderByDesc('fecha_cierre')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function confirmar(
        int $empresaId,
        Carbon $fechaCierre,
        float $saldoInicial,
        float $saldoFinal,
        array $filtros,
        int $usuarioId,
    ): PosicionFinancieraSaldo {
        if ($empresaId <= 0 || $usuarioId <= 0) {
            throw new InvalidArgumentException('Empresa o usuario inválido para confirmar el saldo.');
        }
        if (! $fechaCierre->isLastOfMonth()) {
            throw new InvalidArgumentException('El cierre debe corresponder al último día del mes.');
        }
        if (! $fechaCierre->isBefore(Carbon::today())) {
            throw new InvalidArgumentException('Solo se puede confirmar un período finalizado.');
        }

        return DB::transaction(function () use (
            $empresaId,
            $fechaCierre,
            $saldoInicial,
            $saldoFinal,
            $filtros,
            $usuarioId,
        ) {
            $existe = PosicionFinancieraSaldo::query()
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha_cierre', $fechaCierre->toDateString())
                ->whereNull('anulado_at')
                ->lockForUpdate()
                ->exists();

            if ($existe) {
                throw new InvalidArgumentException('El período ya tiene un saldo final confirmado.');
            }

            return PosicionFinancieraSaldo::query()->create([
                'empresa_id' => $empresaId,
                'fecha_cierre' => $fechaCierre->toDateString(),
                'saldo_inicial' => round($saldoInicial, 2),
                'saldo_final' => round($saldoFinal, 2),
                'origen' => 'calculado_erp',
                'filtros_json' => $filtros,
                'confirmado_por' => $usuarioId,
                'confirmado_at' => now(),
            ]);
        });
    }

    public static function anular(int $id, int $usuarioId, string $motivo): PosicionFinancieraSaldo
    {
        $motivo = trim($motivo);
        if ($usuarioId <= 0 || $motivo === '') {
            throw new InvalidArgumentException('Debe indicar el motivo de anulación.');
        }

        return DB::transaction(function () use ($id, $usuarioId, $motivo) {
            $saldo = PosicionFinancieraSaldo::query()->lockForUpdate()->findOrFail($id);
            if ($saldo->anulado_at !== null) {
                throw new InvalidArgumentException('El saldo ya está anulado.');
            }

            $saldo->update([
                'anulado_por' => $usuarioId,
                'anulado_at' => now(),
                'motivo_anulacion' => mb_substr($motivo, 0, 255),
            ]);

            return $saldo->fresh();
        });
    }
}
