<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoCortesSupport;
use Illuminate\Database\Eloquent\Model;

/**
 * Totales y cortes del universo filtrado. El monto sale de
 * IngresoEgresoListadoMontoSupport (no hay columna SQL de importe).
 */
final class IngresoEgresoListadoResumen
{
    /**
     * @param  iterable<int, object>  $filas
     * @param  list<string>  $agrupar
     * @param  array<string, string>  $etiquetas
     * @return array{
     *   totales: array{cantidad: int, ingresos: float, egresos: float},
     *   cortes: array<string, mixed>
     * }
     */
    public static function desdeFilas(iterable $filas, array $agrupar, array $etiquetas = []): array
    {
        $campos = IngresoEgresoListadoFiltros::camposOrdenables();
        $agrupar = ListadoAgrupacionSupport::normalizar($agrupar, $campos);
        $nivel0 = [];
        $nivel1 = [];
        $totales = [
            'cantidad' => 0,
            'ingresos' => 0.0,
            'egresos' => 0.0,
        ];

        foreach ($filas as $fila) {
            $resumen = IngresoEgresoListadoMontoSupport::resumen($fila);
            if ($fila instanceof Model) {
                $fila->setAttribute('_ie_resumen', $resumen);
            }
            $totales['cantidad']++;
            $totales['ingresos'] += (float) $resumen['ingreso'];
            $totales['egresos'] += (float) $resumen['egreso'];

            if ($agrupar === []) {
                continue;
            }

            $v0 = IngresoEgresoListadoColumnas::valorAgrupacion($fila, $agrupar[0]);
            if (! isset($nivel0[$v0])) {
                $nivel0[$v0] = ['count' => 0, 'ingresos' => 0.0, 'egresos' => 0.0];
            }
            $nivel0[$v0]['count']++;
            $nivel0[$v0]['ingresos'] += (float) $resumen['ingreso'];
            $nivel0[$v0]['egresos'] += (float) $resumen['egreso'];

            if (! isset($agrupar[1])) {
                continue;
            }
            $v1 = IngresoEgresoListadoColumnas::valorAgrupacion($fila, $agrupar[1]);
            $clave = $v0.'||'.$v1;
            if (! isset($nivel1[$clave])) {
                $nivel1[$clave] = [
                    'parent' => $v0,
                    'valor' => $v1,
                    'count' => 0,
                    'ingresos' => 0.0,
                    'egresos' => 0.0,
                ];
            }
            $nivel1[$clave]['count']++;
            $nivel1[$clave]['ingresos'] += (float) $resumen['ingreso'];
            $nivel1[$clave]['egresos'] += (float) $resumen['egreso'];
        }

        return [
            'totales' => $totales,
            'cortes' => self::cortes($agrupar, $nivel0, $nivel1, $totales, $etiquetas),
        ];
    }

    /**
     * @param  list<string>  $agrupar
     * @param  array<string, array{count: int, ingresos: float, egresos: float}>  $nivel0
     * @param  array<string, array{parent: string, valor: string, count: int, ingresos: float, egresos: float}>  $nivel1
     * @param  array{cantidad: int, ingresos: float, egresos: float}  $totales
     * @param  array<string, string>  $etiquetas
     * @return array<string, mixed>
     */
    private static function cortes(array $agrupar, array $nivel0, array $nivel1, array $totales, array $etiquetas): array
    {
        $vacio = [
            'activo' => false,
            'filas' => [],
            'total' => 0,
            'sumas_total' => [],
            'medidas' => [],
            'grupos_nivel0' => 0,
            'truncado' => false,
        ];
        if ($agrupar === []) {
            return $vacio;
        }

        $medidas = [
            ['key' => 'ingresos', 'label' => 'Ingresos'],
            ['key' => 'egresos', 'label' => 'Egresos'],
        ];
        $label0 = $etiquetas[$agrupar[0]] ?? $agrupar[0];
        $label1 = isset($agrupar[1]) ? ($etiquetas[$agrupar[1]] ?? $agrupar[1]) : '';
        ksort($nivel0, SORT_NATURAL | SORT_FLAG_CASE);

        $filas = [];
        $truncado = false;
        foreach ($nivel0 as $valor => $suma) {
            if (count($filas) >= ListadoCortesSupport::MAX_FILAS) {
                $truncado = true;
                break;
            }
            $filas[] = [
                'nivel' => 0,
                'path' => [$valor],
                'label' => $label0,
                'valor' => $valor,
                'count' => $suma['count'],
                'sumas' => [
                    'ingresos' => $suma['ingresos'],
                    'egresos' => $suma['egresos'],
                ],
            ];
            if ($label1 === '') {
                continue;
            }
            $hijos = [];
            foreach ($nivel1 as $hijo) {
                if ($hijo['parent'] === $valor) {
                    $hijos[] = $hijo;
                }
            }
            usort($hijos, static fn (array $a, array $b): int => strnatcasecmp($a['valor'], $b['valor']));
            foreach ($hijos as $hijo) {
                if (count($filas) >= ListadoCortesSupport::MAX_FILAS) {
                    $truncado = true;
                    break 2;
                }
                $filas[] = [
                    'nivel' => 1,
                    'path' => [$valor, $hijo['valor']],
                    'label' => $label1,
                    'valor' => $hijo['valor'],
                    'count' => $hijo['count'],
                    'sumas' => [
                        'ingresos' => $hijo['ingresos'],
                        'egresos' => $hijo['egresos'],
                    ],
                    'parent' => $valor,
                ];
            }
        }

        return [
            'activo' => $filas !== [],
            'filas' => $filas,
            'total' => $totales['cantidad'],
            'sumas_total' => [
                'ingresos' => $totales['ingresos'],
                'egresos' => $totales['egresos'],
            ],
            'medidas' => $medidas,
            'grupos_nivel0' => count($nivel0),
            'truncado' => $truncado,
        ];
    }
}
