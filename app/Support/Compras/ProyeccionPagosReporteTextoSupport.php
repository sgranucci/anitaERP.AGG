<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Listado\ReporteResultadoTextoSupport;

final class ProyeccionPagosReporteTextoSupport
{
    /** @var array<string, array{prop: \Closure, type: string, label: string, flexible?: bool}> */
    public const CAMPOS_TEXTO = [
        'proveedor_codigo' => ['prop' => 'proveedor_codigo', 'type' => 'texto', 'label' => 'Código proveedor', 'flexible' => true],
        'proveedor_nombre' => ['prop' => 'proveedor_nombre', 'type' => 'texto', 'label' => 'Nombre proveedor', 'flexible' => true],
        'empresa' => ['prop' => 'empresa', 'type' => 'texto', 'label' => 'Empresa', 'flexible' => true],
        'comprobante' => ['prop' => 'comprobante', 'type' => 'texto', 'label' => 'Comprobante', 'flexible' => true],
        'tipo' => ['prop' => 'tipo', 'type' => 'texto', 'label' => 'Tipo'],
        'moneda' => ['prop' => 'moneda', 'type' => 'texto', 'label' => 'Moneda'],
        'medio_pago' => ['prop' => 'medio_pago', 'type' => 'texto', 'label' => 'Medio de pago', 'flexible' => true],
        'concepto' => ['prop' => 'concepto', 'type' => 'texto', 'label' => 'Concepto'],
        'detalle_concepto' => ['prop' => 'detalle_concepto', 'type' => 'texto', 'label' => 'Detalle concepto', 'flexible' => true],
        'nro_referencia' => ['prop' => 'nro_referencia', 'type' => 'texto', 'label' => 'Nº referencia'],
    ];

    /**
     * @return array<string, array{prop: \Closure, type: string, label: string, flexible?: bool}>
     */
    public static function camposConResolver(): array
    {
        $out = [];
        foreach (self::CAMPOS_TEXTO as $key => $meta) {
            $prop = $meta['prop'];
            $out[$key] = [
                'prop' => static function (array $fila) use ($prop) {
                    if (($fila['tipo_fila'] ?? '') === 'detalle') {
                        return (string) ($fila['valores'][$prop] ?? '');
                    }

                    return (string) ($fila['etiqueta'] ?? '');
                },
                'type' => $meta['type'],
                'label' => $meta['label'],
                'flexible' => ! empty($meta['flexible']),
            ];
        }

        return $out;
    }

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

        $campos = self::camposConResolver();
        $detallesMatch = [];
        $gruposMatch = [];
        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? '') !== 'detalle') {
                continue;
            }
            if (! ReporteResultadoTextoSupport::filaCoincide($fila, $filtros, $campos)) {
                continue;
            }
            $detallesMatch[$idx] = true;
            $gid = (int) ($fila['grupo_id'] ?? 0);
            if ($gid > 0) {
                $gruposMatch[$gid] = true;
            }
        }

        if ($detallesMatch === []) {
            $resultado['filas'] = [];
            $resultado['totales'] = array_merge($resultado['totales'] ?? [], [
                'cantidad' => 0,
                'importes' => [],
                'total_adeudado' => 0.0,
            ]);

            return $resultado;
        }

        $out = [];
        $detallesKept = [];
        $i = 0;
        $n = count($filas);
        while ($i < $n) {
            $fila = $filas[$i];
            $tipo = (string) ($fila['tipo_fila'] ?? '');

            if ($tipo === 'header_empresa') {
                $j = $i + 1;
                $hay = false;
                while ($j < $n && ($filas[$j]['tipo_fila'] ?? '') !== 'header_empresa') {
                    if (isset($detallesMatch[$j])) {
                        $hay = true;
                    }
                    $j++;
                }
                if ($hay) {
                    $out[] = $fila;
                    for ($k = $i + 1; $k < $j; $k++) {
                        $t = (string) ($filas[$k]['tipo_fila'] ?? '');
                        $gid = (int) ($filas[$k]['grupo_id'] ?? 0);
                        if ($t === 'detalle' && isset($detallesMatch[$k])) {
                            $out[] = $filas[$k];
                            $detallesKept[] = $filas[$k];
                        } elseif (in_array($t, ['cabecera_grupo', 'subtotal_grupo'], true)
                            && $gid > 0 && isset($gruposMatch[$gid])
                        ) {
                            $out[] = $filas[$k];
                        }
                    }
                }
                $i = $j;
                continue;
            }

            if ($tipo === 'total_general') {
                $i++;
                continue;
            }

            if ($tipo === 'detalle' && isset($detallesMatch[$i])) {
                $out[] = $fila;
                $detallesKept[] = $fila;
            } elseif (in_array($tipo, ['cabecera_grupo', 'subtotal_grupo'], true)) {
                $gid = (int) ($fila['grupo_id'] ?? 0);
                if ($gid > 0 && isset($gruposMatch[$gid])) {
                    $out[] = $fila;
                }
            }
            $i++;
        }

        $columnas = $resultado['columnas'] ?? [];
        $importes = [];
        foreach (ProyeccionPagosColumnasSupport::clavesImporte($columnas) as $clave) {
            $importes[$clave] = 0.0;
        }
        $proveedores = [];
        foreach ($detallesKept as $mov) {
            foreach ($importes as $clave => $_) {
                $importes[$clave] = round($importes[$clave] + (float) ($mov['valores'][$clave] ?? 0), 2);
            }
            $pid = (int) ($mov['proveedor_id'] ?? 0);
            if ($pid > 0) {
                $proveedores[$pid] = true;
            }
        }
        $totales = [
            'cantidad' => count($detallesKept),
            'proveedores' => count($proveedores),
            'importes' => $importes,
            'total_adeudado' => (float) ($importes['total_adeudado'] ?? 0),
        ];
        $out[] = [
            'tipo_fila' => 'total_general',
            'etiqueta' => 'Total general',
            'cantidad' => (int) $totales['cantidad'],
            'valores' => $totales['importes'],
        ];

        $resultado['filas'] = $out;
        $resultado['totales'] = $totales;

        return $resultado;
    }
}
