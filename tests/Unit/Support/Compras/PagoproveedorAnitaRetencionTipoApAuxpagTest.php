<?php

namespace Tests\Unit\Support\Compras;

use App\Models\Compras\Pagoproveedor_Retencion;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaRetencionNumeracionSupport;
use Tests\TestCase;

class PagoproveedorAnitaRetencionTipoApAuxpagTest extends TestCase
{
    public function test_tipo_ap_auxpag_es_rgp_rip_rsp_rtp(): void
    {
        $this->assertSame('RGP', PagoproveedorAnitaRetencionNumeracionSupport::tipoApAuxpag(
            Pagoproveedor_Retencion::TIPO_GANANCIAS
        ));
        $this->assertSame('RIP', PagoproveedorAnitaRetencionNumeracionSupport::tipoApAuxpag(
            Pagoproveedor_Retencion::TIPO_IVA
        ));
        $this->assertSame('RSP', PagoproveedorAnitaRetencionNumeracionSupport::tipoApAuxpag(
            Pagoproveedor_Retencion::TIPO_SUSS
        ));
        $this->assertSame('RTP', PagoproveedorAnitaRetencionNumeracionSupport::tipoApAuxpag(
            Pagoproveedor_Retencion::TIPO_IIBB
        ));
        $this->assertNull(PagoproveedorAnitaRetencionNumeracionSupport::tipoApAuxpag('X'));
    }
}
