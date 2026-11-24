<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\Sicore;

use App\Support\Contable\Sicore\SicoreLiquidacionAutocontrolSupport;
use PHPUnit\Framework\TestCase;

class SicoreLiquidacionAutocontrolSupportTest extends TestCase
{
    public function test_dj_resta_saf_e_ingresos_en_217_y_767_pero_en_787_solo_saf(): void
    {
        // F.744 octubre BIYEMAS (Guillermo): sin el ajuste el autocontrol marca diferencias.
        $this->assertSame(
            7041911.79,
            SicoreLiquidacionAutocontrolSupport::sicoreComparableMayor(
                'dj',
                '217',
                12444760.39,
                ['217' => 0.0, '767' => 0.0, '787' => 338251.74],
                [
                    '217' => ['fecha' => '2026-10-20', 'importe' => 5402848.60],
                    '767' => ['fecha' => '2026-10-20', 'importe' => 60900.00],
                    '787' => ['fecha' => '2026-10-20', 'importe' => 14453532.31],
                ],
            ),
        );
        $this->assertSame(
            60900.00,
            SicoreLiquidacionAutocontrolSupport::sicoreComparableMayor(
                'dj',
                '767',
                121800.00,
                ['217' => 0.0, '767' => 0.0, '787' => 338251.74],
                [
                    '217' => ['importe' => 5402848.60],
                    '767' => ['importe' => 60900.00],
                    '787' => ['importe' => 14453532.31],
                ],
            ),
        );
        $this->assertSame(
            14115280.57,
            SicoreLiquidacionAutocontrolSupport::sicoreComparableMayor(
                'dj',
                '787',
                14453532.31,
                ['217' => 0.0, '767' => 0.0, '787' => 338251.74],
                [
                    '217' => ['importe' => 5402848.60],
                    '767' => ['importe' => 60900.00],
                    '787' => ['importe' => 14453532.31],
                ],
            ),
        );
    }

    public function test_1q_no_ajusta_saf_ni_ingresos(): void
    {
        $this->assertSame(
            3130647.91,
            SicoreLiquidacionAutocontrolSupport::sicoreComparableMayor(
                '1q',
                '217',
                3130647.91,
                ['217' => 1000.0],
                ['217' => ['importe' => 500.0]],
            ),
        );
    }

    public function test_evaluar_dj_octubre_cuadra_con_mayor(): void
    {
        $porCodigo = [
            '217' => [
                'part' => ['total' => 12444760.39],
                'conciliacion' => [
                    'items' => [[
                        'codigo_impuesto' => 217,
                        'total_sicore' => 12444760.39,
                        'total_mayor' => 7041911.77,
                        'cuadra' => false,
                    ]],
                ],
            ],
            '767' => [
                'part' => ['total' => 121800.00],
                'conciliacion' => [
                    'items' => [[
                        'codigo_impuesto' => 767,
                        'total_sicore' => 121800.00,
                        'total_mayor' => 60900.00,
                        'cuadra' => false,
                    ]],
                ],
            ],
            '787' => [
                'part' => ['total' => 14453532.31],
                'conciliacion' => [
                    'items' => [[
                        'codigo_impuesto' => 787,
                        'total_sicore' => 14453532.31,
                        'total_mayor' => 14115280.57,
                        'cuadra' => false,
                    ]],
                ],
            ],
        ];

        $resultado = SicoreLiquidacionAutocontrolSupport::evaluar(
            $porCodigo,
            'dj',
            ['217' => 0.0, '767' => 0.0, '787' => 338251.74],
            [
                '217' => ['importe' => 5402848.60],
                '767' => ['importe' => 60900.00],
                '787' => ['importe' => 14453532.31],
            ],
        );

        $this->assertTrue($resultado['ok']);
        foreach ($resultado['items'] as $item) {
            $this->assertTrue($item['ok'], 'código '.$item['codigo']);
        }
    }

    public function test_evaluar_sin_ajuste_marcaba_diferencias(): void
    {
        $porCodigo = [
            '217' => [
                'part' => ['total' => 12444760.39],
                'conciliacion' => [
                    'items' => [[
                        'codigo_impuesto' => 217,
                        'total_sicore' => 12444760.39,
                        'total_mayor' => 7041911.77,
                        'cuadra' => false,
                    ]],
                ],
            ],
        ];

        // Sin tipo DJ (1Q) no resta → no cuadra contra el mayor neto.
        $sinAjuste = SicoreLiquidacionAutocontrolSupport::evaluar($porCodigo, '1q');
        $this->assertFalse($sinAjuste['ok']);
    }
}
