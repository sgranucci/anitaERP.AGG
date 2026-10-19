<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Listado\ReporteResultadoTextoSupport;

final class PagosSabanaReporteTextoSupport
{
    /** @var array<string, array{prop: string, type: string, label: string, flexible?: bool}> */
    public const CAMPOS_TEXTO = [
        'proveedor_codigo' => ['prop' => 'proveedor_codigo', 'type' => 'texto', 'label' => 'Código proveedor', 'flexible' => true],
        'proveedor_nombre' => ['prop' => 'proveedor_nombre', 'type' => 'texto', 'label' => 'Nombre proveedor', 'flexible' => true],
        'tip' => ['prop' => 'tip', 'type' => 'texto', 'label' => 'Tipo OP'],
        'numero_op' => ['prop' => 'numero_op', 'type' => 'texto', 'label' => 'Número OP'],
        'fecha' => ['prop' => 'fecha', 'type' => 'texto', 'label' => 'Fecha'],
        'tipo_medio' => ['prop' => 'tipo_medio', 'type' => 'texto', 'label' => 'Tipo medio'],
        'comprobantes' => ['prop' => 'comprobantes', 'type' => 'texto', 'label' => 'Comprobantes', 'flexible' => true],
        'banco' => ['prop' => 'banco', 'type' => 'texto', 'label' => 'Banco', 'flexible' => true],
        'centros_costo' => ['prop' => 'centros_costo', 'type' => 'texto', 'label' => 'Centros de costo', 'flexible' => true],
        'ordenes_compra' => ['prop' => 'ordenes_compra', 'type' => 'texto', 'label' => 'Órdenes de compra'],
        'detalle' => ['prop' => 'detalle', 'type' => 'texto', 'label' => 'Detalle', 'flexible' => true],
        'nombreempresa' => ['prop' => 'nombreempresa', 'type' => 'texto', 'label' => 'Empresa', 'flexible' => true],
    ];

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function aplicar(array $resultado, array $filtros): array
    {
        if (! ReporteResultadoTextoSupport::tieneAplicados($filtros)) {
            return $resultado;
        }

        $filas = $resultado['filas'] ?? [];
        if (! is_array($filas) || $filas === []) {
            return $resultado;
        }

        $filasFiltradas = ReporteResultadoTextoSupport::filtrarFilas(
            $filas,
            $filtros,
            self::CAMPOS_TEXTO,
            [
                'tipo_key' => 'tipo_fila',
                'detalle_tipos' => ['detalle'],
                'header_tipos' => ['header_empresa'],
            ]
        );

        $resultado['filas'] = $filasFiltradas;
        $resultado['totales'] = self::recalcularTotales($filasFiltradas, $resultado['columnas'] ?? []);

        if (! empty($resultado['secciones']) && is_array($resultado['secciones'])) {
            $secciones = [];
            foreach ($resultado['secciones'] as $seccion) {
                $filasSec = ReporteResultadoTextoSupport::filtrarFilas(
                    $seccion['filas'] ?? [],
                    $filtros,
                    self::CAMPOS_TEXTO,
                    [
                        'tipo_key' => 'tipo_fila',
                        'detalle_tipos' => ['detalle'],
                        'header_tipos' => ['header_empresa'],
                    ]
                );
                if ($filasSec === []) {
                    continue;
                }
                $seccion['filas'] = $filasSec;
                $seccion['totales'] = self::recalcularTotales($filasSec, $resultado['columnas'] ?? []);
                $secciones[] = $seccion;
            }
            $resultado['secciones'] = $secciones;
        }

        return $resultado;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array<string, mixed>>  $columnas
     * @return array{cantidad: int, importes: array<string, float>, total_pago: float}
     */
    private static function recalcularTotales(array $filas, array $columnas): array
    {
        $importes = [];
        foreach ($columnas as $col) {
            if (($col['tipo'] ?? '') === PagosSabanaColumnasSupport::TIPO_IMPORTE) {
                $importes[(string) $col['clave']] = 0.0;
            }
        }
        $cantidad = 0;
        $totalPago = 0.0;
        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? '') !== 'detalle') {
                continue;
            }
            $cantidad++;
            $totalPago += (float) ($fila['total_pago'] ?? 0);
            foreach ($importes as $clave => $_) {
                $importes[$clave] = round($importes[$clave] + (float) ($fila[$clave] ?? 0), 2);
            }
        }

        return [
            'cantidad' => $cantidad,
            'importes' => $importes,
            'total_pago' => round($totalPago, 2),
        ];
    }
}
