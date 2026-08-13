<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\RecepcionProveedorSurmarListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\RecepcionProveedorArticuloSurmar;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Stock\RecepcionProveedorSurmarService;
use App\Services\Stock\SurmarEtiquetaImpresionService;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use App\Support\Pdf\DompdfPaperSupport;
use App\Support\Stock\RecepcionProveedorSurmarListadoFiltros;
use App\Support\Stock\Surmar\RecepcionProveedorSurmarOcSupport;
use App\Support\Stock\Surmar\SurmarUnidadmedidaSeparaSupport;
use App\Support\Stock\SurmarSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RecepcionProveedorSurmarController extends Controller
{
    public function __construct(
        private readonly RecepcionProveedorSurmarService $service,
        private readonly SurmarEtiquetaImpresionService $impresionEtiquetaService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    private function assertSurmar(): void
    {
    }

    public function index(Request $request)
    {
        can('listar-recepcion-proveedor-surmar');
        $this->assertSurmar();
        $filtros = RecepcionProveedorSurmarListadoFiltros::resolverDesdeRequest($request);
        $coleccion = $this->service->listar($filtros, true);
        $filtrosQuery = RecepcionProveedorSurmarListadoFiltros::paraQueryString($filtros);

        return view('stock.recepcion_proveedor_surmar.index', [
            'coleccion' => $coleccion,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => RecepcionProveedorSurmarListadoFiltros::camposFiltro(),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-recepcion-proveedor-surmar');

        $this->assertSurmar();
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = RecepcionProveedorSurmarListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->service->listar($filtros, false);
                $view = \View::make('stock.recepcion_proveedor_surmar.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_recepcion_proveedor_surmar';
                $pdf = \App::make('dompdf.wrapper');
                DompdfPaperSupport::aplicar($pdf, DompdfPaperSupport::CONTEXTO_LISTADO);
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new RecepcionProveedorSurmarListadoExport($this->service))
                    ->parametros($filtros)
                    ->download('recepcion_surmar.xlsx');

            case 'CSV':
                return (new RecepcionProveedorSurmarListadoExport($this->service))
                    ->parametros($filtros)
                    ->download('recepcion_surmar.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('recepcion_proveedor_surmar', RecepcionProveedorSurmarListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-recepcion-proveedor-surmar');

        $this->assertSurmar();
        return view('stock.recepcion_proveedor_surmar.crear', [
            'empresa_id' => SurmarSupport::EMPRESA_ID,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function guardar(Request $request)
    {
        can('crear-recepcion-proveedor-surmar');

        $this->assertSurmar();
        $data = $request->validate([
            'ordencompra_id' => 'required|integer|min:1',
            'proveedor_id' => 'nullable|integer|min:1',
            'deposito_id' => 'required|integer|min:1',
            'fecha' => 'required|date',
            'observacion' => 'nullable|string|max:500',
            'moneda_id' => 'nullable|integer|min:1',
            'cotizacion' => 'nullable|numeric|min:0',
            'certificado_senasa' => 'nullable|string|max:30',
            'tropa' => 'nullable|integer|min:0|max:999999',
            'temperatura_ingreso' => 'nullable|numeric',
            'destino_senasa' => 'nullable|string|max:60',
            'camara' => 'nullable|string|max:60',
            'nro_establecimiento' => 'nullable|integer|min:0|max:10000',
        ]);

        try {
            $recepcion = $this->service->iniciar($data);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $nroOc = optional($recepcion->ordencompras)->numeroordencompra;

        return redirect()
            ->route('cargar_recepcion_proveedor_surmar', $recepcion->id)
            ->with(
                'mensaje',
                'Recepción provisoria Nº '.$recepcion->numerorecepcion
                .($nroOc ? ' (OC '.$nroOc.')' : '')
                .'. Cargue ítems con lote/peso; cada línea se graba y emite etiqueta al aceptar.'
            );
    }

    public function cargar(Request $request, int $id)
    {
        can('editar-recepcion-proveedor-surmar');
        $this->assertSurmar();
        $recepcion = $this->service->buscar($id);

        $lineas = RecepcionProveedorArticuloSurmar::query()
            ->with(['articulos', 'unidadesmedida', 'separaUnidadmedida', 'stock_etiqueta'])
            ->where('recepcion_proveedor_id', $recepcion->id)
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->map(fn ($l) => $this->service->lineaPayload($l))
            ->values()
            ->all();

        $unidadesmedida = SurmarUnidadmedidaSeparaSupport::listadoParaSelector(true);

        return view('stock.recepcion_proveedor_surmar.cargar', [
            'recepcion' => $recepcion,
            'lineas' => $lineas,
            'lineasOc' => $this->service->lineasOcPendientes($recepcion),
            'editable' => $recepcion->estado === Recepcion_Proveedor::ESTADO_BORRADOR,
            'empresa_id' => SurmarSupport::EMPRESA_ID,
            'solapa' => $request->query('solapa', 'items') === 'encabezado' ? 'encabezado' : 'items',
            'unidadesmedida' => $unidadesmedida,
            'separaDefaultId' => SurmarUnidadmedidaSeparaSupport::idDefault(),
            'proveedorNombreEtiqueta' => (string) (
                $recepcion->proveedores->fantasia
                ?? $recepcion->proveedores->nombre
                ?? 'proveedor'
            ),
        ]);
    }

    public function actualizarEncabezado(Request $request, int $id)
    {
        can('editar-recepcion-proveedor-surmar');

        $this->assertSurmar();
        $data = $request->validate([
            'fecha' => 'required|date',
            'deposito_id' => 'required|integer|min:1',
            'observacion' => 'nullable|string|max:500',
            'certificado_senasa' => 'nullable|string|max:30',
            'tropa' => 'nullable|integer|min:0|max:999999',
            'temperatura_ingreso' => 'nullable|numeric',
            'destino_senasa' => 'nullable|string|max:60',
            'camara' => 'nullable|string|max:60',
            'nro_establecimiento' => 'nullable|integer|min:0|max:10000',
            'deposito_codigo' => 'nullable|string|max:30',
            'deposito_descripcion' => 'nullable|string|max:120',
            'volver_solapa' => 'nullable|string|in:items,encabezado',
        ]);

        $solapaDestino = ($request->input('volver_solapa') === 'items') ? 'items' : 'encabezado';
        unset($data['volver_solapa']);

        try {
            $this->service->actualizarEncabezado($id, $data);
        } catch (ValidationException $e) {
            return redirect()
                ->route('cargar_recepcion_proveedor_surmar', ['id' => $id, 'solapa' => 'encabezado'])
                ->withErrors($e->errors())
                ->withInput();
        }

        return redirect()
            ->route('cargar_recepcion_proveedor_surmar', ['id' => $id, 'solapa' => $solapaDestino])
            ->with('mensaje', 'Encabezado / datos SENASA actualizados.');
    }

    public function apiBuscarOcPendientes(Request $request): JsonResponse
    {
        can('crear-recepcion-proveedor-surmar');
        $this->assertSurmar();
        $consulta = $request->query('q');
        $consulta = is_string($consulta) ? trim($consulta) : null;

        return response()->json(
            RecepcionProveedorSurmarOcSupport::buscarPendientes($consulta !== '' ? $consulta : null)
        );
    }

    public function apiPrecargaOc(Request $request): JsonResponse
    {
        can('crear-recepcion-proveedor-surmar');

        $this->assertSurmar();
        $ordencompraId = (int) $request->input('ordencompra_id', 0);
        $numeroOc = (int) $request->input('numero_oc', 0);

        try {
            if ($ordencompraId > 0) {
                $data = RecepcionProveedorSurmarOcSupport::resolver($ordencompraId, true);
            } elseif ($numeroOc > 0) {
                $data = RecepcionProveedorSurmarOcSupport::resolverPorNumero($numeroOc, true);
            } else {
                return response()->json(['error' => 'Indique número de OC o selecciónela del listado.'], 422);
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $oc = $data['cabecera'];

        return response()->json([
            'ordencompra_id' => $oc->id,
            'numeroordencompra' => $oc->numeroordencompra,
            'empresa_id' => $oc->empresa_id,
            'centrocosto_id' => $oc->centrocosto_id,
            'proveedor_id' => $oc->proveedor_id,
            'proveedor_nombre' => optional($oc->proveedores)->nombre,
            'proveedor_codigo' => optional($oc->proveedores)->codigo,
            'empresa_nombre' => optional($oc->empresas)->nombre,
            'lineas' => $data['lineas'],
        ]);
    }

    public function apiGuardarLinea(Request $request, int $id)
    {
        can('editar-recepcion-proveedor-surmar');

        $this->assertSurmar();
        $data = $request->validate([
            'ordencompra_articulo_id' => 'nullable|integer|min:1',
            'articulo_id' => 'required|integer|min:1',
            'lote_proveedor' => 'nullable|string|max:30',
            'certificado' => 'nullable|string|max:30',
            'fecha_vto' => 'nullable|date',
            'peso_bruto' => 'nullable|numeric|min:0',
            'peso_tara' => 'nullable|numeric|min:0',
            'peso_neto' => 'nullable|numeric|min:0',
            'cant_pieza' => 'nullable|numeric|min:0',
            'separa_unidadmedida_id' => 'nullable|integer|min:1',
            'cant_unid_separa' => 'nullable|integer|min:1|max:50',
            'nro_apertura' => 'nullable|integer|min:1|max:50',
            'unidadmedida_id' => 'nullable|integer|min:1',
            'precio' => 'nullable|numeric|min:0',
            'detalle' => 'nullable|string|max:255',
            'copias' => 'nullable|integer|min:1|max:10',
            'imprimir' => 'nullable|boolean',
        ]);

        try {
            $result = $this->service->guardarLineaProvisoria($id, $data);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        $recepcion = $this->service->buscar($id);
        $lineasPayload = array_map(
            fn ($l) => $this->service->lineaPayload($l),
            $result['lineas'] ?? [$result['linea']]
        );
        $copias = max(1, min(10, (int) ($data['copias'] ?? 1)));
        $zplsOut = null;
        if (! empty($data['imprimir'])) {
            $zplsOut = [];
            foreach ($result['zpls'] ?? [$result['zpl']] as $zpl) {
                for ($c = 0; $c < $copias; $c++) {
                    $zplsOut[] = $zpl;
                }
            }
        }

        $nro = (int) ($result['nro_apertura'] ?? $result['linea']->nro_apertura ?? 1);
        $cantUnid = (int) ($result['cant_unid_separa'] ?? $data['cant_unid_separa'] ?? 1);

        return response()->json([
            'ok' => true,
            'linea' => $lineasPayload[count($lineasPayload) - 1],
            'lineas_creadas' => $lineasPayload,
            'lineas_oc' => $this->service->lineasOcPendientes($recepcion),
            'etiqueta_id' => $result['etiqueta']->id ?? null,
            'nro_apertura' => $nro,
            'cant_unid_separa' => $cantUnid,
            'proxima_apertura' => (int) ($result['proxima_apertura'] ?? ($nro + 1)),
            'zpl' => $zplsOut[0] ?? null,
            'zpls' => $zplsOut,
            'preview' => $this->service->previewDesdeEtiqueta($result['etiqueta'], $recepcion),
            'mensaje' => 'Unidad '.$nro.' de '.$cantUnid.' grabada — etiqueta #'.($result['etiqueta']->id ?? ''),
        ]);
    }

    public function apiActualizarLinea(Request $request, int $id, int $lineaId)
    {
        can('editar-recepcion-proveedor-surmar');

        $this->assertSurmar();
        $data = $request->validate([
            'lote_proveedor' => 'nullable|string|max:30',
            'certificado' => 'nullable|string|max:30',
            'fecha_vto' => 'nullable|date',
            'peso_bruto' => 'nullable|numeric|min:0',
            'peso_tara' => 'nullable|numeric|min:0',
            'peso_neto' => 'nullable|numeric|min:0',
            'cant_pieza' => 'nullable|numeric|min:0',
            'separa_unidadmedida_id' => 'nullable|integer|min:1',
            'cant_unid_separa' => 'nullable|integer|min:1|max:999',
            'nro_apertura' => 'nullable|integer|min:1|max:9999',
            'copias' => 'nullable|integer|min:1|max:10',
            'imprimir' => 'nullable|boolean',
        ]);

        try {
            $result = $this->service->actualizarLineaProvisoria($id, $lineaId, $data);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        $copias = max(1, min(10, (int) ($data['copias'] ?? 1)));
        $zplsOut = null;
        if (! empty($data['imprimir'])) {
            $zplsOut = array_fill(0, $copias, $result['zpl']);
        }

        return response()->json([
            'ok' => true,
            'linea' => $this->service->lineaPayload($result['linea']),
            'etiqueta_id' => $result['etiqueta']->id,
            'zpl' => $zplsOut[0] ?? null,
            'zpls' => $zplsOut,
            'preview' => $result['preview'],
            'mensaje' => 'Etiqueta #'.$result['etiqueta']->id.' actualizada',
        ]);
    }

    public function apiPreviewEtiqueta(int $id, int $etiquetaId): JsonResponse
    {
        can('editar-recepcion-proveedor-surmar');
        $this->assertSurmar();
        $recepcion = $this->service->buscar($id);
        $zpl = $this->service->zplEtiqueta($etiquetaId);
        // Re-fetch etiqueta for preview via service
        $etiqueta = \App\Models\Stock\Stock_Etiqueta::query()
            ->with(['articulos', 'unidadesmedida', 'separaUnidadmedida'])
            ->whereKey($etiquetaId)
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->firstOrFail();

        return response()->json([
            'ok' => true,
            'preview' => $this->service->previewDesdeEtiqueta($etiqueta, $recepcion),
            'zpl' => $zpl,
        ]);
    }

    public function apiEliminarLinea(int $id, int $lineaId)
    {
        can('editar-recepcion-proveedor-surmar');

        $this->assertSurmar();
        try {
            $this->service->eliminarLineaProvisoria($id, $lineaId);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        $recepcion = $this->service->buscar($id);

        return response()->json([
            'ok' => true,
            'lineas_oc' => $this->service->lineasOcPendientes($recepcion),
        ]);
    }

    public function confirmar(int $id)
    {
        can('confirmar-recepcion-proveedor-surmar');

        $this->assertSurmar();
        try {
            $recepcion = $this->service->confirmar($id);
        } catch (ValidationException $e) {
            return redirect()
                ->route('cargar_recepcion_proveedor_surmar', $id)
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('cargar_recepcion_proveedor_surmar', $recepcion->id)
            ->with('mensaje', 'Recepción Surmar confirmada. Stock generado.');
    }

    public function anular(int $id)
    {
        can('anular-recepcion-proveedor-surmar');

        $this->assertSurmar();
        try {
            $this->service->anular($id);
        } catch (ValidationException $e) {
            return redirect()
                ->route('cargar_recepcion_proveedor_surmar', $id)
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('recepcion_proveedor_surmar')
            ->with('mensaje', 'Recepción Surmar anulada.');
    }

    public function eliminar(Request $request, int $id)
    {
        can('anular-recepcion-proveedor-surmar');

        $this->assertSurmar();
        try {
            $this->service->eliminarBorrador($id);
        } catch (ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                $msg = collect($e->errors())->flatten()->implode(' ');

                return response()->json(['mensaje' => 'error', 'errores' => $msg ?: 'No se puede eliminar el borrador.'], 422);
            }

            return redirect()
                ->route('recepcion_proveedor_surmar')
                ->withErrors($e->errors());
        } catch (\Throwable $e) {
            report($e);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'mensaje' => 'error',
                    'errores' => 'No se pudo eliminar el borrador: '.$e->getMessage(),
                ], 500);
            }

            return redirect()
                ->route('recepcion_proveedor_surmar')
                ->withErrors(['eliminar' => 'No se pudo eliminar el borrador: '.$e->getMessage()]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['mensaje' => 'ok']);
        }

        return redirect()
            ->route('recepcion_proveedor_surmar')
            ->with('mensaje', 'Borrador Surmar eliminado.');
    }

    public function imprimirEtiqueta(int $etiquetaId)
    {
        can('imprimir-etiqueta-recepcion-surmar');
        $this->assertSurmar();
        $zpl = $this->service->zplEtiqueta($etiquetaId);

        return response($zpl, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="etiqueta_'.$etiquetaId.'.zpl"',
        ]);
    }

    /**
     * Envía etiqueta(s) a la impresora de red configurada (seteosalida / Zebra).
     */
    public function apiImprimirEtiquetaSalida(Request $request): JsonResponse
    {
        can('imprimir-etiqueta-recepcion-surmar');

        $this->assertSurmar();
        $data = $request->validate([
            'etiqueta_id' => 'nullable|integer|min:1',
            'etiqueta_ids' => 'nullable|array',
            'etiqueta_ids.*' => 'integer|min:1',
            'zpl' => 'nullable|string',
            'zpls' => 'nullable|array',
            'zpls.*' => 'string',
            'copias' => 'nullable|integer|min:1|max:10',
        ]);

        $copias = max(1, min(10, (int) ($data['copias'] ?? 1)));
        $zpls = [];

        if (! empty($data['zpls']) && is_array($data['zpls'])) {
            foreach ($data['zpls'] as $z) {
                $z = (string) $z;
                if ($z === '') {
                    continue;
                }
                for ($c = 0; $c < $copias; $c++) {
                    $zpls[] = $z;
                }
            }
        } elseif (! empty($data['zpl'])) {
            for ($c = 0; $c < $copias; $c++) {
                $zpls[] = (string) $data['zpl'];
            }
        } else {
            $ids = [];
            if (! empty($data['etiqueta_ids'])) {
                $ids = array_map('intval', $data['etiqueta_ids']);
            } elseif (! empty($data['etiqueta_id'])) {
                $ids = [(int) $data['etiqueta_id']];
            }
            if ($ids === []) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'Indique etiqueta_id o zpl para imprimir.',
                ], 422);
            }
            foreach ($ids as $id) {
                $zpl = $this->service->zplEtiqueta($id);
                for ($c = 0; $c < $copias; $c++) {
                    $zpls[] = $zpl;
                }
            }
        }

        $result = $this->impresionEtiquetaService->enviarZplAImpresora($zpls);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function pdfEtiqueta(int $etiquetaId)
    {
        can('imprimir-etiqueta-recepcion-surmar');
        $this->assertSurmar();
        $file = $this->impresionEtiquetaService->generarPdfEtiqueta($etiquetaId);

        return response()->download($file['path'], $file['filename'], [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function apiEstadoSalidaEtiqueta(): JsonResponse
    {
        can('listar-recepcion-proveedor-surmar');
        $this->assertSurmar();
        $salida = $this->impresionEtiquetaService->salidaConfigurada();

        return response()->json([
            'ok' => true,
            'programa' => SeteoSalidaProgramaSupport::STOCK_ETIQUETA_SURMAR,
            'destino_default' => $this->impresionEtiquetaService->destinoDefault(),
            'salida' => $salida,
            'tiene_salida' => $salida !== null,
        ]);
    }
}
