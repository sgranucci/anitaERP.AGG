<?php

namespace Tests\Unit\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Configuracion\Provincia;
use App\Support\Compras\ComprobanteProveedorProvinciaDestinoSupport;
use Tests\TestCase;

class ComprobanteProveedorProvinciaDestinoSupportTest extends TestCase
{
    public function test_id_vacio_cae_en_default_de_instalacion(): void
    {
        $default = ComprobanteProveedorProvinciaDestinoSupport::defaultProvinciaId();
        $this->assertSame($default, ComprobanteProveedorProvinciaDestinoSupport::idDesdeRequest(null));
        $this->assertSame($default, ComprobanteProveedorProvinciaDestinoSupport::idDesdeRequest(0));
        $this->assertSame(1, ComprobanteProveedorProvinciaDestinoSupport::idDesdeRequest(1));
    }

    public function test_codigo_anita_default_y_destino(): void
    {
        $this->assertSame(
            ComprobanteProveedorProvinciaDestinoSupport::defaultCodigoAnita(),
            ComprobanteProveedorProvinciaDestinoSupport::codigoAnita(null)
        );

        $ba = new Provincia;
        $ba->codigo = '2';
        $this->assertSame(2, ComprobanteProveedorProvinciaDestinoSupport::codigoAnita($ba));

        $caba = new Provincia;
        $caba->codigo = '1';
        $this->assertSame(1, ComprobanteProveedorProvinciaDestinoSupport::codigoAnita($caba));
    }

    public function test_destino_ba_por_jurisdiccion_902(): void
    {
        $ba = new Provincia;
        $ba->id = 11;
        $ba->codigo = '11';
        $ba->codigoexterno = '11';
        $ba->jurisdiccion = '902';
        $ba->nombre = 'Buenos Aires';
        $cpBa = new Comprobante_Proveedor(['provincia_destino_id' => 11]);
        $cpBa->setRelation('provinciaDestino', $ba);
        $this->assertTrue(ComprobanteProveedorProvinciaDestinoSupport::esDestinoBuenosAires($cpBa));
    }

    public function test_destino_caba_no_aporta_retencion_arba(): void
    {
        $caba = new Provincia;
        $caba->id = 1;
        $caba->codigo = '1';
        $caba->jurisdiccion = '901';
        $caba->nombre = 'Ciudad Autonoma de Bs. As.';
        $cp = new Comprobante_Proveedor(['provincia_destino_id' => 1]);
        $cp->setRelation('provinciaDestino', $caba);
        $this->assertFalse(ComprobanteProveedorProvinciaDestinoSupport::esDestinoBuenosAires($cp));
    }
}
