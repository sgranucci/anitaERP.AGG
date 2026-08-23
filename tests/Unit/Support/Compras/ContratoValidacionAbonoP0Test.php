<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ContratoPeriodoServicioSupport;
use App\Support\Compras\ContratoValidacionAbonoCumplimientoSupport;
use App\Support\Compras\ContratoValidacionAbonoEstados;
use App\Support\Compras\ContratoValidacionAbonoPermisoSupport;
use App\Support\Compras\ContratoValidacionAbonoPoliticaSupport;
use Tests\TestCase;

class ContratoValidacionAbonoP0Test extends TestCase
{
    public function test_mes_vencido_usa_el_mes_anterior_a_la_fecha_del_remito(): void
    {
        $ventana = ContratoPeriodoServicioSupport::ventana(
            ContratoPeriodoServicioSupport::MES_VENCIDO,
            '2026-09-05'
        );

        $this->assertSame('2026-08-01', $ventana['desde']);
        $this->assertSame('2026-08-31', $ventana['hasta']);
        $this->assertSame(ContratoPeriodoServicioSupport::MES_VENCIDO, $ventana['modalidad']);
    }

    public function test_mismo_mes_corta_en_la_fecha_del_remito(): void
    {
        $ventana = ContratoPeriodoServicioSupport::ventana(
            ContratoPeriodoServicioSupport::MISMO_MES,
            '2026-08-20'
        );

        $this->assertSame('2026-08-01', $ventana['desde']);
        $this->assertSame('2026-08-20', $ventana['hasta']);
    }

    public function test_politica_no_aplica_si_no_es_contrato(): void
    {
        $oc = (object) ['es_contrato' => false];
        $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc($oc);

        $this->assertFalse($politica['aplica']);
        $this->assertFalse(ContratoValidacionAbonoPoliticaSupport::cortaRecepcion($politica));
        $this->assertFalse(ContratoValidacionAbonoPoliticaSupport::cortaFactura($politica));
    }

    public function test_exige_ingresos_implica_validacion(): void
    {
        $oc = (object) [
            'es_contrato' => true,
            'contrato_requiere_recepcion' => true,
            'contrato_requiere_validacion_abono' => false,
            'contrato_exige_ingresos' => true,
            'contrato_minimo_ingresos' => 2,
        ];
        $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc($oc);

        $this->assertTrue($politica['aplica']);
        $this->assertTrue($politica['exige_ingresos']);
        $this->assertSame(2, $politica['minimo_ingresos']);
        $this->assertTrue(ContratoValidacionAbonoPoliticaSupport::cortaRecepcion($politica));
    }

    public function test_ruta_sin_com_corta_la_factura(): void
    {
        $oc = (object) [
            'es_contrato' => true,
            'contrato_requiere_recepcion' => false,
            'contrato_requiere_validacion_abono' => true,
        ];
        $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc($oc);

        $this->assertTrue(ContratoValidacionAbonoPoliticaSupport::cortaFactura($politica));
        $this->assertFalse(ContratoValidacionAbonoPoliticaSupport::cortaRecepcion($politica));
    }

    public function test_exige_ingresos_corta_com_aunque_no_requiera_recepcion(): void
    {
        $oc = (object) [
            'es_contrato' => true,
            'contrato_requiere_recepcion' => false,
            'contrato_requiere_validacion_abono' => false,
            'contrato_exige_ingresos' => true,
        ];
        $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc($oc);

        $this->assertTrue($politica['exige_ingresos']);
        $this->assertTrue(ContratoValidacionAbonoPoliticaSupport::cortaRecepcion($politica));
    }

    public function test_sin_validacion_completa_no_se_confirma_com(): void
    {
        $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc((object) [
            'es_contrato' => true,
            'contrato_requiere_recepcion' => true,
            'contrato_requiere_validacion_abono' => true,
        ]);
        $resultado = ContratoValidacionAbonoCumplimientoSupport::evaluar($politica, [
            'estado' => ContratoValidacionAbonoEstados::PENDIENTE,
            'ingresos_informados' => 0,
        ]);

        $this->assertFalse($resultado['puede_confirmar_com']);
        $this->assertTrue($resultado['puede_contabilizar_fac']);
        $this->assertFalse($resultado['puede_enviar_cxp']);
    }

    public function test_ingresos_insuficientes_bloquean_com_y_cxp(): void
    {
        $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc((object) [
            'es_contrato' => true,
            'contrato_requiere_recepcion' => true,
            'contrato_requiere_validacion_abono' => true,
            'contrato_exige_ingresos' => true,
            'contrato_minimo_ingresos' => 1,
        ]);
        $resultado = ContratoValidacionAbonoCumplimientoSupport::evaluar($politica, [
            'estado' => ContratoValidacionAbonoEstados::COMPLETA,
            'ingresos_informados' => 0,
        ]);

        $this->assertFalse($resultado['puede_confirmar_com']);
        $this->assertFalse($resultado['puede_enviar_cxp']);
        $this->assertNotSame([], $resultado['errores']);
    }

    public function test_validacion_completa_con_ingresos_habilita_com(): void
    {
        $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc((object) [
            'es_contrato' => true,
            'contrato_requiere_recepcion' => true,
            'contrato_requiere_validacion_abono' => true,
            'contrato_exige_ingresos' => true,
            'contrato_minimo_ingresos' => 1,
        ]);
        $resultado = ContratoValidacionAbonoCumplimientoSupport::evaluar($politica, [
            'estado' => ContratoValidacionAbonoEstados::COMPLETA,
            'ingresos_informados' => 1,
        ]);

        $this->assertTrue($resultado['puede_confirmar_com']);
        $this->assertTrue($resultado['puede_enviar_cxp']);
    }

    public function test_responsable_puede_completar_sin_permiso_de_compras(): void
    {
        $this->assertTrue(ContratoValidacionAbonoPermisoSupport::puedeCompletar(15, 15, false, false));
        $this->assertFalse(ContratoValidacionAbonoPermisoSupport::puedeCompletar(15, 22, false, false));
        $this->assertTrue(ContratoValidacionAbonoPermisoSupport::puedeCompletar(15, 22, true, false));
        $this->assertTrue(ContratoValidacionAbonoPermisoSupport::puedeCompletar(15, 0, false, true));
    }
}
