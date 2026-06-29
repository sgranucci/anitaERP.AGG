<?php

namespace App\Support\Sala;

final class RequisicionSalaMotivoRechazoSupport
{
    private const PREFIJO_ARBOL = 'Requisición de sala rechazada en árbol: ';

    public static function textoVisible(?string $observacion): string
    {
        $texto = trim((string) $observacion);
        if ($texto === '') {
            return '';
        }
        if (str_starts_with($texto, self::PREFIJO_ARBOL)) {
            $detalle = trim(substr($texto, strlen(self::PREFIJO_ARBOL)));

            return $detalle !== '' ? $detalle : $texto;
        }

        return $texto;
    }
}
