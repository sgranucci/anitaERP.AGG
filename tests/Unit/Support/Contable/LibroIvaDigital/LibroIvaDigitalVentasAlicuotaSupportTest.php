<?php

namespace Tests\Unit\Support\Contable\LibroIvaDigital;

use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalFormatoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalValidacionSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasAgrupacionSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasAlicuotaSupport;
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
            'iva_simple' => ['resumen' => ['total_iva_debito' => 0, 'sin_actividad_arca' => 0]],
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
