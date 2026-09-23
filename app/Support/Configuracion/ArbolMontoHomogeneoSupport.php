<?php

namespace App\Support\Configuracion;

/**
 * Homogeneiza el monto del comprobante a la moneda del nivel del árbol.
 *
 * Regla: nunca asumir coeficiente 1 entre monedas distintas. Eso trata dólares (u otra
 * moneda) como pesos y hace fallar el matching de umbrales (p. ej. Beta ≥ 5M PES).
 */
final class ArbolMontoHomogeneoSupport
{
    /**
     * @param  array{cotizacionventa?: float|int|null, cotizacioncompra?: float|int|null}|float|int|null  $cotizacion
     */
    public static function convertir(
        float $montoDocumento,
        int|string|null $monedaDocumentoId,
        int|string|null $monedaNivelId,
        array|float|int|null $cotizacion
    ): float {
        $de = (int) ($monedaDocumentoId ?: 1);
        $a = (int) ($monedaNivelId ?: 1);

        if ($de === $a) {
            return $montoDocumento;
        }

        $coeficiente = (float) calculaCoeficienteMoneda($a, $de, $cotizacion);
        if ($coeficiente <= 0.0) {
            throw new \RuntimeException(
                "No hay cotización vigente para convertir moneda {$de} → {$a} en el árbol de aprobación. ".
                'No se asume 1:1 (evita tratar moneda extranjera como pesos).'
            );
        }

        return $montoDocumento * $coeficiente;
    }
}
