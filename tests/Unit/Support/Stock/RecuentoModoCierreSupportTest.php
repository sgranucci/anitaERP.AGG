<?php

namespace Tests\Unit\Support\Stock;

use App\Models\Stock\Recuento;
use App\Support\Stock\RecuentoModoCierreSupport;
use Carbon\Carbon;
use Tests\TestCase;

class RecuentoModoCierreSupportTest extends TestCase
{
    public function test_resolver_modo_invalido_usa_saldo_actual(): void
    {
        $this->assertSame(
            RecuentoModoCierreSupport::MODO_SALDO_ACTUAL,
            RecuentoModoCierreSupport::resolverModo('otro')
        );
    }

    public function test_etiquetas_modo(): void
    {
        $this->assertSame(
            'A fecha del recuento',
            RecuentoModoCierreSupport::etiqueta(RecuentoModoCierreSupport::MODO_FECHA_RECUENTO)
        );
        $this->assertSame(
            'Al saldo actual',
            RecuentoModoCierreSupport::etiqueta(RecuentoModoCierreSupport::MODO_SALDO_ACTUAL)
        );
    }

    public function test_rechaza_fecha_con_anio_de_dos_digitos(): void
    {
        Carbon::setTestNow('2026-10-02');

        $mensaje = RecuentoModoCierreSupport::mensajeFechaNoGrabable('0026-10-01');

        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('01/10/0026', $mensaje);
        $this->assertNull(RecuentoModoCierreSupport::mensajeFechaNoGrabable('2026-10-01'));

        Carbon::setTestNow();
    }

    public function test_aviso_saldo_negativo_muestra_decimales(): void
    {
        $recuento = new Recuento();
        $recuento->fecha = Carbon::parse('2026-11-01');

        $mensaje = RecuentoModoCierreSupport::mensajeAvisoSaldoNegativoTrasCierreFecha($recuento, [[
            'articulo_id' => 1,
            'sku' => 'I0043',
            'contado' => 2.2,
            'posterior' => -2.25,
            'vigente' => -0.05,
        ]]);

        $this->assertStringContainsString('I0043 (contado 2,2 + mov. posteriores -2,25 = vigente -0,05)', $mensaje);
        $this->assertStringContainsString('el cierre a fecha del recuento (01/11/2026) dejó saldo vigente negativo', $mensaje);
    }
}
