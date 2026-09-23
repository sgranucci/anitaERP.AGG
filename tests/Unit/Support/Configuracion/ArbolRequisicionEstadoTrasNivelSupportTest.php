<?php

namespace Tests\Unit\Support\Configuracion;

use App\Support\Configuracion\ArbolRequisicionEstadoTrasNivelSupport;
use PHPUnit\Framework\TestCase;

class ArbolRequisicionEstadoTrasNivelSupportTest extends TestCase
{
    public function test_sin_proximo_nivel_respeta_aprobada(): void
    {
        $this->assertSame(
            'APROBADA',
            ArbolRequisicionEstadoTrasNivelSupport::resolver('APROBADA', false)
        );
    }

    public function test_con_proximo_nivel_no_cierra_como_aprobada(): void
    {
        $this->assertSame(
            'EN ARBOL APROBACION',
            ArbolRequisicionEstadoTrasNivelSupport::resolver('APROBADA', true)
        );
    }

    public function test_estados_intermedios_no_se_tocan(): void
    {
        $this->assertSame(
            'EN COMPRAS',
            ArbolRequisicionEstadoTrasNivelSupport::resolver('EN COMPRAS', true)
        );
        $this->assertSame(
            'EN ARBOL APROBACION',
            ArbolRequisicionEstadoTrasNivelSupport::resolver('EN ARBOL APROBACION', true)
        );
    }
}
