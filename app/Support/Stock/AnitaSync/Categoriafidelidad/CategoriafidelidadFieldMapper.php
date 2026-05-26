<?php

namespace App\Support\Stock\AnitaSync\Categoriafidelidad;

/**
 * Mapeo campo a campo: clicat (Anita) → categoriafidelidad_gastronomia (ERP).
 */
final class CategoriafidelidadFieldMapper
{
    public static function mapCodigo(object $row): ?string
    {
        $codigo = (int) ($row->clcat_categoria ?? 0);

        return $codigo > 0 ? (string) $codigo : null;
    }

    public static function mapNombre(object $row): string
    {
        $desc = trim((string) ($row->clcat_desc ?? ''));
        if ($desc !== '') {
            return $desc;
        }

        $codigo = (int) ($row->clcat_categoria ?? 0);

        return $codigo > 0 ? 'Categoría '.$codigo : 'Categoría sin descripción';
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapAll(object $row): array
    {
        return [
            'codigo' => self::mapCodigo($row),
            'nombre' => self::mapNombre($row),
        ];
    }
}
