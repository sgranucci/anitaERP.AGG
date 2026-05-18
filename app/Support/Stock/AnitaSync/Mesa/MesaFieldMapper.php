<?php

namespace App\Support\Stock\AnitaSync\Mesa;

/**
 * Mapeo campo a campo: mesa (Anita) → mesa_gastronomia (ERP).
 */
final class MesaFieldMapper
{
    public static function mapCodigo(object $row): ?string
    {
        $codigo = (int) ($row->mes_codigo ?? 0);

        return $codigo > 0 ? (string) $codigo : null;
    }

    public static function mapNombre(object $row): string
    {
        $detalle = trim((string) ($row->mes_detalle ?? ''));
        if ($detalle !== '') {
            return $detalle;
        }

        return trim((string) ($row->mes_clave ?? '')) ?: 'Mesa sin detalle';
    }

    public static function mapUbicacionNombre(object $row): ?string
    {
        $v = trim((string) ($row->mes_ubicacion ?? ''));

        return $v !== '' ? $v : null;
    }

    public static function mapNumeromesa(object $row): string
    {
        $clave = trim((string) ($row->mes_clave ?? ''));
        if ($clave !== '') {
            return $clave;
        }

        $codigo = (int) ($row->mes_codigo ?? 0);

        return $codigo > 0 ? (string) $codigo : '0';
    }

    public static function mapEmpresaId(): int
    {
        return (int) config('mesa_gastronomia_anita.empresa_default_id', 1);
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapAll(object $row): array
    {
        return [
            'codigo' => self::mapCodigo($row),
            'nombre' => self::mapNombre($row),
            'ubicacion_nombre' => self::mapUbicacionNombre($row),
            'numeromesa' => self::mapNumeromesa($row),
            'empresa_id' => self::mapEmpresaId(),
        ];
    }
}
