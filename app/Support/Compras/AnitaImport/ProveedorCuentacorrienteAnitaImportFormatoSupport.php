<?php

namespace App\Support\Compras\AnitaImport;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Formato Informix compra/promov/aplmovp según instalación (EMPRESA).
 */
final class ProveedorCuentacorrienteAnitaImportFormatoSupport
{
    /** @var list<string> */
    private const CAMPOS_PROMOV_BASE = [
        'prov_proveedor',
        'prov_tipo',
        'prov_letra',
        'prov_sucursal',
        'prov_nro',
        'prov_fecha',
        'prov_fecha_vto',
        'prov_monto',
        'prov_t_pagado',
        'prov_cod_mon',
        'prov_cotizacion',
        'prov_nro_cuota',
        'prov_nro_interno',
        'prov_ref_tipo',
        'prov_fecha_pago',
    ];

    /** @var list<string> */
    private const CAMPOS_COMPRA_BASE = [
        'com_proveedor',
        'com_tipo',
        'com_letra',
        'com_sucursal',
        'com_nro',
        'com_fecha',
        'com_fecha_iva',
        'com_monto',
        'com_cod_mon',
        'com_cotizacion',
        'com_nro_interno',
        'com_condicion_pago',
        'com_cuit_prov',
        'com_nombre_prov',
        'com_leyenda',
        'com_cond_iva_prov',
    ];

    /** @var list<string> */
    private const CAMPOS_APLMOVP_BASE = [
        'aplvp_proveedor',
        'aplvp_tipo',
        'aplvp_letra',
        'aplvp_sucursal',
        'aplvp_nro',
        'aplvp_fecha',
        'aplvp_monto',
        'aplvp_tipo_cob',
        'aplvp_letra_cob',
        'aplvp_sucursal_cob',
        'aplvp_nro_cob',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function perfil(): array
    {
        $cfg = config('proveedor_cuentacorriente_anita', []);
        $tieneEmpresa = self::resolverTieneEmpresa($cfg['tiene_empresa'] ?? null);

        $promov = trim((string) ($cfg['campos_promov'] ?? ''));
        $compra = trim((string) ($cfg['campos_compra'] ?? ''));
        $aplmovp = trim((string) ($cfg['campos_aplmovp'] ?? ''));

        $camposPromov = $promov !== '' ? self::normalizarCampos($promov) : implode(',', self::CAMPOS_PROMOV_BASE);
        $camposCompra = $compra !== '' ? self::normalizarCampos($compra) : implode(',', self::CAMPOS_COMPRA_BASE);
        if ($tieneEmpresa) {
            if (! str_contains($camposPromov, 'prov_empresa')) {
                $camposPromov .= ',prov_empresa';
            }
            if (! str_contains($camposCompra, 'com_empresa')) {
                $camposCompra .= ',com_empresa';
            }
        }

        return [
            'sistema' => (string) ($cfg['sistema'] ?? 'compras'),
            'tabla_promov' => (string) ($cfg['tabla_promov'] ?? 'promov'),
            'tabla_compra' => (string) ($cfg['tabla_compra'] ?? 'compra'),
            'tabla_aplmovp' => (string) ($cfg['tabla_aplmovp'] ?? 'aplmovp'),
            'tiene_empresa' => $tieneEmpresa,
            'campos_promov' => $camposPromov,
            'campos_compra' => $camposCompra,
            'campos_aplmovp' => $aplmovp !== '' ? self::normalizarCampos($aplmovp) : implode(',', self::CAMPOS_APLMOVP_BASE),
            'tipos_no_deuda' => array_values(array_map(
                static fn ($t) => ComprobanteProveedorAnitaImportClaveSupport::tipo((string) $t),
                (array) ($cfg['tipos_no_deuda'] ?? [])
            )),
            'tolerancia_aplicado' => (float) ($cfg['tolerancia_aplicado'] ?? 0.02),
            'empresa_id_default' => max(1, (int) ($cfg['empresa_id_default'] ?? 1)),
            'bridge_list_reintentos' => max(1, (int) ($cfg['bridge_list_reintentos'] ?? 6)),
            'entorno' => EntornoEmpresaSupport::codigo(),
        ];
    }

    public static function esTipoNoDeuda(string $tipo, ?array $perfil = null): bool
    {
        $perfil ??= self::perfil();
        $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo($tipo);

        return $tipo !== '' && in_array($tipo, $perfil['tipos_no_deuda'], true);
    }

    private static function resolverTieneEmpresa(mixed $envValor): bool
    {
        if ($envValor !== null && $envValor !== '') {
            return filter_var($envValor, FILTER_VALIDATE_BOOLEAN);
        }

        return EntornoEmpresaSupport::esAgg();
    }

    private static function normalizarCampos(string $campos): string
    {
        return implode(',', array_values(array_filter(array_map(
            static fn ($c) => trim((string) $c),
            explode(',', $campos)
        ))));
    }
}
