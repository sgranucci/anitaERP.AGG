<?php

namespace App\Support\Ventas;

/**
 * Códigos de unidad de medida ARCA (WSFEX / MTXCA).
 *
 * En ERP `unidadmedida.codigo` suele venir vacío (sync Anita no lo trae) o con
 * ids internos Anita (13xx). Cast a int de vacío = 0 → WSFEX [1775].
 */
final class ArcaUnidadMedidaAfipSupport
{
    /** UMed AFIP que no admiten cantidad/precio/bonificación (solo total ítem). */
    private const UMED_SIN_CANTIDAD = [0, 97, 99];

    /**
     * @param  object|null  $unidad  Modelo con codigo / abreviatura / nombre
     */
    public static function codigoAfip(?object $unidad): int
    {
        if ($unidad === null) {
            return 7;
        }

        $raw = $unidad->codigo ?? null;
        $codigo = is_numeric($raw) ? (int) $raw : 0;
        if (self::esCuantificable($codigo)) {
            return $codigo;
        }

        return self::desdeAbreviatura(
            (string) ($unidad->abreviatura ?? ''),
            (string) ($unidad->nombre ?? '')
        );
    }

    public static function esCuantificable(int $codigoAfip): bool
    {
        return $codigoAfip >= 1 && $codigoAfip <= 96 && ! in_array($codigoAfip, self::UMED_SIN_CANTIDAD, true);
    }

    public static function esSinCantidad(int $codigoAfip): bool
    {
        return in_array($codigoAfip, self::UMED_SIN_CANTIDAD, true);
    }

    public static function desdeAbreviatura(string $abreviatura, string $nombre = ''): int
    {
        $key = strtoupper(trim($abreviatura));
        $key = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $key);

        $mapa = [
            'KG' => 1,
            'KGS' => 1,
            'KILO' => 1,
            'KILOS' => 1,
            'MT' => 2,
            'M' => 2,
            'METRO' => 2,
            'METROS' => 2,
            'M2' => 3,
            'M²' => 3,
            'M3' => 4,
            'M³' => 4,
            'LT' => 5,
            'L' => 5,
            'LITRO' => 5,
            'LITROS' => 5,
            'UN' => 7,
            'U' => 7,
            'UND' => 7,
            'UNIDAD' => 7,
            'UNIDADES' => 7,
            'HS' => 7,
            'HORA' => 7,
            'HORAS' => 7,
            'MIN' => 7,
            'MINUTO' => 7,
            'MINUTOS' => 7,
            'GR' => 14,
            'GRAMO' => 14,
            'GRAMOS' => 14,
            'TN' => 29,
            'TON' => 29,
            'TONELADA' => 29,
            'TONELADAS' => 29,
        ];

        if ($key !== '' && isset($mapa[$key])) {
            return $mapa[$key];
        }

        $nombreUp = strtoupper(trim($nombre));
        $nombreUp = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $nombreUp);
        foreach ($mapa as $abrev => $codigo) {
            if ($abrev !== '' && str_contains($nombreUp, $abrev)) {
                return $codigo;
            }
        }

        // Default AFIP: unidades
        return 7;
    }

    /**
     * Normaliza un código ya presente en el payload (posible vacío / Anita).
     */
    public static function normalizarCodigoPayload(mixed $codigo, ?string $abreviatura = null, ?string $nombre = null): int
    {
        $umed = is_numeric($codigo) ? (int) $codigo : 0;
        if (self::esCuantificable($umed)) {
            return $umed;
        }

        return self::desdeAbreviatura((string) ($abreviatura ?? ''), (string) ($nombre ?? ''));
    }
}
