<?php

declare(strict_types=1);

namespace App\Support\Compras\AnitaImport;

use App\ApiAnita;
use App\Support\Compras\PagosSabanaAnitaBridgeReader;
use Illuminate\Support\Facades\Log;

/**
 * Lecturas Anita para enriquecer impresión de OP importadas: auxpag + ret*mov por período.
 * Solo lectura; no escribe en Anita.
 */
final class PagoproveedorAnitaImpresionBridgeReader
{
    private const AUXPAG_CAMPOS = 'axp_empresa,axp_fecha,axp_tipo,axp_rec,axp_pro,axp_nro,axp_tipo_ap,'
        .'axp_monto_ap,axp_cod_mon_co,axp_banco,axp_letra_comp,axp_sucursal,axp_sucursal_cob,'
        .'axp_nro_interno,axp_concepto,axp_fecha_co,axp_cbu';

    /** Crown / mono: sin axp_empresa, axp_concepto, axp_cbu. */
    private const AUXPAG_CAMPOS_MONO = 'axp_fecha,axp_tipo,axp_rec,axp_pro,axp_nro,axp_tipo_ap,'
        .'axp_monto_ap,axp_cod_mon_co,axp_banco,axp_letra_comp,axp_sucursal,axp_sucursal_cob,'
        .'axp_nro_interno,axp_fecha_co';

    private const RETMOV_CAMPOS = 'retv_proveedor,retv_tipo,retv_letra,retv_sucursal,retv_nro,retv_fecha,'
        .'retv_codigo_ret,retv_gravado,retv_pago_actual,retv_pago_anterior,retv_sujeto,retv_retencion,'
        .'retv_porc_ret,retv_nro_retencion,retv_ret_mes,retv_ret_anterior,retv_nombre_prov,retv_cuit_prov,'
        .'retv_porc_excl,retv_empresa';

    private const RETMOV_CAMPOS_MONO = 'retv_proveedor,retv_tipo,retv_letra,retv_sucursal,retv_nro,retv_fecha,'
        .'retv_codigo_ret,retv_gravado,retv_pago_actual,retv_pago_anterior,retv_sujeto,retv_retencion,'
        .'retv_porc_ret,retv_nro_retencion,retv_ret_mes,retv_ret_anterior,retv_nombre_prov,retv_cuit_prov,'
        .'retv_porc_excl';

    private const RETIMOV_CAMPOS = 'retiv_proveedor,retiv_tipo,retiv_letra,retiv_sucursal,retiv_nro,retiv_fecha,'
        .'retiv_codigo_ret,retiv_gravado,retiv_iva,retiv_pago_actual,retiv_sujeto,retiv_retencion,retiv_porc_ret,'
        .'retiv_nro_ret,retiv_tipo_comp,retiv_letra_comp,retiv_suc_comp,retiv_nro_comp,retiv_fecha_comp,'
        .'retiv_nombre_prov,retiv_cuit_prov,retiv_empresa';

    private const RETIMOV_CAMPOS_MONO = 'retiv_proveedor,retiv_tipo,retiv_letra,retiv_sucursal,retiv_nro,retiv_fecha,'
        .'retiv_codigo_ret,retiv_gravado,retiv_iva,retiv_pago_actual,retiv_sujeto,retiv_retencion,retiv_porc_ret,'
        .'retiv_nro_ret,retiv_tipo_comp,retiv_letra_comp,retiv_suc_comp,retiv_nro_comp,retiv_fecha_comp,'
        .'retiv_nombre_prov,retiv_cuit_prov';

    private const RETSMOV_CAMPOS = 'retsv_proveedor,retsv_tipo,retsv_letra,retsv_sucursal,retsv_nro,retsv_fecha,'
        .'retsv_codigo_ret,retsv_gravado,retsv_retencion,retsv_porc_ret,retsv_nro_ret,retsv_empresa,retsv_base_calculo';

    private const RETSMOV_CAMPOS_MONO = 'retsv_proveedor,retsv_tipo,retsv_letra,retsv_sucursal,retsv_nro,retsv_fecha,'
        .'retsv_codigo_ret,retsv_gravado,retsv_retencion,retsv_porc_ret,retsv_nro_ret,retsv_base_calculo';

    private const RETIBRMOV_CAMPOS = 'retibr_proveedor,retibr_tipo,retibr_letra,retibr_sucursal,retibr_nro,retibr_fecha,'
        .'retibr_gravado,retibr_pago_actual,retibr_sujeto,retibr_retencion,retibr_porc_ret,retibr_nro_ret,'
        .'retibr_tipo_comp,retibr_letra_comp,retibr_suc_comp,retibr_nro_comp,retibr_fecha_comp,retibr_provincia,'
        .'retibr_empresa';

    private const RETIBRMOV_CAMPOS_MONO = 'retibr_proveedor,retibr_tipo,retibr_letra,retibr_sucursal,retibr_nro,retibr_fecha,'
        .'retibr_gravado,retibr_pago_actual,retibr_sujeto,retibr_retencion,retibr_porc_ret,retibr_nro_ret,'
        .'retibr_tipo_comp,retibr_letra_comp,retibr_suc_comp,retibr_nro_comp,retibr_fecha_comp,retibr_provincia';

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita,
        private readonly PagosSabanaAnitaBridgeReader $sabana = new PagosSabanaAnitaBridgeReader,
    ) {}

    /**
     * @param  list<int>  $empresasAnita
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function listarAuxpag(array $empresasAnita, int $fechaDesdeYmd, int $fechaHastaYmd, array &$errores): array
    {
        $tieneEmpresa = PagoproveedorAnitaImportEmpresaSupport::auxpagTieneEmpresa();
        $empresasAnita = $this->normalizarEmpresas($empresasAnita, $tieneEmpresa);
        if ($tieneEmpresa && $empresasAnita === []) {
            return [];
        }

        $campos = $tieneEmpresa ? self::AUXPAG_CAMPOS : self::AUXPAG_CAMPOS_MONO;
        $where = $tieneEmpresa
            ? ' WHERE axp_empresa IN ('.implode(',', $empresasAnita).')'
                .' AND axp_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
                ." AND axp_tipo IN ('OPP','OPA')"
            : ' WHERE axp_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
                ." AND axp_tipo IN ('OPP','OPA')";

        $filas = $this->listar('che_ban', 'auxpag', $campos, $where, $errores, 'impresion-auxpag');
        if (! $tieneEmpresa) {
            foreach ($filas as $fila) {
                if (! isset($fila->axp_empresa)) {
                    $fila->axp_empresa = 0;
                }
            }
        }

        return $filas;
    }

    /**
     * @param  list<int>  $empresasAnita
     * @param  list<string>  $errores
     * @return array<string, string>
     */
    public function mapaTesmae(array $cuentas, array &$errores): array
    {
        return $this->sabana->mapaTesmae($cuentas, $errores);
    }

    /**
     * Retenciones del período, indexadas por empresa|tipo|letra|sucursal|nro.
     *
     * @param  list<int>  $empresasAnita
     * @param  list<string>  $errores
     * @return array<string, array{ganancias:list<object>,iva:list<object>,suss:list<object>,ibr:list<object>}>
     */
    public function indexarRetencionesPeriodo(
        array $empresasAnita,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        array &$errores,
    ): array {
        $tieneEmpresa = PagoproveedorAnitaImportEmpresaSupport::auxpagTieneEmpresa();
        $empresasAnita = $this->normalizarEmpresas($empresasAnita, $tieneEmpresa);
        if ($tieneEmpresa && $empresasAnita === []) {
            return [];
        }

        $tipos = "('OPP','OPA')";
        $rango = ' BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd;
        $inEmp = $tieneEmpresa ? implode(',', $empresasAnita) : '';

        $gan = $this->listar(
            'compras',
            'retmov',
            $tieneEmpresa ? self::RETMOV_CAMPOS : self::RETMOV_CAMPOS_MONO,
            $tieneEmpresa
                ? ' WHERE retv_empresa IN ('.$inEmp.') AND retv_fecha'.$rango.' AND retv_tipo IN '.$tipos
                : ' WHERE retv_fecha'.$rango.' AND retv_tipo IN '.$tipos,
            $errores,
            'impresion-retmov'
        );
        $iva = $this->listar(
            'compras',
            'retimov',
            $tieneEmpresa ? self::RETIMOV_CAMPOS : self::RETIMOV_CAMPOS_MONO,
            $tieneEmpresa
                ? ' WHERE retiv_empresa IN ('.$inEmp.') AND retiv_fecha'.$rango.' AND retiv_tipo IN '.$tipos
                : ' WHERE retiv_fecha'.$rango.' AND retiv_tipo IN '.$tipos,
            $errores,
            'impresion-retimov'
        );
        $suss = $this->listar(
            'compras',
            'retsmov',
            $tieneEmpresa ? self::RETSMOV_CAMPOS : self::RETSMOV_CAMPOS_MONO,
            $tieneEmpresa
                ? ' WHERE retsv_empresa IN ('.$inEmp.') AND retsv_fecha'.$rango.' AND retsv_tipo IN '.$tipos
                : ' WHERE retsv_fecha'.$rango.' AND retsv_tipo IN '.$tipos,
            $errores,
            'impresion-retsmov'
        );
        $ibr = $this->listar(
            'compras',
            'retibrmov',
            $tieneEmpresa ? self::RETIBRMOV_CAMPOS : self::RETIBRMOV_CAMPOS_MONO,
            $tieneEmpresa
                ? ' WHERE retibr_empresa IN ('.$inEmp.') AND retibr_fecha'.$rango.' AND retibr_tipo IN '.$tipos
                : ' WHERE retibr_fecha'.$rango.' AND retibr_tipo IN '.$tipos,
            $errores,
            'impresion-retibrmov'
        );

        $out = [];
        foreach ($gan as $f) {
            if (! $tieneEmpresa && ! isset($f->retv_empresa)) {
                $f->retv_empresa = 0;
            }
            $k = $this->claveRet(
                (int) ($f->retv_empresa ?? 0),
                (string) ($f->retv_tipo ?? ''),
                (string) ($f->retv_letra ?? ''),
                (int) ($f->retv_sucursal ?? 0),
                (int) ($f->retv_nro ?? 0)
            );
            if ($k === '') {
                continue;
            }
            $out[$k]['ganancias'][] = $f;
            $out[$k]['iva'] ??= [];
            $out[$k]['suss'] ??= [];
            $out[$k]['ibr'] ??= [];
        }
        foreach ($iva as $f) {
            if (! $tieneEmpresa && ! isset($f->retiv_empresa)) {
                $f->retiv_empresa = 0;
            }
            $k = $this->claveRet(
                (int) ($f->retiv_empresa ?? 0),
                (string) ($f->retiv_tipo ?? ''),
                (string) ($f->retiv_letra ?? ''),
                (int) ($f->retiv_sucursal ?? 0),
                (int) ($f->retiv_nro ?? 0)
            );
            if ($k === '') {
                continue;
            }
            $out[$k]['iva'][] = $f;
            $out[$k]['ganancias'] ??= [];
            $out[$k]['suss'] ??= [];
            $out[$k]['ibr'] ??= [];
        }
        foreach ($suss as $f) {
            if (! $tieneEmpresa && ! isset($f->retsv_empresa)) {
                $f->retsv_empresa = 0;
            }
            $k = $this->claveRet(
                (int) ($f->retsv_empresa ?? 0),
                (string) ($f->retsv_tipo ?? ''),
                (string) ($f->retsv_letra ?? ''),
                (int) ($f->retsv_sucursal ?? 0),
                (int) ($f->retsv_nro ?? 0)
            );
            if ($k === '') {
                continue;
            }
            $out[$k]['suss'][] = $f;
            $out[$k]['ganancias'] ??= [];
            $out[$k]['iva'] ??= [];
            $out[$k]['ibr'] ??= [];
        }
        foreach ($ibr as $f) {
            if (! $tieneEmpresa && ! isset($f->retibr_empresa)) {
                $f->retibr_empresa = 0;
            }
            $k = $this->claveRet(
                (int) ($f->retibr_empresa ?? 0),
                (string) ($f->retibr_tipo ?? ''),
                (string) ($f->retibr_letra ?? ''),
                (int) ($f->retibr_sucursal ?? 0),
                (int) ($f->retibr_nro ?? 0)
            );
            if ($k === '') {
                continue;
            }
            $out[$k]['ibr'][] = $f;
            $out[$k]['ganancias'] ??= [];
            $out[$k]['iva'] ??= [];
            $out[$k]['suss'] ??= [];
        }

        return $out;
    }

    public function claveRet(int $empresa, string $tipo, string $letra, int $sucursal, int $nro): string
    {
        $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo($tipo);
        // Mono (Crown): empresa Anita = 0 es válida.
        if ($empresa < 0 || $tipo === '' || $nro <= 0) {
            return '';
        }
        $let = ComprobanteProveedorAnitaImportClaveSupport::letra($letra);

        return $empresa.'|'.$tipo.'|'.$let.'|'.$sucursal.'|'.$nro;
    }

    /**
     * @param  list<int>  $empresasAnita
     * @return list<int>
     */
    private function normalizarEmpresas(array $empresasAnita, ?bool $tieneEmpresa = null): array
    {
        $tieneEmpresa ??= PagoproveedorAnitaImportEmpresaSupport::auxpagTieneEmpresa();
        $ids = array_values(array_unique(array_map('intval', $empresasAnita)));
        if ($tieneEmpresa) {
            return array_values(array_filter($ids, static fn (int $id) => $id > 0));
        }

        // Mono: aceptar 0; si viene vacío, usar [0].
        return $ids === [] ? [0] : $ids;
    }

    /**
     * @param  list<string>  $errores
     * @return list<object>
     */
    private function listar(
        string $sistema,
        string $tabla,
        string $campos,
        string $whereArmado,
        array &$errores,
        string $etiqueta,
    ): array {
        $t0 = microtime(true);
        try {
            $raw = $this->api->apiCall([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => $tabla,
                'campos' => $campos,
                'whereArmado' => $whereArmado,
            ]);
        } catch (\Throwable $e) {
            $errores[] = $etiqueta.': '.$e->getMessage();
            Log::warning('pagoproveedor.anita_impresion.bridge', [
                'etiqueta' => $etiqueta,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $msg = ApiAnita::extraerMensajeError($raw);
        if ($msg !== null) {
            if (! str_contains(strtolower($msg), 'sin filas') && ! str_contains($msg, '0 filas')) {
                $errores[] = $etiqueta.': '.$msg;
            }
            Log::info('pagoproveedor.anita_impresion.bridge', [
                'etiqueta' => $etiqueta,
                'ms' => round((microtime(true) - $t0) * 1000, 1),
                'error' => $msg,
            ]);

            return [];
        }

        $filas = ApiAnita::decodificarListaFilas($raw);
        Log::info('pagoproveedor.anita_impresion.bridge', [
            'etiqueta' => $etiqueta,
            'ms' => round((microtime(true) - $t0) * 1000, 1),
            'filas' => count($filas),
        ]);

        return $filas;
    }
}
