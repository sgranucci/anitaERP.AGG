<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Remito_Articulo;
use App\Queries\Ventas\RemitoQueryInterface;
use App\Support\Ventas\RemitoListadoSupport;
use Illuminate\Support\Collection;
use Jurosh\PDFMerge\PDFMerger;

class RemitoListadoPdfService
{
    private const CHUNK_SIZE = 400;

    public function __construct(
        private readonly RemitoQueryInterface $remitoQuery,
    ) {
    }

    public function generar(array $filtros, string $subtituloFiltros = ''): string
    {
        $dir = storage_path('pdf/listados');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio temporal para el PDF.');
        }

        $destino = $dir.'/listado_remito_'.uniqid('', true).'.pdf';
        $temporales = [];
        $lote = [];
        $indice = 0;
        $totalFilas = 0;
        $ultimoLoteCompleto = null;
        $totalesGenerales = [
            'caja' => 0.0,
            'pieza' => 0.0,
            'kilo' => 0.0,
            
        ];

        $cursor = $this->remitoQuery->allRemitoIndexFiltrosCursor($filtros);

        foreach ($cursor as $fila) {
            $totalFilas++;
            $lote[] = $fila;
            if (count($lote) >= self::CHUNK_SIZE) {
                $ultimoLoteCompleto = $lote;
                $temporales[] = $this->generarLotePdf(
                    $lote,
                    $dir,
                    ++$indice,
                    $indice === 1,
                    false,
                    $totalFilas,
                    $totalesGenerales,
                    true,
                    $subtituloFiltros
                );
                $lote = [];
            }
        }

        if ($lote !== []) {
            $temporales[] = $this->generarLotePdf(
                $lote,
                $dir,
                ++$indice,
                $indice === 1,
                true,
                $totalFilas,
                $totalesGenerales,
                true,
                $subtituloFiltros
            );
        } elseif ($ultimoLoteCompleto !== null) {
            @unlink($temporales[array_key_last($temporales)]);
            array_pop($temporales);
            $temporales[] = $this->generarLotePdf(
                $ultimoLoteCompleto,
                $dir,
                $indice,
                $indice === 1,
                true,
                $totalFilas,
                $totalesGenerales,
                false,
                $subtituloFiltros
            );
        }

        if ($temporales === []) {
            $temporales[] = $this->generarLotePdf([], $dir, 1, true, false, 0, $totalesGenerales, true, $subtituloFiltros);
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
     * @param  list<object>  $filas
     * @param  array{caja: float, pieza: float, kilo: float, pesada: float}  $totalesGenerales
     */
    private function generarLotePdf(
        array $filas,
        string $dir,
        int $indice,
        bool $mostrarCabecera,
        bool $mostrarTotalesGenerales,
        int $totalFilas,
        array &$totalesGenerales,
        bool $acumularTotales = true,
        string $subtituloFiltros = ''
    ): string {
        $remitos = $this->enriquecerLote(collect($filas), $totalesGenerales, $acumularTotales);
        $html = view('ventas.remito.listado', [
            'remitos' => $remitos,
            'mostrarCabecera' => $mostrarCabecera,
            'mostrarTotalesGenerales' => $mostrarTotalesGenerales,
            'totalesGenerales' => $totalesGenerales,
            'totalFilas' => $totalFilas,
            'subtituloFiltros' => $subtituloFiltros,
        ])->render();
        $ruta = $dir.'/listado_remito_parte_'.$indice.'_'.uniqid('', true).'.pdf';

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html, 'UTF-8')->save($ruta);

        return $ruta;
    }

    /**
     * @param  Collection<int, object>  $lote
     * @param  array{caja: float, pieza: float, kilo: float, pesada: float}  $totalesGenerales
     * @return Collection<int, object>
     */
    private function enriquecerLote(Collection $lote, array &$totalesGenerales, bool $acumularTotales = true): Collection
    {
        if ($lote->isEmpty()) {
            return $lote;
        }

        $ids = $lote->pluck('id')->filter()->all();
        $articulosPorRemito = Remito_Articulo::query()
            ->whereIn('remito_id', $ids)
            ->get()
            ->groupBy('remito_id');

        foreach ($lote as $remito) {
            $remito->setRelation(
                'remito_articulos',
                $articulosPorRemito->get($remito->id, collect()),
            );

            if ($acumularTotales) {
                $totales = RemitoListadoSupport::totalesRemito($remito);
                $totalesGenerales['caja'] += $totales['caja'];
                $totalesGenerales['pieza'] += $totales['pieza'];
                $totalesGenerales['kilo'] += $totales['kilo'];
            }
        }

        return $lote;
    }
}
