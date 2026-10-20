<?php

namespace App\Support\Caja\RendicionMaquina;

use App\Models\Caja\Flash\FlashCaja;
use Carbon\Carbon;

/**
 * Win online del flash del día (win_ol_slot + win_ol_rul).
 * Es el mismo importe que el cierre contable de máquinas imputa a ventas.
 */
final class RendicionMaquinaWinOnlineFlashSupport
{
    /**
     * @return array{hay_flash: bool, validado: bool, slots: float, ruletas: float, total: float}
     */
    public static function vacio(): array
    {
        return [
            'hay_flash' => false,
            'validado' => false,
            'slots' => 0.0,
            'ruletas' => 0.0,
            'total' => 0.0,
        ];
    }

    /**
     * @return array{hay_flash: bool, validado: bool, slots: float, ruletas: float, total: float}
     */
    public static function leer(int $empresaId, string $fecha): array
    {
        if ($empresaId <= 0 || trim($fecha) === '') {
            return self::vacio();
        }

        $fechaYmd = Carbon::parse($fecha)->toDateString();
        $flash = FlashCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', $fechaYmd)
            ->first(['win_ol_slot', 'win_ol_rul', 'validado']);

        if ($flash === null) {
            return self::vacio();
        }

        return self::desdeFlash($flash);
    }

    /**
     * @param  list<array<string, mixed>>  $grupos
     * @return list<array<string, mixed>>
     */
    public static function adjuntarAGrupos(array $grupos): array
    {
        /** @var array<int, array<string, true>> $porEmpresa */
        $porEmpresa = [];
        foreach ($grupos as $grupo) {
            $empresaId = (int) ($grupo['empresa_id'] ?? 0);
            $fecha = (string) ($grupo['fecha_dia'] ?? '');
            if ($empresaId > 0 && $fecha !== '') {
                $porEmpresa[$empresaId][$fecha] = true;
            }
        }

        /** @var array<int, array<string, array{hay_flash: bool, validado: bool, slots: float, ruletas: float, total: float}>> $mapas */
        $mapas = [];
        foreach ($porEmpresa as $empresaId => $fechas) {
            $claves = array_keys($fechas);
            sort($claves);
            $mapas[$empresaId] = self::mapaPorRango(
                (int) $empresaId,
                $claves[0],
                $claves[array_key_last($claves)],
            );
        }

        foreach ($grupos as &$grupo) {
            $empresaId = (int) ($grupo['empresa_id'] ?? 0);
            $fecha = (string) ($grupo['fecha_dia'] ?? '');
            $fila = $mapas[$empresaId][$fecha] ?? self::vacio();
            $grupo['win_online_slots'] = $fila['slots'];
            $grupo['win_online_ruletas'] = $fila['ruletas'];
            $grupo['win_online_total'] = $fila['total'];
            $grupo['win_online_hay_flash'] = $fila['hay_flash'];
        }
        unset($grupo);

        return $grupos;
    }

    /**
     * @return array<string, array{hay_flash: bool, validado: bool, slots: float, ruletas: float, total: float}>
     */
    private static function mapaPorRango(int $empresaId, string $desde, string $hasta): array
    {
        if ($empresaId <= 0 || $desde === '' || $hasta === '') {
            return [];
        }

        $out = [];
        FlashCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->get(['fecha', 'win_ol_slot', 'win_ol_rul', 'validado'])
            ->each(function (FlashCaja $flash) use (&$out) {
                $fecha = $flash->fecha?->format('Y-m-d');
                if ($fecha === null || $fecha === '') {
                    return;
                }
                $out[$fecha] = self::desdeFlash($flash);
            });

        return $out;
    }

    /**
     * @return array{hay_flash: bool, validado: bool, slots: float, ruletas: float, total: float}
     */
    private static function desdeFlash(FlashCaja $flash): array
    {
        $slots = round((float) $flash->win_ol_slot, 2);
        $ruletas = round((float) $flash->win_ol_rul, 2);

        return [
            'hay_flash' => true,
            'validado' => (bool) ($flash->validado ?? false),
            'slots' => $slots,
            'ruletas' => $ruletas,
            'total' => round($slots + $ruletas, 2),
        ];
    }
}
