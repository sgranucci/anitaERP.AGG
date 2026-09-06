<?php

namespace Tests\Unit;

use App\Support\Compras\Tracking\TrackingPagoEstado as Pago;
use PHPUnit\Framework\TestCase;

class TrackingPagoEstadoTest extends TestCase
{
    public function test_saldo_cancelado_es_pagado(): void
    {
        $estado = Pago::desdeMontos(Pago::ORIGEN_ERP, 1000.0, 0.0);

        $this->assertSame(Pago::PAGADO, $estado->estado);
        $this->assertSame(1000.0, $estado->pagado);
    }

    public function test_sin_nada_pagado_es_sin_pagar(): void
    {
        $estado = Pago::desdeMontos(Pago::ORIGEN_ANITA, 1000.0, 1000.0);

        $this->assertSame(Pago::SIN_PAGAR, $estado->estado);
        $this->assertSame(0.0, $estado->pagado);
    }

    public function test_saldo_intermedio_es_parcial(): void
    {
        $estado = Pago::desdeMontos(Pago::ORIGEN_ERP, 1000.0, 400.0);

        $this->assertSame(Pago::PARCIAL, $estado->estado);
        $this->assertSame(600.0, $estado->pagado);
    }

    /**
     * El redondeo de la cuenta corriente deja centavos sueltos; un saldo de un
     * milésimo no debería mostrarse como deuda viva.
     */
    public function test_tolera_el_redondeo(): void
    {
        $this->assertSame(Pago::PAGADO, Pago::desdeMontos(Pago::ORIGEN_ERP, 1000.0, 0.001)->estado);
        $this->assertSame(Pago::PARCIAL, Pago::desdeMontos(Pago::ORIGEN_ERP, 1000.0, 0.5)->estado);
    }

    /**
     * Las notas de crédito llevan monto y saldo negativos. Si se comparara con
     * signo en lugar de valor absoluto, todas las notas de crédito canceladas
     * aparecerían como pendientes.
     */
    public function test_una_nota_de_credito_aplicada_no_queda_pendiente(): void
    {
        $estado = Pago::desdeMontos(Pago::ORIGEN_ERP, -9694.69, 0.0);

        $this->assertSame(Pago::PAGADO, $estado->estado);
    }

    public function test_una_nota_de_credito_sin_aplicar_se_llama_sin_aplicar(): void
    {
        $estado = Pago::desdeMontos(Pago::ORIGEN_ERP, -1400853.40, -1400853.40);

        $this->assertSame(Pago::SIN_PAGAR, $estado->estado);
        $this->assertSame('Sin aplicar', $estado->etiquetaSegunSigno());
    }

    public function test_una_factura_impaga_se_llama_sin_pagar(): void
    {
        $estado = Pago::desdeMontos(Pago::ORIGEN_ERP, 183600.01, 183600.01);

        $this->assertSame('Sin pagar', $estado->etiquetaSegunSigno());
    }

    public function test_sin_dato_no_cuenta_como_deuda(): void
    {
        $estado = Pago::sinDato();

        $this->assertSame(Pago::SIN_DATO, $estado->estado);
        $this->assertNotContains($estado->estado, Pago::conDeuda());
    }

    public function test_la_busqueda_sin_pagar_incluye_los_pagos_parciales(): void
    {
        $this->assertContains(Pago::SIN_PAGAR, Pago::conDeuda());
        $this->assertContains(Pago::PARCIAL, Pago::conDeuda());
        $this->assertNotContains(Pago::PAGADO, Pago::conDeuda());
    }
}
