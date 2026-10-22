<?php

namespace Tests\Unit\Support\Solicitudpago;

use App\Support\Caja\IngresoEgresoSolicitudpagoSupport;
use App\Support\Solicitudpago\SolicitudpagoRetencionCalculoSupport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SolicitudpagoRetencionCalculoSupportTest extends TestCase
{
    public function test_tasa_de_iva_despeja_el_neto_del_monto_de_la_solicitud(): void
    {
        $bases = SolicitudpagoRetencionCalculoSupport::bases(2096000.0, 'tasa_iva', null, 21.0);

        $this->assertEqualsWithDelta(1732231.40, $bases['neto_gravado'], 0.001);
        $this->assertEqualsWithDelta(363768.60, $bases['importe_iva'], 0.001);
    }

    public function test_monto_con_tasa_calcula_el_iva_sobre_el_neto(): void
    {
        // SP 11824: el IVA del asiento (336.000) es el 21% de 1.600.000.
        $bases = SolicitudpagoRetencionCalculoSupport::bases(2096000.0, 'monto', 1600000.0, 21.0);

        $this->assertSame(1600000.0, $bases['neto_gravado']);
        $this->assertSame(336000.0, $bases['importe_iva']);
    }

    public function test_monto_sin_tasa_deja_el_iva_en_cero(): void
    {
        $bases = SolicitudpagoRetencionCalculoSupport::bases(1000.0, 'monto', 800.0, null);

        $this->assertSame(800.0, $bases['neto_gravado']);
        $this->assertSame(0.0, $bases['importe_iva']);
        $this->assertNull($bases['tasa_iva']);
    }

    public function test_tasa_vacia_no_alcanza_para_calcular(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SolicitudpagoRetencionCalculoSupport::bases(1000.0, 'tasa_iva', null, null);
    }

    public function test_exento_deja_el_neto_igual_al_monto(): void
    {
        $bases = SolicitudpagoRetencionCalculoSupport::bases(1000.0, 'tasa_iva', null, 0.0);

        $this->assertSame(1000.0, $bases['neto_gravado']);
        $this->assertSame(0.0, $bases['importe_iva']);
        $this->assertSame(0.0, $bases['tasa_iva']);
    }

    public function test_asiento_opa_suma_retenciones_al_anticipo_y_deja_la_caja_en_el_neto(): void
    {
        $anticipo = [
            'cuentacontable_id' => 10,
            'codigo' => '114050001',
            'debe' => 1716290.0,
            'haber' => '',
        ];
        $retenciones = [
            ['cuentacontable_id' => 403, 'codigo' => '214010013', 'debe' => '', 'haber' => 347710.0],
            ['cuentacontable_id' => 404, 'codigo' => '214010014', 'debe' => '', 'haber' => 32000.0],
        ];
        $caja = [
            ['cuentacontable_id' => 41, 'codigo' => '111050009', 'debe' => '', 'haber' => 1716290.0],
        ];

        $lineas = IngresoEgresoSolicitudpagoSupport::cerrarAsientoOpaConRetenciones($anticipo, $retenciones, $caja);

        $this->assertCount(4, $lineas);
        $this->assertEqualsWithDelta(2096000.0, (float) $lineas[0]['debe'], 0.001);
        $this->assertEqualsWithDelta(347710.0, (float) $lineas[1]['haber'], 0.001);
        $this->assertEqualsWithDelta(32000.0, (float) $lineas[2]['haber'], 0.001);
        $this->assertEqualsWithDelta(1716290.0, (float) $lineas[3]['haber'], 0.001);

        $debe = 0.0;
        $haber = 0.0;
        foreach ($lineas as $linea) {
            $debe += (float) ($linea['debe'] ?: 0);
            $haber += (float) ($linea['haber'] ?: 0);
        }
        $this->assertEqualsWithDelta($debe, $haber, 0.001);
    }

    public function test_sin_retenciones_el_anticipo_sigue_el_importe_de_caja(): void
    {
        $anticipo = ['debe' => 100.0, 'haber' => ''];
        $caja = [['debe' => '', 'haber' => 100.0]];

        $lineas = IngresoEgresoSolicitudpagoSupport::cerrarAsientoOpaConRetenciones($anticipo, [], $caja);

        $this->assertCount(2, $lineas);
        $this->assertEqualsWithDelta(100.0, (float) $lineas[0]['debe'], 0.001);
    }
}
