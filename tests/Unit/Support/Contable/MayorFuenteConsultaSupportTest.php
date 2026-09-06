<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\MayorFuenteConsultaSupport;
use Tests\TestCase;

class MayorFuenteConsultaSupportTest extends TestCase
{
    public function test_normalizar_modo_default_auto(): void
    {
        $this->assertSame('auto', MayorFuenteConsultaSupport::normalizarModo(''));
        $this->assertSame('erp', MayorFuenteConsultaSupport::normalizarModo('ERP'));
        $this->assertSame('anita', MayorFuenteConsultaSupport::normalizarModo('anita'));
    }

    public function test_forzar_anita_ignora_corte(): void
    {
        $t = MayorFuenteConsultaSupport::resolverTramos(
            20260801,
            20260831,
            MayorFuenteConsultaSupport::MODO_ANITA,
        );
        // corteEfectivo anita = 0; no leemos config en este test de tramos con override...
        // resolverTramos usa corteEfectivo que lee config. Forzamos pasando vía reflexión no;
        // en anita siempre usa_erp=false.
        $this->assertFalse($t['usa_erp']);
        $this->assertTrue($t['usa_anita']);
        $this->assertSame(20260801, $t['tramo_anita_desde']);
        $this->assertSame(20260831, $t['tramo_anita_hasta']);
        $this->assertStringContainsString('Anita', $t['etiqueta']);
    }

    public function test_hibrido_parte_al_dia_siguiente_del_corte(): void
    {
        // Inyectamos corte vía env/config no disponible en unit puro: usamos erp + mock
        // llamando resolver con el corte efectivo ya conocido: reimplementamos escenario
        // con corte fijo usando reflexión del método interno no — mejor test directo
        // del algoritmo con corte conocido vía config fake.
        config(['contable.mayor_plano_cuenta.fuente_erp_hasta' => '2026-08-31']);
        config(['contable.mayor_concepto.fuente_erp_hasta' => '']);

        $t = MayorFuenteConsultaSupport::resolverTramos(
            20260801,
            20260915,
            MayorFuenteConsultaSupport::MODO_AUTO,
            'contable.mayor_plano_cuenta.fuente_erp_hasta',
        );

        $this->assertTrue($t['usa_erp']);
        $this->assertTrue($t['usa_anita']);
        $this->assertSame(20260801, $t['tramo_erp_desde']);
        $this->assertSame(20260831, $t['tramo_erp_hasta']);
        $this->assertSame(20260901, $t['tramo_anita_desde']);
        $this->assertSame(20260915, $t['tramo_anita_hasta']);
        $this->assertStringContainsString('Híbrido', $t['etiqueta']);
    }

    public function test_solo_agosto_queda_en_erp(): void
    {
        config(['contable.mayor_plano_cuenta.fuente_erp_hasta' => '2026-08-31']);

        $t = MayorFuenteConsultaSupport::resolverTramos(
            20260801,
            20260831,
            MayorFuenteConsultaSupport::MODO_ERP,
            'contable.mayor_plano_cuenta.fuente_erp_hasta',
        );

        $this->assertTrue($t['usa_erp']);
        $this->assertFalse($t['usa_anita']);
        $this->assertSame(0, $t['tramo_anita_desde']);
        $this->assertStringContainsString('ERP', $t['etiqueta']);
    }

    public function test_septiembre_solo_anita_aunque_modo_erp(): void
    {
        config(['contable.mayor_plano_cuenta.fuente_erp_hasta' => '2026-08-31']);

        $t = MayorFuenteConsultaSupport::resolverTramos(
            20260901,
            20260930,
            MayorFuenteConsultaSupport::MODO_ERP,
            'contable.mayor_plano_cuenta.fuente_erp_hasta',
        );

        $this->assertFalse($t['usa_erp']);
        $this->assertTrue($t['usa_anita']);
        $this->assertSame(20260901, $t['tramo_anita_desde']);
    }
}
