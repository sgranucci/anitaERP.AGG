<?php

namespace App\Support\Sueldos;

/**
 * Texto del aviso de libretas sanitarias pendientes.
 */
class LibretaSanitariaAvisoSupport
{
    /**
     * @param  list<array{empresa: string, legajo: string, nombre: string, vencimiento: string}>  $filas
     */
    public static function formatearLista(array $filas): string
    {
        if ($filas === []) {
            return '';
        }

        $lineas = [];
        foreach ($filas as $fila) {
            $vencimiento = trim((string) ($fila['vencimiento'] ?? ''));
            $lineas[] = sprintf(
                '%s · legajo %s · %s · %s',
                $fila['empresa'] !== '' ? $fila['empresa'] : 'Sin empresa',
                $fila['legajo'],
                $fila['nombre'] !== '' ? $fila['nombre'] : 'Sin nombre',
                $vencimiento !== '' ? 'vence '.$vencimiento : 'sin vencimiento'
            );
        }

        return implode("\n", $lineas);
    }
}
