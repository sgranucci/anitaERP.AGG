<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\Bingo\RendicionBingoCajaService;
use App\Support\Caja\Bingo\RendicionBingoCajaListadoFiltros;
use App\Support\Caja\Bingo\RendicionBingoCajaPermiso;
use App\Support\Contable\CierreRendicionOrigenConsultaSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RendicionBingoController extends Controller
{
    public function __construct(
        private readonly RendicionBingoCajaService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-rendicion-bingo-caja');

        $filtros = $this->resolverFiltrosListado($request);
        $rendiciones = $this->service->listar($filtros, true);

        return view('caja.rendicionbingo.index', [
            'rendiciones' => $rendiciones,
            'filtros' => $filtros,
            'filtrosQuery' => RendicionBingoCajaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => RendicionBingoCajaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, ?string $formato = null, ?string $busqueda = null)
    {
        can('listar-rendicion-bingo-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $rendiciones = $this->service->listar($filtros, false);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('caja.rendicionbingo.listado', compact('rendiciones'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_rendicion_bingo_caja';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
            case 'CSV':
                $mime = $formato === 'CSV'
                    ? \Maatwebsite\Excel\Excel::CSV
                    : \Maatwebsite\Excel\Excel::XLSX;
                $ext = $formato === 'CSV' ? 'csv' : 'xlsx';

                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\Caja\RendicionBingoCajaExport($rendiciones, $formato === 'CSV'),
                    'rendicion_bingo_caja.'.$ext,
                    $mime,
                );
        }

        return view('caja.rendicionbingo.index', [
            'rendiciones' => $rendiciones,
            'filtros' => $filtros,
            'filtrosQuery' => RendicionBingoCajaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => RendicionBingoCajaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaDefault = optional($empresaQuery->first())->id;
        $filtros = RendicionBingoCajaListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $empresaDefault ? (int) $empresaDefault : null
        );

        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        $filtros['empresas_asignadas'] = $asignadas;

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0 && $asignadas !== [] && ! in_array($empresaId, $asignadas, true)) {
            $filtros['empresa_id'] = $this->resolverEmpresaDefaultId($empresaQuery);
            $filtros['empresa_scope'] = ((int) $filtros['empresa_id']) > 0 ? 'una' : 'todas';
        }

        return $filtros;
    }

    public function crear(?int $caja = null)
    {
        can('crear-rendicion-bingo-caja');

        [$cajaId, $nombreCaja] = $this->resolverCaja($caja);
        if ($cajaId <= 0) {
            return redirect()
                ->route('rendicionbingo')
                ->with('errores', [\App\Support\Caja\CajaRecepcionPcSupport::requiereAsignacion()
                    ? 'No tiene caja asignada para hoy. Debe ingresar desde Movimientos de caja o solicitar asignación de cajero.'
                    : 'No se pudo determinar la caja de recepción. Configure la caja en el punto de venta de esta PC o verifique CAJA_DEFAULT_ID.']);
        }

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaDefaultId = (int) ($empresaQuery->first()->id ?? 0);

        return view('caja.rendicionbingo.crear', [
            'caja_id' => $cajaId,
            'nombreCaja' => $nombreCaja,
            'empresa_query' => $empresaQuery,
            'empresa_default_id' => $empresaDefaultId,
            'codigo_propuesto' => $empresaDefaultId > 0 ? $this->service->proponerCodigoAnita($empresaDefaultId) : '',
        ]);
    }

    public function guardar(Request $request)
    {
        can('crear-rendicion-bingo-caja');

        $request->validate([
            'turno_operativo_bingo_id' => 'required|integer|min:1',
            'empresa_id' => 'required|integer|min:1',
            'codigo' => 'required|string|max:50',
            'observacion' => 'nullable|string|max:500',
            'caja_id' => 'nullable|integer|min:1',
        ]);

        $empresaId = (int) $request->input('empresa_id');
        $this->assertEmpresaPermitida($empresaId);

        try {
            $rendicion = $this->service->guardarPresentacionCaja([
                'turno_operativo_bingo_id' => (int) $request->input('turno_operativo_bingo_id'),
                'empresa_id' => $empresaId,
                'codigo' => trim((string) $request->input('codigo')),
                'observacion' => $request->input('observacion'),
                'caja_id' => (int) $request->input('caja_id', 0),
            ]);
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('errores', [$e->getMessage()]);
        }

        return redirect()
            ->route('rendicionbingo')
            ->with('mensaje', 'Rendición bingo presentada en caja con éxito')
            ->with('url_comprobante_pdf', route('imprimir_rendicion_bingo', [
                'id' => $rendicion->id,
                'inline' => 1,
            ]));
    }

    public function eliminar(Request $request, int $id)
    {
        can('borrar-rendicion-bingo-caja');

        $rendicion = RendicionBingoCaja::query()->findOrFail($id);
        $this->assertEmpresaPermitida((int) $rendicion->empresa_id);

        if (! RendicionBingoCajaPermiso::puedeEliminarPorFecha($rendicion)) {
            $mensaje = RendicionBingoCajaPermiso::mensajeRestriccionFecha();
            if ($request->ajax()) {
                return response()->json(['mensaje' => $mensaje]);
            }

            return redirect()
                ->route('rendicionbingo', RendicionBingoCajaListadoFiltros::paraQueryString($this->resolverFiltrosListado($request)))
                ->with('errores', [$mensaje]);
        }

        if ($request->ajax()) {
            try {
                $this->service->eliminar($id);

                return response()->json(['mensaje' => 'ok']);
            } catch (\Throwable $e) {
                return response()->json(['mensaje' => $e->getMessage() ?: 'ng']);
            }
        }

        try {
            $this->service->eliminar($id);

            return redirect()
                ->route('rendicionbingo', RendicionBingoCajaListadoFiltros::paraQueryString($this->resolverFiltrosListado($request)))
                ->with('mensaje', 'Rendición bingo eliminada con éxito');
        } catch (\Throwable $e) {
            return redirect()
                ->route('rendicionbingo', RendicionBingoCajaListadoFiltros::paraQueryString($this->resolverFiltrosListado($request)))
                ->with('errores', [$e->getMessage() ?: 'No se pudo eliminar la rendición']);
        }
    }

    public function apiConsultaCierre(Request $request): JsonResponse
    {
        can('crear-rendicion-bingo-caja');

        $empresaId = (int) $request->input('empresa_id', 0);
        $this->assertEmpresaPermitida($empresaId);

        $puedeVerComprobante = can('ver-comprobante-cierre-turno-bingo', false);

        $resultado = $this->service->consultaCierresTurno(
            trim((string) $request->input('consulta', '')),
            $empresaId,
            null,
            $puedeVerComprobante,
        );

        return response()->json($resultado);
    }

    public function apiDatosTurno(Request $request): JsonResponse
    {
        can('crear-rendicion-bingo-caja');

        $turnoId = (int) $request->input('turno_operativo_bingo_id', 0);
        if ($turnoId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Turno inválido.'], 422);
        }

        try {
            $datos = $this->service->datosDesdeTurno($turnoId);

            return response()->json(['ok' => true, 'datos' => $datos]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function imprimir(int $id)
    {
        CierreRendicionOrigenConsultaSupport::exigir(
            CierreRendicionOrigenConsultaSupport::puedeVerPdfRendicionBingo(),
        );
        $rendicion = RendicionBingoCaja::query()
            ->with(['empresa', 'turnoOperativo.turno', 'turnoOperativo.usuarioHabilitado', 'jornada', 'creousuario'])
            ->findOrFail($id);
        $this->assertEmpresaPermitida((int) $rendicion->empresa_id);

        $view = view('caja.rendicionbingo.comprobante', ['rendicion' => $rendicion])->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'portrait');
        $pdf->loadHTML($view);

        $nombre = 'rendicion_bingo_'.$rendicion->codigo.'.pdf';

        if (request()->boolean('inline')) {
            return $pdf->stream($nombre);
        }

        return $pdf->download($nombre);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function resolverCaja(?int $cajaParam): array
    {
        return \App\Support\Caja\CajaRecepcionPcSupport::resolver($cajaParam, request());
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }

    private function resolverEmpresaDefaultId($empresaQuery): int
    {
        if ($empresaQuery->count() === 1) {
            return (int) $empresaQuery->first()->id;
        }

        $default = (int) (config('cliente.EMPRESA_DEFAULT_ID') ?? 0);
        if ($default > 0 && $empresaQuery->contains('id', $default)) {
            return $default;
        }

        return $empresaQuery->isNotEmpty() ? (int) $empresaQuery->first()->id : 0;
    }
}
