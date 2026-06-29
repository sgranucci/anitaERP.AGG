<?php

namespace App\Services\Stock;

use App\Models\Stock\Transferencia_Mercaderia;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Stock\TransferenciaBienUsoSupport;

class TransferenciaMercaderiaPdfService
{
    /** @return array{bytes: string, filename: string} */
    public function generarComPdf(int $transferenciaId): array
    {
        $transferencia = Transferencia_Mercaderia::query()
            ->with([
                'tipotransaccion_stock',
                'depositoOrigen:'.implode(',', TransferenciaBienUsoSupport::DEPOSITO_RELATION_COLUMNS),
                'depositoDestino:'.implode(',', TransferenciaBienUsoSupport::DEPOSITO_RELATION_COLUMNS),
                'bienUsoOrigen',
                'bienUsoDestino',
                'usuarioOrigen',
                'usuarioDestino',
                'usuarioAprobador',
                'empresas',
                'articulos.articuloOrigen.unidadesdemedidas',
                'articulos.articuloDestino.unidadesdemedidas',
            ])
            ->findOrFail($transferenciaId);

        $empresa = $transferencia->empresas;
        $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
            collect([$transferencia])->each(function (Transferencia_Mercaderia $row) use ($empresa) {
                $row->setAttribute('nombreempresa', $empresa?->nombre ?? config('app.empresa'));
            })
        );

        $origen = TransferenciaBienUsoSupport::etiquetaOrigenTransferencia($transferencia);
        $destino = TransferenciaBienUsoSupport::etiquetaDestinoTransferencia($transferencia);

        $totalOrigen = 0.0;
        $totalDestino = 0.0;
        foreach ($transferencia->articulos as $linea) {
            $totalOrigen += abs((float) $linea->cantidad_origen);
            $totalDestino += abs((float) $linea->cantidad_destino);
        }

        $html = view('stock.movimientostock.transferencia_com_pdf', compact(
            'transferencia',
            'logos',
            'empresa',
            'origen',
            'destino',
            'totalOrigen',
            'totalDestino',
        ))->render();

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html, 'UTF-8');

        $codigo = preg_replace('/[^\w\-]+/', '_', (string) $transferencia->codigo);
        $filename = 'TRANSFERENCIA_'.$codigo.'.pdf';

        return ['bytes' => $pdf->output(), 'filename' => $filename];
    }

    public function descargarCom(int $transferenciaId, bool $inline = false)
    {
        $doc = $this->generarComPdf($transferenciaId);
        $disposition = $inline ? 'inline' : 'attachment';

        return response($doc['bytes'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$doc['filename'].'"',
        ]);
    }
}
