<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorAccionLineaOc;
use App\Support\Stock\RecepcionProveedorDiferenciaSupport;
use PHPUnit\Framework\TestCase;

class RecepcionProveedorDiferenciaCantidadReportableTest extends TestCase
{
    private const TOL_CERO = [
        'cantidad_pct' => 0.0,
        'precio_pct' => 0.0,
        'precio_abs' => 0.0,
    ];

    public function test_recepcion_parcial_con_saldo_no_es_diferencia(): void
    {
        $item = [
            'tipo_linea' => RecepcionProveedorDiferenciaSupport::TIPO_OC,
            'cantidad_oc' => 12,
            'cantidad_recibida' => 3,
            'cantidad' => 1,
            'cantidad_rechazada' => 0,
            'accion_linea_oc' => RecepcionProveedorAccionLineaOc::RECIBIR,
            'fl_cerrar_linea_oc' => false,
        ];

        $this->assertTrue(RecepcionProveedorAccionLineaOc::esRecepcionParcialConSaldoPendiente($item));
        $this->assertFalse(
            RecepcionProveedorDiferenciaSupport::esDiferenciaCantidadReportable($item, false, self::TOL_CERO)
        );
    }

    public function test_exceso_sobre_pedido_es_diferencia(): void
    {
        $item = [
            'tipo_linea' => RecepcionProveedorDiferenciaSupport::TIPO_OC,
            'cantidad_oc' => 12,
            'cantidad_recibida' => 3,
            'cantidad' => 10,
            'cantidad_rechazada' => 0,
            'accion_linea_oc' => RecepcionProveedorAccionLineaOc::RECIBIR,
            'fl_cerrar_linea_oc' => false,
        ];

        $this->assertFalse(RecepcionProveedorAccionLineaOc::esRecepcionParcialConSaldoPendiente($item));
        $this->assertTrue(
            RecepcionProveedorDiferenciaSupport::esDiferenciaCantidadReportable($item, false, self::TOL_CERO)
        );
    }

    public function test_recepcion_que_completa_el_pedido_no_es_diferencia(): void
    {
        $item = [
            'tipo_linea' => RecepcionProveedorDiferenciaSupport::TIPO_OC,
            'cantidad_oc' => 12,
            'cantidad_recibida' => 3,
            'cantidad' => 9,
            'cantidad_rechazada' => 0,
            'accion_linea_oc' => RecepcionProveedorAccionLineaOc::RECIBIR,
            'fl_cerrar_linea_oc' => false,
        ];

        $this->assertFalse(
            RecepcionProveedorDiferenciaSupport::esDiferenciaCantidadReportable($item, false, self::TOL_CERO)
        );
    }
}
