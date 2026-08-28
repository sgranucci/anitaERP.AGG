<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Pedido_Articulo;
use App\Queries\Ventas\PedidoQueryInterface;
use App\Support\Ventas\PedidoListadoSupport;
use Illuminate\Support\Collection;
use Jurosh\PDFMerge\PDFMerger;

class PedidoListadoPdfService
{
    private const CHUNK_SIZE = 400;

    public function __construct(
        private readonly PedidoQueryInterface $pedidoQuery,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function generar(array $filtros, string $subtituloFiltros = ''): string
    {
        $dir = storage_path('pdf/listados');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio temporal para el PDF.');
        }

        $destino = $dir.'/listado_pedido_'.uniqid('', true).'.pdf';
        $temporales = [];
        $lote = [];
        $indice = 0;
        $totalFilas = 0;
        $ultimoLoteCompleto = null;
        $totalesGenerales = [
            'caja' => 0.0,
            'pieza' => 0.0,
            'kilo' => 0.0,
            'pesada' => 0.0,
        ];

        $totalesPorReparto = $this->pedidoQuery->totalesPedidoIndexPorReparto($filtros);
        $cursor = $this->pedidoQuery->allPedidoIndexFiltrosCursor($filtros);

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
                    $subtituloFiltros,
                    $totalesPorReparto
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
                $subtituloFiltros,
                $totalesPorReparto
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
                $subtituloFiltros,
                $totalesPorReparto
            );
        }

        if ($temporales === []) {
            $temporales[] = $this->generarLotePdf([], $dir, 1, true, false, 0, $totalesGenerales, true, $subtituloFiltros, $totalesPorReparto);
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
     * @param  \Illuminate\Support\Collection<string, object>  $totalesPorReparto
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
        string $subtituloFiltros = '',
        $totalesPorReparto = null
    ): string {
        $pedidos = $this->enriquecerLote(collect($filas), $totalesGenerales, $acumularTotales);
        $html = view('ventas.pedido.listado', [
            'pedidos' => $pedidos,
            'mostrarCabecera' => $mostrarCabecera,
            'mostrarTotalesGenerales' => $mostrarTotalesGenerales,
            'totalesGenerales' => $totalesGenerales,
            'totalFilas' => $totalFilas,
            'subtituloFiltros' => $subtituloFiltros,
            'totalesPorReparto' => $totalesPorReparto ?? collect(),
        ])->render();
        $ruta = $dir.'/listado_pedido_parte_'.$indice.'_'.uniqid('', true).'.pdf';

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
        $articulosPorPedido = Pedido_Articulo::query()
            ->whereIn('pedido_id', $ids)
            ->get()
            ->groupBy('pedido_id');

        foreach ($lote as $pedido) {
            $pedido->setRelation(
                'pedido_articulos',
                $articulosPorPedido->get($pedido->id, collect()),
            );

            if ($acumularTotales) {
                $totales = PedidoListadoSupport::totalesPedido($pedido);
                $totalesGenerales['caja'] += $totales['caja'];
                $totalesGenerales['pieza'] += $totales['pieza'];
                $totalesGenerales['kilo'] += $totales['kilo'];
                $totalesGenerales['pesada'] += $totales['pesada'];
            }
        }

        return $lote;
    }
}
