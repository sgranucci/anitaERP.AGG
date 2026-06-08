<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosPreviewSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use Tests\TestCase;

final class CierreJornadaProcesoAsientosPreviewCompletoTest extends TestCase
{
    private function configBase(): array
    {
        return [
            'cuenta_ventas_id' => 10,
            'cuenta_iva_id' => 20,
            'cuenta_ventas_kiosco_id' => 30,
            'cuenta_diferencia_caja_id' => 40,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosAsientoAnitaMock(): array
    {
        return [
            'total' => 252.0,
            'cantidad_emisiones' => 2,
            'cantidad_notas_credito' => 0,
            'impuesto_interno_total' => 10.0,
            'facturas_con_impuesto_interno' => 1,
            'ventas_gravadas' => 100.0,
            'ventas_kiosco' => 110.0,
            'iva_normal' => 21.0,
            'iva_cigarrillos' => 21.0,
            'debe_por_cuenta' => [
                501 => [
                    'concepto' => 'Medio de cobro — QR Test',
                    'cuenta_id' => 501,
                    'debe' => 121.0,
                ],
                502 => [
                    'concepto' => 'Medio de cobro — Efectivo Test',
                    'cuenta_id' => 502,
                    'debe' => 131.0,
                ],
            ],
            'debe_diferencia_caja' => 0.,
            'cantidad_invitaciones' => 0,
            'advertencias' => [],
        ];
    }

    public function test_asiento_dos_imputa_invitaciones_en_cuenta_diferencia_caja(): void
    {
        $preview = CierreJornadaProcesoAsientosPreviewSupport::generarPreviewCompletoProceso(
            [],
            1,
            $this->configBase(),
            [
                'datos_asiento_anita' => array_merge($this->datosAsientoAnitaMock(), [
                    'debe_diferencia_caja' => 0.72,
                    'cantidad_invitaciones' => 72,
                ]),
            ],
        );

        $asientoDos = null;
        foreach ($preview['asientos'] as $a) {
            if (($a['codigo'] ?? '') === 'ventas_medio_real') {
                $asientoDos = $a;
                break;
            }
        }

        $this->assertNotNull($asientoDos);
        $conceptos = array_column($asientoDos['lineas'], 'concepto');
        $this->assertContains('Diferencia de caja — invitaciones ($0,01)', $conceptos);
        $this->assertSame(72, $asientoDos['cantidad_invitaciones']);
    }

    public function test_asiento_dos_consolida_facturado_anita_jornada_con_kiosco_e_iva_cigarrillos(): void
    {
        $preview = CierreJornadaProcesoAsientosPreviewSupport::generarPreviewCompletoProceso(
            [],
            1,
            $this->configBase(),
            ['datos_asiento_anita' => $this->datosAsientoAnitaMock()],
        );

        $asientoDos = null;
        foreach ($preview['asientos'] as $a) {
            if (($a['codigo'] ?? '') === 'ventas_medio_real') {
                $asientoDos = $a;
                break;
            }
        }

        $this->assertNotNull($asientoDos);
        $this->assertSame(2, $asientoDos['cantidad_facturas']);
        $this->assertSame(252.0, $asientoDos['total']);
        $this->assertSame(10.0, $asientoDos['impuesto_interno_total']);
        $this->assertSame(1, $asientoDos['facturas_con_impuesto_interno']);

        $conceptos = array_column($asientoDos['lineas'], 'concepto');
        $this->assertContains('Ventas gravadas', $conceptos);
        $this->assertContains('Ventas kiosco (gravado + imp. interno)', $conceptos);
        $this->assertContains('IVA débito fiscal', $conceptos);
        $this->assertContains('IVA débito fiscal — cigarrillos / kiosco (imp. interno)', $conceptos);

        $haberVentasKiosco = 0.;
        $cuentaIdVentasKiosco = 0;
        $haberIvaNormal = 0.;
        $haberIvaCigarrillos = 0.;
        foreach ($asientoDos['lineas'] as $ln) {
            if (($ln['concepto'] ?? '') === 'Ventas kiosco (gravado + imp. interno)') {
                $haberVentasKiosco = (float) $ln['haber'];
                $cuentaIdVentasKiosco = (int) ($ln['cuenta_id'] ?? 0);
            }
            if (($ln['concepto'] ?? '') === 'IVA débito fiscal') {
                $haberIvaNormal = (float) $ln['haber'];
            }
            if (($ln['concepto'] ?? '') === 'IVA débito fiscal — cigarrillos / kiosco (imp. interno)') {
                $haberIvaCigarrillos = (float) $ln['haber'];
            }
        }
        $this->assertSame(110.0, $haberVentasKiosco);
        $this->assertSame(30, $cuentaIdVentasKiosco);
        $this->assertSame(21.0, $haberIvaNormal);
        $this->assertSame(21.0, $haberIvaCigarrillos);
        $this->assertSame($asientoDos['resumen_debe'], $asientoDos['resumen_haber']);
    }

    public function test_preview_completo_incluye_cuatro_tipos_de_asiento(): void
    {
        $movimientos = [
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'total' => 50.0,
                'impuesto_interno' => 0.,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 50.0],
                ],
            ],
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_TOTEM,
                'total' => 121.0,
                'impuesto_interno' => 0.,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 121.0],
                ],
            ],
        ];

        $datosAnita = $this->datosAsientoAnitaMock();
        $datosAnita['total'] = 121.0;
        $datosAnita['cantidad_emisiones'] = 1;
        $datosAnita['ventas_gravadas'] = 100.0;
        $datosAnita['ventas_kiosco'] = 0.;
        $datosAnita['iva_normal'] = 21.0;
        $datosAnita['iva_cigarrillos'] = 0.;
        $datosAnita['impuesto_interno_total'] = 0.;
        $datosAnita['facturas_con_impuesto_interno'] = 0;
        $datosAnita['debe_por_cuenta'] = [
            501 => ['concepto' => 'Medio de cobro — QR', 'cuenta_id' => 501, 'debe' => 121.0],
        ];

        $preview = CierreJornadaProcesoAsientosPreviewSupport::generarPreviewCompletoProceso(
            $movimientos,
            1,
            $this->configBase(),
            ['datos_asiento_anita' => $datosAnita],
        );

        $codigos = array_column($preview['asientos'], 'codigo');
        $this->assertSame(
            ['sin_facturar_qr', 'ventas_medio_real', 'totem_ventas_iva', 'totem_puente'],
            $codigos,
        );

        $cuadre = $preview['cuadre'] ?? [];
        $this->assertNotEmpty($cuadre['filas'] ?? []);
        $refs = $cuadre['referencias_cuadro'] ?? [];
        $this->assertSame(50.0, $refs['total_a_facturar_qr_proceso'] ?? 0);
        $this->assertSame(121.0, $refs['total_anita_jornada_asiento'] ?? 0);
        $this->assertSame(121.0, $refs['total_facturado_totem'] ?? 0);
    }

    public function test_cuadre_asiento_dos_contra_total_anita_jornada(): void
    {
        $datosAnita = $this->datosAsientoAnitaMock();
        $datosAnita['total'] = 242.0;
        $datosAnita['debe_por_cuenta'] = [
            501 => ['concepto' => 'Medio de cobro — QR', 'cuenta_id' => 501, 'debe' => 242.0],
        ];
        $datosAnita['ventas_gravadas'] = 200.0;
        $datosAnita['ventas_kiosco'] = 0.;
        $datosAnita['iva_normal'] = 42.0;
        $datosAnita['iva_cigarrillos'] = 0.;

        $preview = CierreJornadaProcesoAsientosPreviewSupport::generarPreviewCompletoProceso(
            [],
            1,
            ['cuenta_ventas_id' => 10, 'cuenta_iva_id' => 20],
            [
                'total_facturacion' => 5000.0,
                'total_pendiente_facturar' => 1000.0,
                'total_anita_jornada_cuadro' => 242.0,
                'datos_asiento_anita' => $datosAnita,
            ],
        );

        $filaDos = null;
        foreach ($preview['cuadre']['filas'] ?? [] as $f) {
            if (($f['asiento_codigo'] ?? '') === 'ventas_medio_real') {
                $filaDos = $f;
                break;
            }
        }
        $this->assertNotNull($filaDos);
        $this->assertSame(242.0, $filaDos['total_asiento']);
        $this->assertSame(242.0, $filaDos['referencia_total']);
        $this->assertTrue($filaDos['referencia_cuadra']);
        $this->assertTrue($filaDos['debe_haber_cuadra']);
    }

    public function test_total_efectivo_no_facturado_solo_comandas_100_porciento_efectivo(): void
    {
        $movimientos = [
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'total' => 50.0,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 50.0],
                ],
            ],
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'total' => 1000.0,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 50.0],
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP, 'monto' => 950.0],
                ],
            ],
        ];

        $this->assertSame(50.0, CierreJornadaProcesoAsientosPreviewSupport::totalEfectivoNoFacturadoProceso($movimientos));
    }

    public function test_preview_sin_totem_incluye_asiento_compensacion_solo_comandas_efectivo_puro(): void
    {
        $movimientos = [
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'total' => 1000.0,
                'impuesto_interno' => 0.,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 950.0],
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 50.0],
                ],
            ],
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'total' => 80.0,
                'impuesto_interno' => 0.,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 80.0],
                ],
            ],
        ];

        $preview = CierreJornadaProcesoAsientosPreviewSupport::generarPreviewCompletoProceso(
            $movimientos,
            1,
            array_merge($this->configBase(), [
                'cuenta_fondo_fijo_maquinas_id' => 60,
                'cuenta_fondo_fijo_maquinas_codigo' => 'FFM',
                'cuenta_fondo_fijo_maquinas_nombre' => 'Fondo fijo máquinas',
            ]),
            ['datos_asiento_anita' => $this->datosAsientoAnitaMock()],
        );

        $codigos = array_column($preview['asientos'], 'codigo');
        $this->assertContains('compensacion_efectivo_no_facturado', $codigos);

        $comp = null;
        foreach ($preview['asientos'] as $asiento) {
            if (($asiento['codigo'] ?? '') === 'compensacion_efectivo_no_facturado') {
                $comp = $asiento;
                break;
            }
        }
        $this->assertNotNull($comp);
        $this->assertSame(80.0, (float) ($comp['total'] ?? 0));
        $this->assertSame(
            CierreJornadaProcesoAsientosPreviewSupport::COMANDAS_ALCANCE_EFECTIVO_NO_FACTURADO,
            $comp['comandas_alcance'] ?? null,
        );
    }

    public function test_movimientos_compensacion_efectivo_solo_comandas_100_porciento_efectivo(): void
    {
        $movimientos = [
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'total' => 1000.0,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 950.0],
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 50.0],
                ],
            ],
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'total' => 1000.0,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 1000.0],
                ],
            ],
        ];

        $this->assertCount(0, CierreJornadaProcesoAsientosPreviewSupport::movimientosCompensacionEfectivoNoFacturado($movimientos));
    }

    public function test_enriquecer_asientos_agrega_codigo_y_nombre_en_lineas(): void
    {
        $config = array_merge($this->configBase(), [
            'cuenta_fondo_fijo_maquinas_id' => 99,
            'cuenta_fondo_fijo_maquinas_codigo' => '111020005',
            'cuenta_fondo_fijo_maquinas_nombre' => 'FONDO FIJO SALA MAQUINAS $',
        ]);
        $asientos = [[
            'lineas' => [
                ['cuenta_id' => 99, 'concepto' => 'Compensación', 'haber' => 100.0],
            ],
        ]];

        $out = CierreJornadaProcesoAsientosPreviewSupport::enriquecerAsientosConEtiquetas($asientos, 1, $config);

        $this->assertSame('111020005', $out[0]['lineas'][0]['cuenta_codigo'] ?? null);
        $this->assertSame('FONDO FIJO SALA MAQUINAS $', $out[0]['lineas'][0]['cuenta_nombre'] ?? null);
    }
}
