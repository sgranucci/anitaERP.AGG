<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProyeccionPagosMedioPagoSupport;
use PHPUnit\Framework\TestCase;

class ProyeccionPagosMedioPagoSupportTest extends TestCase
{
    public function test_nota_de_credito_importada_usa_el_medio_de_la_orden_de_compra(): void
    {
        $this->assertSame(
            'Cheque',
            ProyeccionPagosMedioPagoSupport::resolver('E', 'EFECTIVO', null, null, 'C', 'CHEQUE DIFERIDO', -1, 'ANITA_IMPORT')
        );
        $this->assertSame(
            'Transf',
            ProyeccionPagosMedioPagoSupport::resolver('E', null, null, null, 'T', null, -1, 'ANITA_IMPORT')
        );
    }

    public function test_nota_de_credito_importada_sin_orden_no_figura_como_efectivo(): void
    {
        $this->assertSame(
            '',
            ProyeccionPagosMedioPagoSupport::resolver('E', 'EFECTIVO', null, null, null, null, -1, 'ANITA_IMPORT')
        );
    }

    public function test_nota_de_credito_cargada_en_el_erp_conserva_su_medio(): void
    {
        $this->assertSame(
            'Transf',
            ProyeccionPagosMedioPagoSupport::resolver('T', null, null, null, 'C', null, -1, 'PRECARGA')
        );
    }

    public function test_factura_con_efectivo_en_cuota_usa_medio_de_la_oc(): void
    {
        $this->assertSame(
            'Cheque',
            ProyeccionPagosMedioPagoSupport::resolver('E', null, null, null, 'C', null, 1, 'ANITA_IMPORT')
        );
        $this->assertSame(
            'Cheque',
            ProyeccionPagosMedioPagoSupport::resolver('E', null, null, null, 'C', null, 1, 'LEGAJO')
        );
    }

    public function test_cuota_vinculada_no_efectivo_tiene_prioridad(): void
    {
        $this->assertSame(
            'Transf',
            ProyeccionPagosMedioPagoSupport::resolver('E', null, 'T', null, 'C', null, -1, 'ANITA_IMPORT')
        );
    }

    public function test_oc_vinculada_en_efectivo_no_bloquea_otro_medio(): void
    {
        $this->assertSame(
            'Cheque',
            ProyeccionPagosMedioPagoSupport::resolver('C', null, 'E', null, 'T', null, 1, 'LEGAJO')
        );
    }
}
