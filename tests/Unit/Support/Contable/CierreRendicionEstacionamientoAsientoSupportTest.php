<?php

namespace Tests\Unit\Support\Contable;

use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Models\Caja\Estacionamiento\TurnoOperativoEstacionamiento;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Support\Contable\CierreRendicionEstacionamientoAsientoSupport;
use Carbon\Carbon;
use Tests\TestCase;

class CierreRendicionEstacionamientoAsientoSupportTest extends TestCase
{
    public function test_fecha_asiento_usa_dia_de_fecharendicion(): void
    {
        $rendicion = new RendicionEstacionamientoCaja([
            'fecharendicion' => Carbon::parse('2026-07-03 23:45:00'),
        ]);

        $this->assertSame(
            '2026-07-03',
            CierreRendicionEstacionamientoAsientoSupport::fechaAsientoDesdeRendicion($rendicion),
        );
    }

    public function test_fecha_asiento_fallback_a_jornada_si_falta_fecharendicion(): void
    {
        $jornada = new JornadaEstacionamiento([
            'fecha_jornada' => Carbon::parse('2026-07-02'),
        ]);
        $turno = new TurnoOperativoEstacionamiento();
        $turno->setRelation('jornada', $jornada);

        $rendicion = new RendicionEstacionamientoCaja();
        $rendicion->setRelation('turnoOperativo', $turno);

        $this->assertSame(
            '2026-07-02',
            CierreRendicionEstacionamientoAsientoSupport::fechaAsientoDesdeRendicion($rendicion),
        );
    }

    public function test_diferencia_caja_no_duplica_invitaciones_de_la_rendicion(): void
    {
        $rendicion = new RendicionEstacionamientoCaja([
            'totalredondeoinvitacion' => 0.03,
            'totalredondeo' => 0.0,
            'sobrantefaltante' => 0.0,
        ]);

        $importe = CierreRendicionEstacionamientoAsientoSupport::calcularDiferenciaCajaPreview(
            $rendicion,
            ['debe_diferencia_caja' => 0.03],
        );

        $this->assertSame(0.03, $importe);
    }

    public function test_diferencia_caja_suma_excedente_manual_de_redondeo_invitaciones(): void
    {
        $rendicion = new RendicionEstacionamientoCaja([
            'totalredondeoinvitacion' => 0.04,
            'totalredondeo' => 0.0,
            'sobrantefaltante' => 0.0,
        ]);

        $importe = CierreRendicionEstacionamientoAsientoSupport::calcularDiferenciaCajaPreview(
            $rendicion,
            ['debe_diferencia_caja' => 0.03],
        );

        $this->assertSame(0.04, $importe);
    }

    public function test_diferencia_caja_incluye_sobrante_y_redondeo_turno(): void
    {
        $rendicion = new RendicionEstacionamientoCaja([
            'totalredondeoinvitacion' => 0.0,
            'totalredondeo' => 0.05,
            'sobrantefaltante' => -0.02,
        ]);

        $importe = CierreRendicionEstacionamientoAsientoSupport::calcularDiferenciaCajaPreview(
            $rendicion,
            ['debe_diferencia_caja' => 0.0],
        );

        $this->assertSame(0.03, $importe);
    }

    public function test_payload_asiento_no_incluye_path_sistema(): void
    {
        $payload = CierreRendicionEstacionamientoAsientoSupport::armarPayloadAsiento(
            [
                ['cuenta_id' => 10, 'debe' => 100.0, 'haber' => 0.0, 'concepto' => 'Debe'],
                ['cuenta_id' => 20, 'debe' => 0.0, 'haber' => 100.0, 'concepto' => 'Haber'],
            ],
            1,
            [
                'cuenta_ventas_id' => 10,
                'cuenta_iva_debito_id' => 20,
            ],
            '2026-09-18',
        );

        $this->assertArrayNotHasKey('path_sistema', $payload);
        $this->assertSame([10, 20], $payload['cuentacontable_ids']);
    }
}
