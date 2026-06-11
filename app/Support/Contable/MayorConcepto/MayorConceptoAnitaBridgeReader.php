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

    private function sqlChar(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '" "';
        }

        return '"'.addslashes($valor).'"';
    }
}
