<?php

namespace App\Support\Sueldos;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vigencia laboral a una fecha: activo a hoy vs dado de baja.
 *
 * Un empleado con estado Baja y fecha_egreso futura sigue vigente hasta esa fecha.
 */
class EmpleadoVigenciaSupport
{
    public const FILTRO_ACTIVOS = 'activos';

    public const FILTRO_BAJAS = 'bajas';

    public const FILTRO_TODOS = 'todos';

    /**
     * @return list<string>
     */
    public static function filtros(): array
    {
        return [self::FILTRO_ACTIVOS, self::FILTRO_BAJAS, self::FILTRO_TODOS];
    }

    public static function normalizar(?string $filtro, string $default = self::FILTRO_ACTIVOS): string
    {
        $valor = strtolower(trim((string) $filtro));

        return in_array($valor, self::filtros(), true) ? $valor : $default;
    }

    public static function fechaReferencia(DateTimeInterface|string|null $fecha = null): string
    {
        if ($fecha instanceof DateTimeInterface) {
            return Carbon::parse($fecha)->toDateString();
        }

        $texto = trim((string) $fecha);

        return $texto !== ''
            ? Carbon::parse($texto)->toDateString()
            : Carbon::today()->toDateString();
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function aplicar(
        Builder $query,
        ?string $filtro,
        DateTimeInterface|string|null $fecha = null,
        string $tabla = '',
    ): Builder {
        $filtro = self::normalizar($filtro);
        if ($filtro === self::FILTRO_TODOS) {
            return $query;
        }

        $hasta = self::fechaReferencia($fecha);
        $colEstado = self::columna($tabla, 'estado');
        $colEgreso = self::columna($tabla, 'fecha_egreso');

        if ($filtro === self::FILTRO_BAJAS) {
            return $query
                ->where($colEstado, EmpleadoEstados::BAJA)
                ->whereNotNull($colEgreso)
                ->whereDate($colEgreso, '<=', $hasta);
        }

        return $query->where(function ($qq) use ($hasta, $colEstado, $colEgreso) {
            $qq->whereNull($colEstado)
                ->orWhere($colEstado, '!=', EmpleadoEstados::BAJA)
                ->orWhere(function ($q2) use ($hasta, $colEstado, $colEgreso) {
                    $q2->where($colEstado, EmpleadoEstados::BAJA)
                        ->whereDate($colEgreso, '>', $hasta);
                });
        });
    }

    private static function columna(string $tabla, string $campo): string
    {
        return $tabla !== '' ? $tabla.'.'.$campo : $campo;
    }
}
