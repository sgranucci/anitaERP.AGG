<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoEmpresaSupport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class IngresoEgresoEmpresaSupportTest extends TestCase
{
    public function test_contaduria_elige_empresa_al_ingresar(): void
    {
        $this->assertFalse(IngresoEgresoEmpresaSupport::soloLecturaConPermisos(false, true, false));
        IngresoEgresoEmpresaSupport::assertAlGuardarConPermisos(false, 1, 2, true, false);
        $this->assertTrue(true);
    }

    public function test_fuera_de_contaduria_no_cambia_empresa_al_ingresar(): void
    {
        $this->assertTrue(IngresoEgresoEmpresaSupport::soloLecturaConPermisos(false, false, false));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('al ingresar el movimiento');
        IngresoEgresoEmpresaSupport::assertAlGuardarConPermisos(false, 1, 2, false, false);
    }

    public function test_fuera_de_contaduria_sin_empresa_de_trabajo_no_graba(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('al ingresar el movimiento');
        IngresoEgresoEmpresaSupport::assertAlGuardarConPermisos(false, 0, 2, false, false);
    }

    public function test_movimiento_grabado_no_cambia_de_empresa_sin_permiso(): void
    {
        $this->assertTrue(IngresoEgresoEmpresaSupport::soloLecturaConPermisos(true, true, false));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ya ingresado');
        IngresoEgresoEmpresaSupport::assertAlGuardarConPermisos(true, 1, 3, true, false);
    }

    public function test_contaduria_con_permiso_puede_mover_un_movimiento_grabado(): void
    {
        $this->assertFalse(IngresoEgresoEmpresaSupport::soloLecturaConPermisos(true, true, true));
        IngresoEgresoEmpresaSupport::assertAlGuardarConPermisos(true, 1, 3, true, true);
        IngresoEgresoEmpresaSupport::assertAlGuardarConPermisos(true, 1, 1, true, false);
        $this->assertTrue(true);
    }
}
