<?php

namespace Tests\Unit\Support\Caja;

use App\Services\Caja\RendicionMaquina\RendicionMaquinaCalculoService;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaFormulaCatalogo;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaVariables;
use PHPUnit\Framework\TestCase;

/**
 * Ajuste WIGOS en Completo: transferencia = semilla M+T+N + delta vs Traer.
 * SQLite :memory: no aplica (motor puro, sin BD).
 */
class RendicionMaquinaCalculoCompletoTransferenciaTest extends TestCase
{
    private function contextoManianaEquilibrado(): array
    {
        $ctx = RendicionMaquinaVariables::defaultsVacios('M');
        $ctx['meta.turno'] = 'M';
        $ctx['inputs.fondo_inicial'] = 827700000.0;
        $ctx['calc.comprobante'] = 272300000.0;
        $ctx['calc.vale_rep_fondo'] = 254337000.0;
        $ctx['inputs.drop_billete'] = 250201200.0;
        $ctx['inputs.drop_ruleta'] = 4135800.0;
        $ctx['inputs.dropqr_rodillo'] = 113322382.29;
        $ctx['inputs.venta_ficha'] = 36946960.88;
        $ctx['inputs.tito'] = 28073854.27;
        $ctx['inputs.pago_manual'] = 190801.91;
        $ctx['inputs.sobrantes'] = 1045.93;
        $ctx['inputs.impuesto_drop'] = 2330858.92;
        $ctx['inputs.impuesto_venta'] = 350996.12;
        $ctx['inputs.impuesto_qr'] = 1076562.71;
        $ctx['inputs.impuesto_pago'] = 33500.0;
        $ctx['inputs.vta_ant_gastro'] = 33500.0;
        $ctx['gastos.total'] = 6046.75;
        $ctx['valores.total'] = 123466791.75 - 6046.75 - 33500.0;

        return $ctx;
    }

    public function test_ajuste_impuesto_qr_con_deposito_acompanado_deja_transferencia_en_cero(): void
    {
        $svc = new RendicionMaquinaCalculoService();
        $formulas = RendicionMaquinaFormulaCatalogo::canonicos();
        $ctx = $this->contextoManianaEquilibrado();

        $antes = $svc->calcular($ctx, $formulas);
        $this->assertEqualsWithDelta(0.0, $antes->get('calc.transferencia'), 0.02);

        $deltaImp = 484.92;
        $ctx['inputs.impuesto_qr'] = 1076562.71 + $deltaImp;
        $ctx['valores.total'] = ($ctx['valores.total'] + $deltaImp);

        $despues = $svc->calcular($ctx, $formulas);
        $this->assertEqualsWithDelta(0.0, $despues->get('calc.transferencia'), 0.02);
        $this->assertEqualsWithDelta(
            $antes->get('calc.deposito') + $deltaImp,
            $despues->get('calc.deposito'),
            0.02
        );
    }

    public function test_completo_traer_conserva_semilla_aunque_la_identidad_no_cierre(): void
    {
        $svc = new RendicionMaquinaCalculoService();
        $formulas = RendicionMaquinaFormulaCatalogo::canonicos();
        $ctx = $this->contextoManianaEquilibrado();
        $ctx['meta.turno'] = 'C';
        $ctx['calc.transferencia'] = 0.0;
        $ctx['calc.fondo_cierre'] = 1100000000.0;
        $ctx['calc.resultado_turno'] = 1098235944.17;
        $ctx['inputs.impuesto_qr'] += 50000.0;

        $r = $svc->calcular($ctx, $formulas);
        $this->assertEqualsWithDelta(0.0, $r->get('calc.transferencia'), 0.02);
        $this->assertEqualsWithDelta(1100000000.0, $r->get('calc.fondo_cierre'), 0.02);
        $this->assertEqualsWithDelta(1098235944.17, $r->get('calc.resultado_turno'), 0.02);
    }

    public function test_completo_arqueo_e_impuesto_no_mueven_la_semilla_de_transferencia(): void
    {
        $svc = new RendicionMaquinaCalculoService();
        $formulas = RendicionMaquinaFormulaCatalogo::canonicos();
        $ctx = $this->contextoManianaEquilibrado();
        $ctx['meta.turno'] = 'C';
        $ctx['calc.transferencia'] = 0.0;
        $ctx['calc.fondo_cierre'] = 1100000000.0;
        $ctx['calc.resultado_turno'] = 1098235944.17;
        $ctx['inputs.impuesto_qr'] += 484.92;
        $ctx['valores.total'] += 50520.06;

        $r = $svc->calcular($ctx, $formulas);
        $this->assertEqualsWithDelta(0.0, $r->get('calc.transferencia'), 0.02);
        $this->assertEqualsWithDelta(1100000000.0, $r->get('calc.fondo_cierre'), 0.02);
        $this->assertEqualsWithDelta(1098235944.17, $r->get('calc.resultado_turno'), 0.02);
    }
}
