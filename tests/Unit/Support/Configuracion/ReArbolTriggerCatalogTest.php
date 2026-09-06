<?php

namespace Tests\Unit\Support\Configuracion;

use App\Support\Configuracion\ReArbolTriggerCatalog;
use PHPUnit\Framework\TestCase;

class ReArbolTriggerCatalogTest extends TestCase
{
    public function test_normaliza_accion_rama(): void
    {
        $this->assertSame('A', ReArbolTriggerCatalog::normalizarAccionRama('a'));
        $this->assertSame('B', ReArbolTriggerCatalog::normalizarAccionRama('B'));
        $this->assertSame('ALLOWLIST', ReArbolTriggerCatalog::normalizarAccionRama('ALLOWLIST'));
        $this->assertSame('ALLOWLIST', ReArbolTriggerCatalog::normalizarAccionRama('x'));
        $this->assertSame('ALLOWLIST', ReArbolTriggerCatalog::normalizarAccionRama(null));
    }

    public function test_evaluadores_premium_expuestos(): void
    {
        $evals = ReArbolTriggerCatalog::evaluadores();
        $this->assertContains(ReArbolTriggerCatalog::EVAL_SIEMPRE, $evals);
        $this->assertContains(ReArbolTriggerCatalog::EVAL_MONTO_MAYOR_IGUAL, $evals);
        $this->assertContains(ReArbolTriggerCatalog::EVAL_MONTO_MENOR, $evals);
        $this->assertContains(ReArbolTriggerCatalog::EVAL_LINEA_SIN_CUENTA, $evals);
        $this->assertContains(ReArbolTriggerCatalog::EVAL_CUENTA_ESPECIFICA, $evals);
        $this->assertContains(ReArbolTriggerCatalog::ACCION_RAMA_B, ReArbolTriggerCatalog::accionesRama());
    }

    public function test_flags_params_por_evaluador(): void
    {
        $this->assertTrue(ReArbolTriggerCatalog::usaMonto(ReArbolTriggerCatalog::EVAL_MONTO_MAYOR_IGUAL));
        $this->assertTrue(ReArbolTriggerCatalog::usaCuenta(ReArbolTriggerCatalog::EVAL_CUENTA_ESPECIFICA));
        $this->assertTrue(ReArbolTriggerCatalog::usaAllowlist(ReArbolTriggerCatalog::EVAL_CUENTAS_ALLOWLIST_TODAS));
        $this->assertFalse(ReArbolTriggerCatalog::usaMonto(ReArbolTriggerCatalog::EVAL_SIEMPRE));
    }

    public function test_vigencia_aplica(): void
    {
        $this->assertTrue(ReArbolTriggerCatalog::vigenciaAplica(null, null, '2026-09-06'));
        $this->assertTrue(ReArbolTriggerCatalog::vigenciaAplica('2026-09-01', '2026-09-30', '2026-09-06'));
        $this->assertFalse(ReArbolTriggerCatalog::vigenciaAplica('2026-09-10', null, '2026-09-06'));
        $this->assertFalse(ReArbolTriggerCatalog::vigenciaAplica(null, '2026-09-05', '2026-09-06'));
    }

    public function test_evaluadores_por_grupo(): void
    {
        $grupos = ReArbolTriggerCatalog::evaluadoresPorGrupo();
        $this->assertArrayHasKey(ReArbolTriggerCatalog::GRUPO_MONTO, $grupos);
        $this->assertContains(ReArbolTriggerCatalog::EVAL_MONTO_MENOR, $grupos[ReArbolTriggerCatalog::GRUPO_MONTO]);
    }
}
