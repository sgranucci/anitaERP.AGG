<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Support\Compras\OrdencompraTotalesCabecera;
use Illuminate\Support\Collection;
use Jurosh\PDFMerge\PDFMerger;

class OrdencompraListadoPdfService
{
    private const CHUNK_SIZE = 400;

    public function __construct(
        private readonly OrdencompraRepositoryInterface $ordencompraRepository,
        private readonly CotizacionQueryInterface $cotizacionQuery,
    ) {
    }

    /**
     * Genera el PDF del listado (por lotes si hay muchos registros) y devuelve la ruta del archivo.
     */
    public function generar(?string $busqueda, ?int $sectorUsuarioId): string
    {
        $dir = storage_path('pdf/listados');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $destino = $dir.'/listado_ordencompra_'.uniqid('', true).'.pdf';
        $temporales = [];
        $lote = [];
        $indice = 0;

        foreach ($this->ordencompraRepository->listadoExportCursor($busqueda, $sectorUsuarioId) as $fila) {
            $lote[] = $fila;
            if (count($lote) >= self::CHUNK_SIZE) {
                $temporales[] = $this->generarLotePdf($lote, $dir, ++$indice, $indice === 1);
                $lote = [];
            }
        }

        if ($lote !== []) {
            $temporales[] = $this->generarLotePdf($lote, $dir, ++$indice, $indice === 1);
        }

        if ($temporales === []) {
            $temporales[] = $this->generarLotePdf([], $dir, 1, true);
        }

        try {
            if (count($temporales) === 1) {
                rename($temporales[0], $destino);
            } else {
                $merger = new PDFMerger;
                foreach ($temporales as $ruta) {
                    $merger->addPDF($ruta, 'all', 'horizontal');
                }
                $merger->merge('file', $destino);
            }
        } finally {
            foreach ($temporales as $ruta) {
                if (is_file($ruta)) {
                    @unlink($ruta);
                }
            }
        }

        return $destino;
    }

    /**
     * @param  list<Ordencompra>  $filas
     */
    private function generarLotePdf(array $filas, string $dir, int $indice, bool $mostrarCabecera): string
    {
        $ordencompra = $this->enriquecerLote(collect($filas));
        $html = view('compras.ordencompra.listado', compact('ordencompra', 'mostrarCabecera'))->render();
        $ruta = $dir.'/listado_ordencompra_parte_'.$indice.'_'.uniqid('', true).'.pdf';

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html, 'UTF-8')->save($ruta);

        return $ruta;
    }

    /**
     * @param  Collection<int, Ordencompra>  $lote
     * @return Collection<int, Ordencompra>
     */
    private function enriquecerLote(Collection $lote): Collection
    {
        if ($lote->isEmpty()) {
            return $lote;
        }

        $ids = $lote->pluck('id')->filter()->all();
        $articulosPorOc = Ordencompra_Articulo::query()
            ->whereIn('ordencompra_id', $ids)
            ->with([
                'articulos',
                'monedas',
                'centrocostos_destino',
                'partidagastos.articulos',
                'capexs',
            ])
            ->get()
            ->groupBy('ordencompra_id');

        foreach ($lote as $oc) {
            $oc->setRelation(
                'ordencompra_articulos',
                $articulosPorOc->get($oc->id, collect()),
            );
            OrdencompraTotalesCabecera::aplicarAtributosVirtuales($oc, $this->cotizacionQuery);
        }

        return $lote;
    }
}
