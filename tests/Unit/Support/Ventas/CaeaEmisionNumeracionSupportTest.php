<?php

namespace Tests\Unit\Support\Ventas;

use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Support\Ventas\CaeaEmisionNumeracionSupport;
use Tests\TestCase;

final class CaeaEmisionNumeracionSupportTest extends TestCase
{
    public function test_tipo_anita_desde_tipotransaccion_fce(): void
    {
        $tipo = new Tipotransaccion(['abreviatura' => 'FAC', 'codigo' => '201']);

        $this->assertSame('FCE', CaeaEmisionNumeracionSupport::tipoAnitaDesdeTipotransaccion($tipo));
    }

    public function test_aplicar_reserva_ignora_pv_no_caea(): void
    {
        $payload = [];
        $pv = new Puntoventa(['modofacturacion' => 'C', 'codigo' => '00031']);
        $tipo = new Tipotransaccion(['abreviatura' => 'FAC', 'codigo' => '1']);

        $error = CaeaEmisionNumeracionSupport::aplicarReservaNumeracionAlPayload(
            $payload,
            $pv,
            $tipo,
            'B',
        );

        $this->assertNull($error);
        $this->assertArrayNotHasKey('numerocomprobante_forzado', $payload);
    }

    public function test_aplicar_reserva_respeta_numero_forzado_previo(): void
    {
        $payload = ['numerocomprobante_forzado' => 99999];
        $pv = new Puntoventa(['modofacturacion' => 'A', 'codigo' => '00031']);
        $tipo = new Tipotransaccion(['abreviatura' => 'FAC', 'codigo' => '1']);

        $error = CaeaEmisionNumeracionSupport::aplicarReservaNumeracionAlPayload(
            $payload,
            $pv,
            $tipo,
            'B',
        );

        $this->assertNull($error);
        $this->assertSame(99999, $payload['numerocomprobante_forzado']);
        $this->assertTrue($payload['_omitir_numera_anita_fin']);
    }

    public function test_piso_caea_no_aplica_a_serie_fce(): void
    {
        $this->assertSame(0, CaeaEmisionNumeracionSupport::aplicarPisoCaea(3, 0, 201));
        $this->assertSame(12, CaeaEmisionNumeracionSupport::aplicarPisoCaea(3, 12, 206));
    }
}
