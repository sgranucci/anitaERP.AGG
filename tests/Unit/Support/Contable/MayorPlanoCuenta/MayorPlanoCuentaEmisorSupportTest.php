<?php

namespace Tests\Unit\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaEmisorSupport;
use PHPUnit\Framework\TestCase;

class MayorPlanoCuentaEmisorSupportTest extends TestCase
{
    public function test_emisor_persistido_manda_sobre_la_descripcion(): void
    {
        $resuelto = MayorPlanoCuentaEmisorSupport::resolver(
            'B',
            ' ',
            '3593',
            '9999 Aplicacin anticipo CC OPA A000'
        );

        $this->assertSame('3593', $resuelto['codigo']);
        $this->assertFalse($resuelto['deducido']);
        $this->assertSame(MayorPlanoCuentaEmisorSupport::ENTIDAD_PROVEEDOR, $resuelto['entidad']);
    }

    public function test_descripcion_de_aplicacion_cc_con_codigo_adelante(): void
    {
        $resuelto = MayorPlanoCuentaEmisorSupport::resolver(
            'B',
            ' ',
            '',
            '3593 Aplicacin anticipo CC OPA A000'
        );

        $this->assertSame('3593', $resuelto['codigo']);
        $this->assertTrue($resuelto['deducido']);
    }

    public function test_descripcion_de_aplicacion_cc_sin_codigo_no_inventa_emisor(): void
    {
        $resuelto = MayorPlanoCuentaEmisorSupport::resolver(
            'B',
            ' ',
            '',
            'Aplicacin anticipo CC OPA A000'
        );

        $this->assertSame('', $resuelto['codigo']);
        $this->assertFalse($resuelto['deducido']);
    }

    public function test_descripcion_generica_con_digitos_no_es_emisor(): void
    {
        $resuelto = MayorPlanoCuentaEmisorSupport::resolver(
            'B',
            ' ',
            '',
            '123456 reclasificacion varias'
        );

        $this->assertSame('', $resuelto['codigo']);
    }

    public function test_fac_sin_sistema_es_cliente(): void
    {
        $resuelto = MayorPlanoCuentaEmisorSupport::resolver('', 'FAC', '', 'FAC A 00075 221');

        $this->assertSame('', $resuelto['codigo']);
        $this->assertSame(MayorPlanoCuentaEmisorSupport::ENTIDAD_CLIENTE, $resuelto['entidad']);
        $this->assertFalse($resuelto['deducido']);
    }

    public function test_nce_sin_sistema_es_cliente(): void
    {
        $this->assertSame(
            MayorPlanoCuentaEmisorSupport::ENTIDAD_CLIENTE,
            MayorPlanoCuentaEmisorSupport::entidad('', 'NCE')
        );
    }
}
