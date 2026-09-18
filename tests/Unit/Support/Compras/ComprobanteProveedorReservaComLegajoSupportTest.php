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
}
