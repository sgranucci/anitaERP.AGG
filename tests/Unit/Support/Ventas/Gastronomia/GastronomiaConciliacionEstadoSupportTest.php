<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\GastronomiaConciliacionEstadoSupport;
use PHPUnit\Framework\TestCase;

final class GastronomiaConciliacionEstadoSupportTest extends TestCase
{
    public function test_rendg_faltante_con_ventas_erp_es_sin_rendg(): void
    {
        $det = GastronomiaConciliacionEstadoSupport::resolverDetallado(
            diffErpAnita: 0.0,
            diffErpRendg: null,
            tolerancia: 0.02,
            jornadaAbierta: false,
            ventasErp: 1_816_340.43,
        );

        $this->assertSame('SIN RENDG', $det['estado']);
        $this->assertSame('OK', $det['estado_anita']);
        $this->assertSame('SIN RENDG', $det['estado_rendg']);
    }

    public function test_solo_rendg_desfasado_marca_dif_rendg(): void
    {
        $det = GastronomiaConciliacionEstadoSupport::resolverDetallado(
            diffErpAnita: 0.0,
            diffErpRendg: 1_816_340.43,
            tolerancia: 0.02,
            jornadaAbierta: false,
            ventasErp: 4_048_462.51,
        );

        $this->assertSame('DIF rendg', $det['estado']);
        $this->assertSame('OK', $det['estado_anita']);
        $this->assertSame('DIF', $det['estado_rendg']);
    }

    public function test_solo_venta_anita_desfasada_marca_dif_venta(): void
    {
        $det = GastronomiaConciliacionEstadoSupport::resolverDetallado(
            diffErpAnita: 25_000.0,
            diffErpRendg: 0.0,
            tolerancia: 0.02,
            jornadaAbierta: false,
            ventasErp: 1_000_000.0,
        );

        $this->assertSame('DIF venta', $det['estado']);
        $this->assertSame('DIF', $det['estado_anita']);
        $this->assertSame('OK', $det['estado_rendg']);
    }

    public function test_jornada_abierta_no_exige_rendg(): void
    {
        $det = GastronomiaConciliacionEstadoSupport::resolverDetallado(
            diffErpAnita: 0.0,
            diffErpRendg: null,
            tolerancia: 0.02,
            jornadaAbierta: true,
            ventasErp: 1_000_000.0,
        );

        $this->assertSame('OK', $det['estado']);
        $this->assertSame('—', $det['estado_rendg']);
    }
}
