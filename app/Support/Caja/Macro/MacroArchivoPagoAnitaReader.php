<?php

declare(strict_types=1);

namespace App\Support\Caja\Macro;

use App\ApiAnita;
use App\Support\Caja\InterbankingArchivoPagoAnitaReader;
use Illuminate\Support\Facades\Log;

/**
 * Lectura Anita para Macro: pago / auxpag / propago / promae / cpromae.
 * Reusa listado de pagos del reader Interbanking y amplía campos.
 */
final class MacroArchivoPagoAnitaReader
{
    private const AUXPAG_CAMPOS = 'axp_pro,axp_fecha,axp_rec,axp_tipo,axp_nro,axp_tipo_ap,axp_monto_ap,'
        .'axp_sucursal,axp_empresa,axp_cbu,axp_banco,axp_fecha_co,axp_nro_interno,'
        .'axp_letra_comp';

    private const PROPAGO_CAMPOS = 'prop_proveedor,prop_cbu,prop_forma_pago,prop_cod_banco,prop_cuit,prop_e_mail_conf';

    private const PROMAE_CAMPOS = 'prom_proveedor,prom_nombre,prom_cuit,prom_ret_ibr,prom_cond_iva,'
        .'prom_cond_gan,prom_cod_postal,prom_e_mail';

    private const CPROMAE_CAMPOS = 'cpro_cuenta,cpro_nro_cheque,cpro_fecha_cheque,cpro_fecha_emision,'
        .'cpro_importe,cpro_para_dep';

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
        private readonly InterbankingArchivoPagoAnitaReader $ibReader = new InterbankingArchivoPagoAnitaReader,
    ) {}

    /**
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function listarPagos(
        int $empresaAnita,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        string $tipoOp,
        int $opDesde,
        int $opHasta,
        array &$errores,
    ): array {
        $where = ' WHERE pag_empresa='.$empresaAnita
            .' AND pag_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
            .' AND pag_rec BETWEEN '.$opDesde.' AND '.$opHasta;

        $tipos = MacroArchivoPagoFormatoSupport::tiposComprobanteFiltro($tipoOp);
        if ($tipos === null) {
            // Como p-enviamacro con tipo 0: OP* + IEV (reemplazos de cheques)
            $extras = array_values(array_filter(array_map(
                static fn ($t) => addslashes(strtoupper(substr(trim((string) $t), 0, 3))),
                (array) config('macro.tipos_op_extra_con_opp', ['IEV'])
            )));
            $extraSql = $extras === []
                ? ''
                : " OR pag_tipo IN ('".implode("','", $extras)."')";
            $where .= " AND (pag_tipo LIKE 'OP%'".$extraSql.')';
        } elseif (count($tipos) === 1) {
            $where .= " AND pag_tipo = '".addslashes($tipos[0])."'";
        } else {
            $lista = implode("','", array_map('addslashes', $tipos));
            $where .= " AND pag_tipo IN ('".$lista."')";
        }

        return $this->listar('che_ban', 'pago', 'pag_empresa,pag_fecha,pag_tipo,pag_rec,pag_sucursal,pag_pro,pag_leyenda', $where, $errores, 'pago-macro');
    }

    /**
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function listarAuxpagPeriodo(
        int $empresaAnita,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        array &$errores,
    ): array {
        $where = ' WHERE axp_empresa='.$empresaAnita
            .' AND axp_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd;

        return $this->listar('che_ban', 'auxpag', self::AUXPAG_CAMPOS, $where, $errores, 'auxpag-macro');
    }

    /**
     * @param  list<string>  $codigosProveedor
     * @param  list<string>  $errores
     * @return array<string, object> codigo => datos propago (último con CBU o banco)
     */
    public function mapaPropago(array $codigosProveedor, array &$errores): array
    {
        $mapa = [];
        foreach ($codigosProveedor as $cod) {
            $p = InterbankingArchivoPagoAnitaReader::padProveedor($cod);
            if ($p === '' || isset($mapa[$p])) {
                continue;
            }
            $where = " WHERE prop_proveedor = '".addslashes($p)."'";
            $filas = $this->listar('compras', 'propago', self::PROPAGO_CAMPOS, $where, $errores, 'propago-macro-'.$p);
            $best = null;
            foreach ($filas as $fila) {
                $best = $fila;
            }
            if ($best !== null) {
                $mapa[$p] = $best;
            }
        }

        return $mapa;
    }

    /**
     * @param  list<string>  $codigosProveedor
     * @param  list<string>  $errores
     * @return array<string, object>
     */
    public function mapaPromae(array $codigosProveedor, array &$errores): array
    {
        $mapa = [];
        foreach ($codigosProveedor as $cod) {
            $p = InterbankingArchivoPagoAnitaReader::padProveedor($cod);
            if ($p === '' || isset($mapa[$p])) {
                continue;
            }
            $where = " WHERE prom_proveedor = '".addslashes($p)."'";
            $filas = $this->listar('compras', 'promae', self::PROMAE_CAMPOS, $where, $errores, 'promae-macro-'.$p);
            foreach ($filas as $fila) {
                $mapa[$p] = $fila;
            }
        }

        return $mapa;
    }

    /**
     * @param  list<string>  $errores
     */
    public function leerCheque(
        string $cuentaAnita8,
        int $nroCheque,
        int $fechaChequeYmd,
        array &$errores,
    ): ?object {
        $cta = MacroArchivoPagoFiltros::padCuentaAnita($cuentaAnita8);
        if ($cta === '' || $nroCheque <= 0) {
            return null;
        }

        // Primero con fecha (clave Anita); si no hay match, solo cuenta+nro
        // (axp_fecha_co a veces no coincide con cpro_fecha_cheque).
        $wheres = [];
        if ($fechaChequeYmd > 0) {
            $wheres[] = " WHERE cpro_cuenta = '".addslashes($cta)."'"
                .' AND cpro_nro_cheque = '.$nroCheque
                .' AND cpro_fecha_cheque = '.$fechaChequeYmd;
        }
        $wheres[] = " WHERE cpro_cuenta = '".addslashes($cta)."'"
            .' AND cpro_nro_cheque = '.$nroCheque;

        $erroresLocal = [];
        foreach ($wheres as $i => $where) {
            $err = [];
            $filas = $this->listar(
                'che_ban',
                'cpromae',
                self::CPROMAE_CAMPOS,
                $where,
                $err,
                'cpromae-'.$nroCheque.($i > 0 ? '-nofecha' : '')
            );
            if ($filas !== []) {
                return $filas[0];
            }
            // Solo acumular error real de bridge (no “sin filas”)
            foreach ($err as $e) {
                if (! str_contains(strtolower($e), 'sin filas') && ! str_contains($e, '0 filas')) {
                    $erroresLocal[] = $e;
                }
            }
        }
        foreach ($erroresLocal as $e) {
            $errores[] = $e;
        }

        return null;
    }

    /**
     * Retenciones de una OP Anita (mismo criterio que p-enviamacro / lista_ret*).
     *
     * @param  list<string>  $errores
     * @return array{
     *   ganancias:list<object>,
     *   iva:list<object>,
     *   suss:list<object>,
     *   ibr:list<object>
     * }
     */
    public function listarRetencionesOp(
        string $proveedor6,
        string $tipoOp,
        string $letra,
        int $sucursal,
        int $nroOp,
        int $empresaAnita,
        array &$errores,
    ): array {
        $pro = InterbankingArchivoPagoAnitaReader::padProveedor($proveedor6);
        $tipo = addslashes(strtoupper(substr(trim($tipoOp), 0, 3)));
        $let = addslashes($letra !== '' ? substr($letra, 0, 1) : ' ');
        if ($pro === '' || $tipo === '' || $nroOp <= 0) {
            return ['ganancias' => [], 'iva' => [], 'suss' => [], 'ibr' => []];
        }

        $whereBase = " WHERE %s_proveedor = '".addslashes($pro)."'"
            ." AND %s_tipo = '".$tipo."'"
            ." AND %s_letra = '".$let."'"
            .' AND %s_sucursal = '.$sucursal
            .' AND %s_nro = '.$nroOp;
        if ($empresaAnita > 0) {
            $whereBase .= ' AND %s_empresa = '.$empresaAnita;
        }

        $wGan = sprintf($whereBase, 'retv', 'retv', 'retv', 'retv', 'retv', 'retv');
        $wIva = sprintf($whereBase, 'retiv', 'retiv', 'retiv', 'retiv', 'retiv', 'retiv');
        $wSuss = sprintf($whereBase, 'retsv', 'retsv', 'retsv', 'retsv', 'retsv', 'retsv');
        $wIbr = sprintf($whereBase, 'retibr', 'retibr', 'retibr', 'retibr', 'retibr', 'retibr');

        return [
            'ganancias' => $this->listar('compras', 'retmov', self::RETMOV_CAMPOS, $wGan, $errores, 'retmov-'.$nroOp),
            'iva' => $this->listar('compras', 'retimov', self::RETIMOV_CAMPOS, $wIva, $errores, 'retimov-'.$nroOp),
            'suss' => $this->listar('compras', 'retsmov', self::RETSMOV_CAMPOS, $wSuss, $errores, 'retsmov-'.$nroOp),
            'ibr' => $this->listar('compras', 'retibrmov', self::RETIBRMOV_CAMPOS, $wIbr, $errores, 'retibrmov-'.$nroOp),
        ];
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
            Log::warning('macro.archivo_pago.anita', [
                'etiqueta' => $etiqueta,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $msg = ApiAnita::extraerMensajeError($raw);
        if ($msg !== null) {
            $errores[] = $etiqueta.': '.$this->limpiarMensajeBridge($msg);
            Log::info('macro.archivo_pago.anita', [
                'etiqueta' => $etiqueta,
                'ms' => round((microtime(true) - $t0) * 1000, 1),
                'error' => $msg,
            ]);

            return [];
        }

        $filas = ApiAnita::decodificarListaFilas($raw);
        Log::info('macro.archivo_pago.anita', [
            'etiqueta' => $etiqueta,
            'ms' => round((microtime(true) - $t0) * 1000, 1),
            'filas' => count($filas),
        ]);

        return $filas;
    }

    private function limpiarMensajeBridge(string $msg): string
    {
        // apiERP a veces mete Warning fopen del CSV; el fondo suele ser SQL/campo inválido.
        if (str_contains($msg, 'fopen(') || str_contains($msg, 'failed to open stream')) {
            return 'consulta Anita falló (revisar campos/tabla). Detalle técnico omitido.';
        }
        $msg = preg_replace('/\s+/', ' ', $msg) ?? $msg;

        return mb_substr(trim($msg), 0, 200);
    }
}
