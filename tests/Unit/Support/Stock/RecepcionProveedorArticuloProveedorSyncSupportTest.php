<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorArticuloProveedorSyncSupport;
use PHPUnit\Framework\TestCase;

class RecepcionProveedorArticuloProveedorSyncSupportTest extends TestCase
{
    public function test_sin_codigo_no_interrumpe_el_guardado_de_com_de_servicios(): void
    {
        $this->assertFalse(RecepcionProveedorArticuloProveedorSyncSupport::accionPreviewRequiereModal('sin_codigo'));
        $this->assertFalse(RecepcionProveedorArticuloProveedorSyncSupport::accionPreviewRequiereModal(null));
        $this->assertFalse(RecepcionProveedorArticuloProveedorSyncSupport::accionPreviewRequiereModal(''));
    }

    public function test_alta_o_conflicto_de_catalogo_si_requieren_modal(): void
    {
        $this->assertTrue(RecepcionProveedorArticuloProveedorSyncSupport::accionPreviewRequiereModal('crear'));
        $this->assertTrue(RecepcionProveedorArticuloProveedorSyncSupport::accionPreviewRequiereModal('completar'));
        $this->assertTrue(RecepcionProveedorArticuloProveedorSyncSupport::accionPreviewRequiereModal('complementar'));
        $this->assertTrue(RecepcionProveedorArticuloProveedorSyncSupport::accionPreviewRequiereModal('conflicto'));
    }
}
