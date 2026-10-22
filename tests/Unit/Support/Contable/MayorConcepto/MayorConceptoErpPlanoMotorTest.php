<?php

namespace Tests\Unit\Support\Contable\MayorConcepto;

use App\Support\Contable\MayorConcepto\MayorConceptoErpPlanoMotor;
use PHPUnit\Framework\TestCase;

class MayorConceptoErpPlanoMotorTest extends TestCase
{
    private const LIMITE = 112010008;

    public function test_la_pata_hasta_el_limite_conserva_importe_y_lado(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $clasificado = $motor->clasificar([
            $this->asiento(10, 500, [
                $this->mov(111010001, 'Caja', 4, 'VENTAS', 1000, false),
                $this->mov(411010001, 'Ventas salon', 4, 'VENTAS', 1000, true),
            ]),
        ], [], self::LIMITE);

        $this->assertCount(1, $clasificado['lineas']);
        $linea = $clasificado['lineas'][0];
        $this->assertSame(411010001, $linea['cuenta']);
        $this->assertSame(4, $linea['concepto_id']);
        $this->assertSame(0.0, $linea['debe']);
        $this->assertSame(1000.0, $linea['haber']);
        $this->assertTrue($motor->auditar($clasificado['analitico'], $clasificado['lineas'])['cuadra']);
    }

    public function test_proveedores_reparte_el_importe_de_la_pata_entre_el_gasto(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $clasificado = $motor->clasificar([
            $this->asiento(20, 800, [
                $this->mov(112010001, 'Banco', 0, 'SIN CLASIFICAR', 1000, false),
                $this->mov(211010004, 'Proveedores', 9, 'PROVEEDORES', 1000, true),
            ]),
        ], [
            20 => [
                $this->mov(521180011, 'Mantenimiento', 24, 'MANTENIMIENTO', 0, true, 70),
                $this->mov(521180012, 'Limpieza', 25, 'LIMPIEZA', 0, true, 30),
            ],
        ], self::LIMITE);

        $this->assertCount(2, $clasificado['lineas']);
        $haber = 0.0;
        $cuentas = [];
        foreach ($clasificado['lineas'] as $ln) {
            $this->assertSame(0.0, $ln['debe']);
            $haber += $ln['haber'];
            $cuentas[] = $ln['cuenta'];
        }
        $this->assertEqualsWithDelta(1000.0, $haber, 0.001);
        $this->assertEqualsCanonicalizing([521180011, 521180012], $cuentas);
        $this->assertTrue($motor->auditar($clasificado['analitico'], $clasificado['lineas'])['cuadra']);
    }

    public function test_proveedores_y_otra_contrapartida_suman_la_pata(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $clasificado = $motor->clasificar([
            $this->asiento(21, 801, [
                $this->mov(112010001, 'Banco', 0, 'SIN CLASIFICAR', 1000, false),
                $this->mov(211010004, 'Proveedores', 9, 'PROVEEDORES', 800, true),
                $this->mov(214010004, 'Retencion', 12, 'RETENCIONES', 200, true),
            ]),
        ], [
            21 => [
                $this->mov(521180011, 'Mantenimiento', 24, 'MANTENIMIENTO', 0, true, 1),
            ],
        ], self::LIMITE);

        $haber = 0.0;
        $porCuenta = [];
        foreach ($clasificado['lineas'] as $ln) {
            $haber += $ln['haber'];
            $porCuenta[$ln['cuenta']] = $ln['haber'];
        }
        $this->assertEqualsWithDelta(1000.0, $haber, 0.001);
        $this->assertEqualsWithDelta(800.0, $porCuenta[521180011], 0.001);
        $this->assertEqualsWithDelta(200.0, $porCuenta[214010004], 0.001);
        $this->assertTrue($motor->auditar($clasificado['analitico'], $clasificado['lineas'])['cuadra']);
    }

    public function test_varias_patas_del_mismo_lado_no_duplican_el_asiento(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $clasificado = $motor->clasificar([
            $this->asiento(30, 900, [
                $this->mov(111010001, 'Caja', 1, 'CAJA', 100, true),
                $this->mov(111010002, 'Caja 2', 1, 'CAJA', 50, true),
                $this->mov(411010001, 'Ventas', 4, 'VENTAS', 150, false),
            ]),
        ], [], self::LIMITE);

        $debe = 0.0;
        foreach ($clasificado['lineas'] as $ln) {
            $this->assertSame(411010001, $ln['cuenta']);
            $this->assertSame(0.0, $ln['haber']);
            $debe += $ln['debe'];
        }
        $this->assertEqualsWithDelta(150.0, $debe, 0.001);
        $this->assertTrue($motor->auditar($clasificado['analitico'], $clasificado['lineas'])['cuadra']);
    }

    public function test_traspaso_entre_cuentas_del_limite_conserva_las_dos_patas(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $clasificado = $motor->clasificar([
            $this->asiento(40, 901, [
                $this->mov(111010001, 'Caja', 1, 'CAJA', 100, false),
                $this->mov(112010001, 'Banco', 2, 'BANCO', 100, true),
            ]),
        ], [], self::LIMITE);

        $this->assertCount(2, $clasificado['lineas']);
        $auditoria = $motor->auditar($clasificado['analitico'], $clasificado['lineas']);
        $this->assertTrue($auditoria['cuadra']);
        $this->assertSame(100.0, $auditoria['filas'][0]['debe_analitico']);
        $this->assertSame(100.0, $auditoria['filas'][0]['haber_analitico']);
        $this->assertSame(100.0, $auditoria['filas'][0]['debe_concepto']);
        $this->assertSame(100.0, $auditoria['filas'][0]['haber_concepto']);
    }

    public function test_sin_gasto_de_comprobante_la_pata_queda_en_proveedores(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $clasificado = $motor->clasificar([
            $this->asiento(50, 902, [
                $this->mov(112010001, 'Banco', 0, 'SIN CLASIFICAR', 400, false),
                $this->mov(211010004, 'Proveedores', 9, 'PROVEEDORES', 400, true),
            ]),
        ], [], self::LIMITE);

        $this->assertSame(211010004, $clasificado['lineas'][0]['cuenta']);
        $this->assertSame(9, $clasificado['lineas'][0]['concepto_id']);
        $this->assertSame(400.0, $clasificado['lineas'][0]['haber']);
    }

    public function test_el_reparto_en_tres_cierra_los_centavos(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $clasificado = $motor->clasificar([
            $this->asiento(60, 903, [
                $this->mov(112010001, 'Banco', 0, 'SIN CLASIFICAR', 100, false),
                $this->mov(211010004, 'Proveedores', 9, 'PROVEEDORES', 100, true),
            ]),
        ], [
            60 => [
                $this->mov(521180011, 'A', 1, 'A', 0, true, 1),
                $this->mov(521180012, 'B', 2, 'B', 0, true, 1),
                $this->mov(521180013, 'C', 3, 'C', 0, true, 1),
            ],
        ], self::LIMITE);

        $haber = 0.0;
        foreach ($clasificado['lineas'] as $ln) {
            $haber += $ln['haber'];
        }
        $this->assertEqualsWithDelta(100.0, round($haber, 2), 0.001);
        $this->assertTrue($motor->auditar($clasificado['analitico'], $clasificado['lineas'])['cuadra']);
    }

    public function test_una_cuenta_por_encima_del_limite_no_entra(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $clasificado = $motor->clasificar([
            $this->asiento(70, 904, [
                $this->mov(521180011, 'Gasto', 24, 'MANTENIMIENTO', 50, true),
                $this->mov(211010004, 'Proveedores', 9, 'PROVEEDORES', 50, false),
            ]),
        ], [], self::LIMITE);

        $this->assertSame([], $clasificado['lineas']);
        $this->assertSame([], $clasificado['analitico']);
    }

    public function test_el_auditor_marca_un_importe_distinto(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $clasificado = $motor->clasificar([
            $this->asiento(80, 905, [
                $this->mov(111010001, 'Caja', 1, 'CAJA', 80, true),
                $this->mov(411010001, 'Ventas', 4, 'VENTAS', 80, false),
            ]),
        ], [], self::LIMITE);
        $clasificado['lineas'][0]['debe'] = 70.0;

        $auditoria = $motor->auditar($clasificado['analitico'], $clasificado['lineas']);
        $this->assertFalse($auditoria['cuadra']);
        $this->assertSame(1, $auditoria['asientos_descuadrados']);
        $this->assertSame(10.0, $auditoria['filas_descuadradas'][0]['diferencia']);
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array<string, mixed>
     */
    private function asiento(int $id, int $nro, array $movimientos): array
    {
        return [
            'id' => $id,
            'empresa_id' => 1,
            'nro' => $nro,
            'fecha' => 20261015,
            'tipo' => 'OPP',
            'letra' => 'A',
            'sucursal' => 1,
            'nro_comp' => 10,
            'emisor' => '',
            'observacion' => '',
            'moneda_abrev' => 'PES',
            'movimientos' => $movimientos,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mov(
        int $cuenta,
        string $nombre,
        int $conceptoId,
        string $conceptoNombre,
        float $importe,
        bool $esDebe,
        float $peso = 0,
    ): array {
        $codigo = str_pad((string) $cuenta, 9, '0', STR_PAD_LEFT);

        return [
            'cuenta' => $cuenta,
            'cuenta_codigo' => substr($codigo, 0, 6).'-'.substr($codigo, 6, 3),
            'cuenta_nombre' => $nombre,
            'concepto_id' => $conceptoId,
            'concepto_nombre' => $conceptoNombre,
            'importe' => $importe,
            'es_debe' => $esDebe,
            'descripcion' => '',
            'nro_oc' => 0,
            'cotizacion' => 1,
            'peso' => $peso > 0 ? $peso : $importe,
        ];
    }
}
