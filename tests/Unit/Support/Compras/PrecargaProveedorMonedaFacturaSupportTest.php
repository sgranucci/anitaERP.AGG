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

    public function test_pdf_de_legajo_conserva_la_moneda_elegida_al_asignar(): void
    {
        $pre = new Precarga_Comprobante_Proveedor([
            'moneda_id' => 2,
            'moneda' => 'DOL',
            'cotizacion' => 1,
            'subtotal' => 0,
            'total' => 0,
            'origen_entrada' => PrecargaComprobanteOrigenEntrada::LEGAJO,
        ]);

        $this->assertFalse(PrecargaProveedorMonedaFacturaSupport::conservarMonedaCotizacion($pre));
        $this->assertSame(2, PrecargaProveedorMonedaFacturaSupport::monedaIdParaComprobante($pre));
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
