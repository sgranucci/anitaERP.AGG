<?php

namespace App\Support\Ventas;

/**
 * Reglas para decidir si, tras fallar FECAEASolicitar/solicitarCAEA, conviene consultar el CAEA ya otorgado en ARCA.
 */
final class ArcaCaeaSolicitudSupport
{
    /**
     * AFIP devuelve estos casos cuando el CAEA ya fue solicitado (p. ej. desde Anita u otro sistema).
     */
    public static function debeConsultarTrasFalloSolicitud(string $mensaje, string $webservice = 'wsfev1'): bool
    {
        $m = mb_strtolower($mensaje);

        $comunes = str_contains($m, 'ya otorgado')
            || str_contains($m, 'ya existe un caea')
            || str_contains($m, 'existir un caea')
            || str_contains($m, 'sin caea en la respuesta')
            || str_contains($m, 'sin resultget');

        if ($comunes) {
            return true;
        }

        if ($webservice === 'wsmtxca') {
            return str_contains($mensaje, '[604]');
        }

        return str_contains($mensaje, '[15008]')
            || str_contains($mensaje, '[15007]');
    }
}
