<?php

namespace App\Support\Stock\AnitaSync\Puntoventa;

use App\Models\Configuracion\Empresa;

/**
 * Mapeo sucursal (Anita) → puntoventa (ERP), alineado a la lógica histórica del repositorio de ventas
 * (switch suc_fiscal, nombre = suc_empresa, leyenda/pathafip, división/póliza/remito, reglas por texto en suc_empresa).
 * Correcciones: estado A/S (no ACTIVA/SUSPENDIDA); empresa por suc_nroemp → empresa.codigo cuando exista.
 */
final class PuntoventaFieldMapper
{
    public static function mapCodigo(object $row): ?string
    {
        $n = (int) ($row->suc_numero ?? 0);

        return $n > 0 ? trim((string) $n) : null;
    }

    public static function mapNombre(object $row): string
    {
        $nombre = trim((string) ($row->suc_empresa ?? ''));

        return $nombre !== '' ? $nombre : 'Sucursal '.(string) ($row->suc_numero ?? '');
    }

    public static function mapEmpresaId(object $row): int
    {
        $nroEmp = (int) ($row->suc_nroemp ?? 0);
        if ($nroEmp > 0) {
            $empresa = Empresa::query()->where('codigo', (string) $nroEmp)->first();
            if ($empresa) {
                return (int) $empresa->id;
            }
        }

        $empresaTxt = strtoupper((string) ($row->suc_empresa ?? ''));
        $id = (int) config('puntoventa_anita.empresa_id_default', 3);
        foreach (config('puntoventa_anita.empresa_por_fragmento_suc_empresa', []) as $rule) {
            $frag = strtoupper(trim((string) ($rule['fragmento'] ?? '')));
            if ($frag !== '' && str_contains($empresaTxt, $frag)) {
                $id = (int) ($rule['empresa_id'] ?? $id);
            }
        }

        return $id;
    }

    /**
     * Mismo criterio que el switch suc_fiscal del repositorio histórico:
     * N→M, E→E, L→C, A→A, R→R, M→L, O→O, I→I.
     */
    public static function mapModoFacturacion(object $row): string
    {
        $fiscal = strtoupper(trim((string) ($row->suc_fiscal ?? '')));

        return match ($fiscal) {
            'N' => 'M',
            'E' => 'E',
            'L' => 'C',
            'A' => 'A',
            'R' => 'R',
            'M' => 'L',
            'O' => 'O',
            'I' => 'I',
            default => 'M',
        };
    }

    /** suc_empresa == 'BAJA' → suspendido (repositorio usaba texto equivocado para el enum). */
    public static function mapEstado(object $row): string
    {
        if (trim((string) ($row->suc_empresa ?? '')) === 'BAJA') {
            return 'S';
        }

        return 'A';
    }

    private static function strProp(object $row, string $key): string
    {
        return trim((string) ($row->{$key} ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapAll(object $row): array
    {
        $codigo = self::mapCodigo($row);
        $domicilio = self::strProp($row, 'suc_direccion');
        if ($domicilio === '') {
            $domicilio = '-';
        }

        $codPostal = self::strProp($row, 'suc_cod_postal');

        $division = isset($row->suc_division) ? $row->suc_division : null;
        $numeropoliza = self::strProp($row, 'suc_poliza');
        $numeropoliza = $numeropoliza !== '' ? $numeropoliza : null;
        $puntoventaRemito = isset($row->suc_suc_remito) ? $row->suc_suc_remito : null;

        return [
            'nombre' => self::mapNombre($row),
            'codigo' => $codigo,
            'empresa_id' => self::mapEmpresaId($row),
            'domicilio' => $domicilio,
            'provincia_id' => (int) config('puntoventa_anita.default_provincia_id', 3),
            'localidad_id' => (int) config('puntoventa_anita.default_localidad_id', 108),
            'pais_id' => (int) config('puntoventa_anita.default_pais_id', 1),
            'codigopostal' => $codPostal !== '' ? $codPostal : null,
            'telefono' => self::strProp($row, 'suc_telefono') ?: null,
            'email' => null,
            'leyenda' => self::strProp($row, 'suc_leyenda1') ?: null,
            'modofacturacion' => self::mapModoFacturacion($row),
            'estado' => self::mapEstado($row),
            'webservice' => 'wsfev1',
            'pathafip' => self::strProp($row, 'suc_leyenda2') ?: null,
            'actividad_arca_id' => null,
            'division' => $division,
            'numeropoliza' => $numeropoliza,
            'puntoventa_remito' => $puntoventaRemito,
        ];
    }
}
