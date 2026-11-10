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

    public function test_opp_abre_el_importe_en_las_cuentas_de_gasto_y_prorratea_retenciones(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $clasificado = $motor->clasificar([
            $this->asiento(61, 906, [
                $this->mov(112010001, 'Banco', 0, 'SIN CLASIFICAR', 1000, false),
                $this->mov(211010004, 'Proveedores', 9, 'PROVEEDORES', 1000, true),
            ]),
        ], [
            61 => [
                $this->mov(521180011, 'Mantenimiento', 24, 'MANTENIMIENTO', 0, true, 70),
                $this->mov(521180012, 'Limpieza', 25, 'LIMPIEZA', 0, true, 30),
            ],
        ], self::LIMITE, [61 => 142.72]);

        $haber = 0.0;
        $retenciones = 0.0;
        $porCuenta = [];
        foreach ($clasificado['lineas'] as $ln) {
            $this->assertNotSame(211010004, $ln['cuenta']);
            $haber += $ln['haber'];
            $retenciones += $ln['retenciones'];
            $porCuenta[$ln['cuenta']] = [
                'haber' => $ln['haber'],
                'concepto' => $ln['concepto_id'],
                'retenciones' => $ln['retenciones'],
            ];
        }

        $this->assertEqualsWithDelta(1000.0, $haber, 0.001);
        $this->assertEqualsWithDelta(142.72, $retenciones, 0.001);
        $this->assertSame(24, $porCuenta[521180011]['concepto']);
        $this->assertSame(25, $porCuenta[521180012]['concepto']);
        $this->assertEqualsWithDelta(700.0, $porCuenta[521180011]['haber'], 0.001);
        $this->assertEqualsWithDelta(300.0, $porCuenta[521180012]['haber'], 0.001);
        $this->assertEqualsWithDelta(99.90, $porCuenta[521180011]['retenciones'], 0.001);
        $this->assertEqualsWithDelta(42.82, $porCuenta[521180012]['retenciones'], 0.001);
        $this->assertTrue($motor->auditar($clasificado['analitico'], $clasificado['lineas'])['cuadra']);
    }

    public function test_sin_gasto_la_retencion_queda_en_la_linea_del_proveedor(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $clasificado = $motor->clasificar([
            $this->asiento(62, 907, [
                $this->mov(112010001, 'Banco', 0, 'SIN CLASIFICAR', 400, false),
                $this->mov(211010004, 'Proveedores', 9, 'PROVEEDORES', 400, true),
            ]),
        ], [], self::LIMITE, [62 => 25]);

        $this->assertSame(211010004, $clasificado['lineas'][0]['cuenta']);
        $this->assertSame(25.0, $clasificado['lineas'][0]['retenciones']);
        $this->assertSame(400.0, $clasificado['lineas'][0]['haber']);
    }

    public function test_una_venta_no_arrastra_retenciones(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $clasificado = $motor->clasificar([
            $this->asiento(63, 908, [
                $this->mov(111010001, 'Caja', 4, 'VENTAS', 1000, false),
                $this->mov(411010001, 'Ventas salon', 4, 'VENTAS', 1000, true),
            ]),
        ], [], self::LIMITE, [63 => 80]);

        $this->assertSame(0.0, $clasificado['lineas'][0]['retenciones']);
        $this->assertArrayNotHasKey('lleva_retencion', $clasificado['lineas'][0]);
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

    public function test_venta_maquinas_con_flag_emite_cuentas_fuera_del_limite_lado_nativo(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $asiento = $this->asiento(237290, 237290, [
            $this->mov(111010001, 'CAJA PESOS', 53, 'MOVIMIENTOS COMPENSABLES', 501570.00, true),
            $this->mov(111010001, 'CAJA PESOS', 53, 'MOVIMIENTOS COMPENSABLES', 62000.00, true),
            $this->mov(111010004, 'CAJA PESOS EN TRANSITO', 0, 'SIN CLASIFICAR', 84975448.55, true),
            $this->mov(111050028, 'CTA CTE MERCADO PAGO', 0, 'SIN CLASIFICAR', 640000.00, true),
            $this->mov(113010011, 'TOTAL COIN MAQUINAS', 53, 'MOVIMIENTOS COMPENSABLES', 43063574.45, true),
            $this->mov(113010011, 'TOTAL COIN MAQUINAS', 53, 'MOVIMIENTOS COMPENSABLES', 62000.00, false),
            $this->mov(211010018, 'PARTID.PENDIENTES', 47, 'VENTA', 80814647.50, false),
            $this->mov(412010001, 'VENTAS MAQUINAS', 47, 'VENTA', 32318172.04, false),
            $this->mov(412020001, 'VENTAS RULETAS', 47, 'VENTA', 14121310.00, false),
            $this->mov(521120010, 'IMPUESTO ESPECIFICO', 49, 'IMP.ESPECIFICO', 1924300.52, false),
            $this->mov(521280004, 'DIFERENCIA DE CAJA', 12, 'GAMING SUPPLIES', 4720.00, true),
            $this->mov(521280004, 'DIFERENCIA DE CAJA', 12, 'GAMING SUPPLIES', 6882.94, false),
        ]);
        $asiento['observacion'] = 'Cierre rendición máquinas — 01/10/2026 — PV FSL 14 — rend. 638 — Venta maquinas';
        $asiento['tipo'] = '';

        $clasificado = $motor->clasificar([$asiento], [], self::LIMITE, [], true);
        $this->assertTrue($clasificado['analitico'][237290]['cuadre_neto']);
        $this->assertEqualsWithDelta(86179018.55, $clasificado['analitico'][237290]['debe'], 0.001);
        $this->assertEqualsWithDelta(0.0, $clasificado['analitico'][237290]['haber'], 0.001);

        $debe = 0.0;
        $haber = 0.0;
        $porCuenta = [];
        foreach ($clasificado['lineas'] as $ln) {
            $this->assertGreaterThan(self::LIMITE, $ln['cuenta']);
            $debe += $ln['debe'];
            $haber += $ln['haber'];
            $clave = $ln['cuenta'].'|'.($ln['debe'] > 0 ? 'D' : 'H');
            $porCuenta[$clave] = ($porCuenta[$clave] ?? 0) + $ln['debe'] + $ln['haber'];
        }

        $this->assertEqualsWithDelta(43068294.45, $debe, 0.001);
        $this->assertEqualsWithDelta(129247313.00, $haber, 0.001);
        $this->assertEqualsWithDelta(43063574.45, $porCuenta['113010011|D'], 0.001);
        $this->assertEqualsWithDelta(62000.00, $porCuenta['113010011|H'], 0.001);
        $this->assertEqualsWithDelta(32318172.04, $porCuenta['412010001|H'], 0.001);
        $this->assertEqualsWithDelta(14121310.00, $porCuenta['412020001|H'], 0.001);
        $this->assertEqualsWithDelta(80814647.50, $porCuenta['211010018|H'], 0.001);
        $this->assertEqualsWithDelta(1924300.52, $porCuenta['521120010|H'], 0.001);
        $this->assertEqualsWithDelta(4720.00, $porCuenta['521280004|D'], 0.001);
        $this->assertEqualsWithDelta(6882.94, $porCuenta['521280004|H'], 0.001);

        $auditoria = $motor->auditar($clasificado['analitico'], $clasificado['lineas']);
        $this->assertTrue($auditoria['cuadra']);
        $this->assertSame(1, $auditoria['asientos_cuadre_neto']);
        $this->assertEqualsWithDelta(0.0, $auditoria['filas'][0]['diferencia'], 0.001);
    }

    public function test_venta_maquinas_sin_flag_sigue_prorrateando_sobre_la_pata(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $asiento = $this->asiento(237291, 237291, [
            $this->mov(111010001, 'CAJA PESOS', 53, 'MOVIMIENTOS COMPENSABLES', 1000.00, true),
            $this->mov(113010011, 'TOTAL COIN', 53, 'MOVIMIENTOS COMPENSABLES', 400.00, true),
            $this->mov(412010001, 'VENTAS MAQUINAS', 47, 'VENTA', 1400.00, false),
        ]);
        $asiento['observacion'] = 'Cierre rendición máquinas — Venta maquinas';

        $clasificado = $motor->clasificar([$asiento], [], self::LIMITE, [], false);
        $this->assertFalse($clasificado['analitico'][237291]['cuadre_neto']);
        $this->assertCount(1, $clasificado['lineas']);
        $this->assertSame(412010001, $clasificado['lineas'][0]['cuenta']);
        $this->assertEqualsWithDelta(1000.0, $clasificado['lineas'][0]['debe'], 0.001);
        $this->assertEqualsWithDelta(0.0, $clasificado['lineas'][0]['haber'], 0.001);
        $this->assertTrue($motor->auditar($clasificado['analitico'], $clasificado['lineas'])['cuadra']);
    }

    public function test_bingo_pago_de_premios_emite_premios_mismo_lado_que_caja(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $asiento = $this->asiento(232084, 232084, [
            $this->mov(111010001, 'CAJA PESOS', 0, 'SIN CLASIFICAR', 500000.00, true),
            $this->mov(521050001, 'PREMIOS 53 PORC.', 48, 'PREMIOS', 2997150.00, true),
            $this->mov(211010006, 'POZO BINGO A PAGAR', 48, 'PREMIOS', 471681.14, true),
            $this->mov(521280004, 'DIFERENCIA DE CAJA', 12, 'GAMING SUPPLIES', 12000.00, false),
            $this->mov(411010001, 'VENTAS SALA', 47, 'VENTA', 3956831.14, false),
        ]);
        $asiento['tipo'] = 'BIN';
        $asiento['observacion'] = 'Pago de premios';

        $this->assertTrue($motor->esAsientoBingo($asiento));

        $clasificado = $motor->clasificar([$asiento], [], self::LIMITE);
        $this->assertTrue($clasificado['analitico'][232084]['cuadre_neto']);
        $this->assertCount(4, $clasificado['lineas']);

        $porCuenta = [];
        foreach ($clasificado['lineas'] as $ln) {
            $this->assertGreaterThan(self::LIMITE, $ln['cuenta']);
            $this->assertSame('Cuenta del cierre bingo (lado nativo)', $ln['origen']);
            $clave = $ln['cuenta'].'|'.($ln['debe'] > 0 ? 'D' : 'H');
            $porCuenta[$clave] = [
                'importe' => $ln['debe'] + $ln['haber'],
                'concepto' => $ln['concepto_id'],
            ];
        }

        $this->assertEqualsWithDelta(2997150.00, $porCuenta['521050001|D']['importe'], 0.001);
        $this->assertSame(48, $porCuenta['521050001|D']['concepto']);
        $this->assertEqualsWithDelta(471681.14, $porCuenta['211010006|D']['importe'], 0.001);
        $this->assertSame(48, $porCuenta['211010006|D']['concepto']);
        $this->assertEqualsWithDelta(12000.00, $porCuenta['521280004|H']['importe'], 0.001);
        $this->assertSame(12, $porCuenta['521280004|H']['concepto']);
        $this->assertEqualsWithDelta(3956831.14, $porCuenta['411010001|H']['importe'], 0.001);
        $this->assertSame(47, $porCuenta['411010001|H']['concepto']);

        $auditoria = $motor->auditar($clasificado['analitico'], $clasificado['lineas']);
        $this->assertTrue($auditoria['cuadra']);
        $this->assertSame(1, $auditoria['asientos_cuadre_neto']);
    }

    public function test_sin_marcadores_bingo_no_emite_premios_del_mismo_lado(): void
    {
        $motor = new MayorConceptoErpPlanoMotor;
        $asiento = $this->asiento(232085, 232085, [
            $this->mov(111010001, 'CAJA PESOS', 0, 'SIN CLASIFICAR', 500000.00, true),
            $this->mov(521050001, 'PREMIOS 53 PORC.', 48, 'PREMIOS', 2997150.00, true),
            $this->mov(211010006, 'POZO BINGO A PAGAR', 48, 'PREMIOS', 471681.14, true),
            $this->mov(521280004, 'DIFERENCIA DE CAJA', 12, 'GAMING SUPPLIES', 12000.00, false),
            $this->mov(411010001, 'VENTAS SALA', 47, 'VENTA', 3956831.14, false),
        ]);
        $asiento['tipo'] = 'ASI';
        $asiento['observacion'] = 'Movimiento genérico';

        $this->assertFalse($motor->esAsientoBingo($asiento));

        $clasificado = $motor->clasificar([$asiento], [], self::LIMITE);
        $this->assertFalse($clasificado['analitico'][232085]['cuadre_neto']);

        $cuentas = array_column($clasificado['lineas'], 'cuenta');
        $this->assertNotContains(521050001, $cuentas);
        $this->assertNotContains(211010006, $cuentas);
        $this->assertContains(411010001, $cuentas);
        $this->assertContains(521280004, $cuentas);
        $this->assertTrue($motor->auditar($clasificado['analitico'], $clasificado['lineas'])['cuadra']);
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
