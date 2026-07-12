<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ArcaCaeaInformeDatosDesdeVentaSupport;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class ArcaCaeaInformeDatosDesdeVentaSupportTest extends TestCase
{
    public function test_fecha_hora_generacion_desde_created_at(): void
    {
        $venta = new \App\Models\Ventas\Venta([
            'fecha' => '2026-06-10',
        ]);
        $venta->created_at = Carbon::parse('2026-06-10 14:35:22');

        self::assertSame('20260610143522', ArcaCaeaInformeDatosDesdeVentaSupport::fechaHoraGeneracion($venta));
    }

    public function test_fecha_hora_generacion_fallback_sin_created_at(): void
    {
        $venta = new \App\Models\Ventas\Venta([
            'fecha' => '2026-06-10',
        ]);

        self::assertSame('20260610120000', ArcaCaeaInformeDatosDesdeVentaSupport::fechaHoraGeneracion($venta));
    }

    public function test_moneda_afip_desde_codigo_interno_erp(): void
    {
        $venta = new \App\Models\Ventas\Venta();
        $venta->setRelation('monedas', new \App\Models\Configuracion\Moneda([
            'codigo' => '1',
            'abreviatura' => 'PES',
            'nombre' => 'PESOS',
        ]));

        self::assertSame('PES', ArcaCaeaInformeDatosDesdeVentaSupport::monedaAfipDesdeVenta($venta));
    }

    public function test_moneda_afip_desde_abreviatura_cuando_codigo_vacio(): void
    {
        $venta = new \App\Models\Ventas\Venta();
        $venta->setRelation('monedas', new \App\Models\Configuracion\Moneda([
            'codigo' => '',
            'abreviatura' => 'DOL',
            'nombre' => 'DOLARES',
        ]));

        self::assertSame('DOL', ArcaCaeaInformeDatosDesdeVentaSupport::monedaAfipDesdeVenta($venta));
    }

    public function test_cotizacion_afip_fuerza_uno_cuando_moneda_es_pes(): void
    {
        $venta = new \App\Models\Ventas\Venta([
            'cotizacion' => 1430,
        ]);
        $venta->setRelation('monedas', new \App\Models\Configuracion\Moneda([
            'codigo' => '1',
            'abreviatura' => 'PES',
            'nombre' => 'PESOS',
        ]));

        self::assertSame(1.0, ArcaCaeaInformeDatosDesdeVentaSupport::cotizacionAfipDesdeVenta($venta));
    }

    public function test_cotizacion_afip_conserva_valor_en_dolares(): void
    {
        $venta = new \App\Models\Ventas\Venta([
            'cotizacion' => 1430,
        ]);
        $venta->setRelation('monedas', new \App\Models\Configuracion\Moneda([
            'codigo' => '2',
            'abreviatura' => 'DOL',
            'nombre' => 'DOLARES',
        ]));

        self::assertSame(1430.0, ArcaCaeaInformeDatosDesdeVentaSupport::cotizacionAfipDesdeVenta($venta));
    }
}
