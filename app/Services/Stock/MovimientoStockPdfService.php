<?php

namespace App\Services\Stock;

use App\Models\Stock\MovimientoStock;
use App\Repositories\Stock\MovimientoStockRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Stock\TransferenciaBienUsoSupport;

class MovimientoStockPdfService
{
    public function __construct(
        private readonly MovimientoStockRepositoryInterface $repository,
    ) {
    }

    /** @return array{bytes: string, filename: string} */
    public function generarComPdf(int $movimientoStockId): array
    {
        $movimiento = $this->repository->find($movimientoStockId);
        $movimiento->loadMissing([
            'tipotransaccion_stock',
            'centrocostoDestino',
            'articulos_movimiento.articulos.unidadesdemedidas',
            'articulos_movimiento.depositos:'.implode(',', TransferenciaBienUsoSupport::DEPOSITO_RELATION_COLUMNS).',empresa_id',
            'articulos_movimiento.depositos.empresas',
        ]);

        $empresa = optional($movimiento->articulos_movimiento->first()?->depositos)->empresas;
        $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
            collect([$movimiento])->each(function (MovimientoStock $row) use ($empresa) {
                $row->setAttribute('nombreempresa', $empresa?->nombre ?? config('app.empresa'));
            })
        );

        $totalCantidad = 0.0;
        $totalImporte = 0.0;
        foreach ($movimiento->articulos_movimiento as $linea) {
            $cant = abs((float) $linea->cantidad);
            $totalCantidad += $cant;
            $totalImporte += $cant * abs((float) $linea->precio);
        }

        $usuario = \App\Models\Seguridad\Usuario::query()->find($movimiento->usuario_id);

        $html = view('stock.movimientostock.com_pdf', compact(
            'movimiento',
            'logos',
            'empresa',
            'usuario',
            'totalCantidad',
            'totalImporte',
        ))->render();

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('legal', 'portrait');
        $pdf->loadHTML($html, 'UTF-8');

        $codigo = preg_replace('/[^\w\-]+/', '_', (string) $movimiento->codigo);
        $filename = 'MOV_STOCK_'.$codigo.'.pdf';

        return ['bytes' => $pdf->output(), 'filename' => $filename];
    }

    public function descargarCom(int $movimientoStockId, bool $inline = false)
    {
        $doc = $this->generarComPdf($movimientoStockId);
        $disposition = $inline ? 'inline' : 'attachment';

        return response($doc['bytes'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$doc['filename'].'"',
        ]);
    }
}
