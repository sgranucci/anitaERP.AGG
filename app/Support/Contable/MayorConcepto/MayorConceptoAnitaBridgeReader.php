<?php

namespace App\Support\Contable\MayorConcepto;

use App\ApiAnita;

/**
 * Carga tablas Anita vía bridge HTTP en memoria para un período acotado (≈1 mes).
 */
class MayorConceptoAnitaBridgeReader
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {
    }

    /**
     * @return array{
     *   subdiario: list<object>,
     *   ctamov: list<object>,
     *   auxpag: list<object>,
     *   ctaconc: list<object>,
     *   promae: list<object>,
     *   errores: list<string>
     * }
     */
    public function cargarPeriodo(int $empresaId, int $fechaDesde, int $fechaHasta): array
    {
        $errores = [];

        $subdiario = $this->listar(
            'contab',
            'subdiario',
            'subd_empresa,subd_sistema,subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_emisor,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_nro_operacion,subd_ref_tipo,subd_ref_letra,subd_ref_sucursal,subd_ref_nro,subd_importe,subd_cod_mon,subd_cotizacion,subd_desc_mov,subd_nro_asiento,subd_nro_interno,subd_ccosto_cta,subd_ccosto_con',
            ' WHERE subd_empresa='.$empresaId
            .' AND subd_fecha>='.$fechaDesde
            .' AND subd_fecha<='.$fechaHasta,
            $errores,
            'subdiario'
        );

        $ctamov = $this->listar(
            'contab',
            'ctamov',
            'ctav_empresa,ctav_nro_asiento,ctav_nro_linea,ctav_d_h,ctav_cuenta,ctav_fecha,ctav_tipo,ctav_letra,ctav_sucursal,ctav_nro,ctav_importe,ctav_desc_mov,ctav_cotizacion,ctav_cod_mon,ctav_sistema,ctav_tipo_asiento,ctav_ccosto,ctav_o_compra',
            ' WHERE ctav_empresa='.$empresaId
            .' AND ctav_fecha>='.$fechaDesde
            .' AND ctav_fecha<='.$fechaHasta,
            $errores,
            'ctamov'
        );

        $auxpag = $this->listar(
            'che_ban',
            'auxpag',
            'axp_pro,axp_fecha,axp_rec,axp_tipo,axp_nro,axp_tipo_ap,axp_monto_ap,axp_cod_mon_co,axp_sucursal,axp_empresa,axp_letra_comp,axp_nro_interno,axp_banco,axp_concepto',
            ' WHERE axp_empresa='.$empresaId
            .' AND axp_fecha>='.$fechaDesde
            .' AND axp_fecha<='.$fechaHasta,
            $errores,
            'auxpag'
        );

        $ctaconc = $this->listar(
            'contab',
            'ctaconc',
            'ctaco_empresa,ctaco_cuenta,ctaco_concepto',
            ' WHERE ctaco_empresa='.$empresaId,
            $errores,
            'ctaconc'
        );

        return [
            'subdiario' => $subdiario,
            'ctamov' => $ctamov,
            'auxpag' => $auxpag,
            'ctaconc' => $ctaconc,
            'promae' => [],
            'errores' => $errores,
        ];
    }

    /**
     * Carga datos mínimos para simular un único comprobante de pago.
     *
     * @return array<string, mixed>
     */
    public function cargarParaPago(int $empresaId, string $tipo, string $letra, int $sucursal, int $nro, int $fecha): array
    {
        $errores = [];
        $letraSql = $this->sqlChar($letra);

        $subdiario = $this->listar(
            'contab',
            'subdiario',
            'subd_empresa,subd_sistema,subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_nro_operacion,subd_ref_tipo,subd_ref_letra,subd_ref_sucursal,subd_ref_nro,subd_importe,subd_desc_mov,subd_nro_interno,subd_cod_mon,subd_cotizacion',
            ' WHERE subd_empresa='.$empresaId
            .' AND subd_ref_tipo="'.$tipo.'"'
            .' AND subd_ref_sucursal='.$sucursal
            .' AND subd_ref_nro='.$nro
            .' AND subd_ref_letra='.$letraSql
            .' AND subd_fecha='.$fecha,
            $errores,
            'subdiario-pago'
        );

        $auxpag = $this->listar(
            'che_ban',
            'auxpag',
            'axp_pro,axp_fecha,axp_rec,axp_tipo,axp_nro,axp_tipo_ap,axp_monto_ap,axp_sucursal,axp_empresa,axp_letra_comp,axp_nro_interno,axp_banco',
            ' WHERE axp_empresa='.$empresaId
            .' AND axp_tipo="'.$tipo.'"'
            .' AND axp_rec='.$nro
            .' AND axp_fecha='.$fecha,
            $errores,
            'auxpag-pago'
        );

        $proveedores = [];
        foreach ($auxpag as $fila) {
            $prov = trim((string) ($fila->axp_pro ?? ''));
            if ($prov !== '') {
                $proveedores[$prov] = true;
            }
        }

        $aplicped = [];
        $recepmov = [];
        $comSubdiario = [];

        foreach ($auxpag as $fila) {
            $tipoAp = strtoupper(trim((string) ($fila->axp_tipo_ap ?? '')));
            if (! in_array($tipoAp, MayorConceptoMemoriaMotor::TIPOS_FACTURA_APLICADA, true)) {
                continue;
            }

            if (in_array($tipoAp, MayorConceptoMemoriaMotor::TIPOS_AUXPAG_IGNORAR, true)
                || in_array($tipoAp, MayorConceptoMemoriaMotor::TIPOS_MEDIO_PAGO_AUXPAG, true)) {
                continue;
            }

            $prov = trim((string) $fila->axp_pro);
            $tipoAp = trim((string) $fila->axp_tipo_ap);
            $letraAp = trim((string) ($fila->axp_letra_comp ?? ' '));
            $sucAp = (int) ($fila->axp_sucursal ?? 0);
            $nroAp = (int) ($fila->axp_nro ?? 0);

            $aplicaciones = $this->listar(
                'compras',
                'aplicped',
                'aplp_proveedor,aplp_tipo,aplp_letra,aplp_sucursal,aplp_nro,aplp_ref_tipo,aplp_ref_letra,aplp_ref_sucursal,aplp_ref_nro,aplp_orden,aplp_cantfact',
                ' WHERE aplp_proveedor="'.$prov.'"'
                .' AND aplp_tipo="'.$tipoAp.'"'
                .' AND aplp_letra='.$this->sqlChar($letraAp)
                .' AND aplp_sucursal='.$sucAp
                .' AND aplp_nro='.$nroAp,
                $errores,
                'aplicped'
            );

            foreach ($aplicaciones as $apl) {
                $aplicped[] = $apl;
                if (trim((string) ($apl->aplp_ref_tipo ?? '')) !== 'COM') {
                    continue;
                }

                $comTipo = trim((string) $apl->aplp_ref_tipo);
                $comLetra = trim((string) ($apl->aplp_ref_letra ?? ' '));
                $comSuc = (int) ($apl->aplp_ref_sucursal ?? 0);
                $comNro = (int) ($apl->aplp_ref_nro ?? 0);

                $comSubdiario = array_merge(
                    $comSubdiario,
                    $this->listar(
                        'contab',
                        'subdiario',
                        'subd_empresa,subd_sistema,subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_importe,subd_desc_mov,subd_nro_operacion',
                        ' WHERE subd_tipo="'.$comTipo.'"'
                        .' AND subd_letra='.$this->sqlChar($comLetra)
                        .' AND subd_sucursal='.$comSuc
                        .' AND subd_nro='.$comNro,
                        $errores,
                        'subdiario-com'
                    )
                );

                $recepmov = array_merge(
                    $recepmov,
                    $this->listar(
                        'compras',
                        'recepmov',
                        'recv_proveedor,recv_tipo,recv_letra,recv_sucursal,recv_nro,recv_orden,recv_cantidad,recv_precio,recv_dto_art,recv_tipo_iva,recv_empresa',
                        ' WHERE recv_proveedor="'.$prov.'"'
                        .' AND recv_tipo="'.$comTipo.'"'
                        .' AND recv_letra='.$this->sqlChar($comLetra)
                        .' AND recv_sucursal='.$comSuc
                        .' AND recv_nro='.$comNro,
                        $errores,
                        'recepmov'
                    )
                );
            }
        }

        $promae = [];
        foreach (array_keys($proveedores) as $prov) {
            $filas = $this->listar(
                'compras',
                'promae',
                'prom_proveedor,prom_nombre,prom_cuit,prom_cond_iva',
                ' WHERE prom_proveedor="'.$prov.'"',
                $errores,
                'promae'
            );
            $promae = array_merge($promae, $filas);
        }

        $ctaconc = $this->listar(
            'contab',
            'ctaconc',
            'ctaco_empresa,ctaco_cuenta,ctaco_concepto',
            ' WHERE ctaco_empresa='.$empresaId,
            $errores,
            'ctaconc'
        );

        return [
            'subdiario' => $subdiario,
            'auxpag' => $auxpag,
            'aplicped' => $aplicped,
            'com_subdiario' => $comSubdiario,
            'recepmov' => $recepmov,
            'promae' => $promae,
            'ctaconc' => $ctaconc,
            'errores' => $errores,
        ];
    }

    /**
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
        $payload = [
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $whereArmado,
        ];

        $raw = (string) $this->api->apiCall($payload);
        $msg = ApiAnita::extraerMensajeError($raw);
        if ($msg !== null) {
            $errores[] = $etiqueta.': '.$msg;

            return [];
        }

        return ApiAnita::decodificarListaFilas($raw);
    }

    public function cargarComSubdiario(string $tipo, string $letra, int $sucursal, int $nro, array &$errores): array
    {
        return $this->listar(
            'contab',
            'subdiario',
            'subd_empresa,subd_sistema,subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_importe,subd_desc_mov,subd_nro_operacion,subd_cod_mon,subd_cotizacion',
            ' WHERE subd_tipo="'.$tipo.'"'
            .' AND subd_letra='.$this->sqlChar($letra)
            .' AND subd_sucursal='.$sucursal
            .' AND subd_nro='.$nro,
            $errores,
            'subdiario-com'
        );
    }

    public function cargarAplicpedFactura(
        string $proveedor,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
        array &$errores,
    ): array {
        return $this->listar(
            'compras',
            'aplicped',
            'aplp_proveedor,aplp_tipo,aplp_letra,aplp_sucursal,aplp_nro,aplp_ref_tipo,aplp_ref_letra,aplp_ref_sucursal,aplp_ref_nro,aplp_orden,aplp_cantfact',
            ' WHERE aplp_proveedor="'.$proveedor.'"'
            .' AND aplp_tipo="'.$tipo.'"'
            .' AND aplp_letra='.$this->sqlChar($letra)
            .' AND aplp_sucursal='.$sucursal
            .' AND aplp_nro='.$nro,
            $errores,
            'aplicped'
        );
    }

    /**
     * Aplicaciones que referencian una OC (PEP) u otro documento — ej. COM→PEP 218505.
     *
     * @return list<object>
     */
    public function cargarAplicpedPorReferencia(
        string $refTipo,
        string $refLetra,
        int $refSucursal,
        int $refNro,
        string $proveedor,
        array &$errores,
    ): array {
        if ($refNro <= 0) {
            return [];
        }

        $where = ' WHERE aplp_ref_tipo="'.$refTipo.'"'
            .' AND aplp_ref_letra='.$this->sqlChar($refLetra)
            .' AND aplp_ref_sucursal='.$refSucursal
            .' AND aplp_ref_nro='.$refNro;

        if ($proveedor !== '') {
            $where .= ' AND aplp_proveedor="'.addslashes($proveedor).'"';
        }

        return $this->listar(
            'compras',
            'aplicped',
            'aplp_proveedor,aplp_tipo,aplp_letra,aplp_sucursal,aplp_nro,aplp_ref_tipo,aplp_ref_letra,aplp_ref_sucursal,aplp_ref_nro,aplp_orden,aplp_cantfact',
            $where,
            $errores,
            'aplicped-ref',
        );
    }

    /**
     * Concepto de compras desde OC (PEP) vía artículo → ctaconc (busca_concepto_oc en l-mayorconc.c).
     */
    public function conceptoDesdeOrdenCompra(int $empresaId, int $nroOc, array &$errores): int
    {
        if ($nroOc <= 0) {
            return 0;
        }

        $lineasOc = $this->listar(
            'compras',
            'pendmovp,stkmae',
            'penvp_articulo,penvp_empresa,stkm_cta_contable_c',
            ' WHERE penvp_tipo="PEP"'
            .' AND penvp_letra="X"'
            .' AND penvp_sucursal=0'
            .' AND penvp_nro='.$nroOc
            .' AND penvp_articulo=stkm_articulo',
            $errores,
            'penvp-oc',
        );

        foreach ($lineasOc as $lineaOc) {
            $cuenta = (int) ($lineaOc->stkm_cta_contable_c ?? 0);
            if ($cuenta <= 0) {
                continue;
            }

            $ctaco = $this->listar(
                'contab',
                'ctaconc',
                'ctaco_concepto',
                ' WHERE ctaco_empresa='.$empresaId
                .' AND ctaco_cuenta='.$cuenta,
                $errores,
                'ctaconc-oc',
            );

            $concepto = (int) ($ctaco[0]->ctaco_concepto ?? 0);
            if ($concepto > 0) {
                return $concepto;
            }
        }

        return 0;
    }

    public function cargarPromae(string $proveedor, array &$errores): ?object
    {
        $filas = $this->listar(
            'compras',
            'promae',
            'prom_proveedor,prom_nombre,prom_cuit,prom_cond_iva',
            ' WHERE prom_proveedor="'.$proveedor.'"',
            $errores,
            'promae'
        );

        return $filas[0] ?? null;
    }

    /**
     * Carga aplicped solo para comprobantes concretos (evita traer todo el histórico del proveedor).
     *
     * @param  list<array{0: string, 1: string, 2: string, 3: int, 4: int}>  $facturas  [proveedor, tipo, letra, sucursal, nro]
     * @return list<object>
     */
    public function cargarAplicpedPorFacturas(array $facturas, array &$errores): array
    {
        $facturas = array_values(array_filter($facturas, function (array $f): bool {
            return trim($f[0] ?? '') !== ''
                && trim($f[1] ?? '') !== ''
                && (int) ($f[4] ?? 0) > 0;
        }));

        if ($facturas === []) {
            return [];
        }

        $filas = [];
        foreach (array_chunk($facturas, 30) as $lote) {
            $conds = [];
            foreach ($lote as [$prov, $tipo, $letra, $suc, $nro]) {
                $conds[] = '(aplp_proveedor="'.addslashes($prov).'"'
                    .' AND aplp_tipo="'.addslashes($tipo).'"'
                    .' AND aplp_letra='.$this->sqlChar($letra)
                    .' AND aplp_sucursal='.(int) $suc
                    .' AND aplp_nro='.(int) $nro.')';
            }

            $filas = array_merge(
                $filas,
                $this->listar(
                    'compras',
                    'aplicped',
                    'aplp_proveedor,aplp_tipo,aplp_letra,aplp_sucursal,aplp_nro,aplp_ref_tipo,aplp_ref_letra,aplp_ref_sucursal,aplp_ref_nro,aplp_orden,aplp_cantfact',
                    ' WHERE '.implode(' OR ', $conds),
                    $errores,
                    'aplicped-facturas',
                ),
            );
        }

        return $filas;
    }

    /**
     * @deprecated Usar cargarAplicpedPorFacturas; carga histórico completo del proveedor.
     *
     * @param  list<string>  $proveedores
     * @return list<object>
     */
    public function cargarAplicpedPorProveedores(array $proveedores, array &$errores): array
    {
        $proveedores = array_values(array_unique(array_filter(array_map(
            fn ($p) => trim((string) $p),
            $proveedores,
        ), fn ($p) => $p !== '')));

        if ($proveedores === []) {
            return [];
        }

        $filas = [];
        foreach (array_chunk($proveedores, 80) as $lote) {
            $in = implode(',', array_map(
                fn ($p) => '"'.addslashes($p).'"',
                $lote,
            ));
            $filas = array_merge(
                $filas,
                $this->listar(
                    'compras',
                    'aplicped',
                    'aplp_proveedor,aplp_tipo,aplp_letra,aplp_sucursal,aplp_nro,aplp_ref_tipo,aplp_ref_letra,aplp_ref_sucursal,aplp_ref_nro,aplp_orden,aplp_cantfact',
                    ' WHERE aplp_proveedor IN ('.$in.')',
                    $errores,
                    'aplicped-bulk',
                ),
            );
        }

        return $filas;
    }

    /**
     * @param  list<string>  $proveedores
     * @return list<object>
     */
    public function cargarPromaePorProveedores(array $proveedores, array &$errores): array
    {
        $proveedores = array_values(array_unique(array_filter(array_map(
            fn ($p) => trim((string) $p),
            $proveedores,
        ), fn ($p) => $p !== '')));

        if ($proveedores === []) {
            return [];
        }

        $filas = [];
        foreach (array_chunk($proveedores, 80) as $lote) {
            $in = implode(',', array_map(
                fn ($p) => '"'.addslashes($p).'"',
                $lote,
            ));
            $filas = array_merge(
                $filas,
                $this->listar(
                    'compras',
                    'promae',
                    'prom_proveedor,prom_nombre,prom_cuit,prom_cond_iva',
                    ' WHERE prom_proveedor IN ('.$in.')',
                    $errores,
                    'promae-bulk',
                ),
            );
        }

        return $filas;
    }

    /**
     * Carga subdiario de comprobantes COM en lotes (clave: tipo|letra|sucursal|nro).
     *
     * @param  list<string>  $clavesCom  ej. COM| |1|12345
     * @return array<string, list<object>>
     */
    public function cargarComSubdiarioLote(array $clavesCom, array &$errores): array
    {
        $clavesCom = array_values(array_unique(array_filter($clavesCom, fn ($c) => trim($c) !== '')));
        if ($clavesCom === []) {
            return [];
        }

        $porClave = [];
        $campos = 'subd_empresa,subd_sistema,subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_importe,subd_desc_mov,subd_nro_operacion,subd_cod_mon,subd_cotizacion';

        foreach (array_chunk($clavesCom, 40) as $lote) {
            $condiciones = [];
            foreach ($lote as $clave) {
                [$tipo, $letra, $suc, $nro] = array_pad(explode('|', $clave, 4), 4, '');
                $tipo = trim($tipo);
                if ($tipo === '' || (int) $nro <= 0) {
                    continue;
                }
                $condiciones[] = '(subd_tipo="'.$tipo.'" AND subd_letra='.$this->sqlChar($letra)
                    .' AND subd_sucursal='.(int) $suc.' AND subd_nro='.(int) $nro.')';
            }

            if ($condiciones === []) {
                continue;
            }

            $filas = $this->listar(
                'contab',
                'subdiario',
                $campos,
                ' WHERE '.implode(' OR ', $condiciones),
                $errores,
                'subdiario-com-bulk',
            );

            foreach ($filas as $fila) {
                $clave = $this->claveComDesdeSubdiario($fila);
                if ($clave === '') {
                    continue;
                }
                $porClave[$clave][] = $fila;
            }
        }

        return $porClave;
    }

    private function claveComDesdeSubdiario(object $fila): string
    {
        $tipo = trim((string) ($fila->subd_tipo ?? ''));
        $nro = (int) ($fila->subd_nro ?? 0);
        if ($tipo === '' || $nro <= 0) {
            return '';
        }

        return implode('|', [
            $tipo,
            trim((string) ($fila->subd_letra ?? ' ')),
            (int) ($fila->subd_sucursal ?? 0),
            $nro,
        ]);
    }

    private function sqlChar(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '" "';
        }

        return '"'.addslashes($valor).'"';
    }
}
