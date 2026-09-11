<?php

namespace Tests\Unit\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Configuracion\Provincia;
use App\Support\Compras\ComprobanteProveedorProvinciaDestinoSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorProvinciaDestinoSupportTest extends TestCase
{
    public function test_id_vacio_cae_en_buenos_aires(): void
    {
        $this->assertSame(2, ComprobanteProveedorProvinciaDestinoSupport::idDesdeRequest(null));
        $this->assertSame(2, ComprobanteProveedorProvinciaDestinoSupport::idDesdeRequest(0));
        $this->assertSame(1, ComprobanteProveedorProvinciaDestinoSupport::idDesdeRequest(1));
    }

    public function test_codigo_anita_default_y_destino(): void
    {
        $this->assertSame(2, ComprobanteProveedorProvinciaDestinoSupport::codigoAnita(null));

        $ba = new Provincia;
        $ba->codigo = '2';
        $this->assertSame(2, ComprobanteProveedorProvinciaDestinoSupport::codigoAnita($ba));

        $caba = new Provincia;
        $caba->codigo = '1';
        $this->assertSame(1, ComprobanteProveedorProvinciaDestinoSupport::codigoAnita($caba));
    }

    public function test_destino_null_o_ba_aporta_retencion_arba(): void
    {
        $sinDestino = new Comprobante_Proveedor;
        $this->assertTrue(ComprobanteProveedorProvinciaDestinoSupport::esDestinoBuenosAires($sinDestino));

        $ba = new Provincia;
        $ba->id = 2;
        $ba->codigo = '2';
        $ba->codigoexterno = '2';
        $ba->jurisdiccion = '902';
        $ba->nombre = 'Buenos Aires';
        $cpBa = new Comprobante_Proveedor(['provincia_destino_id' => 2]);
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
