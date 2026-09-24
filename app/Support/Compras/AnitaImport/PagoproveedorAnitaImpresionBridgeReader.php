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

    private const RETMOV_CAMPOS = 'retv_proveedor,retv_tipo,retv_letra,retv_sucursal,retv_nro,retv_fecha,'
        .'retv_codigo_ret,retv_gravado,retv_pago_actual,retv_pago_anterior,retv_sujeto,retv_retencion,'
        .'retv_porc_ret,retv_nro_retencion,retv_ret_mes,retv_ret_anterior,retv_nombre_prov,retv_cuit_prov,'
        .'retv_porc_excl,retv_empresa';

    private const RETIMOV_CAMPOS = 'retiv_proveedor,retiv_tipo,retiv_letra,retiv_sucursal,retiv_nro,retiv_fecha,'
        .'retiv_codigo_ret,retiv_gravado,retiv_iva,retiv_pago_actual,retiv_sujeto,retiv_retencion,retiv_porc_ret,'
        .'retiv_nro_ret,retiv_tipo_comp,retiv_letra_comp,retiv_suc_comp,retiv_nro_comp,retiv_fecha_comp,'
        .'retiv_nombre_prov,retiv_cuit_prov,retiv_empresa';

    private const RETSMOV_CAMPOS = 'retsv_proveedor,retsv_tipo,retsv_letra,retsv_sucursal,retsv_nro,retsv_fecha,'
        .'retsv_codigo_ret,retsv_gravado,retsv_retencion,retsv_porc_ret,retsv_nro_ret,retsv_empresa,retsv_base_calculo';

    private const RETIBRMOV_CAMPOS = 'retibr_proveedor,retibr_tipo,retibr_letra,retibr_sucursal,retibr_nro,retibr_fecha,'
        .'retibr_gravado,retibr_pago_actual,retibr_sujeto,retibr_retencion,retibr_porc_ret,retibr_nro_ret,'
        .'retibr_tipo_comp,retibr_letra_comp,retibr_suc_comp,retibr_nro_comp,retibr_fecha_comp,retibr_provincia,'
        .'retibr_empresa';

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
        $empresasAnita = $this->normalizarEmpresas($empresasAnita);
        if ($empresasAnita === []) {
            return [];
        }

        $where = ' WHERE axp_empresa IN ('.implode(',', $empresasAnita).')'
            .' AND axp_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
            ." AND axp_tipo IN ('OPP','OPA')";

        return $this->listar('che_ban', 'auxpag', self::AUXPAG_CAMPOS, $where, $errores, 'impresion-auxpag');
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
        $empresasAnita = $this->normalizarEmpresas($empresasAnita);
        if ($empresasAnita === []) {
            return [];
        }

        $inEmp = implode(',', $empresasAnita);
        $tipos = "('OPP','OPA')";

        $gan = $this->listar(
            'compras',
            'retmov',
            self::RETMOV_CAMPOS,
            ' WHERE retv_empresa IN ('.$inEmp.') AND retv_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
                .' AND retv_tipo IN '.$tipos,
            $errores,
            'impresion-retmov'
        );
        $iva = $this->listar(
            'compras',
            'retimov',
            self::RETIMOV_CAMPOS,
            ' WHERE retiv_empresa IN ('.$inEmp.') AND retiv_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
                .' AND retiv_tipo IN '.$tipos,
            $errores,
            'impresion-retimov'
        );
        $suss = $this->listar(
            'compras',
            'retsmov',
            self::RETSMOV_CAMPOS,
            ' WHERE retsv_empresa IN ('.$inEmp.') AND retsv_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
                .' AND retsv_tipo IN '.$tipos,
            $errores,
            'impresion-retsmov'
        );
        $ibr = $this->listar(
            'compras',
            'retibrmov',
            self::RETIBRMOV_CAMPOS,
            ' WHERE retibr_empresa IN ('.$inEmp.') AND retibr_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
                .' AND retibr_tipo IN '.$tipos,
            $errores,
            'impresion-retibrmov'
        );

        $out = [];
        foreach ($gan as $f) {
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
        if ($empresa <= 0 || $tipo === '' || $nro <= 0) {
            return '';
        }
        $let = ComprobanteProveedorAnitaImportClaveSupport::letra($letra);

        return $empresa.'|'.$tipo.'|'.$let.'|'.$sucursal.'|'.$nro;
    }

    /**
     * @param  list<int>  $empresasAnita
     * @return list<int>
     */
    private function normalizarEmpresas(array $empresasAnita): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $empresasAnita),
            static fn (int $id) => $id > 0
        )));
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
