<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorImporteParserSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorPieTotalesSupport;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrNumeroOcExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Factura AFIP De Vito José A-00002-00001117 (locación):
 * «Subtotal c/IVA» 266200 no debe inflar el total a 312400 (neto+IVA+IVA).
 * El precio unitario 220000,00 no es OC.
 */
class FacturaProveedorPieDeVitoFac1117Test extends TestCase
{
    private const TEXTO = <<<'TXT'
ORIGINAL

                       DE VITO JOSE                                                    A                 FACTURA
                                                                                      COD. 01
                                                                                                         Punto de Venta: 00002            Comp. Nro: 00001117
Razón Social: DE VITO JOSE                                                                               Fecha de Emisión: 09/11/2026
CUIT: 20935764921
CUIT:    30551587300                                            Apellido y Nombre / Razón Social: CALZADOS FERLI S A

                                                                                                                                                Alicuota
Código    Producto / Servicio                                         Cantidad        U. medida        Precio Unit.     % Bonif    Subtotal
                                                                                                                                                  IVA
                                                                                                                                                           Subtotal c/IVA

          Por Locacion de Inmueble ubicado en Chilavert                        1,00      otras              220000,00     0,00      220000,00    21%           266200,00
          6452 CABA correspondiente al mes de                                          unidades
          Noviembre 2026

                                                                                                                          Importe Neto Gravado: $            220000,00
                                                                                                                                       IVA 27%: $                 0,00
                                                                                                                                       IVA 21%: $             46200,00
                                                                                                                                     IVA 10.5%: $                 0,00
                                                                                                                                        IVA 5%: $                 0,00
                                                                                                                                      IVA 2.5%: $                 0,00
                                                                                                                                        IVA 0%: $                 0,00
                                                                                                                         Importe Otros Tributos: $                0,00
                                                                                                                                  Importe Total: $          266200,00

                                                                                                                                    CAE N°: 86463142519889
TXT;

    public function test_pie_no_infla_total_con_subtotal_c_iva(): void
    {
        $pie = $this->pie()->extraer(self::TEXTO);

        $this->assertEqualsWithDelta(220000.0, (float) $pie['subtotal'], 0.01);
        $this->assertEqualsWithDelta(46200.0, (float) $pie['iva'], 0.01);
        $this->assertEqualsWithDelta(266200.0, (float) $pie['total'], 0.01);

        $suma = round(array_sum(array_column($pie['lineas'], 'importe')), 2);
        $this->assertEqualsWithDelta(266200.0, $suma, 0.01);
    }

    public function test_precio_unitario_no_es_orden_de_compra(): void
    {
        $oc = (new RecepcionProveedorOcrNumeroOcExtractor)->extraer(self::TEXTO);

        $this->assertNull($oc['numero']);
        $this->assertSame([], $oc['candidatos']);
    }

    private function pie(): FacturaProveedorPieTotalesSupport
    {
        return new FacturaProveedorPieTotalesSupport(new FacturaProveedorImporteParserSupport);
    }
}
