<?php

namespace App\Support\Stock\RecepcionProveedorOcr;

/**
 * Score 0..1 del cruce remito/factura OCR vs líneas de OC.
 */
final class RecepcionProveedorOcrMatchScoreSupport
{
    /**
     * @param  array<string, mixed>  $resultado  Salida de OCR+matcher (con o sin metadatos internos)
     * @return array{score: float, advertencias: list<string>}
     */
    public static function evaluar(array $resultado): array
    {
        $resumen = $resultado['_resumen_arr'] ?? null;
        if (! is_array($resumen)) {
            $resumen = self::inferirResumen($resultado);
        }

        $emparejadas = (int) ($resumen['emparejadas'] ?? 0);
        $sinMatch = (int) ($resumen['sin_match'] ?? 0);
        $sinOcr = (int) ($resumen['sin_ocr'] ?? 0);
        $detectadas = (int) ($resultado['ocr_lineas_detectadas'] ?? ($emparejadas + $sinMatch));
        $totalOc = max(1, $emparejadas + $sinOcr);

        $ratioMatch = $emparejadas / $totalOc;
        $ratioRuido = $detectadas > 0 ? $sinMatch / $detectadas : 0.0;
        $score = max(0.0, min(1.0, round(($ratioMatch * 0.85) + ((1 - $ratioRuido) * 0.15), 4)));

        $advertencias = [];
        if ($emparejadas === 0) {
            $advertencias[] = 'Ninguna línea del remito emparejó con la OC.';
        }
        if ($sinMatch > 0) {
            $advertencias[] = $sinMatch.' línea(s) del documento sin match en la OC.';
        }
        if ($sinOcr > 0) {
            $advertencias[] = $sinOcr.' ítem(s) de la OC sin cantidad OCR.';
        }
        $ocDoc = $resultado['numero_oc_detectado'] ?? null;
        $ocCargada = $resultado['numeroordencompra'] ?? null;
        if ($ocDoc && $ocCargada && (int) $ocDoc !== (int) $ocCargada) {
            $advertencias[] = 'OC del documento ('.$ocDoc.') distinta a la cargada ('.$ocCargada.').';
            $score = max(0.0, round($score - 0.15, 4));
        }

        return ['score' => $score, 'advertencias' => $advertencias];
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array{emparejadas: int, sin_match: int, sin_ocr: int}
     */
    private static function inferirResumen(array $resultado): array
    {
        $lineas = is_array($resultado['lineas'] ?? null) ? $resultado['lineas'] : [];
        $emparejadas = 0;
        $sinOcr = 0;
        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $tieneOcr = ! empty($linea['ocr_codigo_proveedor'])
                || ! empty($linea['ocr_descripcion_proveedor'])
                || ! empty($linea['ocr_codigobarra']);
            if ($tieneOcr && (float) ($linea['cantidad'] ?? 0) > 0) {
                $emparejadas++;
            } elseif ((float) ($linea['cantidad'] ?? 0) <= 0) {
                $sinOcr++;
            }
        }

        $detectadas = (int) ($resultado['ocr_lineas_detectadas'] ?? $emparejadas);

        return [
            'emparejadas' => $emparejadas,
            'sin_match' => max(0, $detectadas - $emparejadas),
            'sin_ocr' => $sinOcr,
        ];
    }
}
