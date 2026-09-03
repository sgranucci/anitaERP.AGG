<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaAnitaBridgeReader;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class MayorPlanoCuentaAnitaBridgeReaderTest extends TestCase
{
    public function test_cargar_periodo_acepta_flag_solo_periodo_consultado(): void
    {
        $reader = new MayorPlanoCuentaAnitaBridgeReader();
        $method = new ReflectionMethod(MayorPlanoCuentaAnitaBridgeReader::class, 'cargarPeriodo');

        $this->assertTrue($method->isPublic());
        $this->assertCount(11, $method->getParameters());
        $this->assertSame('soloPeriodoConsultado', $method->getParameters()[10]->getName());
        $this->assertFalse($method->getParameters()[10]->getDefaultValue());
    }
}
