<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Código de tasa para vengrav.veng_codigo_tasa (numérico en Informix).
 *
 * impuesto.codigo a veces llega como texto "NULL" (dato inválido): el bridge
 * lo manda entre comillas y Anita aborta con 1213 Character to numeric conversion.
 * Preferir codigo Anita; si no es numérico, codigoarca; si no, la tasa.
 */
final class AnitaVengravCodigoTasaSupport
{
    public static function resolver(
        int|string|null $codigo,
        int|string|null $codigoarca = null,
        float $tasa = 0.0,
    ): int {
        foreach ([$codigo, $codigoarca] as $candidato) {
            $id = self::aEnteroSiNumerico($candidato);
            if ($id !== null) {
                return $id;
            }
        }

        $porTasa = ArcaMtxcaComprobanteTotalesSupport::codigoPorTasa($tasa);
        if ($porTasa !== null) {
            return $porTasa;
        }

        throw new \RuntimeException(
            'No se pudo resolver veng_codigo_tasa numérico para Anita. '
            ."codigo={$codigo} codigoarca={$codigoarca} tasa={$tasa}"
        );
    }

    /**
     * Normaliza el codigo de maestro impuesto para conceptostotales / Anita.
     */
    public static function normalizarCodigoMaestro(
        int|string|null $codigo,
        int|string|null $codigoarca = null,
        float $tasa = 0.0,
    ): string {
        return (string) self::resolver($codigo, $codigoarca, $tasa);
    }

    private static function aEnteroSiNumerico(int|string|null $candidato): ?int
    {
        if ($candidato === null) {
            return null;
        }

        $s = trim((string) $candidato);
        if ($s === '' || strcasecmp($s, 'NULL') === 0) {
            return null;
        }

        if (! is_numeric($s)) {
            return null;
        }

        return (int) $s;
    }
}
