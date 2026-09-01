<?php

namespace Tests\Unit\Support\Contable\LibroIvaDigital;

use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalFormatoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use PHPUnit\Framework\TestCase;

class LibroIvaDigitalFormatoSupportTest extends TestCase
{
    public function test_compras_cbte_en_pesos_no_reconvierte_dolares(): void
    {
        $cabecera = LibroIvaDigitalMapeosSupport::cabeceraImportesEnPesos([
            'fecha' => '20260715',
            'tipo_comprobante' => '001',
            'punto_venta' => 7,
            'numero_comprobante' => 96,
            'codigo_documento' => '80',
            'numero_identificacion' => '30712345678',
            'nombre_vendedor' => 'PROVEEDOR USD',
            'importe_total' => 13946042.0,
            'codigo_moneda' => 'DOL',
            'tipo_cambio' => 1450.0,
            'cantidad_alicuotas' => 1,
            'credito_fiscal_computable' => 2420383.50,
        ]);

        $linea = LibroIvaDigitalFormatoSupport::registroComprasCbte($cabecera);

        $this->assertSame(325, strlen($linea));
        $this->assertSame('PES', rtrim(substr($linea, 224, 3)));
        $this->assertEqualsWithDelta(
            1.0,
            LibroIvaDigitalFormatoSupport::parseTipoCambio10(substr($linea, 227, 10)),
            0.000001,
        );
        $this->assertEqualsWithDelta(
            13946042.0,
            LibroIvaDigitalFormatoSupport::parseImporte15(substr($linea, 104, 15)),
            0.001,
        );
    }

    public function test_tipo_cambio_desbordado_no_alarga_el_registro(): void
    {
        $this->assertSame(10, strlen(LibroIvaDigitalFormatoSupport::tipoCambio10(1_000_000.0)));
        $this->assertSame(15, strlen(LibroIvaDigitalFormatoSupport::importe15(12.34)));
    }
}
