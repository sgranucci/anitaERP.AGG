<?php

namespace Tests\Unit\Support\Sueldos;

use App\Support\Sueldos\EmpleadoCuentaEmpresaSupport;
use PHPUnit\Framework\TestCase;

class EmpleadoCuentaEmpresaSupportTest extends TestCase
{
    public function test_sin_dato_se_queda_con_el_alta_mas_antigua(): void
    {
        $elegido = EmpleadoCuentaEmpresaSupport::elegir([
            $this->fila(2, 3, null, '2020-01-01', 1000),
            $this->fila(1, 1, null, '2018-05-01', 500),
        ]);

        $this->assertSame(1, $elegido->id);
    }

    public function test_cuenta_en_la_empresa_indicada_aunque_el_alta_sea_posterior(): void
    {
        $elegido = EmpleadoCuentaEmpresaSupport::elegir([
            $this->fila(10, 3, 1, '2010-01-01', 9000),
            $this->fila(11, 1, 1, '2024-06-01', 1000),
            $this->fila(12, 4, 1, '2015-01-01', 8000),
        ]);

        $this->assertSame(11, $elegido->id);
        $this->assertSame(1, $elegido->empresa_id);
    }

    public function test_si_esa_empresa_no_esta_vigente_elige_el_mejor_sueldo_con_tolerancia(): void
    {
        $elegido = EmpleadoCuentaEmpresaSupport::elegir([
            $this->fila(10, 3, 1, '2010-01-01', 5000),
            $this->fila(12, 4, 1, '2015-01-01', 4950),
        ]);

        $this->assertSame(12, $elegido->id);
    }

    public function test_un_sueldo_mas_de_cien_arriba_no_lo_pisa_la_empresa_posterior(): void
    {
        $elegido = EmpleadoCuentaEmpresaSupport::elegir([
            $this->fila(10, 3, 1, '2010-01-01', 5000),
            $this->fila(12, 4, 1, '2015-01-01', 4899),
        ]);

        $this->assertSame(10, $elegido->id);
    }

    private function fila(int $id, int $empresaId, ?int $cuentaId, string $ingreso, float $sueldo): object
    {
        return (object) [
            'id' => $id,
            'empresa_id' => $empresaId,
            'cuenta_empresa_id' => $cuentaId,
            'fecha_ingreso' => $ingreso,
            'sueldo_basico' => $sueldo,
        ];
    }
}
