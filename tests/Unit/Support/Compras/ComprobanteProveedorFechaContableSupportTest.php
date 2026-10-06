<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorFechaContableSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorFechaContableSupportTest extends TestCase
{
    public function test_payload_con_fechaiva_usa_contabilizacion(): void
    {
        $this->assertSame(
            '2026-08-12',
            ComprobanteProveedorFechaContableSupport::fechaYmdDesdePayload([
                'fechaiva' => '2026-08-12',
                'fechacomprobante' => '2026-07-22',
            ])
        );
    }

    public function test_payload_sin_fechaiva_no_usa_fechacomprobante(): void
    {
        $this->assertNotSame(
            '2026-07-22',
            ComprobanteProveedorFechaContableSupport::fechaYmdDesdePayload([
                'fechacomprobante' => '2026-07-22',
            ])
        );
    }

    public function test_fecha_comprobante_dentro_de_30_dias_pasa(): void
    {
        ComprobanteProveedorFechaContableSupport::assertFechaComprobanteNoExcesivamenteFutura(
            '2026-09-10',
            '2026-08-23',
            30
        );
        $this->assertTrue(true);
    }

    public function test_fecha_comprobante_pasada_siempre_pasa(): void
    {
        ComprobanteProveedorFechaContableSupport::assertFechaComprobanteNoExcesivamenteFutura(
            '2026-01-02',
            '2026-08-23',
            30
        );
        $this->assertTrue(true);
    }

    public function test_fecha_contabilizacion_hacia_atras_pasa(): void
    {
        $this->assertSame(
            '2026-09-30',
            ComprobanteProveedorFechaContableSupport::fechaNoPosterior('2026-09-30', '2026-10-01')
        );
    }

    public function test_fecha_contabilizacion_igual_al_tope_pasa(): void
    {
        $this->assertSame(
            '2026-10-01',
            ComprobanteProveedorFechaContableSupport::fechaNoPosterior('2026-10-01', '2026-10-01')
        );
    }

    public function test_fecha_contabilizacion_vacia_queda_en_el_tope(): void
    {
        $this->assertSame(
            '2026-10-05',
            ComprobanteProveedorFechaContableSupport::fechaNoPosterior(null, '2026-10-05')
        );
    }

    public function test_fecha_contabilizacion_hacia_adelante_corta(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no puede ser posterior');
        ComprobanteProveedorFechaContableSupport::fechaNoPosterior('2026-10-06', '2026-10-05');
    }

    public function test_fecha_comprobante_muy_a_futuro_corta(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no puede ser más de 30 días posterior');
        ComprobanteProveedorFechaContableSupport::assertFechaComprobanteNoExcesivamenteFutura(
            '2027-08-23',
            '2026-08-23',
            30
        );
    }
}
