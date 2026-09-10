<?php

namespace Tests\Unit\Support\Contable\LibroIvaDigital;

use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasAnitaArmadoSupport;
use PHPUnit\Framework\TestCase;

class LibroIvaDigitalComprasAnitaArmadoSupportTest extends TestCase
{
    public function test_fin_de_t_comp_compras_no_va_al_iva_compras(): void
    {
        $this->assertFalse(
            LibroIvaDigitalComprasAnitaArmadoSupport::esInformableIvaCompras('FIN', 'N', '01', 'A'),
        );
        $this->assertFalse(
            LibroIvaDigitalComprasAnitaArmadoSupport::esInformableIvaCompras('FIN', 'C', '01', 'A'),
        );
    }

    public function test_cin_interno_tampoco_va_al_iva_compras(): void
    {
        $this->assertFalse(
            LibroIvaDigitalComprasAnitaArmadoSupport::esInformableIvaCompras('CIN', 'N', '03', 'A'),
        );
    }

    public function test_factura_subdiario_compras_si_informa(): void
    {
        $this->assertTrue(
            LibroIvaDigitalComprasAnitaArmadoSupport::esInformableIvaCompras('FAC', 'C', '01', 'A'),
        );
        $this->assertTrue(
            LibroIvaDigitalComprasAnitaArmadoSupport::esInformableIvaCompras('FAC', 'G', '01', 'B'),
        );
    }

    public function test_subdiario_n_letra_e_y_recibo_04_no_informan(): void
    {
        $this->assertFalse(
            LibroIvaDigitalComprasAnitaArmadoSupport::esInformableIvaCompras('FAC', 'N', '01', 'A'),
        );
        $this->assertFalse(
            LibroIvaDigitalComprasAnitaArmadoSupport::esInformableIvaCompras('FAC', 'C', '01', 'E'),
        );
        $this->assertFalse(
            LibroIvaDigitalComprasAnitaArmadoSupport::esInformableIvaCompras('REC', 'C', '04', 'A'),
        );
    }
}
