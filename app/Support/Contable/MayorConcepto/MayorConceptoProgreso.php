<?php

namespace App\Support\Contable\MayorConcepto;

use Illuminate\Support\Facades\Cache;

/**
 * Texto de avance del mayor por concepto, leído por un GET liviano mientras
 * el POST de generación sigue en curso.
 */
class MayorConceptoProgreso
{
    /** Mayor que anita.bridge_timeout (300 s) para no soltar la reserva a mitad de una lectura. */
    private const RESERVA_SEGUNDOS = 420;

    private static bool $activo = false;

    public static function activar(): void
    {
        self::$activo = true;
    }

    public static function marcar(string $mensaje): void
    {
        if (! self::$activo) {
            return;
        }

        $userId = (int) (auth()->id() ?? 0);
        if ($userId <= 0 || $mensaje === '') {
            return;
        }

        Cache::store('file')->put(self::clave($userId), [
            'mensaje' => $mensaje,
            'ts' => time(),
        ], now()->addMinutes(30));
        self::renovarReserva($userId);
    }

    /**
     * Una sola generación por usuario. La reserva se renueva con cada avance
     * y cubre una lectura Anita (timeout del bridge, 300 s).
     */
    public static function reservar(): bool
    {
        $userId = (int) (auth()->id() ?? 0);
        if ($userId <= 0) {
            return true;
        }

        return Cache::store('file')->add(self::claveRun($userId), time(), now()->addSeconds(self::RESERVA_SEGUNDOS));
    }

    public static function liberar(): void
    {
        $userId = (int) (auth()->id() ?? 0);
        if ($userId <= 0) {
            return;
        }

        Cache::store('file')->forget(self::claveRun($userId));
    }

    /**
     * @return array{mensaje: string, ts: int}|null
     */
    public static function leer(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $data = Cache::store('file')->get(self::clave($userId));
        if (! is_array($data)) {
            return null;
        }

        return [
            'mensaje' => (string) ($data['mensaje'] ?? ''),
            'ts' => (int) ($data['ts'] ?? 0),
        ];
    }

    public static function olvidar(?int $userId = null): void
    {
        $userId = $userId ?? (int) (auth()->id() ?? 0);
        if ($userId <= 0) {
            return;
        }

        Cache::store('file')->forget(self::clave($userId));
    }

    private static function renovarReserva(int $userId): void
    {
        Cache::store('file')->put(self::claveRun($userId), time(), now()->addSeconds(self::RESERVA_SEGUNDOS));
    }

    private static function clave(int $userId): string
    {
        return 'mayor_concepto_ui_progreso_'.$userId;
    }

    private static function claveRun(int $userId): string
    {
        return 'mayor_concepto_run_'.$userId;
    }
}
