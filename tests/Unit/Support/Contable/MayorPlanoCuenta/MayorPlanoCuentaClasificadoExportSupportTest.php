<?php

namespace Tests\Unit\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaClasificadoExportSupport;
use PHPUnit\Framework\TestCase;

class MayorPlanoCuentaClasificadoExportSupportTest extends TestCase
{
    public function test_cabeceras_sin_cc_ni_multiempresa(): void
    {
        $cols = MayorPlanoCuentaClasificadoExportSupport::cabeceras([
            'mostrar_columna_centrocosto' => false,
            'empresa_ids' => [1],
            'consolidar_empresas' => true,
        ]);

        $this->assertSame('Fecha', $cols[0]);
        $this->assertSame('Debe', $cols[12]);
        $this->assertSame('Haber', $cols[13]);
        $this->assertSame('Saldo ejerc.', $cols[15]);
        $this->assertCount(16, $cols);
    }

    public function test_fila_header_cuenta_va_en_columna_a(): void
    {
        $filtros = [
            'mostrar_columna_centrocosto' => false,
            'empresa_ids' => [1],
            'consolidar_empresas' => true,
        ];
        $row = MayorPlanoCuentaClasificadoExportSupport::filaAColumnas([
            'tipo_fila' => 'header_cuenta',
            'cuenta_codigo' => '211010-001',
            'cuenta_nombre' => 'Proveedores',
        ], $filtros);

        $this->assertSame('Cuenta: 211010-001 Proveedores', $row[0]);
        $this->assertSame('', $row[12]);
        $this->assertCount(16, $row);
    }

    public function test_fila_total_cuenta_pone_debe_y_haber(): void
    {
        $filtros = [
            'mostrar_columna_centrocosto' => false,
            'empresa_ids' => [1],
            'consolidar_empresas' => true,
        ];
        $row = MayorPlanoCuentaClasificadoExportSupport::filaAColumnas([
            'tipo_fila' => 'total_cuenta',
            'cuenta_codigo' => '211010-001',
            'debe' => 100.5,
            'haber' => 40.25,
        ], $filtros);

        $this->assertSame('Total cuenta 211010-001', $row[0]);
        $this->assertSame(100.5, $row[12]);
        $this->assertSame(40.25, $row[13]);
    }

    public function test_fila_total_cuenta_incluye_nombre(): void
    {
        $filtros = [
            'mostrar_columna_centrocosto' => false,
            'empresa_ids' => [1],
            'consolidar_empresas' => true,
        ];
        $row = MayorPlanoCuentaClasificadoExportSupport::filaAColumnas([
            'tipo_fila' => 'total_cuenta',
            'cuenta_codigo' => '211010-001',
            'cuenta_nombre' => 'Proveedores',
            'debe' => 100.5,
            'haber' => 40.25,
        ], $filtros);

        $this->assertSame('Total cuenta 211010-001 — Proveedores', $row[0]);
        $this->assertSame(100.5, $row[12]);
        $this->assertSame(40.25, $row[13]);
    }

    public function test_fila_saldo_inicial_en_saldo_ejercicio(): void
    {
        $filtros = [
            'mostrar_columna_centrocosto' => false,
            'empresa_ids' => [1],
            'consolidar_empresas' => true,
        ];
        $row = MayorPlanoCuentaClasificadoExportSupport::filaAColumnas([
            'tipo_fila' => 'saldo_inicial',
            'saldo_ejercicio' => 1234.56,
        ], $filtros);

        $this->assertSame('Saldo Inicial', $row[0]);
        $this->assertSame(1234.56, $row[15]);
    }

    public function test_fila_detalle_mapea_columnas_analiticas(): void
    {
        $filtros = [
            'mostrar_columna_centrocosto' => true,
            'empresa_ids' => [1],
            'consolidar_empresas' => true,
        ];
        $row = MayorPlanoCuentaClasificadoExportSupport::filaAColumnas([
            'tipo_fila' => 'detalle',
            'fecha_fmt' => '01/09/2026',
            'nro_asiento_fmt' => 'A-100',
            'tipo_comp' => 'OP',
            'comprobante' => 'A0001-1',
            'emisor' => '123',
            'emisor_nombre' => 'ACME',
            'cuit' => '30-1',
            'descripcion' => 'Pago',
            'centrocosto_codigo' => '10',
            'centrocosto_nombre' => 'Adm',
            'nro_oc' => 55,
            'moneda_abrev' => 'ARS',
            'cotizacion' => 1,
            'mon_referencia' => 10,
            'debe' => 10,
            'haber' => 0,
            'saldo_mes' => 10,
            'saldo_ejercicio' => 20,
        ], $filtros);

        $this->assertSame('01/09/2026', $row[0]);
        $this->assertSame('A-100', $row[1]);
        $this->assertSame('10 Adm', $row[8]);
        $this->assertSame(55, $row[9]);
        $this->assertSame(10.0, $row[13]); // Debe with CC
        $this->assertSame(0.0, $row[14]);
        $this->assertCount(17, $row);
    }

    public function test_estilo_fila_excel_por_tipo(): void
    {
        $this->assertSame('cuenta', MayorPlanoCuentaClasificadoExportSupport::estiloFilaExcel(['tipo_fila' => 'header_cuenta']));
        $this->assertSame('total', MayorPlanoCuentaClasificadoExportSupport::estiloFilaExcel(['tipo_fila' => 'total_cuenta']));
        $this->assertSame('saldo', MayorPlanoCuentaClasificadoExportSupport::estiloFilaExcel(['tipo_fila' => 'saldo_inicial']));
        $this->assertNull(MayorPlanoCuentaClasificadoExportSupport::estiloFilaExcel(['tipo_fila' => 'detalle']));
    }
}
