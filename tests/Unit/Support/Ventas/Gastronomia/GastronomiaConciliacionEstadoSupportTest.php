<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\GastronomiaConciliacionEstadoSupport;
use PHPUnit\Framework\TestCase;

final class GastronomiaConciliacionEstadoSupportTest extends TestCase
{
    public function test_rendg_faltante_con_ventas_erp_es_sin_rendg(): void
    {
        $this->assertSame(
            'SIN RENDG',
            GastronomiaConciliacionEstadoSupport::resolver(
                diffErpAnita: 0.0,
                diffErpRendg: null,
                tolerancia: 0.02,
                jornadaAbierta: false,
                ventasErp: 1_816_340.43,
            ),
        );
    }

    public function test_total_dia_parcial_rendg_marca_dif(): void
    {
        $this->assertSame(
            'DIF',
            GastronomiaConciliacionEstadoSupport::resolver(
                diffErpAnita: 0.0,
                diffErpRendg: 1_816_340.43,
                tolerancia: 0.02,
                jornadaAbierta: false,
                ventasErp: 4_048_462.51,
            ),
        );
    }

    public function test_jornada_abierta_no_exige_rendg(): void
    {
        $this->assertSame(
            'OK',
            GastronomiaConciliacionEstadoSupport::resolver(
                diffErpAnita: 0.0,
                diffErpRendg: null,
                tolerancia: 0.02,
                jornadaAbierta: true,
                ventasErp: 1_000_000.0,
            ),
        );
    }
}
