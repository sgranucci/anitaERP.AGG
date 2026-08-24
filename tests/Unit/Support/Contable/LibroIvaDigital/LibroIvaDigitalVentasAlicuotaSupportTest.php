<?php

namespace Tests\Unit\Support\Contable\LibroIvaDigital;

use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasAlicuotaSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasCuitSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasAnitaArmadoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalFormatoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalIdentificacionSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalValidacionSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasAgrupacionSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasAlicuotaSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasFslAnitaArmadoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasPeriodoSupport;
use PHPUnit\Framework\TestCase;

class LibroIvaDigitalVentasAlicuotaSupportTest extends TestCase
{
    public function test_factura_b_exenta_informa_una_alicuota_cero_y_codigo_e(): void
    {
        $registro = LibroIvaDigitalVentasAlicuotaSupport::asegurarRegistro([
            'cabecera' => $this->cabeceraFacturaBExenta(),
            'alicuotas' => [],
        ]);

        $this->assertSame(1, $registro['cabecera']['cantidad_alicuotas']);
        $this->assertSame('E', $registro['cabecera']['codigo_operacion']);
        $this->assertCount(1, $registro['alicuotas']);
        $this->assertSame('0003', $registro['alicuotas'][0]['alicuota_iva']);
        $this->assertSame(0.0, $registro['alicuotas'][0]['neto_gravado']);
        $this->assertSame(0.0, $registro['alicuotas'][0]['impuesto_liquidado']);
    }

    public function test_archivo_cbte_no_lleva_cantidad_alicuotas_en_cero(): void
    {
        $registro = LibroIvaDigitalVentasAlicuotaSupport::asegurarRegistro([
            'cabecera' => $this->cabeceraFacturaBExenta(),
            'alicuotas' => [],
        ]);
        $linea = LibroIvaDigitalFormatoSupport::registroVentasCbte($registro['cabecera']);
        $alic = LibroIvaDigitalFormatoSupport::registroVentasAlicuota($registro['alicuotas'][0]);

        $this->assertSame(266, strlen($linea));
        $this->assertSame('1', substr($linea, 241, 1));
        $this->assertSame('E', substr($linea, 242, 1));
        $this->assertSame(62, strlen($alic));
        $this->assertSame('0003', substr($alic, 43, 4));
    }

    public function test_factura_c_sigue_sin_alicuotas(): void
    {
        $registro = LibroIvaDigitalVentasAlicuotaSupport::asegurarRegistro([
            'cabecera' => [
                'tipo_comprobante' => '011',
                'punto_venta' => 1,
                'numero_comprobante' => 10,
                'cantidad_alicuotas' => 0,
                'codigo_operacion' => ' ',
            ],
            'alicuotas' => [],
        ]);

        $this->assertSame(0, $registro['cabecera']['cantidad_alicuotas']);
        $this->assertSame([], $registro['alicuotas']);
    }

    public function test_grupo_venta_global_diaria_exenta_consolida_alicuota_cero(): void
    {
        $a = LibroIvaDigitalVentasAlicuotaSupport::asegurarRegistro([
            'cabecera' => $this->cabeceraFacturaBExenta(['numero_comprobante' => 199471, 'importe_total' => 0.01, 'operaciones_exentas' => 0.01]),
            'alicuotas' => [],
        ]);
        $b = LibroIvaDigitalVentasAlicuotaSupport::asegurarRegistro([
            'cabecera' => $this->cabeceraFacturaBExenta(['numero_comprobante' => 199472, 'importe_total' => 0.01, 'operaciones_exentas' => 0.01]),
            'alicuotas' => [],
        ]);

        $grupo = LibroIvaDigitalVentasAgrupacionSupport::consolidarGrupoFacturaB([$a, $b]);

        $this->assertSame(1, $grupo['cabecera']['cantidad_alicuotas']);
        $this->assertSame('E', $grupo['cabecera']['codigo_operacion']);
        $this->assertSame('-VENTA GLOBAL DIARIA-', $grupo['cabecera']['nombre_comprador']);
        $this->assertSame(199471, $grupo['cabecera']['numero_comprobante']);
        $this->assertSame(199472, $grupo['cabecera']['numero_hasta']);
        $this->assertCount(1, $grupo['alicuotas']);
        $this->assertSame('0003', $grupo['alicuotas'][0]['alicuota_iva']);

        $linea = LibroIvaDigitalFormatoSupport::registroVentasCbte($grupo['cabecera']);
        $this->assertSame('1', substr($linea, 241, 1));
    }

    public function test_pes_fuerza_tipo_de_cambio_uno_aunque_la_cotizacion_sea_otra(): void
    {
        $this->assertSame(1.0, LibroIvaDigitalMapeosSupport::tipoCambioArca('PES', 1495.0));
        $this->assertSame(1495.0, LibroIvaDigitalMapeosSupport::tipoCambioArca('DOL', 1495.0));

        $cabecera = $this->cabeceraFacturaBExenta(['tipo_cambio' => 1495.0, 'codigo_moneda' => 'PES']);
        $linea = LibroIvaDigitalFormatoSupport::registroVentasCbte($cabecera);

        $this->assertSame(266, strlen($linea));
        $this->assertSame('PES', substr($linea, 228, 3));
        $this->assertSame('0001000000', substr($linea, 231, 10));
        $this->assertSame(1.0, LibroIvaDigitalFormatoSupport::parseTipoCambio10(substr($linea, 231, 10)));
    }

    public function test_validacion_detecta_tipo_de_cambio_distinto_de_uno_en_pes(): void
    {
        $linea = LibroIvaDigitalFormatoSupport::registroVentasCbte($this->cabeceraFacturaBExenta());
        $lineaMala = substr($linea, 0, 231).'1495000000'.substr($linea, 241);
        $avisos = LibroIvaDigitalValidacionSupport::validar([
            'ventas' => [
                'ventas_cbte' => $lineaMala,
                'ventas_alicuotas' => '',
                'resumen' => ['comprobantes' => 1, 'alicuotas' => 0, 'comprobantes_con_alicuotas' => 0, 'total_iva' => 0],
            ],
            'compras' => ['resumen' => ['comprobantes' => 0]],
            'iva_simple' => ['resumen' => ['total_iva_debito' => 0, 'sin_actividad_arca' => 0, 'total_iva_credito' => 0, 'renglones_credito' => 0]],
        ]);

        $this->assertNotEmpty($avisos);
        $hayTipoCambio = false;
        foreach ($avisos as $aviso) {
            if (str_contains($aviso, 'tipo de cambio')) {
                $hayTipoCambio = true;
                break;
            }
        }
        $this->assertTrue($hayTipoCambio, implode("\n", $avisos));
    }

    public function test_grupo_mixto_descarta_alicuota_cero_dummy(): void
    {
        $exenta = LibroIvaDigitalVentasAlicuotaSupport::asegurarRegistro([
            'cabecera' => $this->cabeceraFacturaBExenta(['numero_comprobante' => 10, 'importe_total' => 0.01, 'operaciones_exentas' => 0.01]),
            'alicuotas' => [],
        ]);
        $gravada = [
            'cabecera' => $this->cabeceraFacturaBExenta([
                'numero_comprobante' => 11,
                'importe_total' => 121.0,
                'operaciones_exentas' => 0.0,
                'cantidad_alicuotas' => 1,
                'codigo_operacion' => ' ',
            ]),
            'alicuotas' => [[
                'tipo_comprobante' => '006',
                'punto_venta' => 11,
                'numero_comprobante' => 11,
                'neto_gravado' => 100.0,
                'alicuota_iva' => '0005',
                'impuesto_liquidado' => 21.0,
            ]],
        ];

        $grupo = LibroIvaDigitalVentasAgrupacionSupport::consolidarGrupoFacturaB([$exenta, $gravada]);

        $this->assertSame(1, $grupo['cabecera']['cantidad_alicuotas']);
        $this->assertSame(' ', $grupo['cabecera']['codigo_operacion']);
        $this->assertSame('0005', $grupo['alicuotas'][0]['alicuota_iva']);
    }

    public function test_rmv_mapea_factura_b_con_documento_89_como_anita(): void
    {
        $this->assertSame('006', LibroIvaDigitalMapeosSupport::tipoComprobanteVentas('RMV', 'Z', 'RMV'));
        $this->assertSame('006', LibroIvaDigitalMapeosSupport::tipoComprobanteVentas('001', 'B', 'FAC'));
        $this->assertTrue(LibroIvaDigitalMapeosSupport::esRmv('RMV'));
        $this->assertFalse(LibroIvaDigitalMapeosSupport::esRmv('IZV'));

        $comprador = LibroIvaDigitalMapeosSupport::compradorRmv('Venta expendedoras');
        $this->assertSame('89', $comprador['codigo_documento']);
        $this->assertSame('1', $comprador['numero_identificacion']);
        $this->assertSame('Venta expendedoras', $comprador['nombre']);
    }

    public function test_fbi_fsl_mapean_factura_b_sin_cae_informables(): void
    {
        $this->assertSame('006', LibroIvaDigitalMapeosSupport::tipoComprobanteVentas('FBI', 'B', 'FBI'));
        $this->assertSame('006', LibroIvaDigitalMapeosSupport::tipoComprobanteVentas('FSL', 'B', 'FSL'));
        $this->assertTrue(LibroIvaDigitalMapeosSupport::esSinCaeInformable('RMV'));
        $this->assertTrue(LibroIvaDigitalMapeosSupport::esSinCaeInformable('FBI'));
        $this->assertTrue(LibroIvaDigitalMapeosSupport::esSinCaeInformable('FSL'));
        $this->assertFalse(LibroIvaDigitalMapeosSupport::esSinCaeInformable('IZV'));
        $this->assertTrue(LibroIvaDigitalMapeosSupport::esFbiOFsl('FBI'));
        $this->assertTrue(LibroIvaDigitalMapeosSupport::esFbiOFsl('FSL'));
        $this->assertFalse(LibroIvaDigitalMapeosSupport::esFbiOFsl('RMV'));
        $this->assertSame(
            ['RMV', 'FBI', 'FSL'],
            LibroIvaDigitalMapeosSupport::abreviaturasSinCaeInformables(),
        );
    }

    public function test_fsl_anita_arma_factura_b_exenta_tipo_006(): void
    {
        $fila = [
            'ven_tipo' => 'FSL',
            'ven_letra' => 'B',
            'ven_sucursal' => '14',
            'ven_nro' => '6903',
            'ven_fecha' => '20260801',
            'ven_fecha_vto' => '20260801',
            'ven_monto' => '81952440.34',
            'ven_exento' => '81952440.34',
            'ven_gravado' => '0',
            'ven_impuesto1' => '0',
            'ven_nombre_cliente' => 'Venta maquinas',
        ];

        $reg = LibroIvaDigitalVentasFslAnitaArmadoSupport::armarRegistroLibro($fila, false);
        $this->assertNotNull($reg);
        $this->assertSame('006', $reg['cabecera']['tipo_comprobante']);
        $this->assertSame(14, $reg['cabecera']['punto_venta']);
        $this->assertSame(6903, $reg['cabecera']['numero_comprobante']);
        $this->assertSame('E', $reg['cabecera']['codigo_operacion']);
        $this->assertEqualsWithDelta(81952440.34, $reg['cabecera']['importe_total'], 0.01);
        $this->assertEqualsWithDelta(81952440.34, $reg['cabecera']['operaciones_exentas'], 0.01);
        $this->assertSame('99', $reg['cabecera']['codigo_documento']);
        $this->assertSame('0', $reg['cabecera']['numero_identificacion']);

        $exento = LibroIvaDigitalVentasFslAnitaArmadoSupport::filaIvaSimpleExento($fila);
        $this->assertNotNull($exento);
        $this->assertSame('920009', $exento['actividad_codigo']);
        $this->assertSame('3', $exento['tipo_operacion']);
        $this->assertEqualsWithDelta(81952440.34, $exento['exento'], 0.01);
        $this->assertSame('14|6903', LibroIvaDigitalVentasFslAnitaArmadoSupport::claveDesdeFilaAnita($fila));
    }

    public function test_fsl_anita_sin_sucursal_usa_punto_venta_default(): void
    {
        $fila = [
            'ven_tipo' => 'FSL',
            'ven_nro' => '100',
            'ven_fecha' => '20260715',
            'ven_exento' => '500.50',
            'ven_nombre_cliente' => 'Sala de máquinas',
        ];

        $this->assertNull(LibroIvaDigitalVentasFslAnitaArmadoSupport::armarRegistroLibro($fila, false));
        $reg = LibroIvaDigitalVentasFslAnitaArmadoSupport::armarRegistroLibro($fila, false, 14);
        $this->assertNotNull($reg);
        $this->assertSame(14, $reg['cabecera']['punto_venta']);
        $this->assertSame(100, $reg['cabecera']['numero_comprobante']);
        $this->assertSame('99', $reg['cabecera']['codigo_documento']);
        $this->assertNotNull(LibroIvaDigitalVentasFslAnitaArmadoSupport::filaIvaSimpleExento($fila, false, 14));
        $this->assertNull(LibroIvaDigitalVentasFslAnitaArmadoSupport::filaIvaSimpleExento($fila));
    }

    public function test_fecha_jornada_usa_jornada_y_cae_si_falta(): void
    {
        $this->assertSame(
            '20260731',
            LibroIvaDigitalVentasPeriodoSupport::fechaYmd('2026-07-31', '2026-08-01', true),
        );
        $this->assertSame(
            '20260801',
            LibroIvaDigitalVentasPeriodoSupport::fechaYmd('2026-07-31', '2026-08-01', false),
        );
        $this->assertSame(
            '20260801',
            LibroIvaDigitalVentasPeriodoSupport::fechaYmd(null, '2026-08-01', true),
        );
    }

    public function test_compra_ico_sin_cuit_resuelve_total_coin_y_fiserv(): void
    {
        $this->assertTrue(LibroIvaDigitalComprasCuitSupport::esCuitValido(LibroIvaDigitalComprasCuitSupport::CUIT_TOTAL_COIN));
        $this->assertTrue(LibroIvaDigitalComprasCuitSupport::esCuitValido(LibroIvaDigitalComprasCuitSupport::CUIT_FISERV));

        $this->assertSame(
            LibroIvaDigitalComprasCuitSupport::CUIT_TOTAL_COIN,
            LibroIvaDigitalComprasCuitSupport::resolver('0', 'TOTAL COIN MAQUINAS 10231/0'),
        );
        $this->assertSame(
            LibroIvaDigitalComprasCuitSupport::CUIT_FISERV,
            LibroIvaDigitalComprasCuitSupport::resolver('', 'MEDIO DE COBRO FISE 10233/0'),
        );

        $registro = LibroIvaDigitalComprasAlicuotaSupport::asegurarRegistro([
            'cabecera' => [
                'tipo_comprobante' => '002',
                'punto_venta' => 0,
                'numero_comprobante' => 15320,
                'codigo_documento' => '80',
                'numero_identificacion' => '0',
                'nombre_vendedor' => 'TOTAL COIN MAQUINAS 10231/0',
                'cantidad_alicuotas' => 0,
                'codigo_operacion' => ' ',
                'operaciones_exentas' => 100.0,
            ],
            'alicuotas' => [],
        ]);

        $this->assertSame(LibroIvaDigitalComprasCuitSupport::CUIT_TOTAL_COIN, $registro['cabecera']['numero_identificacion']);
        $this->assertSame(LibroIvaDigitalComprasCuitSupport::CUIT_TOTAL_COIN, $registro['alicuotas'][0]['numero_identificacion']);
        $this->assertSame('80', $registro['cabecera']['codigo_documento']);
        $this->assertSame(1, $registro['cabecera']['punto_venta']);
    }

    public function test_compra_sin_cuit_ni_alias_usa_documento_99(): void
    {
        $registro = LibroIvaDigitalComprasAlicuotaSupport::asegurarRegistro([
            'cabecera' => [
                'tipo_comprobante' => '002',
                'punto_venta' => 1,
                'numero_comprobante' => 1,
                'codigo_documento' => '80',
                'numero_identificacion' => '0',
                'nombre_vendedor' => 'ICO BANCARIO SIN ALIAS 999/0',
                'cantidad_alicuotas' => 0,
                'codigo_operacion' => ' ',
                'operaciones_exentas' => 50.0,
            ],
            'alicuotas' => [],
        ]);

        $this->assertSame('99', $registro['cabecera']['codigo_documento']);
        $this->assertSame('0', $registro['cabecera']['numero_identificacion']);
        $this->assertSame('99', $registro['alicuotas'][0]['codigo_documento']);
    }

    public function test_identificacion_80_o_96_con_cero_pasa_a_99(): void
    {
        $this->assertSame(
            ['codigo_documento' => '99', 'numero_identificacion' => '0'],
            LibroIvaDigitalIdentificacionSupport::asegurar('80', '0'),
        );
        $this->assertSame(
            ['codigo_documento' => '99', 'numero_identificacion' => '0'],
            LibroIvaDigitalIdentificacionSupport::asegurar('96', ''),
        );
        $this->assertSame(
            ['codigo_documento' => '80', 'numero_identificacion' => '30711942838'],
            LibroIvaDigitalIdentificacionSupport::asegurar('80', '30711942838'),
        );
        $this->assertSame(
            ['codigo_documento' => '99', 'numero_identificacion' => '0'],
            LibroIvaDigitalIdentificacionSupport::asegurar('99', '0'),
        );
    }

    public function test_compra_factura_a_exenta_informa_alicuota_cero(): void
    {
        $registro = LibroIvaDigitalComprasAlicuotaSupport::asegurarRegistro([
            'cabecera' => [
                'tipo_comprobante' => '001',
                'punto_venta' => 151,
                'numero_comprobante' => 427352,
                'codigo_documento' => '80',
                'numero_identificacion' => '30682737650',
                'cantidad_alicuotas' => 0,
                'codigo_operacion' => ' ',
                'operaciones_exentas' => 20418928.23,
            ],
            'alicuotas' => [],
        ]);

        $this->assertSame(1, $registro['cabecera']['cantidad_alicuotas']);
        $this->assertSame('E', $registro['cabecera']['codigo_operacion']);
        $this->assertSame('0003', $registro['alicuotas'][0]['alicuota_iva']);
        $this->assertSame('80', $registro['alicuotas'][0]['codigo_documento']);
        $this->assertSame('30682737650', $registro['alicuotas'][0]['numero_identificacion']);
    }

    public function test_compra_punto_venta_cero_pasa_a_uno(): void
    {
        $registro = LibroIvaDigitalComprasAlicuotaSupport::asegurarRegistro([
            'cabecera' => [
                'tipo_comprobante' => '002',
                'punto_venta' => 0,
                'numero_comprobante' => 15348,
                'codigo_documento' => '80',
                'numero_identificacion' => '30500010084',
                'cantidad_alicuotas' => 1,
                'codigo_operacion' => ' ',
            ],
            'alicuotas' => [[
                'tipo_comprobante' => '002',
                'punto_venta' => 0,
                'numero_comprobante' => 15348,
                'neto_gravado' => 100.0,
                'alicuota_iva' => '0005',
                'impuesto_liquidado' => 21.0,
            ]],
        ]);

        $this->assertSame(1, $registro['cabecera']['punto_venta']);
        $this->assertSame(1, $registro['alicuotas'][0]['punto_venta']);
        $linea = LibroIvaDigitalFormatoSupport::registroComprasCbte(array_merge(
            $this->cabeceraCompraMinima(),
            $registro['cabecera'],
        ));
        $this->assertSame(325, strlen($linea));
        $this->assertSame('00001', substr($linea, 11, 5));
    }

    public function test_compra_factura_c_sigue_sin_alicuotas(): void
    {
        $registro = LibroIvaDigitalComprasAlicuotaSupport::asegurarRegistro([
            'cabecera' => [
                'tipo_comprobante' => '011',
                'punto_venta' => 3,
                'numero_comprobante' => 10,
                'cantidad_alicuotas' => 0,
                'codigo_operacion' => ' ',
            ],
            'alicuotas' => [],
        ]);

        $this->assertSame(0, $registro['cabecera']['cantidad_alicuotas']);
        $this->assertSame([], $registro['alicuotas']);
    }

    public function test_clave_natural_compras_anita_es_estable(): void
    {
        $a = LibroIvaDigitalComprasAnitaArmadoSupport::claveNatural('123', 'FNU', 'A', 1, 55);
        $b = LibroIvaDigitalComprasAnitaArmadoSupport::claveNatural('000123', 'fnu', 'a', 1, 55);
        $this->assertSame($a, $b);
        $this->assertSame('000123|FNU|A|1|55', $a);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function cabeceraCompraMinima(): array
    {
        return [
            'fecha' => '20260701',
            'tipo_comprobante' => '002',
            'punto_venta' => 1,
            'numero_comprobante' => 1,
            'despacho_importacion' => '',
            'codigo_documento' => '80',
            'numero_identificacion' => '30500010084',
            'nombre_vendedor' => 'BANCO MACRO',
            'importe_total' => 121.0,
            'codigo_moneda' => 'PES',
            'tipo_cambio' => 1.0,
            'cantidad_alicuotas' => 1,
            'codigo_operacion' => ' ',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function cabeceraFacturaBExenta(array $overrides = []): array
    {
        return array_merge([
            'fecha' => '20260701',
            'tipo_comprobante' => '006',
            'punto_venta' => 11,
            'numero_comprobante' => 199471,
            'numero_hasta' => 199471,
            'codigo_documento' => '99',
            'numero_identificacion' => '0',
            'nombre_comprador' => '-CONSUMIDOR FINAL-',
            'importe_total' => 1.10,
            'no_integra_neto' => 0.0,
            'percepcion_no_categorizados' => 0.0,
            'operaciones_exentas' => 1.10,
            'percepciones_nacionales' => 0.0,
            'percepciones_iibb' => 0.0,
            'percepciones_municipales' => 0.0,
            'impuestos_internos' => 0.0,
            'codigo_moneda' => 'PES',
            'tipo_cambio' => 1.0,
            'cantidad_alicuotas' => 0,
            'codigo_operacion' => ' ',
            'otros_tributos' => 0.0,
            'fecha_vencimiento' => '00000000',
        ], $overrides);
    }
}
