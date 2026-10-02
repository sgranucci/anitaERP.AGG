<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ArcaFceDatosAdicionalesSupport;
use Tests\TestCase;

final class ArcaFceFechaVencimientoPagoTest extends TestCase
{
    public function test_usa_el_vencimiento_de_la_condicion_si_es_posterior_a_hoy(): void
    {
        $fecha = ArcaFceDatosAdicionalesSupport::fechaVencimientoPago([
            'fechacomprobante' => '20261002',
            'fechavencimiento' => '20261101',
        ], '20261002');

        self::assertSame('20261101', $fecha);
    }

    public function test_no_informa_un_vencimiento_anterior_al_dia_de_presentacion(): void
    {
        $fecha = ArcaFceDatosAdicionalesSupport::fechaVencimientoPago([
            'fechacomprobante' => '20260916',
            'fechavencimiento' => '20260916',
        ], '20261002');

        self::assertSame('20261002', $fecha);
    }

    public function test_wsfe_informa_el_cbu_con_el_opcional_2101(): void
    {
        self::assertSame(2101, ArcaFceDatosAdicionalesSupport::idOpcionalWsfe(21));
        self::assertSame(27, ArcaFceDatosAdicionalesSupport::idOpcionalWsfe(27));
        self::assertSame(22, ArcaFceDatosAdicionalesSupport::idOpcionalWsfe(22));
    }
}
