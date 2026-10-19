<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Support\Listado\ReporteResultadoTextoSupport;

/**
 * Filtro de texto sobre filas del reporte CC clientes.
 */
final class ClienteCuentacorrienteReporteTextoSupport
{
    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function aplicar(array $resultado, array $filtros): array
    {
        if (! ClienteCuentacorrienteReporteFiltros::tieneFiltrosTextoAplicados($filtros)) {
            return $resultado;
        }

        $filas = $resultado['filas'] ?? [];
        if (! is_array($filas) || $filas === []) {
            return $resultado;
        }

        $opciones = [
            'tipo_key' => 'tipo',
            'detalle_tipos' => ['movimiento', 'aplicacion', 'saldo_anterior', 'header_cliente', 'total_cliente', 'header_vendedor', 'total_vendedor'],
            'group_key' => 'cliente_id',
            'header_tipos' => ['header_empresa'],
        ];

        $filasFiltradas = ReporteResultadoTextoSupport::filtrarFilas(
            $filas,
            $filtros,
            ClienteCuentacorrienteReporteFiltros::CAMPOS_TEXTO,
            $opciones
        );
        $resultado['filas'] = $filasFiltradas;
        $resultado['totales'] = self::recalcularTotales(
            $filasFiltradas,
            $resultado['totales'] ?? [],
            (string) ($filtros['modo'] ?? ClienteCuentacorrienteReporteFiltros::MODO_DEUDA)
        );
        $resultado['stats'] = self::recalcularStats($filasFiltradas);

        if (! empty($resultado['secciones']) && is_array($resultado['secciones'])) {
            $secciones = [];
            foreach ($resultado['secciones'] as $seccion) {
                $filasSeccion = ReporteResultadoTextoSupport::filtrarFilas(
                    $seccion['filas'] ?? [],
                    $filtros,
                    ClienteCuentacorrienteReporteFiltros::CAMPOS_TEXTO,
                    $opciones
                );
                if ($filasSeccion === []) {
                    continue;
                }
                $seccion['filas'] = $filasSeccion;
                $seccion['totales'] = self::recalcularTotales(
                    $filasSeccion,
                    $seccion['totales'] ?? [],
                    (string) ($filtros['modo'] ?? ClienteCuentacorrienteReporteFiltros::MODO_DEUDA)
                );
                $seccion['stats'] = self::recalcularStats($filasSeccion);
                $secciones[] = $seccion;
            }
            $resultado['secciones'] = $secciones;
        }

        if ($filasFiltradas === []) {
            $resultado['advertencias'] = array_values(array_unique(array_merge(
                $resultado['advertencias'] ?? [],
                ['Sin filas para el filtro de texto indicado.']
            )));
        }

        return $resultado;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $totalesBase
     * @return array<string, mixed>
     */
    private static function recalcularTotales(array $filas, array $totalesBase, string $modo): array
    {
        $debe = 0.0;
        $haber = 0.0;
        $pendiente = 0.0;
        foreach ($filas as $fila) {
            if (($fila['tipo'] ?? '') !== 'total_cliente') {
                continue;
            }
            $debe += (float) ($fila['debe'] ?? 0);
            $haber += (float) ($fila['haber'] ?? 0);
            $pendiente += (float) ($fila['saldo_pendiente'] ?? 0);
        }

        return [
            'debe' => round($debe, 2),
            'haber' => round($haber, 2),
            'pendiente' => round($pendiente, 2),
            'abreviatura' => $totalesBase['abreviatura'] ?? '',
            'modo' => $modo,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{clientes: int, vendedores: int, movimientos: int, aplicaciones: int}
     */
    private static function recalcularStats(array $filas): array
    {
        $clientes = [];
        $vendedores = [];
        $movimientos = 0;
        $aplicaciones = 0;
        foreach ($filas as $fila) {
            $tipo = (string) ($fila['tipo'] ?? '');
            $cid = (int) ($fila['cliente_id'] ?? 0);
            $vid = (int) ($fila['vendedor_id'] ?? 0);
            if ($cid > 0) {
                $clientes[$cid] = true;
            }
            if ($vid > 0) {
                $vendedores[$vid] = true;
            }
            if ($tipo === 'movimiento') {
                $movimientos++;
            } elseif ($tipo === 'aplicacion') {
                $aplicaciones++;
            }
        }

        return [
            'clientes' => count($clientes),
            'vendedores' => count($vendedores),
            'movimientos' => $movimientos,
            'aplicaciones' => $aplicaciones,
        ];
    }
}
