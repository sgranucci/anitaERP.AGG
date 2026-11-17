<?php

namespace App\Support\Configuracion;

use App\Support\ReglaNegocio\ReglaNegocioConsultaSupport;

/**
 * Umbral a partir del cual, con doble aprobación, el matching de niveles pasa a “piso”.
 * Legacy: niveles con desdemonto >= 5.000.000.
 * BRE (punto configuracion.arbol.umbral_doble_aprobacion): set_dato umbral_nivel_alto.
 */
final class ArbolAprobacionUmbralSupport
{
    public const UMBRAL_NIVEL_ALTO_DEFAULT = 5_000_000.0;

    public const PUNTO_REGLA = 'configuracion.arbol.umbral_doble_aprobacion';

    public static function umbralNivelAlto(): float
    {
        $d = ReglaNegocioConsultaSupport::decidirSiActivo(self::PUNTO_REGLA, [
            'umbral_legacy' => self::UMBRAL_NIVEL_ALTO_DEFAULT,
            'punto_param' => 'umbral_nivel_alto',
        ]);
        if ($d !== null) {
            $v = ReglaNegocioConsultaSupport::floatDato($d->datos, 'umbral_nivel_alto');
            if ($v !== null && $v > 0) {
                return $v;
            }
        }

        return self::UMBRAL_NIVEL_ALTO_DEFAULT;
    }

    /**
     * @param  iterable<int, object>  $nivelesCc  objetos con desdemonto
     */
    public static function umbralPisoDobleAprobacion(iterable $nivelesCc): float
    {
        $umbralAlto = self::umbralNivelAlto();
        $minAlto = null;
        foreach ($nivelesCc as $nivel) {
            $desde = (float) ($nivel->desdemonto ?? 0);
            if ($desde >= $umbralAlto) {
                $minAlto = $minAlto === null ? $desde : min($minAlto, $desde);
            }
        }

        return $minAlto ?? ($umbralAlto + 1.0);
    }
}
