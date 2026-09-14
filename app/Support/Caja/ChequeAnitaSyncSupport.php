<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\SqlDialectSupport;

/**
 * Lectura Anita para sync de cheques al ERP.
 *
 * Quirks de schema Ferli (sin cpro_empresa / cter_empresa, cuentas multiempresa):
 * solo aplica fallback de empresa principal en Ferli. AGG debe resolver empresa real.
 */
final class ChequeAnitaSyncSupport
{
    /**
     * @return list<object>
     */
    public static function listarCpromaeAbiertos(int $fechaDesdeYmd): array
    {
        $where = " WHERE cpro_estado IN (' ', 'N') AND cpro_fecha_cheque >= ".$fechaDesdeYmd.' ';
        $camposBase = '
                    cpro_cuenta,
                    cpro_nro_cheque,
                    cpro_fecha_cheque,
                    cpro_fecha_emision,
                    cpro_importe,
                    cpro_proveedor,
                    cpro_entregado_a,
                    cpro_nro_op,
                    cpro_cod_mon,
                    cpro_cotizacion,
                    cpro_estado,
                    cpro_contrapartida,
                    cpro_fecha_anula,
                    cpro_fl_imprimio,
                    cpro_a_nombre_de,
                    cpro_modelo,
                    cpro_para_dep';
        $camposExtendidos = $camposBase.',
                    cpro_fecha_entrega,
                    cpro_empresa,
                    cpro_negociable,
                    cpro_estado_banco,
                    cpro_sucursal_pago,
                    cpro_tipo_distrib,
                    cpro_nro_e_cheq';

        return self::listarConFallbackCampos('cpromae', $camposExtendidos, $camposBase, $where);
    }

    /**
     * CHT sin baja y con fecha cheque >= desde (todos los estados: cartera, depositados, rechazados, etc.).
     *
     * @return list<object>
     */
    public static function listarCtermaeTodos(int $fechaDesdeYmd): array
    {
        $where = ' WHERE cter_fecha_baja = 0 AND cter_fecha_cheque >= '.$fechaDesdeYmd.' ';
        $camposBase = '
                    cter_nro_interno,
                    cter_nro_cheque,
                    cter_fecha_cheque,
                    cter_fecha_ingreso,
                    cter_fecha_dep,
                    cter_fecha_acreed,
                    cter_fecha_baja,
                    cter_importe,
                    cter_cliente,
                    cter_proveedor,
                    cter_entregado_a,
                    cter_nro_recibo,
                    cter_nro_op,
                    cter_banco_emision,
                    cter_cuenta,
                    cter_entregado_por,
                    cter_interior,
                    cter_cod_mon,
                    cter_cotizacion,
                    cter_estado,
                    cter_cedio_a,
                    cter_sucursal_bco,
                    cter_cta_libradora,
                    cter_cod_banco,
                    cter_cuit_emisor,
                    cter_nro_boleta,
                    cter_nro_caucion';
        $camposExtendidos = $camposBase.',
                    cter_empresa';

        if (EntornoEmpresaSupport::esFerli()) {
            return self::listarConFallbackCampos('ctermae', $camposBase, $camposBase, $where);
        }

        return self::listarConFallbackCampos('ctermae', $camposExtendidos, $camposBase, $where);
    }

    /**
     * Cartera CHT sin baja, estado en cartera (espacio / N), recientes.
     *
     * @return list<object>
     */
    public static function listarCtermaeEnCartera(int $fechaDesdeYmd): array
    {
        $where = " WHERE cter_fecha_baja = 0 AND cter_estado IN (' ', 'N') AND cter_fecha_cheque >= ".$fechaDesdeYmd.' ';
        $camposBase = '
                    cter_nro_interno,
                    cter_nro_cheque,
                    cter_fecha_cheque,
                    cter_fecha_ingreso,
                    cter_fecha_dep,
                    cter_fecha_acreed,
                    cter_fecha_baja,
                    cter_importe,
                    cter_cliente,
                    cter_proveedor,
                    cter_entregado_a,
                    cter_nro_recibo,
                    cter_nro_op,
                    cter_banco_emision,
                    cter_cuenta,
                    cter_entregado_por,
                    cter_interior,
                    cter_cod_mon,
                    cter_cotizacion,
                    cter_estado,
                    cter_cedio_a,
                    cter_sucursal_bco,
                    cter_cta_libradora,
                    cter_cod_banco,
                    cter_cuit_emisor,
                    cter_nro_boleta,
                    cter_nro_caucion';
        $camposExtendidos = $camposBase.',
                    cter_empresa';

        // Ferli: schema sin cter_empresa — no intentar extendido (UNLOAD falla).
        if (EntornoEmpresaSupport::esFerli()) {
            return self::listarConFallbackCampos('ctermae', $camposBase, $camposBase, $where);
        }

        return self::listarConFallbackCampos('ctermae', $camposExtendidos, $camposBase, $where);
    }

    /**
     * @return list<object>
     */
    private static function listarConFallbackCampos(
        string $tabla,
        string $camposPreferidos,
        string $camposFallback,
        string $where
    ): array {
        $api = new ApiAnita();
        $intentos = $camposPreferidos === $camposFallback
            ? [$camposPreferidos]
            : [$camposPreferidos, $camposFallback];

        // Ferli cpromae: schema sin columnas extendidas — ir directo a base.
        if (EntornoEmpresaSupport::esFerli() && $tabla === 'cpromae') {
            $intentos = [$camposFallback];
        }

        foreach ($intentos as $campos) {
            $dataAnita = json_decode($api->apiCall([
                'acc' => 'list',
                'sistema' => 'che_ban',
                'tabla' => $tabla,
                'campos' => $campos,
                'whereArmado' => $where,
            ]));
            if (is_array($dataAnita)) {
                return $dataAnita;
            }
        }

        return [];
    }

    /**
     * @param  mixed  $codigoEmpresaAnita
     * @param  mixed  $cuentacaja  Modelo con empresa_id opcional
     */
    public static function resolverEmpresaId($codigoEmpresaAnita, $cuentacaja, callable $findPorCodigo, callable $findPorId): ?int
    {
        $codigo = (int) preg_replace('/\D/', '', (string) ($codigoEmpresaAnita ?? ''));
        if ($codigo > 0) {
            $empresa = $findPorCodigo($codigo);
            if ($empresa) {
                return (int) $empresa->id;
            }
            $empresa = $findPorId($codigo);
            if ($empresa) {
                return (int) $empresa->id;
            }
        }

        if ($cuentacaja && ! empty($cuentacaja->empresa_id)) {
            return (int) $cuentacaja->empresa_id;
        }

        // Solo Ferli: cuentas multiempresa + Anita sin cpro/cter_empresa.
        if (EntornoEmpresaSupport::esFerli()) {
            return self::empresaPrincipalId();
        }

        return null;
    }

    public static function empresaPrincipalId(): ?int
    {
        $principal = Empresa::query()
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
            ->orderBy('id')
            ->first();

        return $principal ? (int) $principal->id : null;
    }

    public static function fechaDesdeSyncAnios(int $aniosAtras = 2): int
    {
        $anio = (int) date('Y') - max(0, $aniosAtras);

        return $anio * 10000 + 101;
    }
}
