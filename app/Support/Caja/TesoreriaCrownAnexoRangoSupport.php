<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rango de fechas para Anexo I / VIII solo desde el menú Anexos
 * (no desde el escritorio de la jornada).
 */
final class TesoreriaCrownAnexoRangoSupport
{
    public const MAX_DIAS = 62;

    /** @var list<string> */
    public const PANELES = ['anexo', 'anexo8'];

    public static function panelAdmiteRango(string $panel): bool
    {
        return in_array($panel, self::PANELES, true);
    }

    /**
     * @return array{fecha_desde: string|null, fecha_hasta: string|null}
     */
    public static function resolverFechas(Request $request): array
    {
        $desde = self::normalizarFecha($request->input('fecha_desde'));
        $hasta = self::normalizarFecha($request->input('fecha_hasta'));

        if ($desde === null && $hasta === null) {
            $unica = self::normalizarFecha($request->input('fecha'));
            if ($unica !== null) {
                return ['fecha_desde' => $unica, 'fecha_hasta' => $unica];
            }

            return ['fecha_desde' => null, 'fecha_hasta' => null];
        }

        if ($desde === null) {
            $desde = $hasta;
        }
        if ($hasta === null) {
            $hasta = $desde;
        }
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return ['fecha_desde' => $desde, 'fecha_hasta' => $hasta];
    }

    public static function tieneRangoEnRequest(Request $request): bool
    {
        $fechas = self::resolverFechas($request);

        return $fechas['fecha_desde'] !== null && $fechas['fecha_hasta'] !== null;
    }

    /**
     * @return Collection<int, TesoreriaCrownJornada>
     */
    public static function jornadasEnRango(int $salaId, string $desdeYmd, string $hastaYmd): Collection
    {
        if ($salaId <= 0) {
            return collect();
        }

        $desde = Carbon::parse($desdeYmd)->startOfDay();
        $hasta = Carbon::parse($hastaYmd)->startOfDay();
        if ($desde->diffInDays($hasta) + 1 > self::MAX_DIAS) {
            $hasta = $desde->copy()->addDays(self::MAX_DIAS - 1);
        }

        return TesoreriaCrownJornada::query()
            ->with('empresa')
            ->where('sala_id', $salaId)
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->orderBy('fecha')
            ->get();
    }

    /**
     * Querystring del menú Anexos (conserva rango en tabs / export).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function paraQueryString(
        ?int $salaId,
        ?string $fechaDesde,
        ?string $fechaHasta,
        array $extra = []
    ): array {
        return array_filter(array_merge([
            'sala_id' => $salaId,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
        ], $extra), static fn ($v) => $v !== null && $v !== '');
    }

    public static function subtituloRango(string $desdeYmd, string $hastaYmd, int $cantidadJornadas): string
    {
        $desde = Carbon::parse($desdeYmd)->format('d/m/Y');
        $hasta = Carbon::parse($hastaYmd)->format('d/m/Y');
        if ($desdeYmd === $hastaYmd) {
            return $desde.' · '.$cantidadJornadas.' jornada'.($cantidadJornadas === 1 ? '' : 's');
        }

        return $desde.' al '.$hasta.' · '.$cantidadJornadas.' jornada'.($cantidadJornadas === 1 ? '' : 's');
    }

    private static function normalizarFecha(mixed $valor): ?string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        try {
            return Carbon::parse($texto)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
