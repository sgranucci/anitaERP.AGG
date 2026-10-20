<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compras;

use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Support\Compras\PagoproveedorDevolucionRetencionSupport;
use PHPUnit\Framework\TestCase;

class PagoproveedorDevolucionRetencionSupportTest extends TestCase
{
    public function test_leyenda_y_deteccion(): void
    {
        $origen = new Pagoproveedor(['numerotransaccion' => '125457', 'detalle' => 'Orden de pago']);
        $leyenda = PagoproveedorDevolucionRetencionSupport::leyenda($origen, 'Macro');
        $this->assertSame('DEV RET OPP 125457: Macro', $leyenda);

        $hija = new Pagoproveedor(['detalle' => $leyenda]);
        $this->assertTrue(PagoproveedorDevolucionRetencionSupport::esDevolucion($hija));
        $this->assertFalse(PagoproveedorDevolucionRetencionSupport::esDevolucion($origen));
    }

    public function test_validar_seleccion_rechaza_exceso(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('supera el disponible');

        $ret = new Pagoproveedor_Retencion([
            'tiporetencion' => 'G',
            'importe' => 16640,
        ]);
        $ret->id = 10;

        $origen = new Pagoproveedor();
        $origen->setRelation('pagoproveedor_retenciones', collect([$ret]));

        PagoproveedorDevolucionRetencionSupport::validarSeleccion(
            $origen,
            [['id' => 10, 'importe' => 20000]],
            [['id' => 10, 'etiqueta' => 'Ganancias', 'disponible' => 16640]],
        );
    }

    public function test_validar_seleccion_ok(): void
    {
        $ret = new Pagoproveedor_Retencion([
            'tiporetencion' => 'G',
            'importe' => 16640,
        ]);
        $ret->id = 10;

        $origen = new Pagoproveedor();
        $origen->setRelation('pagoproveedor_retenciones', collect([$ret]));

        $out = PagoproveedorDevolucionRetencionSupport::validarSeleccion(
            $origen,
            [['id' => 10, 'importe' => 16640]],
            [['id' => 10, 'etiqueta' => 'Ganancias', 'disponible' => 16640]],
        );

        $this->assertCount(1, $out);
        $this->assertSame(16640.0, $out[0]['importe']);
        $this->assertSame(10, (int) $out[0]['retencion']->id);
    }
}
