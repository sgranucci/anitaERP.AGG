<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Caja\ChequePropioCpromaeAnitaMapper;
use App\Support\Compras\PagoproveedorAnitaAuditoriaCompareSupport;
use PHPUnit\Framework\TestCase;

class PagoproveedorAnitaAuditoriaCompareSupportTest extends TestCase
{
    public function test_op_generada_en_erp_requiere_asiento(): void
    {
        $this->assertFalse(PagoproveedorAnitaAuditoriaCompareSupport::esGeneradaEnErp(null));
        $this->assertFalse(PagoproveedorAnitaAuditoriaCompareSupport::esGeneradaEnErp(0));
        $this->assertTrue(PagoproveedorAnitaAuditoriaCompareSupport::esGeneradaEnErp(164220));
    }

    public function test_estado_espacio_igual_vacio(): void
    {
        $this->assertSame(' ', PagoproveedorAnitaAuditoriaCompareSupport::normalizarChar1(''));
        $this->assertSame(' ', PagoproveedorAnitaAuditoriaCompareSupport::normalizarChar1(' '));
        $this->assertSame('*', PagoproveedorAnitaAuditoriaCompareSupport::normalizarChar1('*'));
    }

    public function test_importes_cercanos(): void
    {
        $this->assertTrue(PagoproveedorAnitaAuditoriaCompareSupport::importesCercanos(1960396.90, '1960396.9'));
        $this->assertFalse(PagoproveedorAnitaAuditoriaCompareSupport::importesCercanos(10, 10.2));
    }

    public function test_asiento_balanceado_signo_monto(): void
    {
        $totales = PagoproveedorAnitaAuditoriaCompareSupport::balanceDesdeAsientoMovimientos([
            (object) ['monto' => 1960396.90],
            (object) ['monto' => -1960396.90],
        ]);
        $this->assertTrue($totales['balanceado']);
        $this->assertSame(2, $totales['lineas_con_importe']);
    }

    public function test_ctamov_totales_d_h(): void
    {
        $totales = PagoproveedorAnitaAuditoriaCompareSupport::totalesDesdeCtamov([
            (object) ['ctav_d_h' => 'D', 'ctav_importe' => 1960396.90],
            (object) ['ctav_d_h' => 'H', 'ctav_importe' => 1960396.90],
        ]);
        $this->assertTrue($totales['balanceado']);
        $this->assertSame(2, $totales['lineas_con_importe']);
        $this->assertEqualsWithDelta(1960396.90, $totales['total_debe'], 0.001);
        $this->assertEqualsWithDelta(1960396.90, $totales['total_haber'], 0.001);
    }

    public function test_ctamov_detecta_falta_y_desbalance_vs_erp(): void
    {
        $erp = [
            'total_debe' => 100.0,
            'total_haber' => 100.0,
            'lineas_con_importe' => 2,
            'balanceado' => true,
        ];
        $this->assertSame(['Falta ctamov'], PagoproveedorAnitaAuditoriaCompareSupport::discrepanciasCtamovVsErp(
            $erp,
            ['total_debe' => 0, 'total_haber' => 0, 'lineas_con_importe' => 0, 'balanceado' => true]
        ));

        $problemas = PagoproveedorAnitaAuditoriaCompareSupport::discrepanciasCtamovVsErp($erp, [
            'total_debe' => 100.0,
            'total_haber' => 80.0,
            'lineas_con_importe' => 3,
            'balanceado' => false,
        ]);
        $this->assertNotEmpty($problemas);
        $texto = implode(' ', $problemas);
        $this->assertTrue(str_contains($texto, 'líneas'));
        $this->assertTrue(str_contains($texto, 'Haber'));
        $this->assertTrue(str_contains($texto, 'desbalanceado'));
    }

    public function test_cpromae_detecta_para_dep_y_cotizacion(): void
    {
        $esperado = ChequePropioCpromaeAnitaMapper::mapear([
            'cuenta' => '127',
            'nro' => 79030366,
            'fecha_emision' => '2026-09-10',
            'fecha_pago' => '2026-09-18',
            'importe' => 1960396.9,
            'nro_op' => 124828,
            'cotizacion' => 0,
            'para_dep' => 'E',
            'negociable' => 'N',
            'chequera_tipo' => 'F',
            'estado_erp' => '*',
        ]);
        $anita = $esperado;
        $anita['cpro_para_dep'] = 'N';
        $anita['cpro_cotizacion'] = '0';
        $anita['cpro_estado'] = '';

        $problemas = PagoproveedorAnitaAuditoriaCompareSupport::discrepanciasCpromae($esperado, $anita);
        $this->assertNotEmpty($problemas);
        $hayParaDep = false;
        $hayEstado = false;
        $hayCotiz = false;
        foreach ($problemas as $p) {
            if (str_contains($p, 'para_dep')) {
                $hayParaDep = true;
            }
            if (str_contains($p, 'estado')) {
                $hayEstado = true;
            }
            if (str_contains($p, 'cotización')) {
                $hayCotiz = true;
            }
        }
        $this->assertTrue($hayParaDep);
        $this->assertFalse($hayEstado);
        $this->assertFalse($hayCotiz);
    }
}
