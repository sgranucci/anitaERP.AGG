<?php

namespace Tests\Unit\Support\Sueldos;

use App\Support\Sueldos\LibretaSanitariaAvisoSupport;
use App\Support\Sueldos\LibretaSanitariaLeyendaSupport;
use PHPUnit\Framework\TestCase;

class LibretaSanitariaLeyendaSupportTest extends TestCase
{
    public function test_debe_y_typo_marcan_la_deuda_sin_fecha(): void
    {
        $dato = LibretaSanitariaLeyendaSupport::interpretar([
            'Observación general',
            'DEBE LIBRETA SANITARIA',
            'DEBE LIBRETA SANIATARIA',
        ]);

        $this->assertTrue($dato['debe']);
        $this->assertNull($dato['vencimiento']);
    }

    public function test_vencimiento_mes_anio_toma_el_ultimo_dia(): void
    {
        $dato = LibretaSanitariaLeyendaSupport::interpretar([
            'VCE. LIBRETA SANITARIA 03/2007',
        ]);

        $this->assertFalse($dato['debe']);
        $this->assertSame('2007-03-31', $dato['vencimiento']);
    }

    public function test_fecha_completa_se_respeta(): void
    {
        $dato = LibretaSanitariaLeyendaSupport::interpretar([
            'Vto. libreta sanitaria 15/08/2026',
        ]);

        $this->assertFalse($dato['debe']);
        $this->assertSame('2026-08-15', $dato['vencimiento']);
    }

    public function test_ignora_texto_que_no_es_libreta(): void
    {
        $dato = LibretaSanitariaLeyendaSupport::interpretar([
            'DEBE DOCUMENTACION',
            'CERTIFICADO MEDICO 01/2020',
        ]);

        $this->assertFalse($dato['debe']);
        $this->assertNull($dato['vencimiento']);
    }

    public function test_el_aviso_lista_empresa_legajo_y_vencimiento(): void
    {
        $texto = LibretaSanitariaAvisoSupport::formatearLista([
            [
                'empresa' => 'BIYEMAS S.A.',
                'legajo' => '26206216',
                'nombre' => 'GARCIA ANA',
                'vencimiento' => '',
            ],
        ]);

        $this->assertStringContainsString('BIYEMAS S.A. · legajo 26206216 · GARCIA ANA · sin vencimiento', $texto);
    }
}
