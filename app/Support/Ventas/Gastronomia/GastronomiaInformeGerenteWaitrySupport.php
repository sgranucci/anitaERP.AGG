<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Puntoventa;

/**
 * Suplemento Waitry en informe gerente: cobros pagados en Waitry aún no facturados en Anita.
 */
final class GastronomiaInformeGerenteWaitrySupport
{
    /**
     * Suma el monto Waitry al PV de proceso de cierre (o lo agrega como fila si no estaba en la grilla).
     *
     * @param  list<array{
     *   puntoventa_id:int,
     *   codigo:string,
     *   nombre:string,
     *   total:float,
     *   total_facturas:float,
     *   cantidad_facturas:int,
     *   cantidad_notas_credito:int,
     *   waitry_sin_facturar?:float,
     *   cantidad_waitry_sin_facturar?:int
     * }>  $filas
     * @param  array{
     *   total:float,
     *   cantidad_ordenes:int,
     *   puntoventa_id:int,
     *   codigo:string,
     *   nombre:string
     * }  $waitry
     * @return list<array<string, mixed>>
     */
    public static function aplicarAVentasPorPuntoventa(array $filas, array $waitry): array
    {
        $monto = round((float) ($waitry['total'] ?? 0), 2);
        if ($monto <= 0.0001) {
            return $filas;
        }

        $pvId = (int) ($waitry['puntoventa_id'] ?? 0);
        if ($pvId <= 0) {
            $pvId = self::resolverPuntoventaIdFallback((int) ($waitry['empresa_id'] ?? 0));
        }
        if ($pvId <= 0) {
            return $filas;
        }

        $encontrado = false;
        foreach ($filas as $i => $fila) {
            if ((int) ($fila['puntoventa_id'] ?? 0) !== $pvId) {
                continue;
            }
            $filas[$i]['total'] = round((float) ($fila['total'] ?? 0) + $monto, 2);
            $filas[$i]['waitry_sin_facturar'] = $monto;
            $filas[$i]['cantidad_waitry_sin_facturar'] = (int) ($waitry['cantidad_ordenes'] ?? 0);
            $encontrado = true;

            break;
        }

        if (! $encontrado) {
            $pv = Puntoventa::query()->find($pvId, ['id', 'codigo', 'nombre']);
            $codigo = trim((string) ($waitry['codigo'] ?? $pv?->codigo ?? ''));
            $nombre = trim((string) ($waitry['nombre'] ?? $pv?->nombre ?? ''));
            $filas[] = [
                'puntoventa_id' => $pvId,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'total' => $monto,
                'total_facturas' => 0.0,
                'cantidad_facturas' => 0,
                'cantidad_notas_credito' => 0,
                'waitry_sin_facturar' => $monto,
                'cantidad_waitry_sin_facturar' => (int) ($waitry['cantidad_ordenes'] ?? 0),
            ];
        }

        usort($filas, fn ($a, $b) => ($b['total'] <=> $a['total']));

        return $filas;
    }

    private static function resolverPuntoventaIdFallback(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        $caeaId = ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('waitry_habilitado', true)
            ->whereNotNull('puntoventa_caea_id')
            ->value('puntoventa_caea_id');

        return (int) ($caeaId ?? 0);
    }
}
