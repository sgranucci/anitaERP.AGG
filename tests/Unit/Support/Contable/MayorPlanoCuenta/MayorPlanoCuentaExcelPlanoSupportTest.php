<?php

namespace Tests\Unit\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaExcelPlanoSupport;
use PHPUnit\Framework\TestCase;

class MayorPlanoCuentaExcelPlanoSupportTest extends TestCase
{
    public function test_solo_movimientos_descarta_cabeceras_y_totales(): void
    {
        $filas = MayorPlanoCuentaExcelPlanoSupport::soloMovimientos([
            ['tipo_fila' => 'header_cuenta', 'cuenta' => 1],
            ['tipo_fila' => 'saldo_inicial', 'cuenta' => 1],
            ['tipo_fila' => 'detalle', 'cuenta' => 1, 'nro_asiento' => 10],
            ['tipo_fila' => 'detalle', 'cuenta' => 2, 'nro_asiento' => 11],
            ['tipo_fila' => 'total_cuenta', 'cuenta' => 1],
        ]);

        $this->assertCount(2, $filas);
        $this->assertSame(10, $filas[0]['nro_asiento']);
        $this->assertSame(11, $filas[1]['nro_asiento']);
    }

    public function test_facturas_van_en_una_sola_celda_sin_duplicar(): void
    {
        $celda = MayorPlanoCuentaExcelPlanoSupport::concatenarEnUnaCelda([
            'FGA A 1-100',
            'FGA A 1-200',
            'FGA A 1-100',
            '',
            ' NCB B 1-9 ',
        ]);

        $this->assertSame('FGA A 1-100; FGA A 1-200; NCB B 1-9', $celda);
        $this->assertStringNotContainsString("\n", $celda);
    }

    public function test_observacion_oc_prioriza_comentario(): void
    {
        $this->assertSame('Alquiler planta', MayorPlanoCuentaExcelPlanoSupport::observacionOc(
            'Alquiler planta',
            'Detalle largo de la OC',
        ));
        $this->assertSame('Detalle largo de la OC', MayorPlanoCuentaExcelPlanoSupport::observacionOc(
            '  ',
            'Detalle largo de la OC',
        ));
        $this->assertSame('', MayorPlanoCuentaExcelPlanoSupport::observacionOc(null, null));
    }

    public function test_ordenar_por_cuenta_o_por_centro_de_costo(): void
    {
        $filas = [
            ['cuenta' => 200, 'centrocosto_codigo' => '10', 'fecha' => 20260302, 'nro_asiento' => 2],
            ['cuenta' => 100, 'centrocosto_codigo' => '20', 'fecha' => 20260301, 'nro_asiento' => 1],
            ['cuenta' => 100, 'centrocosto_codigo' => '10', 'fecha' => 20260303, 'nro_asiento' => 3],
        ];

        $porCuenta = MayorPlanoCuentaExcelPlanoSupport::ordenar($filas, 'cuenta');
        $this->assertSame([100, 100, 200], array_column($porCuenta, 'cuenta'));
        $this->assertSame(['20', '10', '10'], array_column($porCuenta, 'centrocosto_codigo'));

        $porCc = MayorPlanoCuentaExcelPlanoSupport::ordenar($filas, 'centrocosto');
        $this->assertSame(['10', '10', '20'], array_column($porCc, 'centrocosto_codigo'));
        $this->assertSame([200, 100, 100], array_column($porCc, 'cuenta'));
    }

    public function test_dimension_orden_por_cc_si_agrupa_o_solo_filtra_cc(): void
    {
        $this->assertSame('centrocosto', MayorPlanoCuentaExcelPlanoSupport::dimensionOrden([
            'agrupar_por_cc' => true,
            'cuentas' => [100],
        ]));
        $this->assertSame('centrocosto', MayorPlanoCuentaExcelPlanoSupport::dimensionOrden([
            'centrocostos_codigo' => '10,20',
        ]));
        $this->assertSame('cuenta', MayorPlanoCuentaExcelPlanoSupport::dimensionOrden([
            'cuentas' => [100],
        ]));
    }

    public function test_formatear_numero_factura(): void
    {
        $this->assertSame('FGA A 1-946427', MayorPlanoCuentaExcelPlanoSupport::formatearNumeroFactura(
            'fga',
            'A',
            1,
            946427,
        ));
    }

    public function test_com_no_es_factura(): void
    {
        $this->assertFalse(MayorPlanoCuentaExcelPlanoSupport::esTipoFacturaCompra('COM'));
        $this->assertSame('', MayorPlanoCuentaExcelPlanoSupport::formatearNumeroFactura('COM', 'X', 0, 1234));
        $this->assertSame('', MayorPlanoCuentaExcelPlanoSupport::etiquetaFacturaDesdeMovimiento([
            'tipo_comp' => 'COM',
            'comprobante' => 'X0000-00001234',
        ]));
        $this->assertSame('FGA A 1-100', MayorPlanoCuentaExcelPlanoSupport::concatenarEnUnaCelda([
            'COM X 0-1234',
            'FGA A 1-100',
        ]));
    }
}
