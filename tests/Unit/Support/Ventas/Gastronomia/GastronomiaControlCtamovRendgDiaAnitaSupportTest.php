<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\GastronomiaControlCtamovRendgDiaAnitaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaFacturacionAuditoriaCtamovSupport;
use Tests\TestCase;

final class GastronomiaControlCtamovRendgDiaAnitaSupportTest extends TestCase
{
    public function test_suma_ctamov_cuentas_ventas_e_iva(): void
    {
        $filas = [
            ['ctav_cuenta' => 413010001, 'ctav_importe' => 100.0, 'ctav_d_h' => 'H'],
            ['ctav_cuenta' => 214010009, 'ctav_importe' => 21.0, 'ctav_d_h' => 'H'],
            ['ctav_cuenta' => 999999999, 'ctav_importe' => 500.0, 'ctav_d_h' => 'H'],
        ];

        $total = GastronomiaFacturacionAuditoriaCtamovSupport::sumarVentasDesdeCtamov(
            $filas,
            [413010001, 214010009],
        );

        $this->assertSame(121.0, $total);
    }

    public function test_total_rendg_neto_es_z_menos_nc(): void
    {
        $support = app(GastronomiaControlCtamovRendgDiaAnitaSupport::class);
        $cabeceras = [
            (object) ['rendg_total_z' => 100.0, 'rendg_tot_nc' => 10.0],
            (object) ['rendg_total_z' => 50.0, 'rendg_tot_nc' => 0.0],
        ];

        $this->assertSame(140.0, $support->totalRendgNetoDia($cabeceras));
        $this->assertSame(150.0, $support->totalRendgBrutoZ($cabeceras));
        $this->assertSame(10.0, $support->totalRendgNotasCredito($cabeceras));
    }

    public function test_total_venta_anita_resta_nc(): void
    {
        $support = app(GastronomiaControlCtamovRendgDiaAnitaSupport::class);
        $cabeceras = [
            (object) ['ven_tipo' => 'FAC', 'ven_monto' => 1000.0],
            (object) ['ven_tipo' => 'NCD', 'ven_monto' => 200.0],
        ];

        $this->assertSame(800.0, $support->totalVentaAnitaNeto($cabeceras));
    }

    public function test_cuadran_todos_con_tolerancia(): void
    {
        $support = app(GastronomiaControlCtamovRendgDiaAnitaSupport::class);

        $this->assertTrue($support->cuadranTodos([100.0, 100.01, 99.99], 0.02));
        $this->assertFalse($support->cuadranTodos([100.0, 100.05], 0.02));
    }

    public function test_codigos_ctamov_incluyen_iva_debito_e_iva_credito_fiscal_biyemas(): void
    {
        $support = app(GastronomiaControlCtamovRendgDiaAnitaSupport::class);
        $codigos = $support->codigosCtamovEmpresa(1);

        $this->assertContains(214010009, $codigos);
        $this->assertContains(114010011, $codigos);
    }
}
