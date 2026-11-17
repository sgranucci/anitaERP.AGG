<?php

namespace Tests\Unit\Sueldos;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Services\Sueldos\LiquidacionCalculadorService;
use PHPUnit\Framework\TestCase;

class LiquidacionConfidencialMotorTest extends TestCase
{
    public function test_recibo_motor_hereda_flag_confidencial_del_legajo(): void
    {
        $confidencial = new Empleado_Sueldos(['confidencial' => true]);
        $attrsConf = LiquidacionCalculadorService::atributosOrigenReciboMotor($confidencial);

        $this->assertSame(Liquidacion_Recibo_Sueldos::ORIGEN_MOTOR, $attrsConf['origen']);
        $this->assertTrue($attrsConf['confidencial']);

        $normal = new Empleado_Sueldos(['confidencial' => false]);
        $attrsNormal = LiquidacionCalculadorService::atributosOrigenReciboMotor($normal);

        $this->assertSame(Liquidacion_Recibo_Sueldos::ORIGEN_MOTOR, $attrsNormal['origen']);
        $this->assertFalse($attrsNormal['confidencial']);
    }

    public function test_legajo_sin_flag_no_queda_confidencial(): void
    {
        $emp = new Empleado_Sueldos([]);
        $attrs = LiquidacionCalculadorService::atributosOrigenReciboMotor($emp);

        $this->assertFalse($attrs['confidencial']);
        $this->assertSame(Liquidacion_Recibo_Sueldos::ORIGEN_MOTOR, $attrs['origen']);
    }

    public function test_universo_de_corrida_ya_no_excluye_confidenciales(): void
    {
        $ref = new \ReflectionMethod(LiquidacionCalculadorService::class, 'empleados');
        $lines = array_slice(
            file((string) $ref->getFileName()),
            $ref->getStartLine() - 1,
            $ref->getEndLine() - $ref->getStartLine() + 1
        );
        $body = implode('', $lines);

        $this->assertStringNotContainsString("orWhere('confidencial', false)", $body);
        $this->assertStringContainsString('Incluye legajos confidenciales', $body);
    }
}
