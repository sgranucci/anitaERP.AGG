<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraTratamientoMovimientosSupport;
use PHPUnit\Framework\TestCase;

class OrdencompraTratamientoMovimientosSupportTest extends TestCase
{
    public function test_reconoce_las_variantes_de_anticipada(): void
    {
        $this->assertTrue(OrdencompraTratamientoMovimientosSupport::esTratamientoAnticipado('ANTICIPADA'));
        $this->assertTrue(OrdencompraTratamientoMovimientosSupport::esTratamientoAnticipado(' anticipada '));
        $this->assertTrue(OrdencompraTratamientoMovimientosSupport::esTratamientoAnticipado('2'));
        $this->assertTrue(OrdencompraTratamientoMovimientosSupport::esTratamientoAnticipado('S'));
    }

    public function test_no_anticipada_no_se_confunde_con_anticipada(): void
    {
        $this->assertFalse(OrdencompraTratamientoMovimientosSupport::esTratamientoAnticipado('NO ANTICIPADA'));
        $this->assertFalse(OrdencompraTratamientoMovimientosSupport::esTratamientoAnticipado('1'));
        $this->assertFalse(OrdencompraTratamientoMovimientosSupport::esTratamientoAnticipado(null));
    }

    public function test_misma_variante_de_tratamiento_no_es_un_cambio(): void
    {
        OrdencompraTratamientoMovimientosSupport::assertPuedeCambiarTratamiento(0, 'ANTICIPADA', 'S');
        OrdencompraTratamientoMovimientosSupport::assertPuedeCambiarTratamiento(0, 'NO ANTICIPADA', '1');

        $this->addToAssertionCount(2);
    }
}
