<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\Suss;

use App\Support\Contable\Suss\SussMayorComparableSupport;
use PHPUnit\Framework\TestCase;

class SussMayorComparableSupportTest extends TestCase
{
    public function test_excluye_pago_a_afip_proveedor_1299_del_comparable(): void
    {
        $particion = SussMayorComparableSupport::particionar([
            $this->movimiento('001299', -1981735.49, 'Pago: AFIP #120100', 8801),
            $this->movimiento('000217', 15000.00, 'Pago: ACME #120200', 8802),
        ]);

        $this->assertCount(1, $particion['comparables']);
        $this->assertSame('000217', $particion['comparables'][0]['subd_emisor']);
        $this->assertSame(15000.00, $particion['total_comparable']);

        $this->assertCount(1, $particion['excluidos']);
        $this->assertSame('pago_afip', $particion['excluidos'][0]['motivo_exclusion']);
        $this->assertSame(-1981735.49, $particion['total_excluido']);
    }

    public function test_reconoce_codigo_erp_y_detalle_de_pago_afip(): void
    {
        $this->assertTrue(SussMayorComparableSupport::esPagoAfip([
            'codigo_proveedor' => '1299',
        ]));
        $this->assertTrue(SussMayorComparableSupport::esPagoAfip([
            'subd_emisor' => '1299',
        ]));
        $this->assertTrue(SussMayorComparableSupport::esPagoAfip([
            'detalle' => 'Pago: AFIP #120100',
        ]));
        $this->assertFalse(SussMayorComparableSupport::esPagoAfip([
            'subd_emisor' => '000217',
            'detalle' => 'Pago: ACME #120200',
        ]));
    }

    public function test_excluye_ctamov_gemelo_del_mismo_asiento_afip(): void
    {
        $particion = SussMayorComparableSupport::particionar([
            [
                'fecha' => '2026-08-05',
                'asiento_id' => 8801,
                'subd_emisor' => '001299',
                'neto_haber' => -1981735.49,
                'detalle' => 'Pago: AFIP #120100',
            ],
            [
                'fecha' => '2026-08-05',
                'asiento_id' => 8801,
                'neto_haber' => -1981735.49,
                'detalle' => 'Imputación cuenta 214010015',
            ],
        ]);

        $this->assertSame([], $particion['comparables']);
        $this->assertCount(2, $particion['excluidos']);
        $this->assertSame(0.0, $particion['total_comparable']);
    }

    public function test_sigue_excluyendo_pago_suss_por_texto(): void
    {
        $particion = SussMayorComparableSupport::particionar([
            $this->movimiento('000217', -5000.00, 'RETENCIONES SUSS BSA 2Q 07.26', 8803),
        ]);

        $this->assertSame([], $particion['comparables']);
        $this->assertSame('pago_suss', $particion['excluidos'][0]['motivo_exclusion']);
    }

    /**
     * @return array<string, mixed>
     */
    private function movimiento(string $emisor, float $netoHaber, string $detalle, int $asientoId): array
    {
        return [
            'fecha' => '2026-08-05',
            'asiento_id' => $asientoId,
            'subd_emisor' => $emisor,
            'neto_haber' => $netoHaber,
            'detalle' => $detalle,
        ];
    }
}
