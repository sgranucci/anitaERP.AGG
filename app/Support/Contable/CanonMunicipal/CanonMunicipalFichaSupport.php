<?php

declare(strict_types=1);

namespace App\Support\Contable\CanonMunicipal;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Canon_Municipal_Config;
use App\Support\Configuracion\EmpresaLogoArchivo;

/**
 * Ficha de presentación municipal: config + datos de empresa.
 */
final class CanonMunicipalFichaSupport
{
    /**
     * @return array<string, mixed>|null
     */
    public static function resolver(int $empresaId): ?array
    {
        if ($empresaId <= 0) {
            return null;
        }

        /** @var Canon_Municipal_Config|null $config */
        $config = Canon_Municipal_Config::query()
            ->with('empresa')
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->first();

        if ($config === null) {
            return null;
        }

        /** @var Empresa|null $empresa */
        $empresa = $config->empresa;
        if ($empresa === null) {
            $empresa = Empresa::query()->find($empresaId);
        }
        if ($empresa === null) {
            return null;
        }

        $nombre = trim((string) $empresa->nombre);
        $logoPath = EmpresaLogoArchivo::rutaPngEmpresa($nombre)
            ?? EmpresaLogoArchivo::rutaPngDefault();

        return [
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'cuit' => trim((string) ($empresa->nroinscripcion ?? '')),
            'domicilio' => trim((string) ($empresa->domicilio ?? '')),
            'codigopostal' => trim((string) ($empresa->codigopostal ?? '')),
            'municipio' => trim((string) $config->municipio),
            'legajo' => trim((string) $config->legajo),
            'periodicidad' => (string) $config->periodicidad,
            'plantilla' => (string) $config->plantilla,
            'alicuota' => (float) $config->alicuota,
            'firmante_nombre' => trim((string) $config->firmante_nombre),
            'firmante_cargo' => trim((string) $config->firmante_cargo),
            'pie_razon_social' => trim((string) ($config->pie_razon_social ?: $nombre)),
            'direccion_extra' => trim((string) ($config->direccion_extra ?? '')),
            'telefono' => trim((string) ($config->telefono ?? '')),
            'logo_path' => $logoPath,
            'config_id' => (int) $config->id,
        ];
    }

    /**
     * Mapa empresa_id → meta para el JS del filtro (periodicidad / plantilla).
     *
     * @return array<int, array{periodicidad: string, plantilla: string, nombre: string}>
     */
    public static function mapaEmpresasActivas(): array
    {
        $rows = Canon_Municipal_Config::query()
            ->with('empresa:id,nombre')
            ->where('activo', true)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->empresa_id] = [
                'periodicidad' => (string) $row->periodicidad,
                'plantilla' => (string) $row->plantilla,
                'nombre' => (string) ($row->empresa->nombre ?? ''),
            ];
        }

        return $out;
    }
}
