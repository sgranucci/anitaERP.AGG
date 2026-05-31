<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Models\Ventas\TotemWaitryGastronomia;
use App\Models\Ventas\UbicacionGastronomia;
use App\Support\Ventas\Waitry\WaitryTotemJornadaResumenSupport;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class WaitryTotemJornadaResumenSupportTest extends TestCase
{
    public function test_agrupa_por_totem_y_medio_con_total_general(): void
    {
        $ubicacion = new UbicacionGastronomia(['nombre' => 'Salón']);
        $ubicacion->id = 10;

        $totem = new TotemWaitryGastronomia([
            'empresa_id' => 1,
            'ubicacion_id' => 10,
            'waitry_table_id' => 101066,
            'detalle' => 'Tótem entrada',
        ]);
        $totem->id = 5;
        $totem->setRelation('ubicacion', $ubicacion);

        $lineas = [
            [
                'paid_waitry' => true,
                'waitry_tipo_pago' => 'mercadopago',
                'waitry_medio_label' => 'Mercado Pago',
                'cuentacaja_esperada_label' => '201 — MP',
                'monto_cobro_waitry' => 100.0,
                'total' => 100.0,
                'waitry_table_id' => 101066,
            ],
            [
                'paid_waitry' => true,
                'waitry_tipo_pago' => 'totalcoin',
                'waitry_medio_label' => 'Totalcoin',
                'cuentacaja_esperada_label' => '226 — TC',
                'monto_cobro_waitry' => 50.0,
                'total' => 50.0,
                'waitry_table_id' => 101066,
            ],
            [
                'paid_waitry' => false,
                'waitry_tipo_pago' => 'cash',
                'total' => 30.0,
                'waitry_table_id' => 101066,
            ],
        ];

        $resumen = WaitryTotemJornadaResumenSupport::armar(Collection::make([$totem]), $lineas);

        $this->assertCount(1, $resumen['por_totem']);
        $this->assertSame(2, $resumen['por_totem'][0]['cantidad_ordenes']);
        $this->assertSame(150.0, $resumen['por_totem'][0]['total_ingreso']);
        $this->assertCount(2, $resumen['por_totem'][0]['por_medio_pago']);
        $this->assertSame(2, $resumen['total_general']['cantidad_ordenes']);
        $this->assertSame(150.0, $resumen['total_general']['total_ingreso']);
    }
}
