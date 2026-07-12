<?php

namespace Tests\Unit\Support\Contable;

use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Models\Caja\Estacionamiento\TurnoOperativoEstacionamiento;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class CierreRendicionEstacionamientoGrupoSupportTest extends TestCase
{
    public function test_agrupa_por_fecha_jornada_y_punto_venta(): void
    {
        $jornada = new JornadaEstacionamiento(['fecha_jornada' => Carbon::parse('2026-07-03')]);
        $turno = new TurnoOperativoEstacionamiento();
        $turno->setRelation('jornada', $jornada);

        $r1 = $this->rendicionStub(1, 10, 100, $turno);
        $r2 = $this->rendicionStub(2, 10, 100, $turno);
        $r3 = $this->rendicionStub(3, 10, 200, $turno);

        $grupos = CierreRendicionEstacionamientoGrupoSupport::agrupar(new Collection([$r1, $r2, $r3]));

        $this->assertCount(2, $grupos);
        $grupo100 = collect($grupos)->firstWhere('puntoventa_cae_id', 100);
        $this->assertNotNull($grupo100);
        $this->assertSame(2, $grupo100['cantidad_rendiciones']);
        $this->assertSame(200.0, $grupo100['total_cobrado']);
        $this->assertSame(240.0, $grupo100['total_ventas']);
        $this->assertSame(10.0, $grupo100['total_invitaciones']);
        $this->assertSame(CierreRendicionEstacionamientoGrupoSupport::ESTADO_PENDIENTE, $grupo100['estado_grupo']);
    }

    public function test_estado_parcial_cuando_hay_cerradas_y_pendientes(): void
    {
        $jornada = new JornadaEstacionamiento(['fecha_jornada' => Carbon::parse('2026-07-03')]);
        $turno = new TurnoOperativoEstacionamiento();
        $turno->setRelation('jornada', $jornada);

        $pendiente = $this->rendicionStub(1, 10, 100, $turno);
        $cerrada = $this->rendicionStub(2, 10, 100, $turno, asientoId: 99);

        $grupos = CierreRendicionEstacionamientoGrupoSupport::agrupar(new Collection([$pendiente, $cerrada]));

        $this->assertCount(1, $grupos);
        $this->assertSame(CierreRendicionEstacionamientoGrupoSupport::ESTADO_PARCIAL, $grupos[0]['estado_grupo']);
        $this->assertTrue($grupos[0]['puede_cerrar']);
        $this->assertFalse($grupos[0]['puede_anular']);
    }

    public function test_puede_anular_solo_grupo_cerrado_con_un_asiento(): void
    {
        $jornada = new JornadaEstacionamiento(['fecha_jornada' => Carbon::parse('2026-07-03')]);
        $turno = new TurnoOperativoEstacionamiento();
        $turno->setRelation('jornada', $jornada);

        $r1 = $this->rendicionStub(1, 10, 100, $turno, asientoId: 50);
        $r2 = $this->rendicionStub(2, 10, 100, $turno, asientoId: 50);

        $grupos = CierreRendicionEstacionamientoGrupoSupport::agrupar(new Collection([$r1, $r2]));

        $this->assertFalse($grupos[0]['puede_cerrar']);
        $this->assertTrue($grupos[0]['puede_anular']);
        $this->assertSame(50, $grupos[0]['asiento_id']);
    }

    private function rendicionStub(
        int $id,
        int $empresaId,
        int $puntoventaCaeId,
        TurnoOperativoEstacionamiento $turno,
        ?int $asientoId = null,
    ): RendicionEstacionamientoCaja {
        $rendicion = new RendicionEstacionamientoCaja([
            'id' => $id,
            'empresa_id' => $empresaId,
            'puntoventa_cae_id' => $puntoventaCaeId,
            'turno_operativo_estacionamiento_id' => 1,
            'totalcobrado' => 100.0,
            'totalfactura' => 120.0,
            'totalinvitacion' => 5.0,
            'fecharendicion' => Carbon::parse('2026-07-03 18:00:00'),
        ]);

        if ($asientoId !== null) {
            $rendicion->asiento_id = $asientoId;
            $rendicion->cierre_contable_en = now();
        }

        $rendicion->setRelation('turnoOperativo', $turno);

        return $rendicion;
    }
}
