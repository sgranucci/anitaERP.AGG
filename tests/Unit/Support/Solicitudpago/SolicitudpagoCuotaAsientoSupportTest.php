<?php

namespace Tests\Unit\Support\Solicitudpago;

use App\Support\Solicitudpago\SolicitudpagoCuotaAsientoSupport;
use PHPUnit\Framework\TestCase;

class SolicitudpagoCuotaAsientoSupportTest extends TestCase
{
    public function test_hija_usa_monto_de_cuota_aunque_el_asiento_madre_difiera_de_la_cabecera(): void
    {
        $cuentasMadre = [
            ['debe_haber' => 'H', 'monto' => 33990035.30],
            ['debe_haber' => 'D', 'monto' => 33990035.30],
        ];

        $montos = SolicitudpagoCuotaAsientoSupport::montosHija($cuentasMadre, 607975.30);

        $this->assertSame([607975.30, 607975.30], $montos);
    }

    public function test_hija_ajusta_redondeo_en_la_ultima_linea_de_cada_lado(): void
    {
        $cuentasMadre = [
            ['debe_haber' => 'D', 'monto' => 100.00],
            ['debe_haber' => 'D', 'monto' => 50.00],
            ['debe_haber' => 'H', 'monto' => 150.00],
        ];

        $montos = SolicitudpagoCuotaAsientoSupport::montosHija($cuentasMadre, 10.00);

        $this->assertEqualsWithDelta(10.00, $montos[0] + $montos[1], 0.001);
        $this->assertEqualsWithDelta(10.00, $montos[2], 0.001);
    }
}
