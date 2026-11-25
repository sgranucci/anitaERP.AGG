<?php

namespace Tests\Unit\Sueldos;

use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Sueldos\SueldosAsientoExportSupport;
use App\Support\Sueldos\SueldosAsientoModoSupport;
use PHPUnit\Framework\TestCase;

class SueldosAsientoExportSupportTest extends TestCase
{
    public function test_aplanar_filas_marca_preview(): void
    {
        $filas = SueldosAsientoExportSupport::aplanarFilas([
            [
                'etiqueta' => 'CC 10',
                'numeroasiento' => null,
                'lineas' => [
                    [
                        'cuenta_codigo' => '511001',
                        'cuenta_nombre' => 'Sueldos',
                        'centrocosto_codigo' => '10',
                        'centrocosto_nombre' => 'Admin',
                        'debe' => 100.5,
                        'haber' => 0,
                        'observacion' => '',
                    ],
                    [
                        'cuenta_codigo' => '211001',
                        'cuenta_nombre' => 'A pagar',
                        'centrocosto_codigo' => '',
                        'centrocosto_nombre' => '',
                        'debe' => 0,
                        'haber' => 100.5,
                        'observacion' => '',
                    ],
                ],
                'total_debe' => 100.5,
                'total_haber' => 100.5,
            ],
        ], true);

        $this->assertCount(2, $filas);
        $this->assertSame('PREVIEW', $filas[0]['numeroasiento']);
        $this->assertSame('CC 10', $filas[0]['grupo']);
        $this->assertSame('511001', $filas[0]['cuenta_codigo']);
        $this->assertSame(100.5, $filas[0]['debe']);
        $this->assertSame('10 Admin', $filas[0]['centrocosto']);
        $this->assertSame(100.5, $filas[1]['haber']);
    }

    public function test_aplanar_filas_usa_numero_real(): void
    {
        $filas = SueldosAsientoExportSupport::aplanarFilas([
            [
                'etiqueta' => null,
                'numeroasiento' => '370701',
                'lineas' => [
                    [
                        'cuenta_codigo' => '511001',
                        'cuenta_nombre' => 'Sueldos',
                        'centrocosto_codigo' => '',
                        'centrocosto_nombre' => '',
                        'debe' => 50,
                        'haber' => 0,
                        'observacion' => '',
                    ],
                ],
                'total_debe' => 50,
                'total_haber' => 0,
            ],
        ], false);

        $this->assertSame('370701', $filas[0]['numeroasiento']);
    }

    public function test_desde_preview_sin_numero(): void
    {
        $liq = new Liquidacion_Sueldos();
        $liq->id = 9;
        $liq->numero = 42;
        $liq->empresa_id = 1;
        $liq->descripcion = 'Mensual';
        $liq->periodo = '2026-11';
        $liq->periodo_mes = 11;
        $liq->periodo_anio = 2026;
        $liq->estado = 'cerrada';
        $liq->setRelation('empresa', (object) ['id' => 1, 'nombre' => 'Demo SA']);
        $liq->setRelation('asientosSueldos', collect());
        $liq->asiento_id = null;

        $preview = [
            'modo' => SueldosAsientoModoSupport::ERP,
            'lineas' => [
                [
                    'cuenta_codigo' => '511001',
                    'cuenta_nombre' => 'Sueldos',
                    'centrocosto_codigo' => '1',
                    'centrocosto_nombre' => 'Gral',
                    'debe' => 200,
                    'haber' => 0,
                    'observacion' => 'x',
                ],
                [
                    'cuenta_codigo' => '211001',
                    'cuenta_nombre' => 'A pagar',
                    'centrocosto_codigo' => '',
                    'centrocosto_nombre' => '',
                    'debe' => 0,
                    'haber' => 200,
                    'observacion' => '',
                ],
            ],
            'grupos' => [],
            'total_debe' => 200,
            'total_haber' => 200,
            'haber_a_pagar' => 200,
            'total_neto_cabecera' => 200,
            'errores' => [],
            'advertencias' => ['aviso'],
        ];

        $payload = SueldosAsientoExportSupport::desdePreview($liq, $preview);

        $this->assertTrue($payload['es_preview']);
        $this->assertSame('PREVIEW (sin número)', $payload['numeros_asiento_label']);
        $this->assertSame([], $payload['numeros_asiento']);
        $this->assertStringContainsString('PREVIEW', $payload['titulo']);
        $this->assertSame(200.0, $payload['total_debe']);
        $this->assertSame(200.0, $payload['total_haber']);
        $this->assertCount(2, $payload['filas']);
        $this->assertSame('asiento_liquidacion_42_preview.pdf', SueldosAsientoExportSupport::nombreArchivo($payload, 'pdf'));
    }

    public function test_nombre_archivo_con_numero_real(): void
    {
        $nombre = SueldosAsientoExportSupport::nombreArchivo([
            'es_preview' => false,
            'liquidacion_numero' => 42,
            'numeros_asiento' => ['370701'],
        ], 'xlsx');

        $this->assertSame('asiento_liquidacion_42_370701.xlsx', $nombre);
    }
}
