<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Services\Stock\SurmarTrazabilidadService;
use Illuminate\Http\Request;

class TrazabilidadSurmarController extends Controller
{
    public function __construct(
        private readonly SurmarTrazabilidadService $service,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-trazabilidad-surmar');

        $etiquetaId = (int) $request->input('etiqueta_id', 0);
        $articuloId = (int) $request->input('articulo_id', 0);
        $lote = trim((string) $request->input('lote', ''));
        $codigo = trim((string) $request->input('codigo', ''));
        $anitaNint = (int) $request->input('anita_nro_interno', 0);
        $anitaNap = (int) $request->input('anita_nro_apertura', 0);
        $consultar = (string) $request->input('consultar', '') === '1';

        $etiquetas = collect();
        $historial = null;
        $filtrosQuery = array_filter([
            'etiqueta_id' => $etiquetaId > 0 ? $etiquetaId : null,
            'articulo_id' => $articuloId > 0 ? $articuloId : null,
            'lote' => $lote !== '' ? $lote : null,
            'codigo' => $codigo !== '' ? $codigo : null,
            'anita_nro_interno' => $anitaNint > 0 ? $anitaNint : null,
            'anita_nro_apertura' => $anitaNap > 0 ? $anitaNap : null,
            'consultar' => $consultar ? '1' : null,
        ], static fn ($v) => $v !== null && $v !== '');

        if ($consultar) {
            $etiquetas = $this->service->buscarEtiquetas(
                $etiquetaId > 0 ? $etiquetaId : null,
                $articuloId > 0 ? $articuloId : null,
                $lote !== '' ? $lote : null,
                $codigo !== '' ? $codigo : null,
                $anitaNint > 0 ? $anitaNint : null,
                $anitaNap > 0 ? $anitaNap : null,
            );
            if ($etiquetas->count() === 1) {
                $historial = $this->service->historialEtiqueta((int) $etiquetas->first()->id);
            }
        }

        return view('stock.trazabilidad_surmar.index', [
            'etiqueta_id' => $etiquetaId > 0 ? $etiquetaId : '',
            'articulo_id' => $articuloId > 0 ? $articuloId : '',
            'articulo_sku' => (string) $request->input('articulo_sku', ''),
            'articulo_desc' => (string) $request->input('articulo_desc', ''),
            'lote' => $lote,
            'codigo' => $codigo,
            'anita_nro_interno' => $anitaNint > 0 ? $anitaNint : '',
            'anita_nro_apertura' => $anitaNap > 0 ? $anitaNap : '',
            'consultar' => $consultar,
            'etiquetas' => $etiquetas,
            'historial' => $historial,
            'filtrosQuery' => $filtrosQuery,
        ]);
    }
}
