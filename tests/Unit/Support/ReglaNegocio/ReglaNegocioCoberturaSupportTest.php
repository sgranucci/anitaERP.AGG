<?php

declare(strict_types=1);

namespace Tests\Unit\Support\ReglaNegocio;

use App\Support\ReglaNegocio\ReglaNegocioCoberturaSupport;
use Tests\TestCase;

class ReglaNegocioCoberturaSupportTest extends TestCase
{
    public function test_puntos_cableados_y_resumen(): void
    {
        $cableados = ReglaNegocioCoberturaSupport::puntosCableados();
        $this->assertArrayHasKey('stock.tm.requiere_aprobacion', $cableados);
        $this->assertArrayHasKey('compras.com.reserva_factura', $cableados);
        $this->assertArrayHasKey('contable.canon.alicuota', $cableados);
        $this->assertArrayHasKey('caja.programa_pago.cheques_param', $cableados);

        $resumen = ReglaNegocioCoberturaSupport::resumen();
        $this->assertNotEmpty($resumen);
        $puntos = array_column($resumen, 'punto');
        $this->assertContains('stock.tm.requiere_aprobacion', $puntos);
        $tm = collect($resumen)->firstWhere('punto', 'stock.tm.requiere_aprobacion');
        $this->assertTrue($tm['cableado']);
        $this->assertContains($tm['estado'], [
            'operativo', 'cableado_sin_activas', 'cableado_sin_reglas',
        ]);
    }
}
