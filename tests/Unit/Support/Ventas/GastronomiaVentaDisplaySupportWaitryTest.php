<?php

namespace Tests\Unit\Support\Ventas;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\GastronomiaVentaDisplaySupport;
use PHPUnit\Framework\TestCase;

final class GastronomiaVentaDisplaySupportWaitryTest extends TestCase
{
    public function test_linea_papelito_con_display_id_y_order_id(): void
    {
        $venta = new Venta(['id' => 1]);
        $emision = new VentaGastronomiaEmision(['waitry_order_id' => 100]);
        $cuenta = new CuentaGastronomia(['waitry_order_id' => 100, 'waitry_display_id' => 'B-9']);
        $emision->setRelation('cuenta', $cuenta);
        $venta->setRelation('gastronomiaEmision', $emision);

        $this->assertSame(
            'Papelito Waitry: B-9',
            GastronomiaVentaDisplaySupport::lineaOrdenWaitry($venta),
        );
    }

    public function test_linea_papelito_solo_display_id(): void
    {
        $venta = new Venta(['id' => 1]);
        $emision = new VentaGastronomiaEmision(['waitry_order_id' => null]);
        $cuenta = new CuentaGastronomia(['waitry_display_id' => 'Z1']);
        $emision->setRelation('cuenta', $cuenta);
        $venta->setRelation('gastronomiaEmision', $emision);

        $this->assertSame('Papelito Waitry: Z1', GastronomiaVentaDisplaySupport::lineaOrdenWaitry($venta));
    }

    public function test_linea_papelito_solo_order_id_numerico_retorna_null(): void
    {
        $venta = new Venta(['id' => 1]);
        $emision = new VentaGastronomiaEmision(['waitry_order_id' => 55]);
        $cuenta = new CuentaGastronomia(['waitry_order_id' => 55]);
        $emision->setRelation('cuenta', $cuenta);
        $venta->setRelation('gastronomiaEmision', $emision);

        $this->assertNull(GastronomiaVentaDisplaySupport::lineaOrdenWaitry($venta));
    }
}
