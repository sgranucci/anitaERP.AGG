<?php

namespace App\Support\Compras;

use setasign\Fpdi\Fpdi;
use Throwable;

/**
 * Genera una copia temporal de nota_oc.pdf para fusionarla con la OC:
 * recorte opcional por la izquierda, escala uniforme y margen fino para no ver el bloque pegado al borde.
 */
final class NotaOcPdfRecorteMargenIzquierdo
{
    /** ~15 mm en puntos PDF (1 pt = 1/72 in). Ajustable con NOTA_OC_RECORTE_IZQUIERDO_PT en .env */
    public const RECORTE_POR_DEFECTO_PT = 42.52;

    /** Escala uniforme del contenido (1 = sin cambio). Ajustable con NOTA_OC_ESCALA en .env (ej. 0.85) */
    public const ESCALA_POR_DEFECTO = 0.88;

    /** Empuja el contenido un poco a la derecha (pt PDF). Ajustable con NOTA_OC_MARGEN_IZQUIERDO_PT en .env */
    public const MARGEN_IZQUIERDO_POR_DEFECTO_PT = 8.0;

    /**
     * @param  float  $recorteIzquierdoPt  Puntos PDF a desplazar el lienzo a la izquierda (0 = sin recorte).
     * @param  float  $escala              Factor 0,25–1; menor = letra/contenido más chico respecto al papel.
     * @param  float  $margenIzquierdoPt   Suma a X para dejar aire a la izquierda (corrige sensación “corrida”).
     * @return string|null Ruta del PDF temporal, o null si no hace falta transformación o falla.
     */
    public static function generarTemporal(string $rutaPdf, float $recorteIzquierdoPt, float $escala = 1.0, float $margenIzquierdoPt = 0.0): ?string
    {
        if (! is_file($rutaPdf)) {
            return null;
        }

        $recorteIzquierdoPt = max(0.0, $recorteIzquierdoPt);
        $escala = max(0.25, min(1.0, $escala));
        $margenIzquierdoPt = max(0.0, $margenIzquierdoPt);

        if ($recorteIzquierdoPt <= 0.0 && $escala >= 0.999 && $margenIzquierdoPt <= 0.0) {
            return null;
        }

        $rutaSalida = sys_get_temp_dir().'/nota_oc_'.uniqid('', true).'.pdf';

        try {
            $pdf = new Fpdi;
            $n = $pdf->setSourceFile($rutaPdf);
            for ($i = 1; $i <= $n; $i++) {
                $tpl = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                if ($size === false) {
                    return null;
                }
                $w = (float) $size['width'];
                $h = (float) $size['height'];
                $orient = (($size['orientation'] ?? 'P') === 'L') ? 'L' : 'P';
                $pdf->AddPage($orient, [$w, $h]);

                $tw = $w * $escala;
                $th = $h * $escala;
                $x = ($w - $tw) / 2 - $recorteIzquierdoPt + $margenIzquierdoPt;
                $y = max(0.0, ($h - $th) / 2);
                $pdf->useTemplate($tpl, $x, $y, $tw, $th);
            }
            $pdf->Output('F', $rutaSalida);

            return $rutaSalida;
        } catch (Throwable $e) {
            report($e);
            if (is_file($rutaSalida)) {
                @unlink($rutaSalida);
            }

            return null;
        }
    }
}
