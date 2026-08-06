<?php

namespace Tests\Unit\Sueldos;

use App\Support\Sueldos\Formula\EntornoArray;
use App\Support\Sueldos\Formula\EvaluadorFormula;
use App\Support\Sueldos\Formula\FormulaException;
use PHPUnit\Framework\TestCase;

/**
 * Tests puros del motor de fórmulas (sin BD ni framework).
 */
class EvaluadorFormulaTest extends TestCase
{
    private function motor(): EvaluadorFormula
    {
        return new EvaluadorFormula;
    }

    private function entorno(): EntornoArray
    {
        return new EntornoArray(
            [
                'empleado.sueldo_basico' => 100000.0,
                'empleado.antiguedad_anios' => 10,
                'periodo.dias' => 30,
                'corrida.tipo' => 'mensual',
            ],
            [
                'concepto' => fn ($cod) => [100 => 100000.0, 200 => 5000.0][(int) $cod] ?? 0.0,
                'acum' => fn ($cod) => ['REM' => 120000.0][(string) $cod] ?? 0.0,
                'param' => fn ($cod) => ['TOPE_SIPA' => 2000000.0][(string) $cod] ?? 0.0,
                'antiguedad' => fn () => 10,
            ],
        );
    }

    public function test_aritmetica_basica(): void
    {
        $this->assertEqualsWithDelta(7.0, $this->motor()->evaluar('1 + 2 * 3', $this->entorno()), 1e-9);
        $this->assertEqualsWithDelta(9.0, $this->motor()->evaluar('(1 + 2) * 3', $this->entorno()), 1e-9);
        $this->assertEqualsWithDelta(8.0, $this->motor()->evaluar('2 ^ 3', $this->entorno()), 1e-9);
        $this->assertEqualsWithDelta(1.0, $this->motor()->evaluar('10 % 3', $this->entorno()), 1e-9);
    }

    public function test_precedencia_potencia_asociativa_derecha(): void
    {
        // 2 ^ 3 ^ 2 = 2 ^ 9 = 512
        $this->assertEqualsWithDelta(512.0, $this->motor()->evaluar('2 ^ 3 ^ 2', $this->entorno()), 1e-9);
    }

    public function test_variables_con_ruta(): void
    {
        $r = $this->motor()->evaluar('empleado.sueldo_basico * 0.11', $this->entorno());
        $this->assertEqualsWithDelta(11000.0, $r, 1e-9);
    }

    public function test_redondeo_y_truncado(): void
    {
        $this->assertEqualsWithDelta(11000.13, $this->motor()->evaluar('redondear(11000.125, 2)', $this->entorno()), 1e-9);
        $this->assertEqualsWithDelta(11000.12, $this->motor()->evaluar('truncar(11000.129, 2)', $this->entorno()), 1e-9);
    }

    public function test_min_max_entre(): void
    {
        $this->assertEqualsWithDelta(5.0, $this->motor()->evaluar('min(5, 8, 12)', $this->entorno()), 1e-9);
        $this->assertEqualsWithDelta(12.0, $this->motor()->evaluar('max(5, 8, 12)', $this->entorno()), 1e-9);
        $this->assertEqualsWithDelta(10.0, $this->motor()->evaluar('entre(15, 2, 10)', $this->entorno()), 1e-9);
    }

    public function test_condicional_si(): void
    {
        $f = 'si(empleado.antiguedad_anios >= 5, empleado.sueldo_basico * 0.02 * empleado.antiguedad_anios, 0)';
        $this->assertEqualsWithDelta(20000.0, $this->motor()->evaluar($f, $this->entorno()), 1e-9);
    }

    public function test_condicional_string(): void
    {
        $f = 'si(corrida.tipo == "sac", 1, 0)';
        $this->assertEqualsWithDelta(0.0, $this->motor()->evaluar($f, $this->entorno()), 1e-9);
    }

    public function test_logicos_y_comparaciones(): void
    {
        $this->assertTrue((bool) $this->motor()->evaluar('3 > 2 && 1 < 5', $this->entorno()));
        $this->assertFalse((bool) $this->motor()->evaluar('3 > 2 && 1 > 5', $this->entorno()));
        $this->assertTrue((bool) $this->motor()->evaluar('3 > 2 || 1 > 5', $this->entorno()));
    }

    public function test_funciones_de_dominio(): void
    {
        $this->assertEqualsWithDelta(100000.0, $this->motor()->evaluar('concepto(100)', $this->entorno()), 1e-9);
        $this->assertEqualsWithDelta(120000.0, $this->motor()->evaluar('acum("REM")', $this->entorno()), 1e-9);
        $this->assertEqualsWithDelta(13200.0, $this->motor()->evaluar('redondear(acum("REM") * 0.11, 2)', $this->entorno()), 1e-9);
    }

    public function test_tope_con_param(): void
    {
        // Base jubilatoria acotada al tope SIPA
        $f = 'min(acum("REM"), param("TOPE_SIPA")) * 0.11';
        $this->assertEqualsWithDelta(13200.0, $this->motor()->evaluar($f, $this->entorno()), 1e-9);
    }

    public function test_division_por_cero(): void
    {
        // Anita: división por 0 → HUGE_VAL / INF (no excepción).
        $this->assertInfinite($this->motor()->evaluar('10 / 0', $this->entorno()));
    }

    public function test_variable_no_definida(): void
    {
        $this->expectException(FormulaException::class);
        $this->motor()->evaluar('empleado.inexistente + 1', $this->entorno());
    }

    public function test_sintaxis_invalida(): void
    {
        $this->assertNotNull($this->motor()->validar('1 + * 2'));
        $this->assertNull($this->motor()->validar('1 + 2'));
    }

    public function test_rastro_registra_pasos(): void
    {
        [$valor, $rastro] = $this->motor()->evaluarConRastro('redondear(empleado.sueldo_basico * 0.11, 2)', $this->entorno());
        $this->assertEqualsWithDelta(11000.0, $valor, 1e-9);
        $texto = $rastro->texto();
        $this->assertStringContainsString('empleado.sueldo_basico', $texto);
        $this->assertStringContainsString('11000', $texto);
        $this->assertNotEmpty($rastro->arbol());
    }

    public function test_rastro_short_circuit_no_evalua_rama_no_tomada(): void
    {
        // Si la condición es falsa, la rama verdadera (que usaría variable inexistente)
        // no debe evaluarse: el resultado es 42 y no se lanza excepción por la variable.
        $f = 'si(1 > 2, empleado.inexistente, 42)';
        [$valor, $rastro] = $this->motor()->evaluarConRastro($f, $this->entorno());
        $this->assertEqualsWithDelta(42.0, $valor, 1e-9);
        // El nodo raíz muestra la expresión completa, pero no debe existir un
        // nodo hijo evaluado para la variable inexistente.
        $this->assertFalse($this->contieneNodo($rastro->arbol(), 'empleado.inexistente'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodos
     */
    private function contieneNodo(array $nodos, string $expr): bool
    {
        foreach ($nodos as $nodo) {
            if ($nodo['expr'] === $expr) {
                return true;
            }
            if ($this->contieneNodo($nodo['hijos'], $expr)) {
                return true;
            }
        }

        return false;
    }
}
