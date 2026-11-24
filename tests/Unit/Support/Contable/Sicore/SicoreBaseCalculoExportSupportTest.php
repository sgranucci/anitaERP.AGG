<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\Sicore;

use App\Support\Contable\Sicore\SicoreBaseCalculoExportSupport;
use App\Support\Contable\Sicore\SicoreFormatoV8Support;
use PHPUnit\Framework\TestCase;

class SicoreBaseCalculoExportSupportTest extends TestCase
{
    public function test_nc_exporta_la_retencion_anulada_no_la_base_de_operacion(): void
    {
        $base = SicoreBaseCalculoExportSupport::baseDesdeRetencionErp(
            -16640.0,
            832000.0,
            [
                'devolucion' => true,
                'base_retenible' => 832000.0,
                'neto_pago' => 1056000.0,
            ],
        );

        $this->assertSame(16640.0, $base);
    }

    public function test_base_del_pago_resta_acumulado_previo_con_mni(): void
    {
        // OP 125889: acumulada 2.637.468,24 − previa 1.156.474,83 = 1.480.993,41
        $base = SicoreBaseCalculoExportSupport::basePagoDesdeDetalleAcumulado(2637468.24, [
            'base_retenible' => 2637468.24,
            'neto_acumulado_previo' => 1380474.83,
            'monto_excedente' => 224000.0,
            'inscripto' => true,
            'forma_calculo' => 'S',
            'neto_pago' => 1480993.41,
        ]);

        $this->assertSame(1480993.41, $base);
    }

    public function test_primer_pago_del_mes_exporta_retenible_completa(): void
    {
        $base = SicoreBaseCalculoExportSupport::basePagoDesdeDetalleAcumulado(1156474.83, [
            'base_retenible' => 1156474.83,
            'neto_acumulado_previo' => 0,
            'monto_excedente' => 224000.0,
            'inscripto' => true,
            'forma_calculo' => 'S',
        ]);

        $this->assertSame(1156474.83, $base);
    }

    public function test_ret_gc_neto_sin_iva_menos_mni_primer_pago(): void
    {
        $netoAcum = [];
        $base = SicoreBaseCalculoExportSupport::baseDesdeBrutoAnita(
            1079000.38,
            224000.0,
            true,
            '30717910202|78|2026-11',
            $netoAcum,
        );

        $this->assertSame(667735.85, $base);
        $this->assertSame(891735.85, $netoAcum['30717910202|78|2026-11']);
    }

    public function test_ret_gc_segundo_pago_sin_descontar_mni_otra_vez(): void
    {
        $netoAcum = ['30717910202|78|2026-11' => 891735.85];
        $base = SicoreBaseCalculoExportSupport::baseDesdeBrutoAnita(
            255000.06,
            224000.0,
            true,
            '30717910202|78|2026-11',
            $netoAcum,
        );

        $this->assertSame(210743.85, $base);
    }

    public function test_op_anulada_125567_exporta_base_del_pago_no_mni_doble(): void
    {
        // OP 125567 REVERTIDA: Anita B.2 sin acum. previo daba 428.915,84 (MNI de más).
        // Con detalle ERP (neto previo del mes) la base del pago es 652.915,84.
        $base = SicoreBaseCalculoExportSupport::baseDesdeRetencionErp(13058.31, 1572173.6, [
            'base_retenible' => 1572173.6,
            'neto_pago' => 652915.84,
            'neto_acumulado_previo' => 1143257.76,
            'monto_excedente' => 224000.0,
            'inscripto' => true,
            'forma_calculo' => 'S',
        ]);
        $this->assertSame(652915.84, $base);

        $anitaSinSeed = [];
        $anitaMal = SicoreBaseCalculoExportSupport::baseDesdeBrutoAnita(
            790028.17,
            224000.0,
            true,
            'cuit|78|2026-10',
            $anitaSinSeed,
        );
        $this->assertSame(428915.84, $anitaMal);
    }

    public function test_op_anulada_125608_exporta_retenible_erp_no_bruto_anita(): void
    {
        // OP 125608: bruto OP/1.21 − MNI = 734.180,37 (mal). ERP neto − MNI = 688.849,57.
        $base = SicoreBaseCalculoExportSupport::baseDesdeRetencionErp(13776.99, 688849.57, [
            'base_retenible' => 688849.57,
            'neto_pago' => 912849.57,
            'neto_acumulado_previo' => 0,
            'monto_excedente' => 224000.0,
            'inscripto' => true,
            'forma_calculo' => 'S',
        ]);
        $this->assertSame(688849.57, $base);

        $anitaSinSeed = [];
        $anitaMal = SicoreBaseCalculoExportSupport::baseDesdeBrutoAnita(
            1159398.25,
            224000.0,
            true,
            'cuit|78|2026-10',
            $anitaSinSeed,
        );
        $this->assertSame(734180.37, $anitaMal);
    }

    public function test_certificado_nc_plan_b_usa_nro_op_si_falta(): void
    {
        $this->assertSame(23936, SicoreBaseCalculoExportSupport::nroCertificadoNc(23936, 125457));
        $this->assertSame(125457, SicoreBaseCalculoExportSupport::nroCertificadoNc(0, 125457));
    }

    public function test_formato_v8_cod_comp_con_cero_a_izquierda(): void
    {
        $linea = SicoreFormatoV8Support::formatearLinea([
            'cod_comp' => SicoreFormatoV8Support::COD_COMP_NOTA_CREDITO,
            'fecha_comp' => '2026-10-15',
            'nro_comp' => 125506,
            'importe_comp' => 18560.0,
            'cod_impuesto' => 217,
            'cod_regimen' => 78,
            'cod_operacion' => 1,
            'base_calculo' => 16640.0,
            'fecha_retencion' => '2026-10-15',
            'cod_condicion' => 1,
            'importe' => 16640.0,
            'porc_excl' => 0,
            'fecha_boletin' => '',
            'cod_documento' => 80,
            'nro_documento' => '20228546926',
            'nro_cert' => 23936,
        ]);

        $this->assertSame('03', substr($linea, 0, 2));
        $this->assertStringContainsString('16640.00', $linea);
    }
}
