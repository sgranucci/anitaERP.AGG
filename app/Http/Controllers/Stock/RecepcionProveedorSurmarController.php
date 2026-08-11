<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\RecepcionProveedorArticuloSurmar;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Stock\RecepcionProveedorSurmarService;
use App\Support\Stock\RecepcionProveedorSurmarListadoFiltros;
use App\Support\Stock\Surmar\RecepcionProveedorSurmarOcSupport;
use App\Support\Stock\SurmarSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RecepcionProveedorSurmarController extends Controller
{
    public function __construct(
        private readonly RecepcionProveedorSurmarService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-recepcion-proveedor-surmar');
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

    public function crear()
    {
        can('crear-recepcion-proveedor-surmar');

        return view('stock.recepcion_proveedor_surmar.crear', [
            'empresa_id' => SurmarSupport::EMPRESA_ID,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function guardar(Request $request)
    {
        can('crear-recepcion-proveedor-surmar');

        $data = $request->validate([
            'ordencompra_id' => 'required|integer|min:1',
            'proveedor_id' => 'nullable|integer|min:1',
            'deposito_id' => 'required|integer|min:1',
            'fecha' => 'required|date',
            'observacion' => 'nullable|string|max:500',
            'moneda_id' => 'nullable|integer|min:1',
            'cotizacion' => 'nullable|numeric|min:0',
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

    public function cargar(int $id)
    {
        can('editar-recepcion-proveedor-surmar');
        $recepcion = $this->service->buscar($id);

        $lineas = RecepcionProveedorArticuloSurmar::query()
            ->with(['articulos', 'unidadesmedida', 'stock_etiqueta'])
            ->where('recepcion_proveedor_id', $recepcion->id)
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->map(fn ($l) => $this->service->lineaPayload($l))
            ->values()
            ->all();

        return view('stock.recepcion_proveedor_surmar.cargar', [
            'recepcion' => $recepcion,
            'lineas' => $lineas,
            'lineasOc' => $this->service->lineasOcPendientes($recepcion),
            'editable' => $recepcion->estado === Recepcion_Proveedor::ESTADO_BORRADOR,
            'empresa_id' => SurmarSupport::EMPRESA_ID,
        ]);
    }

    public function apiBuscarOcPendientes(Request $request): JsonResponse
    {
        can('crear-recepcion-proveedor-surmar');
        $consulta = $request->query('q');
        $consulta = is_string($consulta) ? trim($consulta) : null;

        return response()->json(
            RecepcionProveedorSurmarOcSupport::buscarPendientes($consulta !== '' ? $consulta : null)
        );
    }

    public function apiPrecargaOc(Request $request): JsonResponse
    {
        can('crear-recepcion-proveedor-surmar');

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

        $data = $request->validate([
            'ordencompra_articulo_id' => 'required|integer|min:1',
            'articulo_id' => 'required|integer|min:1',
            'lote_proveedor' => 'required|string|max:30',
            'certificado' => 'nullable|string|max:30',
            'fecha_vto' => 'nullable|date',
            'peso_bruto' => 'nullable|numeric|min:0',
            'peso_neto' => 'required|numeric|min:0.0001',
            'cant_pieza' => 'nullable|numeric|min:0',
            'unidadmedida_id' => 'nullable|integer|min:1',
            'precio' => 'nullable|numeric|min:0',
            'detalle' => 'nullable|string|max:255',
            'imprimir' => 'nullable|boolean',
        ]);

        try {
            $result = $this->service->guardarLineaProvisoria($id, $data);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        $recepcion = $this->service->buscar($id);

        return response()->json([
            'ok' => true,
            'linea' => $this->service->lineaPayload($result['linea']),
            'lineas_oc' => $this->service->lineasOcPendientes($recepcion),
            'etiqueta_id' => $result['etiqueta']->id,
            'zpl' => ! empty($data['imprimir']) ? $result['zpl'] : null,
            'mensaje' => 'Ítem grabado '.$result['linea']->hora_piqueo.' — etiqueta #'.$result['etiqueta']->id,
        ]);
    }

    public function apiEliminarLinea(int $id, int $lineaId)
    {
        can('editar-recepcion-proveedor-surmar');

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

    public function eliminar(int $id)
    {
        can('anular-recepcion-proveedor-surmar');

        try {
            $this->service->eliminarBorrador($id);
        } catch (ValidationException $e) {
            return redirect()
                ->route('cargar_recepcion_proveedor_surmar', $id)
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('recepcion_proveedor_surmar')
            ->with('mensaje', 'Borrador Surmar eliminado.');
    }

    public function imprimirEtiqueta(int $etiquetaId)
    {
        can('imprimir-etiqueta-recepcion-surmar');
        $zpl = $this->service->zplEtiqueta($etiquetaId);

        return response($zpl, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="etiqueta_'.$etiquetaId.'.zpl"',
        ]);
    }
}
