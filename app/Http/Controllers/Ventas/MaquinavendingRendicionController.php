<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\MaquinavendingRendicionListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMaquinavendingRendicion;
use App\Models\Caja\Cuentacaja;
use App\Models\Ventas\Maquinavending;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\MaquinavendingRendicionRepositoryInterface;
use App\Services\Ventas\MaquinavendingRendicionService;
use App\Support\Ventas\GastronomiaCuentacajaIconoSupport;
use App\Support\Ventas\GastronomiaCuentacajaSoloAutomaticaSupport;
use App\Support\Ventas\GastronomiaUsoCuentacajaSupport;
use App\Support\Ventas\MaquinavendingRendicionListadoFiltros;
use App\Support\Ventas\MaquinavendingRendicionPermiso;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MaquinavendingRendicionController extends Controller
{
    public function __construct(
        private MaquinavendingRendicionRepositoryInterface $repository,
        private MaquinavendingRendicionService $service,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-maquinavending-rendicion-gastronomia');

        $filtros = MaquinavendingRendicionListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = MaquinavendingRendicionListadoFiltros::paraQueryString($filtros);
        $coleccion = $this->repository->leeRendiciones($filtros, true);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $maquinas_query = $this->maquinasPorEmpresa((int) ($filtros['empresa_id'] ?? 0));

        return view('ventas.maquinavending_rendicion.index', compact(
            'coleccion',
            'filtros',
            'filtrosQuery',
            'empresa_query',
            'maquinas_query',
        ));
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-maquinavending-rendicion-gastronomia');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = MaquinavendingRendicionListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $filtrosQuery = MaquinavendingRendicionListadoFiltros::paraQueryString($filtros);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeRendiciones($filtros, false);
                $view = \View::make('ventas.maquinavending_rendicion.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_maquinavending_rendicion';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new MaquinavendingRendicionListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('rendiciones_vending.xlsx');

            case 'CSV':
                return (new MaquinavendingRendicionListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('rendiciones_vending.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_maquinavending_rendicion_gastronomia', $filtrosQuery);
    }

    public function crear()
    {
        can('crear-maquinavending-rendicion-gastronomia');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresaDefaultId = (int) ($empresa_query->first()?->id ?? config('cliente.EMPRESA_DEFAULT_ID'));
        $maquinas_query = $this->maquinasPorEmpresa($empresaDefaultId > 0 ? $empresaDefaultId : null);

        return view('ventas.maquinavending_rendicion.crear', compact(
            'empresa_query',
            'empresaDefaultId',
            'maquinas_query',
        ) + [
            'usocuentacaja_gastronomia_id' => GastronomiaUsoCuentacajaSupport::resolverId(),
        ]);
    }

    public function guardar(ValidacionMaquinavendingRendicion $request)
    {
        can('crear-maquinavending-rendicion-gastronomia');

        try {
            $rendicion = $this->service->guardar($request->validated());
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['general' => $e->getMessage()]);
        }

        $redirect = redirect()
            ->route('consultar_maquinavending_rendicion_gastronomia')
            ->with('mensaje', 'Rendición vending #'.$rendicion->numero_cierre.' (empresa) registrada correctamente.');

        if (can('ver-comprobante-maquinavending-rendicion-gastronomia', false)) {
            $redirect->with('url_comprobante_pdf', route('maquinavending_rendicion_comprobante', [
                'id' => $rendicion->id,
                'inline' => 1,
            ]));
        }

        return $redirect;
    }

    public function editar(int $id)
    {
        can('editar-maquinavending-rendicion-gastronomia');

        $rendicion = $this->repository->findOrFail($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $rendicion->empresa_id)) {
            abort(403);
        }
        if (! MaquinavendingRendicionPermiso::puedeModificar($rendicion)) {
            return redirect()
                ->route('consultar_maquinavending_rendicion_gastronomia')
                ->with('errores', [MaquinavendingRendicionPermiso::mensajeBloqueoModificacion($rendicion)]);
        }

        $empresa_query = $this->empresaRepository->allFiltrado();
        $maquinas_query = $this->maquinasPorEmpresa((int) $rendicion->empresa_id);

        return view('ventas.maquinavending_rendicion.editar', [
            'rendicion' => $rendicion,
            'empresa_query' => $empresa_query,
            'maquinas_query' => $maquinas_query,
            'usocuentacaja_gastronomia_id' => GastronomiaUsoCuentacajaSupport::resolverId(),
        ]);
    }

    public function actualizar(ValidacionMaquinavendingRendicion $request, int $id)
    {
        can('actualizar-maquinavending-rendicion-gastronomia');

        $rendicion = $this->repository->findOrFail($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $rendicion->empresa_id)) {
            abort(403);
        }

        try {
            $rendicion = $this->service->actualizar($rendicion, $request->validated());
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['general' => $e->getMessage()]);
        }

        $redirect = redirect()
            ->route('consultar_maquinavending_rendicion_gastronomia')
            ->with('mensaje', 'Rendición vending #'.$rendicion->numero_cierre.' actualizada correctamente.');

        if (can('ver-comprobante-maquinavending-rendicion-gastronomia', false)) {
            $redirect->with('url_comprobante_pdf', route('maquinavending_rendicion_comprobante', [
                'id' => $rendicion->id,
                'inline' => 1,
            ]));
        }

        return $redirect;
    }

    public function eliminar(Request $request, int $id)
    {
        can('borrar-maquinavending-rendicion-gastronomia');

        $rendicion = $this->repository->findOrFail($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $rendicion->empresa_id)) {
            abort(403);
        }

        $numeroCierre = (int) $rendicion->numero_cierre;

        try {
            $this->service->eliminar($rendicion);
        } catch (InvalidArgumentException $e) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()]);
            }

            return redirect()
                ->route('consultar_maquinavending_rendicion_gastronomia')
                ->with('errores', [$e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('maquinavending_rendicion.eliminar.fallo', [
                'rendicion_id' => $id,
                'mensaje' => $e->getMessage(),
            ]);

            $mensaje = $this->mensajeErrorEliminarRendicion($e);

            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ng', 'error' => $mensaje]);
            }

            return redirect()
                ->route('consultar_maquinavending_rendicion_gastronomia')
                ->with('errores', [$mensaje]);
        }

        if ($request->ajax()) {
            return response()->json(['mensaje' => 'ok']);
        }

        return redirect()
            ->route('consultar_maquinavending_rendicion_gastronomia')
            ->with('mensaje', 'Rendición vending #'.$numeroCierre.' eliminada correctamente.');
    }

    private function mensajeErrorEliminarRendicion(\Throwable $e): string
    {
        $msg = mb_strtolower($e->getMessage());

        if (str_contains($msg, 'foreign key constraint') || str_contains($msg, '1451')) {
            return 'No se puede eliminar: existe una presentación en caja vinculada. '
                .'Anúlela primero en Caja → Rendiciones vending.';
        }

        return 'No se pudo eliminar la rendición vending.';
    }

    public function comprobante(Request $request, int $id)
    {
        can('ver-comprobante-maquinavending-rendicion-gastronomia');

        $rendicion = $this->repository->findOrFail($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $rendicion->empresa_id)) {
            abort(403);
        }

        $datos = $this->service->datosComprobante($rendicion);
        $pdf = Pdf::loadView('ventas.maquinavending_rendicion.comprobante', ['d' => $datos])
            ->setPaper('legal', 'portrait');

        $nombre = 'rendicion_vending_'.$rendicion->numero_cierre.'_'.(int) $rendicion->maquinavending_id.'.pdf';

        if ($request->boolean('inline')) {
            return $pdf->stream($nombre);
        }

        return $pdf->download($nombre);
    }

    public function apiMaquinasPorEmpresa(int $empresaId): JsonResponse
    {
        $this->puedeUsarFormularioRendicion();

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json(['error' => 'Empresa no permitida.'], 403);
        }

        $maquinas = Maquinavending::query()
            ->with('puntoventa:id,codigo,nombre')
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'puntoventa_id']);

        return response()->json([
            'maquinas' => $maquinas->map(static fn ($m) => [
                'id' => (int) $m->id,
                'nombre' => (string) $m->nombre,
                'puntoventa_codigo' => (string) ($m->puntoventa->codigo ?? ''),
                'puntoventa_nombre' => (string) ($m->puntoventa->nombre ?? ''),
                'etiqueta' => trim(($m->puntoventa->codigo ?? '').' — '.$m->nombre, ' —'),
            ])->values(),
        ]);
    }

    public function apiArticulosMaquina(Request $request, int $maquinavendingId): JsonResponse
    {
        $this->puedeUsarFormularioRendicion();

        $empresaId = (int) $request->input('empresa_id', 0);
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json(['error' => 'Empresa no permitida.'], 403);
        }

        try {
            $fechaJornada = $request->input('fecha_jornada');
            $articulos = $this->service->articulosParaMaquina(
                $maquinavendingId,
                $empresaId,
                is_string($fechaJornada) && $fechaJornada !== '' ? $fechaJornada : null,
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['articulos' => $articulos]);
    }

    public function apiCuentasCaja(Request $request): JsonResponse
    {
        $this->puedeUsarFormularioRendicion();

        $empresaId = (int) $request->input('empresa_id', 0);
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json(['error' => 'Empresa no permitida.'], 403);
        }

        $usoId = GastronomiaUsoCuentacajaSupport::resolverId();
        if (! $usoId) {
            return response()->json([
                'error' => 'No está configurado el uso de cuenta de caja para gastronomía (usocuentacaja «Gastronomia» o GASTRONOMIA_USO_CUENTACAJA_ID).',
                'cuentas_caja' => [],
            ], 422);
        }

        $excluidas = GastronomiaCuentacajaSoloAutomaticaSupport::idsParaEmpresa($empresaId);

        $cuentas = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->whereHas('usocuentacajas', fn ($r) => $r->whereKey($usoId))
            ->when($excluidas !== [], fn ($q) => $q->whereNotIn('id', $excluidas))
            ->with('monedas:id,abreviatura,nombre')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo', 'moneda_id']);

        return response()->json([
            'usocuentacaja_id' => $usoId,
            'cuentas_caja' => $cuentas->map(function ($c) {
                $presentacion = GastronomiaCuentacajaIconoSupport::presentacion((string) $c->nombre, (string) $c->codigo);

                return [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'codigo' => $c->codigo,
                    'moneda_id' => $c->moneda_id,
                    'moneda_abreviatura' => $c->monedas->abreviatura ?? null,
                    'icono' => $presentacion['icono'],
                    'icono_color' => $presentacion['color'],
                    'etiqueta_boton' => $presentacion['etiqueta_boton'] ?? null,
                ];
            })->values(),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Maquinavending> */
    private function maquinasPorEmpresa(?int $empresaId)
    {
        $query = Maquinavending::query()
            ->with('puntoventa:id,codigo,nombre')
            ->orderBy('nombre');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->get(['id', 'nombre', 'empresa_id', 'puntoventa_id']);
    }

    private function puedeUsarFormularioRendicion(): void
    {
        if (can('crear-maquinavending-rendicion-gastronomia', false)
            || can('editar-maquinavending-rendicion-gastronomia', false)
            || can('actualizar-maquinavending-rendicion-gastronomia', false)) {
            return;
        }

        abort(403);
    }
}
