<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\AnitaSync\RendicionAnitaIdempotenciaWhereSupport;
use PHPUnit\Framework\TestCase;

class RendicionAnitaIdempotenciaWhereSupportTest extends TestCase
{
    public function test_where_turno_incluye_host_para_separar_modulos(): void
    {
        $where = RendicionAnitaIdempotenciaWhereSupport::whereTurnoOperativo(
            96,
            1,
            'F',
            '10.20.29.40',
        );

        $this->assertStringContainsString("rendg_nro_rend_vta = '96'", $where);
        $this->assertStringContainsString("rendg_host = '10.20.29.40'", $where);
    }

    public function test_filtro_host_vacio_no_restringe(): void
    {
        $this->assertSame('', RendicionAnitaIdempotenciaWhereSupport::filtroHost(null));
        $this->assertSame('', RendicionAnitaIdempotenciaWhereSupport::filtroHost(''));
    }

    public function test_host_con_comilla_se_escapa(): void
    {
        $where = RendicionAnitaIdempotenciaWhereSupport::whereTurnoOperativo(
            1,
            1,
            'F',
            "pc'x",
        );

        $this->assertStringContainsString("rendg_host = 'pc''x'", $where);
    }
}
