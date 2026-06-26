<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Support\Ventas\CaeaEmisionNumeracionSupport;
use App\Support\Ventas\Gastronomia\GastronomiaEmisionNumeracionCaeaSupport;
use Tests\TestCase;

/** @deprecated GastronomiaEmisionNumeracionCaeaSupport delega en CaeaEmisionNumeracionSupport */
final class GastronomiaEmisionNumeracionCaeaSupportTest extends TestCase
{
    public function test_wrapper_delega_aplicar_reserva(): void
    {
        $payload = ['numerocomprobante_forzado' => 12001];
        $pv = new Puntoventa(['modofacturacion' => 'A', 'codigo' => '00031']);
        $tipo = new Tipotransaccion(['abreviatura' => 'FAC', 'codigo' => '1']);

        $errorWrapper = GastronomiaEmisionNumeracionCaeaSupport::aplicarReservaNumeracionAlPayload(
            $payload,
            $pv,
            $tipo,
            'B',
        );
        $payloadDirecto = ['numerocomprobante_forzado' => 12001];
        $errorDirecto = CaeaEmisionNumeracionSupport::aplicarReservaNumeracionAlPayload(
            $payloadDirecto,
            $pv,
            $tipo,
            'B',
        );

        $this->assertSame($errorDirecto, $errorWrapper);
        $this->assertSame($payloadDirecto['numerocomprobante_forzado'], $payload['numerocomprobante_forzado']);
    }
}
