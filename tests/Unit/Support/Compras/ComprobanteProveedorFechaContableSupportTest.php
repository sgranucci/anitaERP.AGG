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

    public function test_techo_no_adelanta_si_la_grabada_ya_cubre_el_comprobante(): void
    {
        $this->assertSame(
            '2026-10-02',
            ComprobanteProveedorFechaContableSupport::techoContabilizacion(
                '2026-10-09',
                '2026-10-02',
                '2026-10-01',
                '2026-10-01'
            )
        );
    }

    public function test_techo_sube_hasta_la_fecha_del_comprobante(): void
    {
        $this->assertSame(
            '2026-10-01',
            ComprobanteProveedorFechaContableSupport::techoContabilizacion(
                '2026-10-09',
                '2026-09-28',
                '2026-10-01',
                null
            )
        );
    }

    public function test_techo_sube_al_primer_dia_abierto_si_la_grabada_esta_cerrada(): void
    {
        $this->assertSame(
            '2026-10-01',
            ComprobanteProveedorFechaContableSupport::techoContabilizacion(
                '2026-10-09',
                '2026-09-28',
                '2026-09-28',
                '2026-10-01'
            )
        );
    }

    public function test_techo_no_pasa_de_hoy(): void
    {
        $this->assertSame(
            '2026-10-09',
            ComprobanteProveedorFechaContableSupport::techoContabilizacion(
                '2026-10-09',
                '2026-10-09',
                '2026-10-20',
                '2026-10-15'
            )
        );
    }

    public function test_fecha_comprobante_no_puede_superar_contabilizacion(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no puede ser posterior a la fecha de contabilización');
        ComprobanteProveedorFechaContableSupport::assertFechasCargaCoherentes(
            '2026-10-15',
            '2026-10-09',
            '2026-10-09'
        );
    }

    public function test_fecha_contabilizacion_no_puede_superar_hoy(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no puede ser posterior a hoy');
        ComprobanteProveedorFechaContableSupport::assertFechasCargaCoherentes(
            '2026-10-09',
            '2026-10-10',
            '2026-10-09'
        );
    }

    public function test_fechas_iguales_y_hasta_hoy_pasan(): void
    {
        ComprobanteProveedorFechaContableSupport::assertFechasCargaCoherentes(
            '2026-10-01',
            '2026-10-09',
            '2026-10-09'
        );
        ComprobanteProveedorFechaContableSupport::assertFechasCargaCoherentes(
            '2026-10-09',
            '2026-10-09',
            '2026-10-09'
        );
        $this->assertTrue(true);
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
