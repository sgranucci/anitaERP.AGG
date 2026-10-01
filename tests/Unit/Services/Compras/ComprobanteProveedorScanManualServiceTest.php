<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Services\Compras\ComprobanteProveedorScanManualService;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use Tests\TestCase;

final class ComprobanteProveedorScanManualServiceTest extends TestCase
{
    public function test_copia_el_primer_pdf_al_nombre_canonico_del_scan(): void
    {
        $base = sys_get_temp_dir().'/facturas_scan_'.uniqid('', true);
        mkdir($base.'/comprobantes', 0775, true);

        $origen = $base.'/factura-origen.pdf';
        file_put_contents($origen, '%PDF-scan-manual');

        $comprobante = new Comprobante_Proveedor([
            'letra' => 'A',
            'sucursal' => 5,
            'numerocomprobante' => 104572,
            'fechacomprobante' => '2026-10-01',
        ]);
        $comprobante->setRelation('proveedores', new Proveedor([
            'nroinscripcion' => '30714198179',
        ]));
        $comprobante->setRelation('tipotransaccion_compras', new Tipotransaccion_Compra([
            'abreviatura' => 'FGA',
        ]));

        $servicio = new ComprobanteProveedorScanManualService(
            scanPath: (new PrecargaFacturaScanPathResolver)->withBasePath($base),
        );

        $publicado = $servicio->copiarAFacturasScan($comprobante, $origen);

        $esperado = $base.'/comprobantes/30-71419817-9/2026-10/FGA-A-00005-00104572.pdf';
        $this->assertSame($esperado, $publicado['destino']);
        $this->assertSame(
            'storage:/comprobantes/30-71419817-9/2026-10/FGA-A-00005-00104572.pdf',
            $publicado['referencia']
        );
        $this->assertSame('%PDF-scan-manual', file_get_contents($esperado));
    }
}
