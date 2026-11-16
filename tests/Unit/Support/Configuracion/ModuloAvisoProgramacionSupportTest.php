<?php

namespace Tests\Unit\Support\Configuracion;

use App\Support\Configuracion\ModuloAvisoProgramacionSupport;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class ModuloAvisoProgramacionSupportTest extends TestCase
{
    public function test_un_dia_habil_a_la_hora_corresponde_una_sola_vez(): void
    {
        $miercoles = Carbon::parse('2026-11-04 08:20', 'America/Argentina/Buenos_Aires');

        $this->assertTrue(ModuloAvisoProgramacionSupport::corresponde(
            true,
            '08:20',
            '1,2,3,4,5',
            null,
            $miercoles
        ));
        $this->assertFalse(ModuloAvisoProgramacionSupport::corresponde(
            true,
            '08:20',
            '1,2,3,4,5',
            '2026-11-04',
            $miercoles
        ));
    }

    public function test_antes_de_la_hora_o_un_domingo_no_corresponde(): void
    {
        $temprano = Carbon::parse('2026-11-04 08:19', 'America/Argentina/Buenos_Aires');
        $domingo = Carbon::parse('2026-11-08 09:00', 'America/Argentina/Buenos_Aires');

        $this->assertFalse(ModuloAvisoProgramacionSupport::corresponde(true, '08:20', '1,2,3,4,5', null, $temprano));
        $this->assertFalse(ModuloAvisoProgramacionSupport::corresponde(true, '08:20', '1,2,3,4,5', null, $domingo));
        $this->assertFalse(ModuloAvisoProgramacionSupport::corresponde(false, '08:20', '1,2,3,4,5', null, $domingo));
    }

    public function test_la_etiqueta_de_dias_habiles(): void
    {
        $this->assertSame(
            'Lun a vie 08:20',
            ModuloAvisoProgramacionSupport::etiqueta('08:20', '1,2,3,4,5', true)
        );
        $this->assertSame('Sin programar', ModuloAvisoProgramacionSupport::etiqueta('08:20', '1,2,3,4,5', false));
        $this->assertSame('Pausada', ModuloAvisoProgramacionSupport::etiqueta('08:20', '1,2,3,4,5', true, 1, null, null, true));
        $this->assertSame('Día 15 09:00', ModuloAvisoProgramacionSupport::etiqueta('09:00', null, true, 1, null, 15));
    }

    public function test_dia_del_mes_y_skip_feriado(): void
    {
        $dia15 = Carbon::parse('2026-11-15 09:00', 'America/Argentina/Buenos_Aires');
        $this->assertTrue(ModuloAvisoProgramacionSupport::corresponde(
            true, '09:00', null, null, $dia15, 1, null, null, false, 15
        ));
        $this->assertFalse(ModuloAvisoProgramacionSupport::corresponde(
            true, '09:00', null, null, $dia15, 1, null, null, false, 16
        ));
        $this->assertFalse(ModuloAvisoProgramacionSupport::corresponde(
            true, '09:00', '1,2,3,4,5', null, $dia15, 1, null, null, false, null, true, ['2026-11-15']
        ));
    }

    public function test_pausada_no_corresponde(): void
    {
        $miercoles = Carbon::parse('2026-11-04 08:20', 'America/Argentina/Buenos_Aires');
        $this->assertFalse(ModuloAvisoProgramacionSupport::corresponde(
            true, '08:20', '1,2,3,4,5', null, $miercoles, 1, null, null, true
        ));
    }

    public function test_ventana_horaria_corta_despues_de_hora_hasta(): void
    {
        $enVentana = Carbon::parse('2026-11-04 08:10', 'America/Argentina/Buenos_Aires');
        $fuera = Carbon::parse('2026-11-04 08:31', 'America/Argentina/Buenos_Aires');

        $this->assertTrue(ModuloAvisoProgramacionSupport::corresponde(
            true, '08:00', '1,2,3,4,5', null, $enVentana, 1, null, null, false, null, false, null, '08:30'
        ));
        $this->assertFalse(ModuloAvisoProgramacionSupport::corresponde(
            true, '08:00', '1,2,3,4,5', null, $fuera, 1, null, null, false, null, false, null, '08:30'
        ));
        $this->assertSame(
            'Lun a vie 08:00–08:30',
            ModuloAvisoProgramacionSupport::etiqueta('08:00', '1,2,3,4,5', true, 1, null, null, false, false, '08:30')
        );
    }
}
