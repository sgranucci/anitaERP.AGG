<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorAccionLineaOc;
use App\Support\Stock\RecepcionProveedorDiferenciaSupport;
use PHPUnit\Framework\TestCase;

class RecepcionProveedorAccionLineaOcTest extends TestCase
{
    public function test_resolver_recepcion_parcial_sin_cierre_es_recibir(): void
    {
        $item = [
            'tipo_linea' => RecepcionProveedorDiferenciaSupport::TIPO_OC,
            'cantidad_oc' => 100,
            'cantidad' => 40,
            'cantidad_rechazada' => 0,
            'fl_cerrar_linea_oc' => false,
        ];

        $this->assertSame(RecepcionProveedorAccionLineaOc::RECIBIR, RecepcionProveedorAccionLineaOc::resolver($item));
        $this->assertTrue(RecepcionProveedorAccionLineaOc::esRecepcionParcialConSaldoPendiente($item));
    }

    public function test_segunda_recepcion_completa_saldo_no_es_parcial(): void
    {
        $item = [
            'tipo_linea' => RecepcionProveedorDiferenciaSupport::TIPO_OC,
            'cantidad_oc' => 500,
            'cantidad_recibida' => 250,
            'cantidad' => 250,
            'cantidad_rechazada' => 0,
            'fl_cerrar_linea_oc' => false,
        ];

        $this->assertFalse(RecepcionProveedorAccionLineaOc::esRecepcionParcialConSaldoPendiente($item));
    }

    public function test_segunda_recepcion_parcial_del_pendiente(): void
    {
        $item = [
            'tipo_linea' => RecepcionProveedorDiferenciaSupport::TIPO_OC,
            'cantidad_oc' => 500,
            'cantidad_recibida' => 250,
            'cantidad' => 100,
            'cantidad_rechazada' => 0,
            'fl_cerrar_linea_oc' => false,
        ];

        $this->assertTrue(RecepcionProveedorAccionLineaOc::esRecepcionParcialConSaldoPendiente($item));
    }

    public function test_resolver_recepcion_parcial_con_cierre_es_cerrar(): void
    {
        $item = [
            'tipo_linea' => RecepcionProveedorDiferenciaSupport::TIPO_OC,
            'cantidad_oc' => 100,
            'cantidad' => 40,
            'cantidad_rechazada' => 0,
            'fl_cerrar_linea_oc' => true,
        ];

        $this->assertSame(RecepcionProveedorAccionLineaOc::CERRAR, RecepcionProveedorAccionLineaOc::resolver($item));
        $this->assertFalse(RecepcionProveedorAccionLineaOc::esRecepcionParcialConSaldoPendiente($item));
    }

    public function test_resolver_linea_sin_cantidad_explicita_pendiente(): void
    {
        $item = [
            'tipo_linea' => RecepcionProveedorDiferenciaSupport::TIPO_OC,
            'cantidad_oc' => 100,
            'cantidad' => 0,
            'accion_linea_oc' => RecepcionProveedorAccionLineaOc::PENDIENTE,
        ];

        $this->assertSame(RecepcionProveedorAccionLineaOc::PENDIENTE, RecepcionProveedorAccionLineaOc::resolver($item));
        $this->assertFalse(RecepcionProveedorAccionLineaOc::requiereDefinicionEnGuardado($item));
    }

    public function test_linea_sin_cantidad_queda_pendiente_sin_exigir_cierre(): void
    {
        $item = [
            'tipo_linea' => RecepcionProveedorDiferenciaSupport::TIPO_OC,
            'cantidad_oc' => 2,
            'cantidad' => 0,
            'cantidad_rechazada' => 0,
        ];

        $this->assertSame(RecepcionProveedorAccionLineaOc::PENDIENTE, RecepcionProveedorAccionLineaOc::resolver($item));
        $this->assertFalse(RecepcionProveedorAccionLineaOc::requiereDefinicionEnGuardado($item));
        $this->assertTrue(RecepcionProveedorAccionLineaOc::esPendiente($item));
    }
}
