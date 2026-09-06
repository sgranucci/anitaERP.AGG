<?php

namespace Tests\Unit;

use App\Support\Compras\Tracking\TrackingAntiguedadDeuda;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TrackingAntiguedadDeudaTest extends TestCase
{
    public function test_prefiere_el_vencimiento_sobre_la_fecha_del_comprobante(): void
    {
        [$fecha, $origen] = TrackingAntiguedadDeuda::fechaBase('2026-08-01', '2026-01-01');

        $this->assertSame('2026-08-01', $fecha);
        $this->assertSame(TrackingAntiguedadDeuda::ORIGEN_VENCIMIENTO, $origen);
    }

    public function test_cae_a_la_fecha_del_comprobante_si_no_hay_vencimiento(): void
    {
        [$fecha, $origen] = TrackingAntiguedadDeuda::fechaBase(null, '2026-01-15');

        $this->assertSame('2026-01-15', $fecha);
        $this->assertSame(TrackingAntiguedadDeuda::ORIGEN_COMPROBANTE, $origen);
    }

    public function test_arma_los_tramos_incluyendo_a_vencer(): void
    {
        $hoy = new DateTimeImmutable('2026-09-05');

        $this->assertSame(
            TrackingAntiguedadDeuda::CORRIENTE,
            TrackingAntiguedadDeuda::tramo(TrackingAntiguedadDeuda::dias('2026-10-01', $hoy))
        );
        $this->assertSame(
            TrackingAntiguedadDeuda::HASTA_30,
            TrackingAntiguedadDeuda::tramo(TrackingAntiguedadDeuda::dias('2026-08-20', $hoy))
        );
        $this->assertSame(
            TrackingAntiguedadDeuda::DE_31_A_60,
            TrackingAntiguedadDeuda::tramo(TrackingAntiguedadDeuda::dias('2026-07-20', $hoy))
        );
        $this->assertSame(
            TrackingAntiguedadDeuda::DE_61_A_90,
            TrackingAntiguedadDeuda::tramo(TrackingAntiguedadDeuda::dias('2026-06-20', $hoy))
        );
        $this->assertSame(
            TrackingAntiguedadDeuda::MAS_DE_90,
            TrackingAntiguedadDeuda::tramo(TrackingAntiguedadDeuda::dias('2026-01-01', $hoy))
        );
    }

    public function test_descarta_vencimientos_absurdamente_futuros(): void
    {
        [$fecha] = TrackingAntiguedadDeuda::fechaBase('2055-01-01', '2026-01-15');

        $this->assertSame('2026-01-15', $fecha);
    }

    public function test_fecha_invalida_no_inventa_antiguedad(): void
    {
        $this->assertNull(TrackingAntiguedadDeuda::dias(null));
        $this->assertNull(TrackingAntiguedadDeuda::dias('0000-00-00'));
        $this->assertNull(TrackingAntiguedadDeuda::dias('1999-12-31'));
        $this->assertNull(TrackingAntiguedadDeuda::tramo(null));
    }

    public function test_etiqueta_y_clase_del_tramo_urgente(): void
    {
        $this->assertSame('+90 días', TrackingAntiguedadDeuda::etiqueta(TrackingAntiguedadDeuda::MAS_DE_90));
        $this->assertSame('tf-alerta', TrackingAntiguedadDeuda::clasePill(TrackingAntiguedadDeuda::MAS_DE_90));
        $this->assertSame('tf-ok', TrackingAntiguedadDeuda::clasePill(TrackingAntiguedadDeuda::CORRIENTE));
    }
}
