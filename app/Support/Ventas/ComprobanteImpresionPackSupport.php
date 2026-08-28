<?php

namespace App\Support\Ventas;

/**
 * Clasifica líneas del pack: papel (impresora / PDF Acrobat) vs NAS (archivo en segundo plano).
 */
final class ComprobanteImpresionPackSupport
{
    public static function esNas(array $linea): bool
    {
        if (($linea['medio'] ?? '') === 'ARCHIVO') {
            return true;
        }

        return ! empty($linea['es_nas']);
    }

    public static function vaAlPdfSesion(array $linea): bool
    {
        if (self::esNas($linea)) {
            return false;
        }

        return (bool) ($linea['incluir_en_pdf_sesion'] ?? true);
    }

    /**
     * @param  list<array<string, mixed>>  $pack
     * @param  list<int>|null  $idxsElegidos
     * @return array{papel: list<int>, nas: list<int>}
     */
    public static function idxsPapelYNas(array $pack, ?array $idxsElegidos, bool $soloCopia): array
    {
        $papel = [];
        $nas = [];

        foreach ($pack as $idx => $linea) {
            $esNas = self::esNas(is_array($linea) ? $linea : []);
            if ($soloCopia) {
                if ($idxsElegidos !== null && in_array((int) $idx, $idxsElegidos, true)) {
                    if ($esNas) {
                        $nas[] = (int) $idx;
                    } else {
                        $papel[] = (int) $idx;
                    }
                }

                continue;
            }

            if ($esNas) {
                $nas[] = (int) $idx;

                continue;
            }

            if ($idxsElegidos === null || in_array((int) $idx, $idxsElegidos, true)) {
                $papel[] = (int) $idx;
            }
        }

        return ['papel' => $papel, 'nas' => $nas];
    }

    /**
     * Reusa el PDF de papel del mismo documento para no volver a renderizar el NAS.
     *
     * @param  array<string, mixed>  $lineaNas
     * @param  array<int, array<string, mixed>>  $resultadosPapel
     */
    public static function pdfReusoParaNas(array $lineaNas, array $resultadosPapel): ?string
    {
        $formulario = (string) ($lineaNas['formulario'] ?? '');
        $documentoId = (int) ($lineaNas['documento_id'] ?? 0);
        $leyenda = (string) ($lineaNas['leyenda'] ?? '');
        $mismoLeyenda = null;
        $original = null;
        $cualquiera = null;

        foreach ($resultadosPapel as $resultado) {
            if (self::esNas($resultado)) {
                continue;
            }
            if ((string) ($resultado['formulario'] ?? '') !== $formulario) {
                continue;
            }
            if ((int) ($resultado['documento_id'] ?? 0) !== $documentoId) {
                continue;
            }
            $ruta = (string) ($resultado['pdf_path'] ?? '');
            if ($ruta === '' || ! is_file($ruta)) {
                continue;
            }
            $cualquiera ??= $ruta;
            $leyendaPapel = (string) ($resultado['leyenda'] ?? '');
            if (strcasecmp($leyendaPapel, $leyenda) === 0) {
                $mismoLeyenda = $ruta;
            }
            if (self::esLeyendaOriginal($leyendaPapel)) {
                $original ??= $ruta;
            }
        }

        return $mismoLeyenda ?? $original ?? $cualquiera;
    }

    private static function esLeyendaOriginal(string $leyenda): bool
    {
        $texto = strtoupper(trim($leyenda));

        return $texto === 'ORIGINAL' || $texto === 'ORI';
    }
}
