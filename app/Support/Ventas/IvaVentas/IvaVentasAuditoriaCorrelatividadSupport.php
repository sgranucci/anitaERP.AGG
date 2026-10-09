<?php

declare(strict_types=1);

namespace App\Support\Ventas\IvaVentas;

use App\Models\Ventas\Venta;
use App\Support\Ventas\IvaVentasListadoFiltros;
use App\Support\Ventas\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Detecta saltos de numeración por punto de venta, letra y familia fiscal
 * (factura, nota de crédito, nota de débito). La A y la B no comparten serie.
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
            $letra = strtoupper(trim((string) ($fila['letra'] ?? '')));
            $familia = self::familia((string) ($fila['tipo'] ?? ''));
            if ($pvId <= 0 || $tipoId <= 0 || $numero <= 0 || $familia === '') {
                continue;
            }

            $clave = $pvId.'|'.$familia.'|'.$letra;
            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'puntoventa_id' => $pvId,
                    'puntoventa_codigo' => (string) ($fila['puntoventa_codigo'] ?? ''),
                    'puntoventa_nombre' => (string) ($fila['puntoventa_nombre'] ?? ''),
                    'tipotransaccion_id' => $tipoId,
                    'tipo' => trim($familia.($letra !== '' ? ' '.$letra : '')),
                    'familia' => $familia,
                    'letra' => $letra,
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
                (string) $grupo['familia'],
                (string) $grupo['letra'],
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
     * Factura A y factura B son series distintas. NCD y NCP (y las ND) comparten
     * la numeración fiscal del punto de venta y la letra.
     */
    private static function familia(string $abreviatura): string
    {
        $abreviatura = strtoupper(trim($abreviatura));
        if (str_starts_with($abreviatura, 'NC')) {
            return 'NC';
        }
        if (str_starts_with($abreviatura, 'ND')) {
            return 'ND';
        }

        return $abreviatura;
    }

    /**
     * @param  list<int>  $numeros
     * @param  array<string, mixed>  $filtros
     * @return array<int, string>
     */
    private static function numerosExistentesFueraPeriodo(int $puntoventaId, string $familia, string $letra, array $numeros, array $filtros): array
    {
        if ($numeros === [] || $familia === '') {
            return [];
        }

        $campoFecha = ($filtros['orden_fecha'] ?? IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA) === IvaVentasListadoFiltros::ORDEN_FECHA
            ? 'venta.fecha'
            : 'venta.fechajornada';
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        $letra = strtoupper(trim($letra));

        $out = [];
        foreach (array_chunk($numeros, 500) as $chunk) {
            $query = Venta::query()
                ->join('tipotransaccion as tt', 'tt.id', '=', 'venta.tipotransaccion_id')
                ->where('venta.puntoventa_id', $puntoventaId)
                ->whereIn('venta.numerocomprobante', $chunk)
                ->where(function (Builder $q) use ($campoFecha, $desde, $hasta) {
                    $q->whereDate($campoFecha, '<', $desde)
                        ->orWhereDate($campoFecha, '>', $hasta);
                });
            self::aplicarFamilia($query, $familia);
            if ($letra !== '') {
                $query->where('venta.codigo', 'like', $familia.'% '.$letra.'-%');
            }

            $rows = $query->get(['venta.numerocomprobante', 'venta.codigo', $campoFecha.' as fecha_corr']);

            foreach ($rows as $row) {
                $num = (int) ($row->numerocomprobante ?? 0);
                if ($num <= 0) {
                    continue;
                }
                if ($letra !== '' && LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) $row->codigo) !== $letra) {
                    continue;
                }
                $fecha = (string) ($row->fecha_corr ?? '');
                $out[$num] = $fecha !== '' ? date('d/m/Y', strtotime($fecha)) : '—';
            }
        }

        return $out;
    }

    private static function aplicarFamilia(Builder $query, string $familia): void
    {
        if ($familia === 'NC' || $familia === 'ND') {
            $query->where('tt.abreviatura', 'like', $familia.'%');

            return;
        }

        $query->where('tt.abreviatura', $familia);
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
