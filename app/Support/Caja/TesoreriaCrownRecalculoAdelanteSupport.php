<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownPoderPublico;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Recálculo hacia adelante de lo que tesorería Crown deja grabado y arrastra de un día al otro.
 *
 * Un cambio en una jornada (bóveda, rendición, mesa, slot, stock, resultado on-line, bingo,
 * propina) la marca con `recalculo_pendiente`. Antes de leer un anexo se aplica lo pendiente
 * desde la jornada marcada más vieja:
 * - `caja_final_viii` (es la caja inicial del día siguiente) y `caja_final_dia` / `caja_final_mes`
 *   quedan en null y se recalculan al leerlos.
 * - Poder público: se regenera la cadena día → mes → acumulado. Las filas tomadas de Anita
 *   (`origen = anita`) no se pisan: la cadena sigue desde ellas.
 */
final class TesoreriaCrownRecalculoAdelanteSupport
{
    private static bool $aplicando = false;

    /** @var array<int, true> jornadas ya marcadas en este request */
    private static array $marcadas = [];

    /** @var array<int, true> salas ya verificadas sin pendientes en este request */
    private static array $verificadas = [];

    private static ?bool $columnaLista = null;

    public static function marcar(?int $jornadaId): void
    {
        if ($jornadaId === null || $jornadaId <= 0 || self::$aplicando || ! self::columnaLista()) {
            return;
        }
        if (isset(self::$marcadas[$jornadaId])) {
            return;
        }
        DB::table('tesoreria_crown_jornada')
            ->where('id', $jornadaId)
            ->update(['recalculo_pendiente' => true, 'caja_final_viii' => null]);
        self::$marcadas[$jornadaId] = true;
        self::$verificadas = [];
    }

    public static function marcarSalaFecha(?int $salaId, ?string $fechaYmd): void
    {
        if ($salaId === null || $salaId <= 0 || $fechaYmd === null || $fechaYmd === '' || ! self::columnaLista()) {
            return;
        }
        $id = DB::table('tesoreria_crown_jornada')
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fechaYmd)
            ->value('id');
        self::marcar($id !== null ? (int) $id : null);
    }

    /**
     * Aplica lo pendiente de la sala. Devuelve la fecha desde la que recalculó (null si no había nada).
     */
    public static function aplicarPendientes(int $salaId): ?string
    {
        if ($salaId <= 0 || self::$aplicando || isset(self::$verificadas[$salaId]) || ! self::columnaLista()) {
            return null;
        }
        if (! self::hayPendientes($salaId)) {
            self::$verificadas[$salaId] = true;

            return null;
        }

        try {
            return Cache::lock('tc-recalculo-adelante:'.$salaId, 300)->block(60, fn () => self::aplicar($salaId));
        } catch (LockTimeoutException) {
            return null;
        }
    }

    /**
     * Aplica lo pendiente de la sala de la jornada y, si quedó dentro del tramo recalculado,
     * actualiza los caches en memoria del modelo (sin tocar otros atributos sin guardar).
     */
    public static function asegurar(TesoreriaCrownJornada $jornada): void
    {
        $desde = self::aplicarPendientes((int) $jornada->sala_id);
        if ($desde === null || $jornada->fecha === null || $jornada->fecha->format('Y-m-d') < $desde) {
            return;
        }
        $limpios = [
            'caja_final_viii' => null,
            'caja_final_dia' => null,
            'caja_final_mes' => null,
            'recalculo_pendiente' => false,
        ];
        $jornada->forceFill($limpios)->syncOriginalAttributes(array_keys($limpios));
    }

    public static function hayPendientes(int $salaId): bool
    {
        return self::columnaLista()
            && DB::table('tesoreria_crown_jornada')
                ->where('sala_id', $salaId)
                ->where('recalculo_pendiente', true)
                ->exists();
    }

    private static function aplicar(int $salaId): ?string
    {
        $desde = DB::table('tesoreria_crown_jornada')
            ->where('sala_id', $salaId)
            ->where('recalculo_pendiente', true)
            ->min('fecha');
        if ($desde === null) {
            self::$verificadas[$salaId] = true;

            return null;
        }
        $desde = substr((string) $desde, 0, 10);

        self::$aplicando = true;
        try {
            DB::table('tesoreria_crown_jornada')
                ->where('sala_id', $salaId)
                ->whereDate('fecha', '>=', $desde)
                ->update([
                    'recalculo_pendiente' => false,
                    'caja_final_viii' => null,
                    'caja_final_dia' => null,
                    'caja_final_mes' => null,
                ]);

            $fechas = TesoreriaCrownPoderPublico::query()
                ->where('sala_id', $salaId)
                ->whereDate('fecha', '>=', $desde)
                ->orderBy('fecha')
                ->get(['fecha', 'origen']);
            foreach ($fechas as $fila) {
                if ((string) $fila->origen === TesoreriaCrownPoderPublico::ORIGEN_ANITA) {
                    continue;
                }
                TesoreriaCrownPoderPublicoGenerador::generar($salaId, $fila->fecha->format('Y-m-d'));
            }
        } catch (Throwable $e) {
            DB::table('tesoreria_crown_jornada')
                ->where('sala_id', $salaId)
                ->whereDate('fecha', $desde)
                ->update(['recalculo_pendiente' => true]);
            Log::warning('Tesorería Crown: falló el recálculo hacia adelante', [
                'sala_id' => $salaId,
                'desde' => $desde,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            self::$aplicando = false;
            self::$marcadas = [];
        }
        self::$verificadas[$salaId] = true;

        return $desde;
    }

    private static function columnaLista(): bool
    {
        return self::$columnaLista ??= Schema::hasColumn('tesoreria_crown_jornada', 'recalculo_pendiente');
    }
}
