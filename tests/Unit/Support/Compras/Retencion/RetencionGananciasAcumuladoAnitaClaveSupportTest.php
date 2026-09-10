<?php

namespace Tests\Unit\Support\Compras\Retencion;

use App\Support\Compras\Retencion\RetencionGananciasAcumuladoAnitaClaveSupport;
use PHPUnit\Framework\TestCase;

class RetencionGananciasAcumuladoAnitaClaveSupportTest extends TestCase
{
    public function test_clave_completa_incluye_letra(): void
    {
        $this->assertSame(
            'OPP|A|1|79030365|1',
            RetencionGananciasAcumuladoAnitaClaveSupport::clave('OPP', 'A', 1, 79030365, 1)
        );
        $this->assertNotSame(
            RetencionGananciasAcumuladoAnitaClaveSupport::clave('OPP', 'A', 1, 79030365, 1),
            RetencionGananciasAcumuladoAnitaClaveSupport::clave('OPP', ' ', 1, 79030365, 1)
        );
    }

    public function test_letra_vacia_es_espacio_informix(): void
    {
        $this->assertSame(' ', RetencionGananciasAcumuladoAnitaClaveSupport::letra(''));
        $this->assertSame('A', RetencionGananciasAcumuladoAnitaClaveSupport::letra('a'));
    }

    public function test_erp_sin_letra_ocupa_tambien_a_de_anita(): void
    {
        $claves = RetencionGananciasAcumuladoAnitaClaveSupport::clavesOcupacionDesdeErp(
            'OPP',
            ' ',
            1,
            79030365,
            1
        );
        $this->assertContains('OPP| |1|79030365|1', $claves);
        $this->assertContains('OPP|A|1|79030365|1', $claves);
    }

    public function test_erp_con_letra_a_no_ocupa_otra_letra(): void
    {
        $claves = RetencionGananciasAcumuladoAnitaClaveSupport::clavesOcupacionDesdeErp(
            'OPP',
            'A',
            1,
            79030365,
            1
        );
        $this->assertSame(['OPP|A|1|79030365|1'], $claves);
        $this->assertNotContains('OPP|B|1|79030365|1', $claves);
    }

    public function test_parsear_usa_gravado_y_clave_con_letra(): void
    {
        $parsed = RetencionGananciasAcumuladoAnitaClaveSupport::parsearFilaRetmov([
            'retv_tipo' => 'OPP',
            'retv_letra' => 'A',
            'retv_sucursal' => 1,
            'retv_nro' => 41561955,
            'retv_empresa' => 1,
            'retv_fecha' => '20260910',
            'retv_gravado' => 100000.50,
            'retv_pago_actual' => 121000.50,
            'retv_retencion' => 2000.00,
            'retv_nro_retencion' => 331001,
            'retv_codigo_ret' => 78,
        ]);

        $this->assertNotNull($parsed);
        $this->assertSame('OPP|A|1|41561955|1', $parsed['clave']);
        $this->assertEqualsWithDelta(100000.50, $parsed['neto'], 0.001);
        $this->assertEqualsWithDelta(2000.00, $parsed['retenido'], 0.001);
        $this->assertSame('2026-09-10', $parsed['fecha']);
    }

    public function test_aop_resta_neto_y_retenido(): void
    {
        $parsed = RetencionGananciasAcumuladoAnitaClaveSupport::parsearFilaRetmov([
            'retv_tipo' => 'AOP',
            'retv_letra' => 'A',
            'retv_sucursal' => 1,
            'retv_nro' => 10,
            'retv_empresa' => 1,
            'retv_fecha' => 20260908,
            'retv_gravado' => 50000,
            'retv_pago_actual' => 50000,
            'retv_retencion' => 1000,
            'retv_nro_retencion' => 1,
            'retv_codigo_ret' => 78,
        ]);

        $this->assertNotNull($parsed);
        $this->assertSame('AOP|A|1|10|1', $parsed['clave']);
        $this->assertEqualsWithDelta(-50000.0, $parsed['neto'], 0.001);
        $this->assertEqualsWithDelta(-1000.0, $parsed['retenido'], 0.001);
    }

    public function test_codigo_ret_coincide_con_padding(): void
    {
        $this->assertTrue(RetencionGananciasAcumuladoAnitaClaveSupport::codigoRetCoincide(78, '78'));
        $this->assertTrue(RetencionGananciasAcumuladoAnitaClaveSupport::codigoRetCoincide(78, '078'));
        $this->assertFalse(RetencionGananciasAcumuladoAnitaClaveSupport::codigoRetCoincide(78, '19'));
    }
}
