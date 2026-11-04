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
    }
}
