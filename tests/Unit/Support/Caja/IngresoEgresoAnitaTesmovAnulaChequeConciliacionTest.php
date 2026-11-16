<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use Tests\TestCase;

/**
 * Anulación de cheque propio: tesmov ACP sin heredar conciliación del CHP original.
 */
class IngresoEgresoAnitaTesmovAnulaChequeConciliacionTest extends TestCase
{
    public function test_tipo_anula_cheque_es_acp(): void
    {
        $this->assertSame('ACP', IngresoEgresoAnitaTesmovSupport::TIPO_TESMOV_ANULA_CHEQUE);
    }

    public function test_importe_acp_es_absoluto(): void
    {
        $this->assertSame(
            9470949.75,
            IngresoEgresoAnitaTesmovSupport::importeTesmovAnulaChequePropio(9470949.75)
        );
        $this->assertSame(
            9470949.75,
            IngresoEgresoAnitaTesmovSupport::importeTesmovAnulaChequePropio(-9470949.75)
        );
    }

    public function test_set_sql_limpia_conciliacion_sin_heredar_del_original(): void
    {
        $sql = IngresoEgresoAnitaTesmovSupport::setSqlTesmovSinConciliacion();

        $this->assertStringContainsString("tesv_conciliado = ' '", $sql);
        $this->assertStringNotContainsString('tesv_nro_conc = 38383', $sql);
        // AGG/Crown: también nro/fecha en 0. Ferli solo conciliado.
        if (str_contains($sql, 'tesv_nro_conc')) {
            $this->assertStringContainsString('tesv_nro_conc = 0', $sql);
            $this->assertStringContainsString('tesv_fecha_conc = 0', $sql);
        }
    }
}
