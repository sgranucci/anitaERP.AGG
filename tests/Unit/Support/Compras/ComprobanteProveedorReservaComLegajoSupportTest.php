<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorReservaComLegajoSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorReservaComLegajoSupportTest extends TestCase
{
    public function test_bloquea_dos_com_cuando_una_sola_cubre_el_importe(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoComQueCubrenSolas(
            60260.74,
            [
                ['id' => 64730, 'numerorecepcion' => 166402, 'provision' => 60260.74],
                ['id' => 64731, 'numerorecepcion' => 166403, 'provision' => 60260.74],
            ],
            5.0,
        );

        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('coincide con una sola COM', $mensaje);
        $this->assertStringContainsString('166402', $mensaje);
    }

    public function test_permite_varias_com_si_ninguna_cubre_sola(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoComQueCubrenSolas(
            120521.48,
            [
                ['id' => 1, 'numerorecepcion' => 100, 'provision' => 60260.74],
                ['id' => 2, 'numerorecepcion' => 101, 'provision' => 60260.74],
            ],
            5.0,
        );

        $this->assertNull($mensaje);
    }

    public function test_bloquea_si_no_quedan_com_para_otras_facturas_pendientes(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeInsuficienteParaOtrasPendientes(
            2,
            true,
            [64730, 64731],
            [64730, 64731],
        );

        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('factura(s) pendiente(s)', $mensaje);
    }

    public function test_permite_una_com_dejando_otra_para_pendiente(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeInsuficienteParaOtrasPendientes(
            2,
            true,
            [64730, 64731],
            [64731],
        );

        $this->assertNull($mensaje);
    }

    public function test_bloquea_com_reservada_en_otra_precarga_pendiente(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeConflictoReservaBandeja(
            [64730, 64731],
            [
                332 => [64730],
                676 => [],
            ],
            676,
        );

        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('64730', $mensaje);
        $this->assertStringContainsString('precarga #332', $mensaje);
    }

    public function test_permite_com_reservada_de_la_misma_precarga(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeConflictoReservaBandeja(
            [64730],
            [332 => [64730]],
            332,
        );

        $this->assertNull($mensaje);
    }

    public function test_bloquea_misma_com_en_dos_facturas_del_legajo(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeComDuplicadaEntreFacturas(
            [
                746 => [67323],
                752 => [67323],
            ],
            [67323 => 'Nº 167637'],
        );

        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('167637', $mensaje);
        $this->assertStringContainsString('otra factura', $mensaje);
    }

    public function test_permite_com_distintas_por_factura(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeComDuplicadaEntreFacturas([
            746 => [67323],
            752 => [67322],
        ]);

        $this->assertNull($mensaje);
    }

    public function test_bloquea_com_ya_vinculada_a_cp_del_legajo(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeComDuplicadaEntreFacturas(
            [
                'cp-26847' => [64439],
                927 => [64439],
            ],
            [64439 => 'Nº 166067'],
        );

        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('166067', $mensaje);
    }

    /** Caso real: COM 67291 con provisión 54.810 y la factura de 69.360 que no le corresponde. */
    public function test_bloquea_factura_que_excede_la_provision_de_la_com(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            [769 => [67291]],
            [67291 => 54810.00],
            [769 => 69360.00],
            [67291 => 'Nº 167291'],
            5.0,
        );

        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('167291', $mensaje);
        $this->assertStringContainsString('54.810,00', $mensaje);
        $this->assertStringContainsString('69.360,00', $mensaje);
    }

    public function test_permite_factura_que_coincide_con_la_provision(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            [773 => [67291]],
            [67291 => 54810.00],
            [773 => 54810.00],
            [],
            5.0,
        );

        $this->assertNull($mensaje);
    }

    /** Anticipada / contrato: dos facturas parciales caben si la suma no excede la provisión. */
    public function test_permite_dos_facturas_parciales_dentro_de_la_provision(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            [
                801 => [70001],
                802 => [70001],
            ],
            [70001 => 100000.00],
            [801 => 50000.00, 802 => 50000.00],
            [],
            5.0,
        );

        $this->assertNull($mensaje);
    }

    public function test_bloquea_dos_facturas_parciales_que_superan_la_provision(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            [
                801 => [70001],
                802 => [70001],
            ],
            [70001 => 100000.00],
            [801 => 80000.00, 802 => 80000.00],
            [70001 => 'Nº 170001'],
            5.0,
        );

        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('2 factura(s)', $mensaje);
    }

    public function test_respeta_la_tolerancia_configurada(): void
    {
        // 102.000 sobre 100.000 = 2% de exceso, dentro del 5% tolerado.
        $this->assertNull(ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            [801 => [70001]],
            [70001 => 100000.00],
            [801 => 102000.00],
            [],
            5.0,
        ));
    }

    /** COM histórica sin asiento ni líneas: no se puede validar, no debe bloquear. */
    public function test_no_bloquea_cuando_la_com_no_tiene_provision_conocida(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            [769 => [67291]],
            [67291 => 0.0],
            [769 => 69360.00],
            [],
            5.0,
        );

        $this->assertNull($mensaje);
    }

    /** Precarga de scan Anita con total 0: suma 0 y no bloquea al resto del legajo. */
    public function test_factura_sin_importe_no_bloquea(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            [
                404 => [65392],
                927 => [65392],
            ],
            [65392 => 118404.00],
            [404 => 0.0, 927 => 118404.00],
            [],
            5.0,
        );

        $this->assertNull($mensaje);
    }

    /**
     * Caso real OC 223753: una factura cubre dos remitos (COM 167738 + 167736).
     * Antes se cargaba el neto entero a cada COM y la chica disparaba falso exceso.
     */
    public function test_permite_varias_com_cuya_suma_cubre_la_factura(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            [986 => [67426, 67424]],
            [
                67426 => 10773.76,
                67424 => 211731.35,
            ],
            [986 => 222505.11],
            [
                67426 => 'Nº 167738',
                67424 => 'Nº 167736',
            ],
            5.0,
        );

        $this->assertNull($mensaje);
    }

    public function test_bloquea_varias_com_si_la_suma_no_alcanza_la_factura(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            [986 => [67426, 67424]],
            [
                67426 => 10773.76,
                67424 => 211731.35,
            ],
            [986 => 300000.00],
            [],
            5.0,
        );

        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('2 COM', $mensaje);
        $this->assertStringContainsString('222.505,11', $mensaje);
        $this->assertStringContainsString('300.000,00', $mensaje);
    }

    public function test_sigue_bloqueando_si_solo_se_asigna_la_com_chica(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            [986 => [67426]],
            [67426 => 10773.76],
            [986 => 222505.11],
            [67426 => 'Nº 167738'],
            5.0,
        );

        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('167738', $mensaje);
        $this->assertStringContainsString('10.773,76', $mensaje);
        $this->assertStringContainsString('222.505,11', $mensaje);
    }

    /** Caso captura: FC 109296.54 vs COM 98038.30 sin NC → bloquea (~11,5%). */
    public function test_bloquea_exceso_mayor_al_5_sin_cupo_nc(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            [900 => [167771]],
            [167771 => 98038.30],
            [900 => 109296.54],
            [167771 => 'Nº 167771'],
            5.0,
            0.0,
        );

        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('167771', $mensaje);
        $this->assertStringContainsString('98.038,30', $mensaje);
        $this->assertStringContainsString('109.296,54', $mensaje);
    }

    /** Misma captura con NC del legajo que cubre el exceso → permite. */
    public function test_permite_exceso_mayor_al_5_si_cupo_nc_cubre(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            [900 => [167771]],
            [167771 => 98038.30],
            [900 => 109296.54],
            [167771 => 'Nº 167771'],
            5.0,
            11258.24,
        );

        $this->assertNull($mensaje);
    }

    /** CGA subida al legajo sin montos (PDF): también desbloquea el exceso. */
    public function test_permite_exceso_si_hay_nc_pendiente_sin_importe(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            [900 => [167771]],
            [167771 => 98038.30],
            [900 => 109296.54],
            [167771 => 'Nº 167771'],
            5.0,
            0.0,
            true,
        );

        $this->assertNull($mensaje);
    }

    /** Dos FC con exceso y una sola NC: la segunda sigue bloqueada si el cupo no alcanza. */
    public function test_cupo_nc_se_consume_entre_facturas(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            [
                901 => [100],
                902 => [200],
            ],
            [100 => 100000.0, 200 => 100000.0],
            [901 => 112000.0, 902 => 112000.0],
            [100 => 'Nº 100', 200 => 'Nº 200'],
            5.0,
            12000.0,
        );

        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('factura(s)', $mensaje);
    }

    /** Caso real OC 216191: malla Anita 10 FC × 16 COM no debe disparar exceso al guardar NC. */
    public function test_ignora_malla_anita_con_demasiadas_com_por_factura(): void
    {
        $coms = range(65324, 65339);
        $asignaciones = [];
        $importes = [];
        foreach ([965, 'cp-30976', 'cp-30977', 'cp-30982', 'cp-30984', 'cp-30985', 'cp-30987', 'cp-30993', 'cp-30998', 'cp-30999'] as $i => $clave) {
            $asignaciones[$clave] = $coms;
            $importes[$clave] = 16462.78;
        }
        $provision = [];
        foreach ($coms as $rid) {
            $provision[$rid] = 7128.0;
        }

        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
            $asignaciones,
            $provision,
            $importes,
            [65324 => '#65324'],
            5.0,
        );

        $this->assertNull($mensaje);
    }

    /** OC 216515: factura marcada en dólares con importes en pesos (= provisión × cotización). */
    public function test_detecta_factura_en_me_con_importes_que_parecen_pesos(): void
    {
        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeMonedaIncoherenteFacturaVsCom(
            [814 => [67355]],
            [67355 => 266.0],
            [814 => 406980.0],
            [814 => ['moneda_id' => 2, 'cotizacion' => 1530.0]],
            [67355 => 'Nº 167669'],
        );

        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('pesos', $mensaje);
        $this->assertStringContainsString('167669', $mensaje);
        $this->assertStringContainsString('266,00', $mensaje);
        $this->assertStringContainsString('406.980,00', $mensaje);
    }

    public function test_no_alerta_moneda_cuando_importe_coincide_con_provision_en_me(): void
    {
        $this->assertNull(ComprobanteProveedorReservaComLegajoSupport::mensajeMonedaIncoherenteFacturaVsCom(
            [814 => [67355]],
            [67355 => 266.0],
            [814 => 266.0],
            [814 => ['moneda_id' => 2, 'cotizacion' => 1530.0]],
        ));
    }

    public function test_sin_coms_tocadas_no_hay_asignaciones_relevantes(): void
    {
        $this->assertSame(
            [],
            ComprobanteProveedorReservaComLegajoSupport::asignacionesQueTocanComs(
                [1030 => [], 965 => [65324, 65325]],
                [],
            )
        );
    }

    public function test_filtra_asignaciones_que_tocan_las_com_del_guardado(): void
    {
        $out = ComprobanteProveedorReservaComLegajoSupport::asignacionesQueTocanComs(
            [
                1030 => [],
                965 => [65324, 65325],
                900 => [70001],
            ],
            [65324],
        );

        $this->assertArrayHasKey(965, $out);
        $this->assertSame([65324, 65325], $out[965]);
        $this->assertArrayNotHasKey(1030, $out);
        $this->assertArrayNotHasKey(900, $out);
    }

    public function test_duplicada_solo_controla_coms_tocadas(): void
    {
        $this->assertNull(ComprobanteProveedorReservaComLegajoSupport::mensajeComDuplicadaEntreFacturas(
            [
                'cp-1' => [65324],
                'cp-2' => [65324],
                1030 => [70001],
            ],
            [],
            [70001],
        ));

        $mensaje = ComprobanteProveedorReservaComLegajoSupport::mensajeComDuplicadaEntreFacturas(
            [
                'cp-1' => [65324],
                1030 => [65324],
            ],
            [65324 => '#65324'],
            [65324],
        );
        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('65324', $mensaje);
    }
}
