<?php

namespace App\Support\Configuracion;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consulta el padrón AFIP de sujetos no alcanzados por percepción de IVA.
 */
final class ExclusionPercepcionIvaSupport
{
    /** @var array<string, list<array{desde: string, hasta: string|null}>> */
    private static array $rangosPorCuit = [];

    public static function normalizarCuit(mixed $cuit): string
    {
        return preg_replace('/\D+/', '', (string) ($cuit ?? '')) ?? '';
    }

    /**
     * True si el CUIT tiene vigencia de exclusión que cubre la fecha del comprobante.
     */
    public static function estaExcluidoEnFecha(mixed $cuit, mixed $fecha = null): bool
    {
        $cuitNorm = self::normalizarCuit($cuit);
        if ($cuitNorm === '' || strlen($cuitNorm) < 10) {
            return false;
        }

        $fechaYmd = self::normalizarFecha($fecha) ?? Carbon::today()->format('Y-m-d');

        foreach (self::rangosDelCuit($cuitNorm) as $rango) {
            if ($rango['desde'] > $fechaYmd) {
                continue;
            }
            if ($rango['hasta'] === null || $rango['hasta'] >= $fechaYmd) {
                return true;
            }
        }

        return false;
    }

    /**
     * Limpia el cache de request (útil en tests o imports largos).
     */
    public static function resetCache(): void
    {
        self::$rangosPorCuit = [];
    }

    /**
     * @return list<array{desde: string, hasta: string|null}>
     */
    private static function rangosDelCuit(string $cuitNorm): array
    {
        if (array_key_exists($cuitNorm, self::$rangosPorCuit)) {
            return self::$rangosPorCuit[$cuitNorm];
        }

        $filas = DB::table('padron_exclusionpercepcioniva')
            ->select(['desdefecha', 'hastafecha'])
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(cuit, '-', ''), '.', ''), ' ', '') = ?",
                [$cuitNorm]
            )
            ->get();

        $rangos = [];
        foreach ($filas as $fila) {
            $desde = self::normalizarFecha($fila->desdefecha);
            if ($desde === null) {
                continue;
            }
            $rangos[] = [
                'desde' => $desde,
                'hasta' => self::normalizarFecha($fila->hastafecha),
            ];
        }

        self::$rangosPorCuit[$cuitNorm] = $rangos;

        return $rangos;
    }

    private static function normalizarFecha(mixed $fecha): ?string
    {
        if ($fecha === null || $fecha === '' || $fecha === '0000-00-00') {
            return null;
        }

        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $fecha)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
