<?php

namespace Tests\Unit\Support\Configuracion;

use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Support\Configuracion\AiFacturasCanalKpisSupport;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AiFacturasCanalKpisSupportTest extends TestCase
{
    public function test_normalizar_origen_vacio_es_agente_api(): void
    {
        $this->assertSame(
            PrecargaComprobanteOrigenEntrada::API,
            AiFacturasCanalKpisSupport::normalizarOrigen(null)
        );
        $this->assertSame(
            PrecargaComprobanteOrigenEntrada::API,
            AiFacturasCanalKpisSupport::normalizarOrigen('  ')
        );
        $this->assertSame(
            PrecargaComprobanteOrigenEntrada::MAIL,
            AiFacturasCanalKpisSupport::normalizarOrigen('mail')
        );
    }

    public function test_canal_hero_agrupa_ia_y_scan(): void
    {
        $this->assertSame(
            'agente_api',
            AiFacturasCanalKpisSupport::canalHeroDeOrigen(PrecargaComprobanteOrigenEntrada::API)
        );
        $this->assertSame(
            'scan',
            AiFacturasCanalKpisSupport::canalHeroDeOrigen(PrecargaComprobanteOrigenEntrada::SCAN_ANITA)
        );
        $this->assertSame(
            'ia_anita',
            AiFacturasCanalKpisSupport::canalHeroDeOrigen(PrecargaComprobanteOrigenEntrada::MAIL)
        );
        $this->assertSame(
            'ia_anita',
            AiFacturasCanalKpisSupport::canalHeroDeOrigen(PrecargaComprobanteOrigenEntrada::BATCH_IA)
        );
        $this->assertSame(
            'ia_anita',
            AiFacturasCanalKpisSupport::canalHeroDeOrigen(PrecargaComprobanteOrigenEntrada::PDF_IA)
        );
        $this->assertSame(
            'otros',
            AiFacturasCanalKpisSupport::canalHeroDeOrigen('DESCONOCIDO')
        );
    }

    public function test_resolver_periodo_presets(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-24'));

        $d7 = AiFacturasCanalKpisSupport::resolverPeriodo(AiFacturasCanalKpisSupport::PRESET_7D, null, null);
        $this->assertSame('2026-11-18', $d7['fecha_desde']);
        $this->assertSame('2026-11-24', $d7['fecha_hasta']);
        $this->assertSame(AiFacturasCanalKpisSupport::PRESET_7D, $d7['preset']);

        $d30 = AiFacturasCanalKpisSupport::resolverPeriodo(null, null, null);
        $this->assertSame('2026-10-26', $d30['fecha_desde']);
        $this->assertSame('2026-11-24', $d30['fecha_hasta']);
        $this->assertSame(AiFacturasCanalKpisSupport::PRESET_30D, $d30['preset']);

        $mes = AiFacturasCanalKpisSupport::resolverPeriodo(AiFacturasCanalKpisSupport::PRESET_MES, null, null);
        $this->assertSame('2026-11-01', $mes['fecha_desde']);
        $this->assertSame('2026-11-24', $mes['fecha_hasta']);

        $libre = AiFacturasCanalKpisSupport::resolverPeriodo('', '2026-01-01', '2026-01-31');
        $this->assertSame('2026-01-01', $libre['fecha_desde']);
        $this->assertSame('2026-01-31', $libre['fecha_hasta']);
        $this->assertSame('', $libre['preset']);

        Carbon::setTestNow();
    }
}
