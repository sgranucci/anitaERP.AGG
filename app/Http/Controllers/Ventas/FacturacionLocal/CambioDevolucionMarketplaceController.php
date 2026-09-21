<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCambioDevolucionMarketplace;
use App\Models\Ventas\CambioDevolucionMarketplace;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionLocal\CambioDevolucionMarketplaceService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceCatalogoSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceEstadosSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceListadoFiltros;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceLiquidacionSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplacePuenteSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

/**
 * Legajo cambio/devolución marketplace — solo Facturación Local Ferli.
 * No afecta Facturación POS gastronomía AGG.
 */
class CambioDevolucionMarketplaceController extends Controller
{
    public function __construct(
        private readonly CambioDevolucionMarketplaceService $service,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-cambio-devolucion-marketplace-facturacion-local');

        $filtros = CambioDevolucionMarketplaceListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->leeListado($filtros, true);
        $locales = LocalVenta::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']);

        return view('ventas.facturacion_local.cambio_devolucion.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => CambioDevolucionMarketplaceListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => CambioDevolucionMarketplaceListadoFiltros::CAMPOS,
            'locales' => $locales,
            'estados' => CambioDevolucionMarketplaceEstadosSupport::opcionesSelect(),
            'canales' => CambioDevolucionMarketplaceCatalogoSupport::CANALES,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        $this->assertFerli();
        can('listar-cambio-devolucion-marketplace-facturacion-local');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = CambioDevolucionMarketplaceListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $datas = $this->leeListado($filtros, false);

        if ($formato === 'PDF') {
            $view = View::make('ventas.facturacion_local.cambio_devolucion.listado', compact('datas', 'filtros'))->render();
            $path = storage_path('pdf/listados');
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
            $nombrePdf = 'listado_cambio_devolucion_marketplace';
            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal', 'landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

            return response()->download($path.'/'.$nombrePdf.'.pdf');
        }

        return redirect()->route(
            'facturacion_local_cambios_devolucion',
            CambioDevolucionMarketplaceListadoFiltros::paraQueryString($filtros)
        );
    }

    public function crear()
    {
        $this->assertFerli();
        can('crear-cambio-devolucion-marketplace-facturacion-local');

        $data = new CambioDevolucionMarketplace([
            'canal' => CambioDevolucionMarketplaceCatalogoSupport::CANAL_TIENDANUBE,
            'estado' => CambioDevolucionMarketplaceEstadosSupport::BORRADOR,
        ]);

        return view('ventas.facturacion_local.cambio_devolucion.crear', $this->formData($data));
    }

    public function guardar(ValidacionCambioDevolucionMarketplace $request)
    {
        $this->assertFerli();
        can('crear-cambio-devolucion-marketplace-facturacion-local');

        $cambio = $this->service->crear($request->validated(), $request->lineasNormalizadas());
        $this->service->sincronizarArchivos(
            $cambio,
            $request->file('nombrearchivos', []) ?: [],
            []
        );

        return redirect()
            ->route('editar_cambio_devolucion_marketplace', $cambio->id)
            ->with('mensaje', 'Legajo Nº '.$cambio->numero.' creado.');
    }

    public function editar($id)
    {
        $this->assertFerli();
        can('ver-cambio-devolucion-marketplace-facturacion-local');

        $data = CambioDevolucionMarketplace::query()
            ->with([
                'lineas.articulo',
                'estados.usuario',
                'archivos',
                'localVenta',
                'ventaOriginal',
                'ventaReemplazo',
                'ventaNc',
                'tiendanubePedido',
            ])
            ->findOrFail($id);

        return view('ventas.facturacion_local.cambio_devolucion.editar', $this->formData($data));
    }

    public function actualizar(ValidacionCambioDevolucionMarketplace $request, $id)
    {
        $this->assertFerli();
        can('actualizar-cambio-devolucion-marketplace-facturacion-local');

        $cambio = CambioDevolucionMarketplace::query()->findOrFail($id);
        try {
            $this->service->actualizar($cambio, $request->validated(), $request->lineasNormalizadas());
            $this->service->sincronizarArchivos(
                $cambio->fresh(),
                $request->file('nombrearchivos', []) ?: [],
                $request->input('nombresanteriores', []) ?: []
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('editar_cambio_devolucion_marketplace', $id)
            ->with('mensaje', 'Legajo actualizado.');
    }

    public function confirmar($id)
    {
        $this->assertFerli();
        can('actualizar-cambio-devolucion-marketplace-facturacion-local');

        try {
            $cambio = $this->service->confirmar(CambioDevolucionMarketplace::query()->findOrFail($id));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('editar_cambio_devolucion_marketplace', $cambio->id)
            ->with('mensaje', 'Legajo confirmado (abierto).');
    }

    public function emitirFac($id)
    {
        $this->assertFerli();
        can('emitir-fac-cambio-devolucion-marketplace-facturacion-local');

        try {
            $resultado = $this->service->emitirFacReemplazo(CambioDevolucionMarketplace::query()->findOrFail($id));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (empty($resultado['ok'])) {
            return back()->with('error', $resultado['error'] ?? 'Error al emitir FAC.');
        }

        return redirect()
            ->route('editar_cambio_devolucion_marketplace', $id)
            ->with('mensaje', $resultado['mensaje'] ?? 'FAC emitida.');
    }

    public function registrarRecepcion(Request $request, $id)
    {
        $this->assertFerli();
        can('registrar-recepcion-cambio-devolucion-marketplace-facturacion-local');

        $disposicion = (string) $request->input('disposicion', '');
        $obs = trim((string) $request->input('observacion_recepcion', ''));

        try {
            $cambio = $this->service->registrarRecepcion(
                CambioDevolucionMarketplace::query()->findOrFail($id),
                $disposicion,
                $obs !== '' ? $obs : null
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('editar_cambio_devolucion_marketplace', $cambio->id)
            ->with('mensaje', 'Recepción registrada.');
    }

    public function emitirNc($id)
    {
        $this->assertFerli();
        can('emitir-nc-cambio-devolucion-marketplace-facturacion-local');

        try {
            $resultado = $this->service->emitirNcOriginal(CambioDevolucionMarketplace::query()->findOrFail($id));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (empty($resultado['ok'])) {
            return back()->with('error', $resultado['error'] ?? 'Error al emitir NC.');
        }

        return redirect()
            ->route('editar_cambio_devolucion_marketplace', $id)
            ->with('mensaje', $resultado['mensaje'] ?? 'NC emitida.');
    }

    public function registrarCompensacion(Request $request, $id)
    {
        $this->assertFerli();
        can('registrar-compensacion-cambio-devolucion-marketplace-facturacion-local');

        $obs = trim((string) $request->input('compensacion_observacion', ''));
        if ($obs === '') {
            return back()->with('error', 'Indique la observación de compensación (medio de pago, importe, etc.).');
        }

        try {
            $cambio = $this->service->registrarCompensacion(
                CambioDevolucionMarketplace::query()->findOrFail($id),
                $obs
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('editar_cambio_devolucion_marketplace', $cambio->id)
            ->with('mensaje', 'Compensación registrada. Legajo cerrado.');
    }

    public function anular(Request $request, $id)
    {
        $this->assertFerli();
        can('anular-cambio-devolucion-marketplace-facturacion-local');

        $obs = trim((string) $request->input('observacion_anulacion', ''));
        if ($obs === '') {
            return back()->with('error', 'Indique el motivo de anulación.');
        }

        try {
            $cambio = $this->service->anular(CambioDevolucionMarketplace::query()->findOrFail($id), $obs);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('editar_cambio_devolucion_marketplace', $cambio->id)
            ->with('mensaje', 'Legajo anulado.');
    }

    public function descargarArchivo($id, $archivoId)
    {
        $this->assertFerli();
        can('ver-cambio-devolucion-marketplace-facturacion-local');

        $cambio = CambioDevolucionMarketplace::query()->findOrFail($id);
        $archivo = $cambio->archivos()->whereKey($archivoId)->firstOrFail();
        $path = $this->service->directorioArchivos((int) $cambio->id).'/'.$archivo->nombrearchivo;
        if (! is_file($path)) {
            abort(404);
        }

        return response()->download($path, $archivo->nombrearchivo);
    }

    public function apiBuscarVenta(Request $request)
    {
        $this->assertFerli();
        if (! can('crear-cambio-devolucion-marketplace-facturacion-local', false)
            && ! can('actualizar-cambio-devolucion-marketplace-facturacion-local', false)) {
            abort(403);
        }

        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $ventas = Venta::query()
            ->with(['clientes:id,nombre,numerodocumento'])
            ->where(function ($builder) use ($q) {
                $builder->where('codigo', 'like', '%'.$q.'%')
                    ->orWhere('id', ctype_digit($q) ? (int) $q : 0);
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'codigo', 'fecha', 'total', 'cliente_id']);

        $data = $ventas->map(function (Venta $v) {
            return [
                'id' => (int) $v->id,
                'codigo' => (string) $v->codigo,
                'fecha' => optional($v->fecha)->format('d/m/Y'),
                'total' => (float) $v->total,
                'cliente' => trim((string) ($v->clientes->nombre ?? '')),
                'documento' => trim((string) ($v->clientes->numerodocumento ?? '')),
                'cliente_id' => (int) ($v->cliente_id ?? 0),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(CambioDevolucionMarketplace $data): array
    {
        $locales = LocalVenta::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre', 'empresa_id']);
        $editable = ! $data->exists
            || CambioDevolucionMarketplaceEstadosSupport::esEditable((string) $data->estado);
        $puente = CambioDevolucionMarketplacePuenteSupport::resolverCuentacaja();

        return [
            'data' => $data,
            'locales' => $locales,
            'canales' => CambioDevolucionMarketplaceCatalogoSupport::CANALES,
            'motivos' => CambioDevolucionMarketplaceCatalogoSupport::MOTIVOS,
            'disposiciones' => CambioDevolucionMarketplaceCatalogoSupport::DISPOSICIONES,
            'tiposLinea' => CambioDevolucionMarketplaceCatalogoSupport::TIPOS_LINEA,
            'estadosEtiquetas' => CambioDevolucionMarketplaceEstadosSupport::ETIQUETAS,
            'sentidoEtiquetas' => CambioDevolucionMarketplaceLiquidacionSupport::ETIQUETAS_SENTIDO,
            'editable' => $editable,
            'puenteCuentacaja' => $puente,
        ];
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    private function leeListado(array $filtros, bool $paginar)
    {
        $query = CambioDevolucionMarketplace::query()
            ->select('cambio_devolucion_marketplace.*')
            ->leftJoin('local_venta', 'local_venta.id', '=', 'cambio_devolucion_marketplace.local_venta_id')
            ->with(['localVenta:id,codigo,nombre', 'ventaOriginal:id,codigo', 'ventaReemplazo:id,codigo']);

        if (CambioDevolucionMarketplaceListadoFiltros::tieneCriteriosAplicados($filtros)
            || ! empty($filtros['solo_pendientes'])
            || trim((string) ($filtros['estado'] ?? '')) !== ''
            || trim((string) ($filtros['canal'] ?? '')) !== ''
            || (int) ($filtros['local_venta_id'] ?? 0) > 0) {
            CambioDevolucionMarketplaceListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderByDesc('cambio_devolucion_marketplace.numero');

        return $paginar ? $query->paginate(15) : $query->get();
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
