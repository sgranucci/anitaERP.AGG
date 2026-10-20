<?php

namespace Tests\Unit\Support\Contable\PercepcionSufrida;

use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasImportesSupport;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaArchivoSupport;
use PHPUnit\Framework\TestCase;

class PercepcionSufridaArchivoIvaTest extends TestCase
{
    public function test_factura_fdt_fib_fis_sale_tipo_1_con_punto_y_numero(): void
    {
        foreach (['FDT', 'FIB', 'FIS', 'FAC'] as $tipo) {
            $codigo = PercepcionSufridaArchivoSupport::tipoComprobanteIva($tipo, 100);
            $this->assertSame(1, $codigo, $tipo);
        }

        $this->assertSame(
            '00881-00005170',
            PercepcionSufridaArchivoSupport::numeroComprobanteIva(1, 881, 5170),
        );
    }

    public function test_liquidacion_bancaria_tipo_5_tiene_16_digitos(): void
    {
        $this->assertSame(5, PercepcionSufridaArchivoSupport::tipoComprobanteIva('ICO', 2526));
        $this->assertSame(
            '0000000000015406',
            PercepcionSufridaArchivoSupport::numeroComprobanteIva(5, 0, 15406),
        );
    }

    public function test_cuit_de_itau_se_informa_como_macro(): void
    {
        $this->assertSame(
            '30500010084',
            PercepcionSufridaArchivoSupport::cuitAgentePercepcionIva('30580189411'),
        );
        $this->assertSame(
            '30703088534',
            PercepcionSufridaArchivoSupport::cuitAgentePercepcionIva('30703088534'),
        );
    }

    public function test_retencion_iibb_de_ico_segun_cuit_del_banco(): void
    {
        $this->assertSame(
            'no_integra',
            LibroIvaDigitalComprasImportesSupport::destinoRubroIco('30703088534', 'Ret.IIBB', 'perc_iibb'),
        );
        $this->assertSame(
            'perc_iibb',
            LibroIvaDigitalComprasImportesSupport::destinoRubroIco('30500010084', 'Ret.IIBB', 'no_integra'),
        );
        $this->assertSame(
            'perc_nacional',
            LibroIvaDigitalComprasImportesSupport::destinoRubroIco('30500010912', 'O.Conceptos', 'perc_municipal'),
        );
        $this->assertSame(
            'perc_municipal',
            LibroIvaDigitalComprasImportesSupport::destinoRubroIco('30522211563', 'O.Conceptos', 'perc_iibb'),
        );
    }

    public function test_concepto_duplicado_no_se_suma_dos_veces(): void
    {
        $unicos = LibroIvaDigitalComprasImportesSupport::conceptosSinDuplicar([
            ['concepto' => 10, 'importe' => 844480.0],
            ['concepto' => 11, 'importe' => 228009.60],
            ['concepto' => 10, 'importe' => 844480.0],
            ['concepto' => 11, 'importe' => 228009.60],
        ]);

        $this->assertCount(2, $unicos);
    }

    public function test_otros_tributos_no_queda_negativo(): void
    {
        $registro = LibroIvaDigitalComprasImportesSupport::equilibrarResidual([
            'cabecera' => [
                'tipo_comprobante' => '001',
                'importe_total' => 100.0,
                'otros_tributos' => 0.0,
            ],
            'alicuotas' => [[
                'neto_gravado' => 200.0,
                'impuesto_liquidado' => 42.0,
            ]],
        ]);

        $this->assertSame(0.0, $registro['cabecera']['otros_tributos']);
    }
}
