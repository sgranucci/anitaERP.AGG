<?php

declare(strict_types=1);

namespace App\Support\Ticket;

use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoCortesSupport;

/**
 * Cortes del universo que ya pasó por el alcance de rol y usuario.
 */
final class AdministracionTicketListadoResumen
{
    /**
     * @param  iterable<int, object>  $filas
     * @param  list<string>  $agrupar
     * @param  array<string, string>  $etiquetas
     * @return array{cortes: array<string, mixed>}
     */
    public static function desdeFilas(iterable $filas, array $agrupar, array $etiquetas = []): array
    {
        $campos = AdministracionTicketListadoFiltros::camposOrdenables();
        $agrupar = ListadoAgrupacionSupport::normalizar($agrupar, $campos);
        $nivel0 = [];
        foreach ($filas as $fila) {
            if ($agrupar === []) {
                break;
            }
            $v0 = AdministracionTicketListadoColumnas::valorAgrupacion($fila, $agrupar[0]);
            if (! isset($nivel0[$v0])) {
                $nivel0[$v0] = ['count' => 0, 'minutos' => 0.0];
            }
            $nivel0[$v0]['count']++;
            $nivel0[$v0]['minutos'] += (float) ($fila->tiempo_insumido_total ?? 0);
        }

        return ['cortes' => self::cortes($agrupar, $nivel0, $etiquetas)];
    }

    /**
     * @param  list<string>  $agrupar
     * @param  array<string, array{count: int, minutos: float}>  $nivel0
     * @param  array<string, string>  $etiquetas
     * @return array<string, mixed>
     */
    private static function cortes(array $agrupar, array $nivel0, array $etiquetas): array
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

        ksort($nivel0, SORT_NATURAL | SORT_FLAG_CASE);
        $label0 = $etiquetas[$agrupar[0]] ?? $agrupar[0];
        $filas = [];
        $total = 0;
        $minutos = 0.0;
        foreach ($nivel0 as $valor => $suma) {
            if (count($filas) >= ListadoCortesSupport::MAX_FILAS) {
                break;
            }
            $filas[] = [
                'nivel' => 0,
                'path' => [$valor],
                'label' => $label0,
                'valor' => $valor,
                'count' => $suma['count'],
                'sumas' => [
                    'tiempo_insumido' => $suma['minutos'],
                ],
            ];
            $total += $suma['count'];
            $minutos += $suma['minutos'];
        }

        return [
            'activo' => true,
            'filas' => $filas,
            'total' => $total,
            'sumas_total' => ['tiempo_insumido' => $minutos],
            'medidas' => [
                ['key' => 'tiempo_insumido', 'label' => 'Minutos'],
            ],
            'grupos_nivel0' => count($nivel0),
            'truncado' => count($nivel0) > count($filas),
        ];
    }
}
