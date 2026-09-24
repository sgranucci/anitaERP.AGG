<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caja\Macro;

use App\Support\Caja\Macro\MacroArchivoPagoFormatoSupport;
use PHPUnit\Framework\TestCase;

/**
 * Layouts BNF / OPG / RTN contra p-enviamacro.c (separador TAB).
 */
class MacroArchivoPagoFormatoSupportTest extends TestCase
{
    public function test_opg_transferencia_con_tab_y_coma_decimal(): void
    {
        $opg = MacroArchivoPagoFormatoSupport::generarOpg([[
            'cuit' => '30-71011529-6',
            'sucursal_banco' => 9,
            'orden_pago' => 'OPP-0001-00001234',
            'nombre' => 'PROVEEDOR DEMO SA',
            'importe' => 12345.67,
            'cuenta_debito' => '365109401250715',
            'referencia_cbu_o_cheque' => '0070091830004035975387',
            'modalidad' => MacroArchivoPagoFormatoSupport::MODALIDAD_TRANSF_OTROS,
            'flag_entrega' => 0,
            'fecha_pago' => '2026-09-16',
            'fecha_cheque' => '',
        ]]);

        $this->assertStringNotContainsString("\0", $opg);
        $campos = explode("\t", rtrim($opg, "\n"));
        $this->assertSame('10', $campos[0]);
        $this->assertSame('30710115296', $campos[1]);
        $this->assertSame('9', $campos[2]);
        $this->assertSame(str_pad('OPP-0001-00001234', 30), $campos[3]);
        $this->assertSame('12345,67', trim($campos[5]));
        $this->assertSame('365109401250715', $campos[6]);
        $this->assertStringStartsWith('0070091830004035975387', $campos[7]);
        $this->assertSame('4', $campos[8]);
        $this->assertSame('0', $campos[9]);
        $this->assertSame('16/09/2026', $campos[10]);
    }

    public function test_bnf_beneficiario(): void
    {
        $bnf = MacroArchivoPagoFormatoSupport::generarBnf([[
            'cuit' => '20-12345678-9',
            'ing_bruto' => 1,
            'ganancia' => 1,
            'iva' => 1,
            'nombre' => 'JUAN PEREZ',
            'proveedor_codigo' => '001234',
            'domicilio' => 'NO INFORMADA',
            'cod_postal' => '1870',
            'email' => 'a@b.com',
        ]]);

        $campos = explode("\t", rtrim($bnf, "\n"));
        $this->assertSame('10', $campos[0]);
        $this->assertSame('20123456789', $campos[1]);
        $this->assertSame('01', $campos[2]);
        $this->assertSame(16, count($campos));
        $this->assertSame('1870', $campos[9]);
        $this->assertSame(40, strlen($campos[5]));
        $this->assertStringContainsString('JUAN PEREZ', $campos[5]);
        $this->assertStringContainsString('001234', $campos[5]);
    }

    public function test_bnf_cp_vacio_usa_1001_y_ib_999_queda_en_2_digitos(): void
    {
        $bnf = MacroArchivoPagoFormatoSupport::generarBnf([[
            'cuit' => '30710115296',
            'ing_bruto' => 999,
            'ganancia' => 2,
            'iva' => 1,
            'nombre' => str_repeat('Ñ', 30),
            'proveedor_codigo' => '99',
            'domicilio' => "CALLE\tCON\nTAB",
            'cod_postal' => '',
            'email' => 'a@b.com',
        ]]);

        $campos = explode("\t", rtrim($bnf, "\n"));
        $this->assertCount(16, $campos);
        $this->assertSame('02', $campos[2]); // 999 → default 02 (máx 2 dígitos)
        $this->assertSame('1001', $campos[9]);
        $this->assertSame(40, strlen($campos[5]));
        $this->assertStringNotContainsString("\t", $campos[6]);
    }

    public function test_bnf_omite_cuit_corto(): void
    {
        $bnf = MacroArchivoPagoFormatoSupport::generarBnf([[
            'cuit' => '20123456',
            'ing_bruto' => 1,
            'ganancia' => 1,
            'iva' => 1,
            'nombre' => 'X',
            'proveedor_codigo' => '1',
            'cod_postal' => '1001',
            'email' => 'a@b.com',
        ]]);

        $this->assertSame('', $bnf);
    }

    public function test_modalidad_macro_vs_otros(): void
    {
        $this->assertSame(
            MacroArchivoPagoFormatoSupport::MODALIDAD_TRANSF_MACRO,
            MacroArchivoPagoFormatoSupport::modalidadTransferencia('2850651330094012507151')
        );
        $this->assertSame(
            MacroArchivoPagoFormatoSupport::MODALIDAD_TRANSF_OTROS,
            MacroArchivoPagoFormatoSupport::modalidadTransferencia('0070091830004035975387')
        );
        $this->assertSame(
            MacroArchivoPagoFormatoSupport::MODALIDAD_CHEQUE,
            MacroArchivoPagoFormatoSupport::modalidadCheque('20260916', '20260916')
        );
        $this->assertSame(
            MacroArchivoPagoFormatoSupport::MODALIDAD_CHEQUE_DIFERIDO,
            MacroArchivoPagoFormatoSupport::modalidadCheque('20260901', '20260916')
        );
    }

    public function test_rtn_zona_header_vs_detalle(): void
    {
        $rtn = MacroArchivoPagoFormatoSupport::generarRtn([
            [
                'orden_pago' => 'OPP-0001-00000001',
                'tipo_id' => 90,
                'zona_id' => 1,
                'secuencia_id' => 1,
                'texto' => "CABECERA\nCON Ñ",
                'usuario' => 'FIRMANTE1',
            ],
            [
                'orden_pago' => 'OPP-0001-00000001',
                'tipo_id' => 90,
                'zona_id' => 2,
                'secuencia_id' => 2,
                'texto' => 'detalle',
                'usuario' => 'FIRMANTE1',
            ],
        ]);
        $lineas = array_values(array_filter(explode("\n", $rtn)));
        $this->assertCount(2, $lineas);
        $this->assertStringContainsString('FIRMANTE1', $lineas[0]);
        $this->assertStringNotContainsString("\n", explode("\t", $lineas[0])[4]);
    }
}
