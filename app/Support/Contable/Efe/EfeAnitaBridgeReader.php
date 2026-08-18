<?php

namespace App\Support\Contable\Efe;

use App\ApiAnita;

/**
 * Lecturas puntuales del bridge Anita para el EFE.
 */
class EfeAnitaBridgeReader
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {
    }

    /**
     * Cheques propios (cpromae) — base para solapas Chq. diferido / Resumen Cheques Diferidos.
     *
     * @return list<object>
     */
    public function listarChequesPropios(int $empresaId, int $fechaDesde, int $fechaHasta): array
    {
        $errores = [];

        return $this->listar(
            'che_ban',
            'cpromae',
            'cpro_cuenta,cpro_nro_cheque,cpro_fecha_cheque,cpro_fecha_emision,cpro_importe,cpro_proveedor,cpro_entregado_a,cpro_nro_op,cpro_cod_mon,cpro_cotizacion,cpro_estado,cpro_contrapartida,cpro_empresa',
            ' WHERE cpro_empresa='.$empresaId
            .' AND cpro_fecha_emision>='.$fechaDesde
            .' AND cpro_fecha_emision<='.$fechaHasta,
            $errores,
            'cpromae-efe',
        );
    }

    /**
     * Saldos diarios de posición financiera (saldoposf).
     *
     * @return list<object>
     */
    public function listarSaldoposf(int $empresaId, int $fechaDesde, int $fechaHasta): array
    {
        $errores = [];

        return $this->listar(
            'caja',
            'saldoposf',
            'salpf_fecha,salpf_saldo,salpf_empresa',
            ' WHERE salpf_empresa='.$empresaId
            .' AND salpf_fecha>='.$fechaDesde
            .' AND salpf_fecha<='.$fechaHasta,
            $errores,
            'saldoposf-efe',
        );
    }

    /**
     * Saldos históricos anteriores al inicio solicitado.
     *
     * @return list<object>
     */
    public function listarSaldoposfAnteriores(int $empresaId, int $fechaExclusiva): array
    {
        $errores = [];

        return $this->listar(
            'caja',
            'saldoposf',
            'salpf_fecha,salpf_saldo,salpf_empresa',
            ' WHERE salpf_empresa='.$empresaId
            .' AND salpf_fecha<'.$fechaExclusiva,
            $errores,
            'saldoposf-anterior-efe',
        );
    }

    /**
     * Rendiciones bingo del período (rendbingo).
     *
     * @return list<object>
     */
    public function listarRendbingo(int $empresaId, int $fechaDesde, int $fechaHasta): array
    {
        $errores = [];

        return $this->listar(
            'caja',
            'rendbingo',
            'rendb_nro_oper,rendb_tipo_oper,rendb_fecha,rendb_sobrante,rendb_vales,rendb_redondeo,rendb_total_carton,rendb_empresa,rendb_turno',
            ' WHERE rendb_empresa='.$empresaId
            .' AND rendb_fecha>='.$fechaDesde
            .' AND rendb_fecha<='.$fechaHasta,
            $errores,
            'rendbingo-efe',
        );
    }

    /**
     * Rendiciones máquinas del período (rendmaquina).
     *
     * @return list<object>
     */
    public function listarRendmaquina(int $empresaId, int $fechaDesde, int $fechaHasta): array
    {
        $errores = [];

        return $this->listar(
            'caja',
            'rendmaquina',
            'rendm_nro_oper,rendm_fecha,rendm_empresa,rendm_turno,rendm_venta_ficha,rendm_drop_billete,rendm_billem_rod,rendm_pago_manual,rendm_tito,rendm_hopper,rendm_venta_ruleta,rendm_drop_ruleta,rendm_billem_rul,rendm_salida_rul,rendm_tito_ruleta,rendm_deposito,rendm_vales,rendm_reintegros,rendm_dif_caja,rendm_variacion_ff,rendm_transfer,rendm_vtaant_gast,rendm_sobrantes,rendm_canje_gastro',
            ' WHERE rendm_empresa='.$empresaId
            .' AND rendm_fecha>='.$fechaDesde
            .' AND rendm_fecha<='.$fechaHasta,
            $errores,
            'rendmaquina-efe',
        );
    }

    /**
     * Conceptos bingo (concbingo) con cuentas contables.
     *
     * @return list<object>
     */
    public function listarConcbingoExtendido(): array
    {
        $errores = [];

        return $this->listar(
            'caja',
            'concbingo',
            // Informix: columna real es concb_contrap (el .def C usa alias concb_contrapartida).
            'concb_concepto,concb_desc,concb_tipo_conc,concb_porcentaje,concb_cta_contable,concb_contrap',
            '',
            $errores,
            'concbingo-cierre-bingo',
        );
    }

    /**
     * Conceptos bingo (concbingo).
     *
     * @return list<object>
     */
    public function listarConcbingo(): array
    {
        $errores = [];

        return $this->listar(
            'caja',
            'concbingo',
            'concb_concepto,concb_desc,concb_tipo_conc,concb_porcentaje',
            '',
            $errores,
            'concbingo-efe',
        );
    }

    /**
     * Pozo acumulado (pozoacum) al inicio del período — p-vtabingo.c POZOAC_busca_pozo_acum.
     *
     * @return list<object>
     */
    public function listarPozoacum(int $empresaId, int $fechaHasta): array
    {
        $errores = [];

        return $this->listar(
            'caja',
            'pozoacum',
            'pozoa_fecha,pozoa_empresa,pozoa_importe',
            ' WHERE pozoa_empresa='.$empresaId
            .' AND pozoa_fecha<='.$fechaHasta,
            $errores,
            'pozoacum-bingo',
        );
    }

    /**
     * Premios bingo del período (rendpremio).
     *
     * @return list<object>
     */
    public function listarRendpremio(int $fechaDesde, int $fechaHasta): array
    {
        $errores = [];

        return $this->listar(
            'caja',
            'rendpremio',
            'rendp_nro_oper,rendp_tipo_oper,rendp_concepto,rendp_pagado,rendp_real,rendp_fecha',
            ' WHERE rendp_fecha>='.$fechaDesde
            .' AND rendp_fecha<='.$fechaHasta,
            $errores,
            'rendpremio-efe',
        );
    }

    /**
     * Pedidos pendientes (pendmovp) — solapa Inf.OC / PROYECTOS CAPEX.
     *
     * @return list<object>
     */
    public function listarPedidosPendientes(int $empresaId, int $fechaDesde, int $fechaHasta): array
    {
        $errores = [];

        return $this->listar(
            'compras',
            'pendmovp',
            'penvp_proveedor,penvp_tipo,penvp_letra,penvp_sucursal,penvp_nro,penvp_nro_interno,penvp_fecha,penvp_fecha_ent,penvp_articulo,penvp_cantidad,penvp_cant_entr,penvp_precio,penvp_cuenta,penvp_ccosto,penvp_ccosto_dest,penvp_proyecto,penvp_empresa,penvp_orden,penvp_estado',
            ' WHERE penvp_empresa='.$empresaId
            .' AND penvp_fecha>='.$fechaDesde
            .' AND penvp_fecha<='.$fechaHasta,
            $errores,
            'pendmovp-efe',
        );
    }

    /**
     * Cabeceras gastronomía / estacionamiento (rendgastro).
     *
     * @return list<object>
     */
    public function listarRendgastro(int $empresaId, int $fechaDesde, int $fechaHasta): array
    {
        $errores = [];

        return $this->listar(
            'caja',
            'rendgastro',
            'rendg_nro_oper,rendg_tipo_oper,rendg_fecha,rendg_empresa,rendg_sucursal,rendg_total_z,rendg_tot_nc,rendg_tot_redondeo,rendg_dif_caja,rendg_ab_pago,rendg_turno',
            ' WHERE rendg_empresa='.$empresaId
            .' AND rendg_fecha>='.$fechaDesde
            .' AND rendg_fecha<='.$fechaHasta,
            $errores,
            'rendgastro-efe',
        );
    }

    /**
     * Valores rendidos (rendvalor) del período.
     *
     * @return list<object>
     */
    public function listarRendvalor(int $fechaDesde, int $fechaHasta): array
    {
        $errores = [];

        return $this->listar(
            'caja',
            'rendvalor',
            'rendv_nro_oper,rendv_tipo_oper,rendv_codigo,rendv_total,rendv_fecha,rendv_cotizacion',
            ' WHERE rendv_fecha>='.$fechaDesde
            .' AND rendv_fecha<='.$fechaHasta,
            $errores,
            'rendvalor-efe',
        );
    }

    /**
     * Catálogo de valores (valormae) de la empresa.
     *
     * @return list<object>
     */
    public function listarValormae(int $empresaId): array
    {
        $errores = [];

        return $this->listar(
            'caja',
            'valormae',
            'valm_codigo,valm_desc,valm_tipo_valor,valm_empresa',
            ' WHERE valm_empresa='.$empresaId,
            $errores,
            'valormae-efe',
        );
    }

    /**
     * Conceptos de apertura de gasto (apgasto).
     *
     * @return list<object>
     */
    public function listarApgasto(): array
    {
        $errores = [];

        return $this->listar(
            'caja',
            'apgasto',
            'apg_concepto,apg_desc',
            '',
            $errores,
            'apgasto-efe',
        );
    }

    /**
     * Apertura de gastos por operación de máquinas (rendmapgasto).
     *
     * @return list<object>
     */
    public function listarRendmapgasto(int $nroOperDesde, int $nroOperHasta): array
    {
        $errores = [];
        $colOper = (string) config('rendicion_maquina_anita.gasto_col_nro_oper', 'renmap_nro_oper');
        $colOrden = (string) config('rendicion_maquina_anita.gasto_col_orden', 'renmap_orden');
        $colCodigo = (string) config('rendicion_maquina_anita.gasto_col_codigo', 'renmap_codigo');
        $colImporte = (string) config('rendicion_maquina_anita.gasto_col_importe', 'renmap_importe');

        return $this->listar(
            'caja',
            (string) config('rendicion_maquina_anita.tabla_gasto', 'rendmapgasto'),
            $colOper.','.$colOrden.','.$colCodigo.','.$colImporte,
            ' WHERE '.$colOper.'>='.$nroOperDesde
            .' AND '.$colOper.'<='.$nroOperHasta,
            $errores,
            'rendmapgasto-efe',
        );
    }

    /**
     * Remesas del período (tabla remesas).
     *
     * @return list<object>
     */
    public function listarRemesas(int $empresaId, int $fechaDesde, int $fechaHasta): array
    {
        $errores = [];

        return $this->listar(
            'caja',
            'remesas',
            'reme_empresa,reme_nro_remesa,reme_fecha,reme_importe,reme_cod_valor,reme_tipo_valor,reme_cotizacion',
            ' WHERE reme_empresa='.$empresaId
            .' AND reme_fecha>='.$fechaDesde
            .' AND reme_fecha<='.$fechaHasta,
            $errores,
            'remesas-efe',
        );
    }

    /**
     * Cabeceras de remesa (rememae) — destino / moneda.
     *
     * @return list<object>
     */
    public function listarRememae(int $empresaId, int $fechaDesde, int $fechaHasta): array
    {
        $errores = [];

        return $this->listar(
            'caja',
            'rememae',
            'remem_nro_remesa,remem_destino,remem_empresa,remem_fecha,remem_importe,remem_cod_mon,remem_cotizacion,remem_tipo_remesa',
            ' WHERE remem_empresa='.$empresaId
            .' AND remem_fecha>='.$fechaDesde
            .' AND remem_fecha<='.$fechaHasta,
            $errores,
            'rememae-efe',
        );
    }

    /**
     * @return list<object>
     */
    private function listar(
        string $sistema,
        string $tabla,
        string $campos,
        string $where,
        array &$errores,
        string $etiqueta,
    ): array {
        try {
            $payload = [
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => $tabla,
                'campos' => $campos,
                'whereArmado' => $where,
            ];
            $raw = $this->api->apiCall($payload);
            $decoded = json_decode($raw);

            if (! is_array($decoded)) {
                $errores[] = $etiqueta.': respuesta bridge inválida';

                return [];
            }

            return $decoded;
        } catch (\Throwable $e) {
            $errores[] = $etiqueta.': '.$e->getMessage();

            return [];
        }
    }
}
