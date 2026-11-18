<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Resuelve la jornada en pantallas fuera del escritorio (Mesas, Bóveda, Slots, …).
 * Prioridad: jornada_id → fecha → abierta → última.
 */
final class TesoreriaCrownJornadaConsultaSupport
{
    public static function pedida(Request $request, int $salaId): ?TesoreriaCrownJornada
    {
        if ($salaId <= 0) {
            return null;
        }
        $id = (int) $request->input('jornada_id', 0);
        if ($id > 0) {
            $jornada = TesoreriaCrownJornada::query()->whereKey($id)->where('sala_id', $salaId)->first();
            if ($jornada === null) {
                abort(404);
            }

            return $jornada;
        }
        $fecha = trim((string) $request->input('fecha', ''));
        if ($fecha !== '') {
            return TesoreriaCrownJornada::query()
                ->where('sala_id', $salaId)
                ->whereDate('fecha', $fecha)
                ->first();
        }

        return TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->where('estado', TesoreriaCrownJornada::ABIERTA)
            ->first()
            ?? TesoreriaCrownJornada::query()->where('sala_id', $salaId)->orderByDesc('fecha')->first();
    }

    /** Máxima fecha de jornada ya cargada en la sala (Y-m-d) o null. */
    public static function ultimaFechaSala(int $salaId): ?string
    {
        if ($salaId <= 0) {
            return null;
        }
        $max = TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->max('fecha');
        if ($max === null || $max === '') {
            return null;
        }

        return Carbon::parse($max)->format('Y-m-d');
    }

    /**
     * Primera fecha que se puede abrir después de la última jornada (Y-m-d),
     * o null si todavía no hay jornadas.
     */
    public static function proximaFechaDisponible(int $salaId): ?string
    {
        $ultima = self::ultimaFechaSala($salaId);
        if ($ultima === null) {
            return null;
        }

        return Carbon::parse($ultima)->addDay()->format('Y-m-d');
    }

    /**
     * Sin permiso de salto: solo última+1.
     * Con `abrir-tesoreria-crown-jornada-salto`: cualquier fecha >= última+1 y <= hoy.
     * Nunca hacia atrás.
     *
     * @return string|null mensaje de error o null si OK
     */
    public static function validarFechaApertura(int $salaId, string $fechaYmd, bool $puedeSalto): ?string
    {
        $hoy = now()->toDateString();
        if ($fechaYmd > $hoy) {
            return 'No se puede abrir una jornada con fecha futura.';
        }

        $ultima = self::ultimaFechaSala($salaId);
        if ($ultima === null) {
            return null;
        }
        if ($fechaYmd < $ultima) {
            return 'No se puede abrir una fecha anterior a la última jornada ('
                .Carbon::parse($ultima)->format('d/m/Y')
                .').';
        }

        $proxima = self::proximaFechaDisponible($salaId);
        if ($proxima === null) {
            return null;
        }
        if ($fechaYmd === $proxima) {
            return null;
        }
        if ($fechaYmd > $proxima) {
            if ($puedeSalto) {
                return null;
            }

            return 'Solo se puede abrir el día siguiente a la última jornada ('
                .Carbon::parse($proxima)->format('d/m/Y')
                .'). Para saltar huecos hace falta el permiso de abrir con salto.';
        }

        return null;
    }
}
