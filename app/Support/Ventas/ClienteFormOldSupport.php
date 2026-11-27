<?php

namespace App\Support\Ventas;

use Illuminate\Support\Collection;

/**
 * ABM cliente: rearmar las grillas (seguimiento, CM05, exclusiones, artículos suspendidos…)
 * desde lo que el operador había tipeado cuando el guardado falla por validación.
 *
 * Sin esto, cada grilla recorría solo los registros de la base y al volver con errores
 * las filas cargadas en pantalla se perdían.
 */
final class ClienteFormOldSupport
{
    /**
     * true cuando el formulario se vuelve a mostrar tras un error de validación.
     */
    public static function hayOld(): bool
    {
        return session()->hasOldInput();
    }

    /**
     * Una fila (stdClass) por índice de los arrays posteados.
     *
     * @param  array<string, string>  $campos  propiedad => nombre del campo posteado (sin "[]")
     * @return Collection<int, \stdClass>
     */
    public static function filas(array $campos): Collection
    {
        $total = 0;
        foreach ($campos as $nombre) {
            $valor = old($nombre);
            if (is_array($valor)) {
                $total = max($total, count($valor));
            }
        }

        $filas = collect();
        for ($i = 0; $i < $total; $i++) {
            $fila = new \stdClass;
            foreach ($campos as $propiedad => $nombre) {
                $valor = old($nombre);
                $fila->{$propiedad} = is_array($valor) ? ($valor[$i] ?? null) : null;
            }
            $filas->push($fila);
        }

        return $filas;
    }
}
