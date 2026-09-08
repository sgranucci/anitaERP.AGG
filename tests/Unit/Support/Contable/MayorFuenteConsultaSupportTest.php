<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\MayorFuenteConsultaSupport;
use Tests\TestCase;

class MayorFuenteConsultaSupportTest extends TestCase
{
    public function test_normalizar_modo_default_erp(): void
    {
        $this->assertSame('erp', MayorFuenteConsultaSupport::normalizarModo(''));
        $this->assertSame('erp', MayorFuenteConsultaSupport::normalizarModo('auto'));
        $this->assertSame('erp', MayorFuenteConsultaSupport::normalizarModo('ERP'));
        $this->assertSame('anita', MayorFuenteConsultaSupport::normalizarModo('anita'));
    }

    public function test_forzar_anita_todo_el_periodo(): void
    {
        config(['contable.mayor_plano_cuenta.fuente_erp_hasta' => '2026-08-31']);

        $t = MayorFuenteConsultaSupport::resolverTramos(
            20260801,
            20260915,
            MayorFuenteConsultaSupport::MODO_ANITA,
            'contable.mayor_plano_cuenta.fuente_erp_hasta',
        );

        $this->assertFalse($t['usa_erp']);
        $this->assertTrue($t['usa_anita']);
        $this->assertSame(20260801, $t['tramo_anita_desde']);
        $this->assertSame(20260915, $t['tramo_anita_hasta']);
        $this->assertSame(0, $t['tramo_erp_desde']);
        $this->assertStringContainsString('Anita', $t['etiqueta']);
    }

    public function test_erp_cubre_todo_el_periodo_sin_hibrido(): void
    {
        config(['contable.mayor_plano_cuenta.fuente_erp_hasta' => '2026-08-31']);

        $t = MayorFuenteConsultaSupport::resolverTramos(
            20260801,
            20260915,
            MayorFuenteConsultaSupport::MODO_ERP,
            'contable.mayor_plano_cuenta.fuente_erp_hasta',
        );

        $this->assertTrue($t['usa_erp']);
        $this->assertFalse($t['usa_anita']);
        $this->assertSame(20260801, $t['tramo_erp_desde']);
        $this->assertSame(20260915, $t['tramo_erp_hasta']);
        $this->assertSame(0, $t['tramo_anita_desde']);
        $this->assertStringContainsString('ERP', $t['etiqueta']);
        $this->assertStringNotContainsString('Híbrido', $t['etiqueta']);
    }

    public function test_auto_alias_se_comporta_como_erp(): void
    {
        config(['contable.mayor_plano_cuenta.fuente_erp_hasta' => '2026-08-31']);

        $auto = MayorFuenteConsultaSupport::resolverTramos(
            20260801,
            20260915,
            MayorFuenteConsultaSupport::MODO_AUTO,
            'contable.mayor_plano_cuenta.fuente_erp_hasta',
        );
        $erp = MayorFuenteConsultaSupport::resolverTramos(
            20260801,
            20260915,
            MayorFuenteConsultaSupport::MODO_ERP,
            'contable.mayor_plano_cuenta.fuente_erp_hasta',
        );

        $this->assertSame($erp['usa_erp'], $auto['usa_erp']);
        $this->assertSame($erp['usa_anita'], $auto['usa_anita']);
        $this->assertSame($erp['tramo_erp_desde'], $auto['tramo_erp_desde']);
        $this->assertSame($erp['tramo_erp_hasta'], $auto['tramo_erp_hasta']);
    }

    public function test_septiembre_en_modo_erp_sigue_erp(): void
    {
        config(['contable.mayor_plano_cuenta.fuente_erp_hasta' => '2026-08-31']);

        $t = MayorFuenteConsultaSupport::resolverTramos(
            20260901,
            20260930,
            MayorFuenteConsultaSupport::MODO_ERP,
            'contable.mayor_plano_cuenta.fuente_erp_hasta',
        );

        $this->assertTrue($t['usa_erp']);
        $this->assertFalse($t['usa_anita']);
        $this->assertSame(20260901, $t['tramo_erp_desde']);
        $this->assertSame(20260930, $t['tramo_erp_hasta']);
    }

    public function test_mayor_concepto_vacio_no_hereda_corte_del_plano(): void
    {
        config(['contable.mayor_plano_cuenta.fuente_erp_hasta' => '2026-08-31']);
        config(['contable.mayor_concepto.fuente_erp_hasta' => '']);

        $this->assertSame(
            0,
            MayorFuenteConsultaSupport::corteYmd('contable.mayor_concepto.fuente_erp_hasta'),
        );

        // Modo Anita: solo bridge aunque el plano tenga corte.
        $t = MayorFuenteConsultaSupport::resolverTramos(
            20260801,
            20260831,
            MayorFuenteConsultaSupport::MODO_ANITA,
            'contable.mayor_concepto.fuente_erp_hasta',
        );

        $this->assertFalse($t['usa_erp']);
        $this->assertTrue($t['usa_anita']);
        $this->assertSame(20260801, $t['tramo_anita_desde']);
        $this->assertSame(20260831, $t['tramo_anita_hasta']);
    }
}
