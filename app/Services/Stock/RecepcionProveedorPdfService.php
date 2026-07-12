<?php

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Pdf\DompdfPaperSupport;

class RecepcionProveedorPdfService
{
    public function __construct(
        private readonly Recepcion_ProveedorRepositoryInterface $repository,
    ) {
    }

    /** @return array{bytes: string, filename: string} */
    public function generarComPdf(int $recepcionId): array
    {
        $recepcion = $this->repository->find($recepcionId);
        $recepcion->loadMissing([
            'empresas', 'proveedores', 'ordencompras', 'monedas',
            'depositos.empresas',
            'recepcion_proveedor_articulos.articulos',
            'recepcion_proveedor_articulos.monedas',
            'recepcion_proveedor_partes_unicas.recepcion_proveedor_articulos.articulos',
            'creousuarios',
        ]);

        $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([$recepcion]));
        $total = 0.0;
        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $total += (float) $linea->cantidad * (float) $linea->precio;
        }

        $html = view('stock.recepcion_proveedor.com_pdf', compact('recepcion', 'logos', 'total'))->render();

        $pdf = app('dompdf.wrapper');
        DompdfPaperSupport::aplicar($pdf, DompdfPaperSupport::CONTEXTO_COMPROBANTE);
        $pdf->loadHTML($html, 'UTF-8');

        $filename = 'COM_'.preg_replace('/[^\w\-]+/', '_', $recepcion->numerorecepcion).'.pdf';

        return ['bytes' => $pdf->output(), 'filename' => $filename];
    }

    public function descargarCom(int $recepcionId, bool $inline = false)
    {
        $doc = $this->generarComPdf($recepcionId);
        $disposition = $inline ? 'inline' : 'attachment';

        return response($doc['bytes'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$doc['filename'].'"',
        ]);
    }
}
