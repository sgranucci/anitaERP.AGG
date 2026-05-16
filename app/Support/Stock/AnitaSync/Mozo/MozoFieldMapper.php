<?php

namespace App\Support\Stock\AnitaSync\Mozo;

use App\Models\Configuracion\Empresa;

/**
 * Mapeo campo a campo: vendedor (Anita) → mozo_gastronomia (ERP).
 */
final class MozoFieldMapper
{
    public static function mapCodigo(object $row): ?string
    {
        $codigo = (int) ($row->vend_codigo ?? 0);

        return $codigo > 0 ? (string) $codigo : null;
    }

    public static function mapNombre(object $row): string
    {
        $nombre = trim((string) ($row->vend_nombre ?? ''));
        if ($nombre !== '') {
            return $nombre;
        }

        $codigo = (int) ($row->vend_codigo ?? 0);

        return $codigo > 0 ? 'Mozo '.$codigo : 'Mozo sin nombre';
    }

    public static function mapEmpresaId(object $row): int
    {
        $codigoEmpresaAnita = (int) ($row->vend_empresa ?? 0);
        if ($codigoEmpresaAnita > 0) {
            $empresa = Empresa::query()
                ->where('codigo', (string) $codigoEmpresaAnita)
                ->first();
            if ($empresa) {
                return (int) $empresa->id;
            }
        }

        return (int) config('mozo_gastronomia_anita.empresa_default_id', 1);
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapAll(object $row): array
    {
        return [
            'codigo' => self::mapCodigo($row),
            'nombre' => self::mapNombre($row),
            'empresa_id' => self::mapEmpresaId($row),
        ];
    }
}
