<?php

namespace App\Support\Ventas\AnitaImport;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Resuelve el formato Informix de climov/aplmov según la instalación (EMPRESA).
 */
final class ClienteCuentacorrienteAnitaImportFormatoSupport
{
    /** @var list<string> */
    private const CAMPOS_CLIMOV_BASE = [
        'cliv_cliente',
        'cliv_tipo',
        'cliv_letra',
        'cliv_sucursal',
        'cliv_nro',
        'cliv_ref_tipo',
        'cliv_ref_letra',
        'cliv_ref_sucursal',
        'cliv_ref_nro',
        'cliv_fecha',
        'cliv_fecha_vto',
        'cliv_monto',
        'cliv_t_cobrado',
        'cliv_estado',
        'cliv_cod_mon',
        'cliv_cotizacion',
        'cliv_nro_cuota',
    ];

    /** @var list<string> */
    private const CAMPOS_APLMOV_BASE = [
        'aplv_tipo',
        'aplv_letra',
        'aplv_sucursal',
        'aplv_nro',
        'aplv_nro_cuota',
        'aplv_monto',
        'aplv_fecha',
        'aplv_tipo_cob',
        'aplv_letra_cob',
        'aplv_sucursal_cob',
        'aplv_nro_cob',
        'aplv_fecha_aplic',
        'aplv_ref_tipo',
        'aplv_ref_letra',
        'aplv_ref_sucursal',
        'aplv_ref_nro',
        'aplv_cod_mon',
        'aplv_cotizacion',
    ];

    /**
     * @return array{
     *   sistema: string,
     *   tabla_climov: string,
     *   tabla_aplmov: string,
     *   climov_tiene_empresa: bool,
     *   campos_climov: string,
     *   campos_aplmov: string,
     *   aplmov_fallback_ref_como_cob: bool,
     *   tipos_no_deuda: list<string>,
     *   tipos_credito_sin_venta: list<string>,
     *   tolerancia_aplicado: float,
     *   bridge_list_reintentos: int,
     *   entorno: string
     * }
     */
    public static function perfil(): array
    {
        $cfg = config('cliente_cuentacorriente_anita', []);
        $tieneEmpresa = self::resolverTieneEmpresa($cfg['climov_tiene_empresa'] ?? null);

        $camposClimovOverride = trim((string) ($cfg['campos_climov'] ?? ''));
        $camposAplmovOverride = trim((string) ($cfg['campos_aplmov'] ?? ''));

        $camposClimov = $camposClimovOverride !== ''
            ? self::normalizarCampos($camposClimovOverride)
            : self::camposClimovDefault($tieneEmpresa);

        $camposAplmov = $camposAplmovOverride !== ''
            ? self::normalizarCampos($camposAplmovOverride)
            : implode(',', self::CAMPOS_APLMOV_BASE);

        return [
            'sistema' => (string) ($cfg['sistema'] ?? 'ventas'),
            'tabla_climov' => (string) ($cfg['tabla_climov'] ?? 'climov'),
            'tabla_aplmov' => (string) ($cfg['tabla_aplmov'] ?? 'aplmov'),
            'climov_tiene_empresa' => $tieneEmpresa,
            'campos_climov' => $camposClimov,
            'campos_aplmov' => $camposAplmov,
            'aplmov_fallback_ref_como_cob' => (bool) ($cfg['aplmov_fallback_ref_como_cob'] ?? true),
            'tipos_no_deuda' => array_values(array_map(
                static fn ($t) => ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) $t),
                (array) ($cfg['tipos_no_deuda'] ?? [])
            )),
            'tipos_credito_sin_venta' => array_values(array_map(
                static fn ($t) => ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) $t),
                (array) ($cfg['tipos_credito_sin_venta'] ?? ['COA'])
            )),
            'tolerancia_aplicado' => (float) ($cfg['tolerancia_aplicado'] ?? 0.02),
            'bridge_list_reintentos' => max(1, (int) ($cfg['bridge_list_reintentos'] ?? 6)),
            'entorno' => EntornoEmpresaSupport::codigo(),
        ];
    }

    public static function esTipoNoDeuda(string $tipo, ?array $perfil = null): bool
    {
        $perfil ??= self::perfil();
        $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo($tipo);

        return $tipo !== '' && in_array($tipo, $perfil['tipos_no_deuda'], true);
    }

    public static function esTipoCreditoSinVenta(string $tipo, ?array $perfil = null): bool
    {
        $perfil ??= self::perfil();
        $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo($tipo);

        return $tipo !== '' && in_array($tipo, $perfil['tipos_credito_sin_venta'], true);
    }

    private static function resolverTieneEmpresa(mixed $envValor): bool
    {
        if ($envValor !== null && $envValor !== '') {
            return filter_var($envValor, FILTER_VALIDATE_BOOLEAN);
        }

        return EntornoEmpresaSupport::esAgg();
    }

    private static function camposClimovDefault(bool $tieneEmpresa): string
    {
        $campos = self::CAMPOS_CLIMOV_BASE;
        if ($tieneEmpresa) {
            $campos[] = 'cliv_empresa';
        }

        return implode(',', $campos);
    }

    private static function normalizarCampos(string $campos): string
    {
        $partes = array_values(array_filter(array_map(
            static fn ($c) => trim((string) $c),
            explode(',', $campos)
        )));

        return implode(',', $partes);
    }
}
