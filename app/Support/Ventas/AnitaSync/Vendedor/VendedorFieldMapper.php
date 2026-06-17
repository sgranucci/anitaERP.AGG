<?php

namespace App\Support\Ventas\AnitaSync\Vendedor;

use App\Models\Configuracion\Empresa;

/**
 * Mapeo campo a campo: vendedor (Anita) → vendedor (ERP).
 */
final class VendedorFieldMapper
{
    public static function mapCodigo(object $row): ?string
    {
        $raw = trim((string) ($row->vend_codigo ?? ''));
        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            $norm = ltrim($raw, '0');

            return $norm !== '' ? $norm : '0';
        }

        return $raw;
    }

    public static function mapNombre(object $row): string
    {
        return trim((string) ($row->vend_nombre ?? ''));
    }

    public static function mapAplicaSobre(object $row): string
    {
        $aplicacion = strtoupper(trim((string) ($row->vend_aplicacion ?? '')));

        return $aplicacion === 'B' ? 'Sobre Bruto' : 'Sobre Neto';
    }

    public static function mapEstado(object $row): string
    {
        $estado = strtoupper(trim((string) ($row->vend_estado ?? '')));

        return $estado === 'N' ? 'No Carga Clientes' : 'Activo';
    }

    public static function mapEmpresaId(object $row): int
    {
        $codigoEmpresaAnita = (int) ($row->vend_empresa ?? 0);
        if ($codigoEmpresaAnita > 0) {
            $porCodigo = Empresa::query()
                ->where('codigo', (string) $codigoEmpresaAnita)
                ->first();
            if ($porCodigo) {
                return (int) $porCodigo->id;
            }

            $porId = Empresa::query()->where('id', $codigoEmpresaAnita)->first();
            if ($porId) {
                return (int) $porId->id;
            }
        }

        return (int) config('vendedor_anita.empresa_id_default', 1);
    }

    public static function mapLegajoId(object $row): ?int
    {
        $legajo = (int) ($row->vend_legajo ?? 0);

        return $legajo > 0 ? $legajo : null;
    }

    public static function mapEmail(object $row): ?string
    {
        if (! isset($row->vend_email)) {
            return null;
        }
        $email = trim((string) $row->vend_email);

        return $email !== '' ? $email : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapAll(object $row): array
    {
        return [
            'codigo' => self::mapCodigo($row),
            'nombre' => self::mapNombre($row),
            'comisionventa' => (float) ($row->vend_comision_vta ?? 0),
            'comisioncobranza' => (float) ($row->vend_comision_cob ?? 0),
            'aplicasobre' => self::mapAplicaSobre($row),
            'empresa_id' => self::mapEmpresaId($row),
            'legajo_id' => self::mapLegajoId($row),
            'email' => self::mapEmail($row),
            'estado' => self::mapEstado($row),
        ];
    }
}
