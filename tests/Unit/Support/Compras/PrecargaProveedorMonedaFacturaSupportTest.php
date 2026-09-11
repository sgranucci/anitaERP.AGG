<?php

namespace Tests\Unit\Support\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Support\Compras\PrecargaProveedorMonedaFacturaSupport;
use Tests\TestCase;

class PrecargaProveedorMonedaFacturaSupportTest extends TestCase
{
    public function test_conserva_moneda_si_la_api_ya_trajo_importes_en_pesos(): void
    {
        $pre = new Precarga_Comprobante_Proveedor([
            'moneda_id' => 1,
            'moneda' => 'pesos',
            'cotizacion' => 1,
            'subtotal' => 429800,
            'total' => 547135.4,
            'origen_entrada' => PrecargaComprobanteOrigenEntrada::API,
        ]);

        $this->assertTrue(PrecargaProveedorMonedaFacturaSupport::conservarMonedaCotizacion($pre));

        $payload = PrecargaProveedorMonedaFacturaSupport::payloadSinPisarMoneda([
            'moneda_id' => 2,
            'moneda' => 'DOL',
            'cotizacion' => 1,
            'rutaalmacenamiento' => 'storage:/x.pdf',
            'origen_entrada' => PrecargaComprobanteOrigenEntrada::LEGAJO,
        ], $pre);
        $payload = PrecargaProveedorMonedaFacturaSupport::payloadSinPisarOrigenFactura($payload, $pre);

        $this->assertArrayNotHasKey('moneda_id', $payload);
        $this->assertArrayNotHasKey('moneda', $payload);
        $this->assertArrayNotHasKey('cotizacion', $payload);
        $this->assertArrayNotHasKey('origen_entrada', $payload);
        $this->assertSame('storage:/x.pdf', $payload['rutaalmacenamiento']);
    }

    public function test_stub_de_scan_sin_importes_puede_tomar_moneda_de_la_oc(): void
    {
        $pre = new Precarga_Comprobante_Proveedor([
            'moneda_id' => 1,
            'moneda' => 'PESOS',
            'cotizacion' => 1,
            'subtotal' => 0,
            'total' => 0,
            'origen_entrada' => PrecargaComprobanteOrigenEntrada::SCAN_ANITA,
        ]);

        $this->assertFalse(PrecargaProveedorMonedaFacturaSupport::conservarMonedaCotizacion($pre));

        $payload = PrecargaProveedorMonedaFacturaSupport::payloadSinPisarMoneda([
            'moneda_id' => 2,
            'moneda' => 'DOL',
            'cotizacion' => 1,
        ], $pre);

        $this->assertSame(2, $payload['moneda_id']);
        $this->assertSame('DOL', $payload['moneda']);
    }

    public function test_origen_ia_sin_importe_tambien_conserva(): void
    {
        $pre = new Precarga_Comprobante_Proveedor([
            'moneda_id' => 1,
            'subtotal' => 0,
            'total' => 0,
            'origen_entrada' => PrecargaComprobanteOrigenEntrada::PDF_IA,
        ]);

        $this->assertTrue(PrecargaProveedorMonedaFacturaSupport::conservarMonedaCotizacion($pre));
    }
}
