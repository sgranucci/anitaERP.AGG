<?php

declare(strict_types=1);

namespace App\Support\Ventas\IvaVentas;

use App\Models\Ventas\Venta;
use App\Support\Ventas\IvaVentasListadoFiltros;
use Illuminate\Database\Eloquent\Builder;

/**
 * Detecta saltos de numeración de comprobantes por punto de venta y tipo de transacción.
 */
final class IvaVentasAuditoriaCorrelatividadSupport
{
    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function auditar(array $filas, array $filtros): array
    {
        if ($filas === []) {
            return self::vacio();
        }

        $grupos = [];
        foreach ($filas as $fila) {
            $pvId = (int) ($fila['puntoventa_id'] ?? 0);
            $tipoId = (int) ($fila['tipotransaccion_id'] ?? 0);
            $numero = (int) ($fila['numerocomprobante'] ?? 0);
            if ($pvId <= 0 || $tipoId <= 0 || $numero <= 0) {
                continue;
            }

            $clave = $pvId.'|'.$tipoId;
            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'puntoventa_id' => $pvId,
                    'puntoventa_codigo' => (string) ($fila['puntoventa_codigo'] ?? ''),
                    'puntoventa_nombre' => (string) ($fila['puntoventa_nombre'] ?? ''),
                    'tipotransaccion_id' => $tipoId,
                    'tipo' => (string) ($fila['tipo'] ?? ''),
                    'seccion_label' => (string) ($fila['seccion_label'] ?? ''),
                    'numeros' => [],
                    'numeros_map' => [],
                ];
            }

            $grupos[$clave]['numeros'][] = $numero;
            $grupos[$clave]['numeros_map'][$numero] = [
                'venta_id' => (int) ($fila['venta_id'] ?? 0),
                'comprobante' => (string) ($fila['comprobante'] ?? ''),
                'fecha_mov' => (string) ($fila['fecha_mov'] ?? ''),
            ];
        }

        if ($grupos === []) {
            return self::vacio();
        }

        $saltos = [];
        $totalSaltos = 0;
        $totalFaltantes = 0;

        foreach ($grupos as $grupo) {
            $numeros = array_values(array_unique($grupo['numeros']));
            sort($numeros, SORT_NUMERIC);

            if (count($numeros) < 2) {
                continue;
            }

            $faltantesEnPeriodo = [];
            $detalleSaltos = [];

            for ($i = 0, $len = count($numeros) - 1; $i < $len; $i++) {
                $actual = $numeros[$i];
                $siguiente = $numeros[$i + 1];
                if ($siguiente - $actual <= 1) {
                    continue;
                }

                $faltantes = [];
                for ($n = $actual + 1; $n < $siguiente; $n++) {
                    $faltantes[] = $n;
                }

                if ($faltantes === []) {
                    continue;
                }

                $faltantesEnPeriodo = array_merge($faltantesEnPeriodo, $faltantes);
                $detalleSaltos[] = [
                    'desde' => $actual,
                    'hasta' => $siguiente,
                    'faltantes' => $faltantes,
                    'comprobante_desde' => $grupo['numeros_map'][$actual]['comprobante'] ?? '',
                    'comprobante_hasta' => $grupo['numeros_map'][$siguiente]['comprobante'] ?? '',
                ];
            }

            if ($detalleSaltos === []) {
                continue;
            }

            $faltantesEnPeriodo = array_values(array_unique($faltantesEnPeriodo));
            sort($faltantesEnPeriodo, SORT_NUMERIC);
            $fueraPeriodo = self::numerosExistentesFueraPeriodo(
                (int) $grupo['puntoventa_id'],
                (int) $grupo['tipotransaccion_id'],
                $faltantesEnPeriodo,
                $filtros,
            );

            $totalSaltos += count($detalleSaltos);
            $totalFaltantes += count($faltantesEnPeriodo);

            $saltos[] = [
                'puntoventa_id' => (int) $grupo['puntoventa_id'],
                'puntoventa_codigo' => $grupo['puntoventa_codigo'],
                'puntoventa_nombre' => $grupo['puntoventa_nombre'],
                'tipotransaccion_id' => (int) $grupo['tipotransaccion_id'],
                'tipo' => $grupo['tipo'],
                'seccion_label' => $grupo['seccion_label'],
                'min_numero' => $numeros[0],
                'max_numero' => $numeros[count($numeros) - 1],
                'cantidad_periodo' => count($numeros),
                'saltos' => $detalleSaltos,
                'faltantes' => $faltantesEnPeriodo,
                'faltantes_fuera_periodo' => $fueraPeriodo,
                'faltantes_sin_registro' => array_values(array_diff(
                    $faltantesEnPeriodo,
                    array_keys($fueraPeriodo),
                )),
            ];
        }

        usort($saltos, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['puntoventa_codigo'] ?? ''), (string) ($b['puntoventa_codigo'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['tipo'] ?? ''), (string) ($b['tipo'] ?? ''));
        });

        return [
            'habilitada' => true,
            'grupos_con_saltos' => count($saltos),
            'total_saltos' => $totalSaltos,
            'total_faltantes' => $totalFaltantes,
            'grupos' => $saltos,
        ];
    }

    /**
     * @param  list<int>  $numeros
     * @param  array<string, mixed>  $filtros
     * @return array<int, string>
     */
    private static function numerosExistentesFueraPeriodo(int $puntoventaId, int $tipotransaccionId, array $numeros, array $filtros): array
    {
        if ($numeros === []) {
            return [];
        }

        $campoFecha = ($filtros['orden_fecha'] ?? IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA) === IvaVentasListadoFiltros::ORDEN_FECHA
            ? 'fecha'
            : 'fechajornada';
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');

        $out = [];
        foreach (array_chunk($numeros, 500) as $chunk) {
            $rows = Venta::query()
                ->where('puntoventa_id', $puntoventaId)
                ->where('tipotransaccion_id', $tipotransaccionId)
                ->whereIn('numerocomprobante', $chunk)
                ->where(function (Builder $q) use ($campoFecha, $desde, $hasta) {
                    $q->whereDate($campoFecha, '<', $desde)
                        ->orWhereDate($campoFecha, '>', $hasta);
                })
                ->select(['numerocomprobante', $campoFecha])
                ->get();

            foreach ($rows as $row) {
                $num = (int) ($row->numerocomprobante ?? 0);
                if ($num <= 0) {
                    continue;
                }
                $fecha = (string) ($row->{$campoFecha} ?? '');
                $out[$num] = $fecha !== '' ? date('d/m/Y', strtotime($fecha)) : '—';
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function vacio(): array
    {
        return [
            'habilitada' => false,
            'grupos_con_saltos' => 0,
            'total_saltos' => 0,
            'total_faltantes' => 0,
            'grupos' => [],
        ];
    }
}
