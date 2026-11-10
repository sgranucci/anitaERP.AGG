<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

use App\Models\Contable\Sicore_Config;

/**
 * Cuentas de una configuración SICORE.
 * Si el régimen tiene cuentas cargadas para la empresa, esas mandan.
 * Ganancias sin cuentas en la configuración cae a la cuenta automática RGP.
 */
final class SicoreConfigCuentaSupport
{
    /**
     * @return list<array{id: int, codigo: string, nombre: string, tipocuenta: ?string}>
     */
    public static function detalle(Sicore_Config $config, int $empresaId): array
    {
        $desdeConfig = self::detalleConfig($config, $empresaId);
        if ($desdeConfig !== []) {
            return $desdeConfig;
        }

        if ($config->criterio === 'compras_ganancias') {
            $rgp = SicoreCuentaRgpSupport::ganancias($empresaId);
            if ($rgp !== null) {
                return [[
                    'id' => $rgp['id'],
                    'codigo' => $rgp['codigo'],
                    'nombre' => $rgp['nombre'],
                    'tipocuenta' => $rgp['tipocuenta'],
                ]];
            }
        }

        return [];
    }

    /**
     * Códigos numéricos Anita de las cuentas que usa el proceso.
     *
     * @return list<int>
     */
    public static function codigosAnita(Sicore_Config $config, int $empresaId): array
    {
        $codigos = [];
        foreach (self::detalle($config, $empresaId) as $cuenta) {
            $codigo = (int) preg_replace('/\D/', '', (string) ($cuenta['codigo'] ?? ''));
            if ($codigo > 0) {
                $codigos[$codigo] = $codigo;
            }
        }

        return array_values($codigos);
    }

    /**
     * @return list<array{id: int, codigo: string, nombre: string, tipocuenta: ?string}>
     */
    private static function detalleConfig(Sicore_Config $config, int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        if (! $config->relationLoaded('cuentas')) {
            $config->load('cuentas.cuentacontable');
        }

        return $config->cuentas
            ->where('empresa_id', $empresaId)
            ->map(static fn ($c) => [
                'id' => (int) $c->cuentacontable_id,
                'codigo' => (string) ($c->cuentacontable?->codigo ?? ''),
                'nombre' => (string) ($c->cuentacontable?->nombre ?? ''),
                'tipocuenta' => $c->cuentacontable?->tipocuenta !== null
                    ? (string) $c->cuentacontable->tipocuenta
                    : null,
            ])
            ->filter(static fn (array $c) => $c['codigo'] !== '')
            ->values()
            ->all();
    }
}
