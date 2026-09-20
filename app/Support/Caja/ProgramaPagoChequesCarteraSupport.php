<?php

namespace App\Support\Caja;

use Carbon\Carbon;

/**
 * Cheques en cartera (CHT libre) agrupados por mes del programa de pagos Ferli.
 */
final class ProgramaPagoChequesCarteraSupport
{
    /**
     * @param  list<array{clave: string, etiqueta: string, anio_mes: ?string}>  $columnas
     * @return array{por_clave: array<string, float>, total: float}
     */
    public static function montosPorColumna(int $empresaId, array $columnas): array
    {
        $porClave = [];
        foreach ($columnas as $col) {
            if (($col['anio_mes'] ?? null) === null) {
                continue;
            }
            $porClave[$col['clave']] = 0.0;
        }

        if ($porClave === []) {
            return ['por_clave' => [], 'total' => 0.0];
        }

        $claves = array_keys($porClave);
        sort($claves);
        $desde = Carbon::createFromFormat('Y-m', $claves[0])->startOfMonth()->toDateString();
        $hasta = Carbon::createFromFormat('Y-m', $claves[count($claves) - 1])->endOfMonth()->toDateString();

        $query = ProgramaPagoAsignarChequesSupport::queryCarteraDisponible($empresaId)
            ->whereBetween('fechapago', [$desde, $hasta]);

        foreach ($query->get(['fechapago', 'monto']) as $cheque) {
            $clave = Carbon::parse((string) $cheque->fechapago)->format('Y-m');
            if (! array_key_exists($clave, $porClave)) {
                continue;
            }
            $porClave[$clave] += (float) $cheque->monto;
        }

        foreach ($porClave as $k => $v) {
            $porClave[$k] = round($v, 2);
        }

        return [
            'por_clave' => $porClave,
            'total' => round(array_sum($porClave), 2),
        ];
    }
}
