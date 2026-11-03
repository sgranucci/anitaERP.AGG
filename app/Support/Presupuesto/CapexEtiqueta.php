<?php

namespace App\Support\Presupuesto;

/**
 * Texto de un CAPEX en órdenes de compra y requisiciones.
 * El número (capex.codigo) se conserva y se agrega el código de proyecto (capex.codigoproyecto).
 */
final class CapexEtiqueta
{
    public static function numeroYCodigoProyecto(mixed $capex): string
    {
        if ($capex === null) {
            return '';
        }

        if (is_array($capex)) {
            $numero = trim((string) ($capex['codigo'] ?? ''));
            $codigo = trim((string) ($capex['codigoproyecto'] ?? ''));
        } else {
            $numero = trim((string) ($capex->codigo ?? ''));
            $codigo = trim((string) ($capex->codigoproyecto ?? ''));
        }

        if ($codigo !== '' && strcasecmp($codigo, $numero) !== 0) {
            return $numero !== '' ? $numero.' — '.$codigo : $codigo;
        }

        return $numero;
    }
}
