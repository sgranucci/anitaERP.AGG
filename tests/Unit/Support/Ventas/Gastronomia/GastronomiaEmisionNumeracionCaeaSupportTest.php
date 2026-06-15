<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Repositories\Ventas\VentaRepository;
use App\Support\Ventas\Gastronomia\GastronomiaEmisionNumeracionCaeaSupport;
use Mockery;
use Tests\TestCase;

final class GastronomiaEmisionNumeracionCaeaSupportTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_aplicar_reserva_ignora_pv_no_caea(): void
    {
        $payload = [];
        $pv = new Puntoventa(['modofacturacion' => 'C', 'codigo' => '00031']);
        $tipo = new Tipotransaccion(['abreviatura' => 'FAC', 'codigo' => '1']);

        $error = GastronomiaEmisionNumeracionCaeaSupport::aplicarReservaNumeracionAlPayload(
            $payload,
            $pv,
            $tipo,
            'B',
        );

        $this->assertNull($error);
        $this->assertArrayNotHasKey('numerocomprobante_forzado', $payload);
    }

    public function test_aplicar_reserva_usa_compemis_en_caea(): void
    {
        $repo = Mockery::mock(VentaRepository::class);
        $repo->shouldReceive('numeraAnita')
            ->once()
            ->with('FAC', 'B', '00031')
            ->andReturn(14049);
        $this->app->instance(VentaRepository::class, $repo);

        $payload = [];
        $pv = new Puntoventa(['modofacturacion' => 'A', 'codigo' => '00031']);
        $tipo = new Tipotransaccion(['abreviatura' => 'FAC', 'codigo' => '1']);

        $error = GastronomiaEmisionNumeracionCaeaSupport::aplicarReservaNumeracionAlPayload(
            $payload,
            $pv,
            $tipo,
            'B',
        );

        $this->assertNull($error);
        $this->assertSame(14049, $payload['numerocomprobante_forzado']);
        $this->assertTrue($payload['_omitir_numera_anita_fin']);
    }

    public function test_aplicar_reserva_respeta_numero_forzado_previo(): void
    {
        $payload = ['numerocomprobante_forzado' => 99999];
        $pv = new Puntoventa(['modofacturacion' => 'A', 'codigo' => '00031']);
        $tipo = new Tipotransaccion(['abreviatura' => 'FAC', 'codigo' => '1']);

        $error = GastronomiaEmisionNumeracionCaeaSupport::aplicarReservaNumeracionAlPayload(
            $payload,
            $pv,
            $tipo,
            'B',
        );

        $this->assertNull($error);
        $this->assertSame(99999, $payload['numerocomprobante_forzado']);
        $this->assertTrue($payload['_omitir_numera_anita_fin']);
    }
}
