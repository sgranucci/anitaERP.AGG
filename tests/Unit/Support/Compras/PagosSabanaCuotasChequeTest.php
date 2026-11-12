<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\PagosSabanaAnitaArmadoSupport;
use PHPUnit\Framework\TestCase;

/**
 * Cuotas diferidas con el mismo numerocheque deben salir como filas separadas en sábana
 * (igual que OPG.TXT / Anita clásico), no agregadas con "|".
 */
class PagosSabanaCuotasChequeTest extends TestCase
{
    public function test_anita_desglose_conserva_tres_cuotas_mismo_nro(): void
    {
        $lineas = [
            (object) [
                'axp_tipo_ap' => 'CHP',
                'axp_monto_ap' => 12549561.33,
                'axp_nro' => '82048489',
                'axp_banco' => '0127',
                'axp_fecha_co' => '20261205',
            ],
            (object) [
                'axp_tipo_ap' => 'CHP',
                'axp_monto_ap' => 12549561.33,
                'axp_nro' => '82048489',
                'axp_banco' => '0127',
                'axp_fecha_co' => '20270105',
            ],
            (object) [
                'axp_tipo_ap' => 'CHP',
                'axp_monto_ap' => 12549561.33,
                'axp_nro' => '82048489',
                'axp_banco' => '0127',
                'axp_fecha_co' => '20270205',
            ],
        ];

        $out = PagosSabanaAnitaArmadoSupport::desgloseMedios($lineas, ['0127' => 'MACRO']);

        $this->assertSame(37648683.99, $out['ch_propios']);
        $this->assertCount(3, $out['cuotas_ch_propios']);
        $this->assertSame('05/12/2026', $out['cuotas_ch_propios'][0]['fecha']);
        $this->assertSame('05/01/2027', $out['cuotas_ch_propios'][1]['fecha']);
        $this->assertSame('05/02/2027', $out['cuotas_ch_propios'][2]['fecha']);
    }

    public function test_expandir_fila_emite_tres_lineas_como_txt(): void
    {
        $cuotas = [
            ['importe' => 12549561.33, 'emi' => '82048489/MACR.', 'fecha' => '05/12/2026', 'banco' => 'MACRO'],
            ['importe' => 12549561.33, 'emi' => '82048489/MACR.', 'fecha' => '05/01/2027', 'banco' => 'MACRO'],
            ['importe' => 12549561.33, 'emi' => '82048489/MACR.', 'fecha' => '05/02/2027', 'banco' => 'MACRO'],
        ];

        $fila = [
            'tipo_fila' => 'detalle',
            'tip' => 'OPP',
            'numero_op' => '126010',
            'proveedor_nombre' => 'NOVA GAMES S.A',
            'tipo_medio' => 'CHQ',
            'total_pago' => 37648683.99,
            'efectivo' => 0.0,
            'transferencia' => 0.0,
            'ch_propios' => 37648683.99,
            'ch_terceros' => 0.0,
            'doc_propios' => 0.0,
            'doc_terceros' => 0.0,
            'retencion_iva' => 0.0,
            'retencion_gan' => 0.0,
            'retencion_ibr' => 0.0,
            'retencion_suss' => 0.0,
            'creditos' => 0.0,
            'adelantos' => 0.0,
            'varios' => 0.0,
            'intercompany' => 0.0,
            'ch_prop_emi' => '82048489/MACR. 82048489/MACR. 82048489/MACR.',
            'fecha_cheque' => '05/12/2026 | 05/01/2027 | 05/02/2027',
            'banco' => 'MACRO',
            'comprobantes' => '',
            'comprobantes_links' => [],
            'comprobantes_refs' => [],
            'ch_terc_ent' => '',
            'doc_prop_emit' => '',
            'doc_terc_entr' => '',
            'centros_costo' => '',
            'ordenes_compra' => '',
            'ordenes_compra_links' => [],
        ];

        $filas = PagosSabanaAnitaArmadoSupport::expandirFilaPorCuotasPropios($fila, $cuotas);

        $this->assertCount(3, $filas);
        $this->assertSame(12549561.33, $filas[0]['ch_propios']);
        $this->assertSame(12549561.33, $filas[1]['ch_propios']);
        $this->assertSame(12549561.33, $filas[2]['ch_propios']);
        $this->assertSame('05/12/2026', $filas[0]['fecha_cheque']);
        $this->assertSame('05/01/2027', $filas[1]['fecha_cheque']);
        $this->assertSame('05/02/2027', $filas[2]['fecha_cheque']);
        $this->assertSame('82048489/MACR.', $filas[0]['ch_prop_emi']);
        $this->assertStringNotContainsString('|', (string) $filas[0]['fecha_cheque']);
        $this->assertSame(12549561.33, $filas[0]['total_pago']);
        $this->assertSame(12549561.33, $filas[1]['total_pago']);
        $this->assertSame(12549561.33, $filas[2]['total_pago']);
    }

    public function test_armar_filas_anita_emite_una_linea_por_cuota(): void
    {
        $pagos = [
            (object) [
                'pag_empresa' => 1,
                'pag_tipo' => 'OPP',
                'pag_rec' => 126010,
                'pag_pro' => '937',
                'pag_entregado_a' => 'NOVA GAMES S.A',
                'pag_fecha' => '20261112',
                'pag_trec' => 37648683.99,
                'pag_leyenda' => '',
            ],
        ];
        $auxpags = [
            (object) [
                'axp_empresa' => 1,
                'axp_tipo' => 'OPP',
                'axp_rec' => 126010,
                'axp_tipo_ap' => 'CHP',
                'axp_monto_ap' => 12549561.33,
                'axp_nro' => '82048489',
                'axp_banco' => '0127',
                'axp_fecha_co' => '20261205',
            ],
            (object) [
                'axp_empresa' => 1,
                'axp_tipo' => 'OPP',
                'axp_rec' => 126010,
                'axp_tipo_ap' => 'CHP',
                'axp_monto_ap' => 12549561.33,
                'axp_nro' => '82048489',
                'axp_banco' => '0127',
                'axp_fecha_co' => '20270105',
            ],
            (object) [
                'axp_empresa' => 1,
                'axp_tipo' => 'OPP',
                'axp_rec' => 126010,
                'axp_tipo_ap' => 'CHP',
                'axp_monto_ap' => 12549561.33,
                'axp_nro' => '82048489',
                'axp_banco' => '0127',
                'axp_fecha_co' => '20270205',
            ],
        ];

        $filas = PagosSabanaAnitaArmadoSupport::armarFilas(
            $pagos,
            $auxpags,
            ['0127' => 'MACRO'],
            [1 => ['id' => 1, 'codigo' => 'BIY', 'nombre' => 'Biyemas']],
            ['000937' => ['id' => 10, 'codigo' => '937', 'nombre' => 'NOVA GAMES S.A']],
        );

        $this->assertCount(3, $filas);
        $this->assertSame(['05/12/2026', '05/01/2027', '05/02/2027'], array_column($filas, 'fecha_cheque'));
        $this->assertSame(12549561.33, $filas[0]['ch_propios']);
        $this->assertSame(37648683.99, round(array_sum(array_column($filas, 'ch_propios')), 2));
    }
}
