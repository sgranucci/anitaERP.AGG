<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\TransferenciaCrown\PxsolArcaArchivoParser;
use PHPUnit\Framework\TestCase;

final class PxsolArcaArchivoParserFacturaTTest extends TestCase
{
    public function test_factura_t_recompone_imp_total_cuando_arca_omite_el_iva(): void
    {
        $path = $this->csvTemporal([
            'Fecha de Emisión;Tipo de Comprobante;Punto de Venta;Número Desde;Número Hasta;Cód. Autorización;Tipo Doc. Receptor;Nro. Doc. Receptor;Denominación Receptor;Tipo Cambio;Moneda;Imp. Neto Gravado IVA 0%;IVA 2,5%;Imp. Neto Gravado IVA 2,5%;IVA 5%;Imp. Neto Gravado IVA 5%;IVA 10,5%;Imp. Neto Gravado IVA 10,5%;IVA 21%;Imp. Neto Gravado IVA 21%;IVA 27%;Imp. Neto Gravado IVA 27%;Imp. Neto Gravado Total;Imp. Neto No Gravado;Imp. Op. Exentas;Otros Tributos;Total IVA;Imp. Total',
            '2026-09-07;195;127;123;123;86361836581543;91;;;1,00;$;;;;;;;;42411,60;201960,00;;;201960,00;;;0,00;42411,60;201960,00',
            '2026-09-01;1;127;1008;1008;86350951957313;80;33508358259;QUILMES;1,00;$;;;;;;;;32879,70;156570,00;;;156570,00;0,00;0,00;0,00;32879,70;189449,70',
        ]);

        try {
            $filas = (new PxsolArcaArchivoParser())->leer($path);
            $this->assertCount(2, $filas);

            $t = $filas[0];
            $this->assertSame(195, $t['tipo_afip']);
            $this->assertSame('T', $t['letra']);
            $this->assertEqualsWithDelta(201960.0, $t['gravado21'], 0.01);
            $this->assertEqualsWithDelta(42411.6, $t['iva21'], 0.01);
            $this->assertEqualsWithDelta(244371.6, $t['total'], 0.01);

            $a = $filas[1];
            $this->assertSame(1, $a['tipo_afip']);
            $this->assertEqualsWithDelta(189449.7, $a['total'], 0.01);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param  list<string>  $lineas
     */
    private function csvTemporal(array $lineas): string
    {
        $path = tempnam(sys_get_temp_dir(), 'arca_t_');
        $this->assertNotFalse($path);
        file_put_contents($path, implode("\n", $lineas)."\n");

        return $path;
    }
}
