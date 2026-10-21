<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\ApiAnita;
use App\Support\Compras\AnitaImport\PagoproveedorAnitaImportEmpresaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Lectura masiva Anita che_ban para el reporte sábana (temporal).
 * Una list de `pago` + una de `auxpag` por rango/empresas; join en memoria.
 */
final class PagosSabanaAnitaBridgeReader
{
    private const PAGO_CAMPOS = 'pag_empresa,pag_fecha,pag_tipo,pag_rec,pag_sucursal,pag_pro,'
        .'pag_trec,pag_leyenda,pag_entregado_a,pag_cotizacion,pag_cod_mon_me,pag_letra';

    /** Descripción al final: si el CSV de Informix se corre, no desplaza montos. */
    private const AUXPAG_CAMPOS = 'axp_empresa,axp_fecha,axp_tipo,axp_rec,axp_pro,axp_nro,axp_tipo_ap,'
        .'axp_monto_ap,axp_cod_mon_co,axp_banco,axp_letra_comp,axp_sucursal,axp_sucursal_cob,'
        .'axp_nro_interno,axp_concepto,axp_fecha_co';

    private const TESMAE_CAMPOS = 'tesm_cuenta,tesm_desc,tesm_tipo_cuenta';

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita,
    ) {}

    /**
     * @param  list<int>  $empresasAnita
     * @param  list<string>  $errores
     * @return list<object>
     */
    /**
     * @param  list<string>  $tipos
     */
    public function listarPagos(
        array $empresasAnita,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        array &$errores,
        array $tipos = ['OPP', 'OPA'],
    ): array {
        // Crown mono: pag_empresa=0 (no filtrar con > 0).
        $empresasAnita = array_values(array_unique(array_map('intval', $empresasAnita)));
        if ($empresasAnita === []) {
            return [];
        }
        $tiposSql = self::tiposSql($tipos);
        if ($tiposSql === '') {
            return [];
        }

        $where = ' WHERE pag_empresa IN ('.implode(',', $empresasAnita).')'
            .' AND pag_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
            .' AND pag_tipo IN ('.$tiposSql.')';

        return $this->listar('che_ban', 'pago', self::PAGO_CAMPOS, $where, $errores, 'pagos-sabana-pago');
    }

    /**
     * OPP/OPA anuladas en Anita: existe un AOP con el mismo pag_rec (la anulación
     * puede ser otro día, por eso no se filtra por fecha).
     *
     * @param  list<int>  $empresasAnita
     * @param  list<int>  $recs
     * @param  list<string>  $errores
     * @return array<string, true> clave empresaAnita|rec
     */
    public function mapaRecsAnuladosPorAop(array $empresasAnita, array $recs, array &$errores): array
    {
        $empresasAnita = array_values(array_unique(array_filter(array_map('intval', $empresasAnita))));
        $recs = array_values(array_unique(array_filter(array_map('intval', $recs))));
        if ($empresasAnita === [] || $recs === []) {
            return [];
        }

        $mapa = [];
        foreach (array_chunk($recs, 80) as $lote) {
            $where = ' WHERE pag_empresa IN ('.implode(',', $empresasAnita).')'
                ." AND pag_tipo = 'AOP'"
                .' AND pag_rec IN ('.implode(',', $lote).')';
            $filas = $this->listar(
                'che_ban',
                'pago',
                'pag_empresa,pag_tipo,pag_rec',
                $where,
                $errores,
                'pagos-sabana-aop',
            );
            foreach ($filas as $fila) {
                $rec = (int) ($fila->pag_rec ?? 0);
                $emp = (int) ($fila->pag_empresa ?? 0);
                if ($rec > 0 && $emp > 0) {
                    $mapa[$emp.'|'.$rec] = true;
                }
            }
        }

        return $mapa;
    }

    /**
     * @param  list<int>  $empresasAnita
     * @param  list<string>  $errores
     * @return list<object>
     */
    /**
     * @param  list<string>  $tipos
     */
    public function listarAuxpag(
        array $empresasAnita,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        array &$errores,
        array $tipos = ['OPP', 'OPA'],
    ): array {
        $tieneEmpresa = PagoproveedorAnitaImportEmpresaSupport::auxpagTieneEmpresa();
        $empresasAnita = array_values(array_unique(array_map('intval', $empresasAnita)));
        if ($tieneEmpresa && $empresasAnita === []) {
            return [];
        }

        // Crown: sin axp_empresa / axp_concepto / axp_cbu.
        $campos = $tieneEmpresa
            ? self::AUXPAG_CAMPOS
            : 'axp_fecha,axp_tipo,axp_rec,axp_pro,axp_nro,axp_tipo_ap,'
                .'axp_monto_ap,axp_cod_mon_co,axp_banco,axp_letra_comp,axp_sucursal,axp_sucursal_cob,'
                .'axp_nro_interno,axp_fecha_co';

        $tiposSql = self::tiposSql($tipos);
        if ($tiposSql === '') {
            return [];
        }

        $where = $tieneEmpresa
            ? ' WHERE axp_empresa IN ('.implode(',', $empresasAnita).')'
                .' AND axp_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
                .' AND axp_tipo IN ('.$tiposSql.')'
            : ' WHERE axp_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
                .' AND axp_tipo IN ('.$tiposSql.')';

        $filas = $this->listar('che_ban', 'auxpag', $campos, $where, $errores, 'pagos-sabana-auxpag');
        if ($tieneEmpresa) {
            return $filas;
        }

        // Inventar axp_empresa=0 para claves mono (mismo criterio que import).
        foreach ($filas as $fila) {
            if (! isset($fila->axp_empresa)) {
                $fila->axp_empresa = 0;
            }
        }

        return $filas;
    }

    /**
     * Una lectura aplicped: factura → PEP (nro OC Anita = numeroordencompra ERP).
     *
     * @param  list<int|string>  $numerosFactura
     * @param  list<string>  $tiposFactura
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function listarAplicpedFacturas(array $numerosFactura, array $tiposFactura, array &$errores): array
    {
        $nros = [];
        foreach ($numerosFactura as $nro) {
            $n = (int) preg_replace('/\D+/', '', (string) $nro);
            if ($n > 0) {
                $nros[$n] = true;
            }
        }
        $tipos = [];
        foreach ($tiposFactura as $tipo) {
            $t = strtoupper(trim((string) $tipo));
            if ($t !== '') {
                $tipos[$t] = true;
            }
        }
        if ($nros === [] || $tipos === []) {
            return [];
        }

        $tiposSql = [];
        foreach (array_keys($tipos) as $tipo) {
            $tiposSql[] = "'".addslashes((string) $tipo)."'";
        }

        $where = ' WHERE aplp_nro IN ('.implode(',', array_keys($nros)).')'
            .' AND aplp_tipo IN ('.implode(',', $tiposSql).')'
            ." AND aplp_ref_tipo = 'PEP'";

        return $this->listar(
            'compras',
            'aplicped',
            'aplp_proveedor,aplp_tipo,aplp_letra,aplp_sucursal,aplp_nro,'
            .'aplp_ref_tipo,aplp_ref_letra,aplp_ref_sucursal,aplp_ref_nro,aplp_orden,aplp_cantfact',
            $where,
            $errores,
            'pagos-sabana-aplicped',
        );
    }

    /**
     * Nombres de cuenta tesorería para códigos vistos en auxpag (una sola lectura).
     *
     * @param  list<string>  $cuentas
     * @param  list<string>  $errores
     * @return array<string, string> cuenta => descripción
     */
    public function mapaTesmae(array $cuentas, array &$errores): array
    {
        $unicas = [];
        foreach ($cuentas as $cuenta) {
            $c = strtoupper(trim((string) $cuenta));
            if ($c !== '' && $c !== '00000000') {
                $unicas[$c] = true;
            }
        }
        if ($unicas === []) {
            return [];
        }

        $lista = [];
        foreach (array_keys($unicas) as $cuenta) {
            $lista[] = "'".addslashes((string) $cuenta)."'";
        }

        $where = ' WHERE tesm_cuenta IN ('.implode(',', $lista).')';
        $filas = $this->listar('che_ban', 'tesmae', self::TESMAE_CAMPOS, $where, $errores, 'pagos-sabana-tesmae');

        $mapa = [];
        foreach ($filas as $fila) {
            $cuenta = strtoupper(trim((string) ($fila->tesm_cuenta ?? '')));
            $desc = trim((string) ($fila->tesm_desc ?? ''));
            if ($cuenta !== '' && $desc !== '') {
                $mapa[$cuenta] = $desc;
            }
        }

        return $mapa;
    }

    /**
     * Piernas de subdiario de un tipo (OPV, OPP, …) en el rango. Solo lectura.
     *
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function listarSubdiarioTipo(string $tipo, int $fechaDesdeYmd, int $fechaHastaYmd, array &$errores): array
    {
        $tipo = strtoupper(trim($tipo));
        if (! in_array($tipo, ['OPP', 'OPA', 'OPV', 'AOP'], true) || $fechaDesdeYmd <= 0 || $fechaHastaYmd <= 0) {
            return [];
        }

        $where = " WHERE subd_tipo = '".$tipo."'"
            .' AND subd_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd;

        return $this->listar(
            'contab',
            'subdiario',
            'subd_fecha,subd_tipo,subd_nro,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_importe',
            $where,
            $errores,
            'subdiario-'.$tipo,
        );
    }

    /**
     * @param  list<string>  $tipos
     */
    private static function tiposSql(array $tipos): string
    {
        $ok = [];
        foreach ($tipos as $tipo) {
            $t = strtoupper(trim((string) $tipo));
            if (in_array($t, ['OPP', 'OPA', 'OPV', 'AOP'], true)) {
                $ok[$t] = "'".$t."'";
            }
        }

        return implode(',', $ok);
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
            Log::warning('pagos_sabana.anita', ['etiqueta' => $etiqueta, 'error' => $e->getMessage()]);

            return [];
        }

        $msg = ApiAnita::extraerMensajeError($raw);
        if ($msg !== null) {
            $errores[] = $etiqueta.': '.$msg;
            Log::info('pagos_sabana.anita', [
                'etiqueta' => $etiqueta,
                'ms' => round((microtime(true) - $t0) * 1000, 1),
                'error' => $msg,
            ]);

            return [];
        }

        $filas = ApiAnita::decodificarListaFilas($raw);
        Log::info('pagos_sabana.anita', [
            'etiqueta' => $etiqueta,
            'ms' => round((microtime(true) - $t0) * 1000, 1),
            'filas' => count($filas),
        ]);

        return $filas;
    }
}
