<?php

namespace Tests\Unit\Support\Compras;

use App\Models\Compras\Pagoproveedor_Retencion;
use App\Support\Compras\PagoproveedorRetencionCertificadoLineasSupport;
use PHPUnit\Framework\TestCase;

class PagoproveedorRetencionCertificadoLineasSupportTest extends TestCase
{
    public function test_iibb_usa_gravado_propio_y_nc_resta(): void
    {
        $aplicaciones = [
            ['cc_id' => 1, 'numero' => 'FIS A0002-00002655', 'fecha' => '30/08/2026'],
            ['cc_id' => 2, 'numero' => 'CIS A0002-00000128', 'fecha' => '11/08/2026'],
            ['cc_id' => 3, 'numero' => 'FIS A0002-00002428', 'fecha' => '31/07/2026'],
            ['cc_id' => 4, 'numero' => 'CNS A0002-00000127', 'fecha' => '14/07/2026'],
            ['cc_id' => 5, 'numero' => 'FIS A0002-00002136', 'fecha' => '30/06/2026'],
            ['cc_id' => 6, 'numero' => 'FNS A0002-00002022', 'fecha' => '31/05/2026'],
            ['cc_id' => 99, 'numero' => 'OPA 1-123', 'fecha' => '01/06/2026'],
        ];
        $detalle = [
            ['cc_id' => 1, 'retieneIIBB' => 'S', 'destino_buenos_aires' => true, 'porcion_pago' => 31733472.19],
            ['cc_id' => 2, 'retieneIIBB' => 'S', 'destino_buenos_aires' => true, 'porcion_pago' => -66081.20],
            ['cc_id' => 3, 'retieneIIBB' => 'S', 'destino_buenos_aires' => true, 'porcion_pago' => 31100800.51],
            ['cc_id' => 4, 'retieneIIBB' => 'S', 'destino_buenos_aires' => true, 'porcion_pago' => -162957.15],
            ['cc_id' => 5, 'retieneIIBB' => 'S', 'destino_buenos_aires' => true, 'porcion_pago' => 27430941.40],
            ['cc_id' => 6, 'retieneIIBB' => 'S', 'destino_buenos_aires' => true, 'porcion_pago' => 25640112.98],
            ['cc_id' => 99, 'omitido_retencion' => 'opa', 'porcion_pago' => 500000.00],
        ];

        $base = 115676288.73;
        $importe = 3470288.66;
        $lineas = PagoproveedorRetencionCertificadoLineasSupport::lineas(
            $aplicaciones,
            $detalle,
            Pagoproveedor_Retencion::TIPO_IIBB,
            $base,
            $importe,
            3.0,
        );

        $this->assertCount(6, $lineas);
        $this->assertEqualsWithDelta(31733472.19, $lineas[0]['gravado'], 0.01);
        $this->assertEqualsWithDelta(-66081.20, $lineas[1]['gravado'], 0.01);
        $this->assertNotEqualsWithDelta(19279381.46, $lineas[0]['base_imp'], 1.0);
        $this->assertLessThan(0, $lineas[1]['base_imp']);
        $this->assertLessThan(0, $lineas[1]['retencion']);
        $this->assertEqualsWithDelta($base, array_sum(array_column($lineas, 'base_imp')), 0.02);
        $this->assertEqualsWithDelta($importe, array_sum(array_column($lineas, 'retencion')), 0.02);
        $this->assertSame([], array_filter($lineas, static fn ($l) => $l['cc_id'] === 99));
    }

    public function test_opa_sola_no_genera_filas(): void
    {
        $lineas = PagoproveedorRetencionCertificadoLineasSupport::lineas(
            [['cc_id' => 7, 'numero' => 'OPA 1-1', 'fecha' => '01/01/2026']],
            [['cc_id' => 7, 'omitido_retencion' => 'opa']],
            Pagoproveedor_Retencion::TIPO_IIBB,
            1000.0,
            30.0,
            3.0,
        );

        $this->assertSame([], $lineas);
    }
}
