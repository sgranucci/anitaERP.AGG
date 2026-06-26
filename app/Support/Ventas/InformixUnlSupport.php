<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Formato pipe (|) compatible con Informix UNLOAD / LOAD FROM DELIMITER '|'.
 */
final class InformixUnlSupport
{
    /**
     * @param  list<int|float|string|null>  $valores
     */
    public static function linea(array $valores): string
    {
        $partes = [];
        foreach ($valores as $valor) {
            if ($valor === null) {
                $partes[] = '';

                continue;
            }

            $texto = (string) $valor;
            $texto = str_replace(["\r", "\n"], ' ', $texto);
            $texto = str_replace('|', ' ', $texto);

            $partes[] = $texto;
        }

        return implode('|', $partes);
    }
}
