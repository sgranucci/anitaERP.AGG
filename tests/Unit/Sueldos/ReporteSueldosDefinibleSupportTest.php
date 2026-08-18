<?php

namespace Tests\Unit\Sueldos;

use App\Models\Sueldos\Empleado_Leyenda_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleCampoEmpleadoSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleFormulaSupport;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ReporteSueldosDefinibleSupportTest extends TestCase
{
    public function test_evalua_formula_entre_columnas(): void
    {
        $resultado = ReporteSueldosDefinibleFormulaSupport::evaluar(
            '(C1 + C02) / C3',
            [1 => 100, 2 => 50, 3 => 2]
        );

        $this->assertSame(75.0, $resultado);
    }

    public function test_rechaza_referencia_inexistente_y_autorreferencia(): void
    {
        $errores = ReporteSueldosDefinibleFormulaSupport::validar('C2 + C99', [1, 2, 3], 2);

        $this->assertCount(2, $errores);
        $this->assertStringContainsString('propia columna C2', $errores[0]);
        $this->assertStringContainsString('inexistente C99', $errores[1]);
    }

    public function test_detecta_parentesis_desbalanceados_y_ciclos(): void
    {
        $this->assertNotEmpty(
            ReporteSueldosDefinibleFormulaSupport::validar('(C1 + C2', [1, 2, 3], 3)
        );
        $this->assertTrue(ReporteSueldosDefinibleFormulaSupport::tieneCiclo([
            3 => 'C4 + 1',
            4 => 'C3 - 1',
        ]));
        $this->assertFalse(ReporteSueldosDefinibleFormulaSupport::tieneCiclo([
            3 => 'C1 + C2',
            4 => 'C3 / 2',
        ]));
    }

    public function test_resuelve_las_dos_leyendas_heredadas(): void
    {
        $empleado = new Empleado_Sueldos;
        $empleado->setRelation('leyendas', new Collection([
            new Empleado_Leyenda_Sueldos(['linea' => 1, 'leyenda' => 'REFERENTE']),
            new Empleado_Leyenda_Sueldos(['linea' => 2, 'leyenda' => 'ENCARGADO DE TURNO']),
        ]));

        $this->assertSame(
            'REFERENTE',
            ReporteSueldosDefinibleCampoEmpleadoSupport::resolver(23, null, $empleado)
        );
        $this->assertSame(
            'ENCARGADO DE TURNO',
            ReporteSueldosDefinibleCampoEmpleadoSupport::resolver(24, null, $empleado)
        );
    }
}
