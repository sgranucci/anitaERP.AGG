<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRendicionEstacionamientoCaja;
use App\Models\Caja\Caja;
use App\Queries\Caja\Caja_AsignacionQueryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\RendicionEstacionamientoCajaService;
use App\Support\Caja\EstacionamientoJornadaComprobantePermiso;
use App\Support\Caja\RendicionEstacionamientoCajaListadoFiltros;
use App\Support\Caja\RendicionEstacionamientoCajaPermiso;
use App\Support\Caja\RendicionEstacionamientoPdfPermiso;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\QueryRetornoListado;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RendicionEstacionamientoController extends Controller
{
    public function __construct(
        private RendicionEstacionamientoCajaService $service,
        private Caja_AsignacionQueryInterface $cajaAsignacionQuery,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-rendicion-estacionamiento-caja');

        $filtros = $this->resolverFiltrosListado($request);
        $rendiciones = $this->service->listar($filtros, true);

        return view('caja.rendicionestacionamiento.index', [
            'rendiciones' => $rendiciones,
            'filtros' => $filtros,
            'filtrosQuery' => RendicionEstacionamientoCajaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => RendicionEstacionamientoCajaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, ?string $formato = null, ?string $busqueda = null)
    {
        can('listar-rendicion-estacionamiento-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $rendiciones = $this->service->listar($filtros, false);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('caja.rendicionestacionamiento.listado', compact('rendiciones'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_rendicion_estacionamiento_caja';

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
                    new \App\Exports\Caja\RendicionEstacionamientoCajaExport($rendiciones),
                    'rendicion_estacionamiento_caja.'.$ext,
                    $mime,
                );
        }

        return view('caja.rendicionestacionamiento.index', [
            'rendiciones' => $rendiciones,
            'filtros' => $filtros,
            'filtrosQuery' => RendicionEstacionamientoCajaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => RendicionEstacionamientoCajaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = RendicionEstacionamientoCajaListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        $filtros['empresas_asignadas'] = $asignadas;

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return $filtros;
        }

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);

        // Solo preseleccionar empresa cuando el usuario tiene una única empresa asignada.
        if ($empresaId <= 0 && count($asignadas) === 1 && ! $request->has('empresa_id')) {
            $filtros['empresa_id'] = $this->resolverEmpresaDefaultId($empresaQuery);
        } elseif ($empresaId > 0 && count($asignadas) >= 1 && ! in_array($empresaId, $asignadas, true)) {
            $filtros['empresa_id'] = $this->resolverEmpresaDefaultId($empresaQuery);
        }

        return $filtros;
    }

    public function crear(Request $request, ?int $caja = null)
    {
        can('crear-rendicion-estacionamiento-caja');

        [$cajaId, $nombreCaja] = $this->resolverCaja($caja);
        if ($cajaId <= 0) {
            return redirect()
                ->route('rendicionestacionamiento', QueryRetornoListado::desdeRequest($request, RendicionEstacionamientoCajaListadoFiltros::class))
                ->with('errores', ['No tiene caja asignada para hoy. Debe ingresar desde Movimientos de caja o solicitar asignación de cajero.']);
        }

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaDefaultId = $this->resolverEmpresaDefaultId($empresaQuery);
        $codigoPropuesto = $empresaDefaultId > 0
            ? $this->service->proponerCodigoAnita($empresaDefaultId)
            : '';
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, RendicionEstacionamientoCajaListadoFiltros::class);

        return view('caja.rendicionestacionamiento.crear', [
            'caja_id' => $cajaId,
            'nombreCaja' => $nombreCaja,
            'empresa_query' => $empresaQuery,
            'empresa_default_id' => $empresaDefaultId,
            'codigo_propuesto' => $codigoPropuesto,
            'data' => null,
            'filtrosQuery' => $filtrosQuery,
        ]);
    }

    public function guardar(ValidacionRendicionEstacionamientoCaja $request)
    {
        can('crear-rendicion-estacionamiento-caja');

        try {
            $cabecera = $this->service->cabeceraDesdeRequest($request->validated());
            $movimientos = $this->service->normalizarMovimientosRequest($request->input('movimientos', []));
            $this->service->guardar($cabecera, $movimientos);
        } catch (InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->withInput()->with('errores', [$e->getMessage()]);
        }

        return redirect()->route(
            'rendicionestacionamiento',
            QueryRetornoListado::desdeRequest($request, RendicionEstacionamientoCajaListadoFiltros::class),
        )->with('mensaje', 'Rendición de estacionamiento registrada con éxito');
    }

    public function imprimir(Request $request, int $id)
    {
        if (! RendicionEstacionamientoPdfPermiso::puedeVerPdfRendicion()) {
            abort(403, 'No tiene permiso para ver el PDF de la rendición.');
        }

        $payload = $this->service->datosParaImpresion($id);
        $nombre = 'rendicion_estacionamiento_'.$id.'_'.($payload['datos']['codigo_anita'] ?: 'sin_codigo').'.pdf';

        if ($request->input('formato') === 'HTML') {
            $payload['urlPdf'] = route('imprimir_rendicion_estacionamiento', [
                'id' => $id,
                'inline' => 1,
            ]);
            $payload['urlVolver'] = route('editar_rendicionestacionamiento', ['id' => $id]);

            return view('caja.rendicionestacionamiento.imprimir', $payload);
        }

        return $this->pdfComprobanteRendicion(
            $payload['datos'],
            $nombre,
            $request->boolean('inline', true),
        );
    }

    /**
     * Mismo criterio que el comprobante de cierre de turno estacionamiento: A4 vertical.
     *
     * @param  array<string, mixed>  $datos
     */
    private function pdfComprobanteRendicion(array $datos, string $nombreArchivo, bool $inline)
    {
        ini_set('memory_limit', '-1');

        $html = view('caja.rendicionestacionamiento.comprobante', compact('datos'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html, 'UTF-8');

        return $inline
            ? $pdf->stream($nombreArchivo)
            : $pdf->download($nombreArchivo);
    }

    public function editar(Request $request, int $id)
    {
        $soloConsulta = QueryRetornoListado::esModalConsulta($request);
        if ($soloConsulta) {
            can('listar-rendicion-estacionamiento-caja');
        } else {
            can('editar-rendicion-estacionamiento-caja');
        }

        $retornoIndex = QueryRetornoListado::desdeRequest($request, RendicionEstacionamientoCajaListadoFiltros::class);

        $data = $this->service->findConDetalle($id);

        if (! $soloConsulta) {
            if (! RendicionEstacionamientoCajaPermiso::puedeActualizarPorFecha($data)) {
                return redirect()->route('rendicionestacionamiento', $retornoIndex)
                    ->with('errores', [RendicionEstacionamientoCajaPermiso::mensajeRestriccionFecha()]);
            }
            if (! RendicionEstacionamientoCajaPermiso::puedeModificarRendicionTurno($data)) {
                return redirect()->route('rendicionestacionamiento', $retornoIndex)
                    ->with('errores', [RendicionEstacionamientoCajaPermiso::mensajeJornadaPresentadaBloqueoTurno()]);
            }
        }

        $puedeActualizarRendicion = ! $soloConsulta
            && can('actualizar-rendicion-estacionamiento-caja', false)
            && RendicionEstacionamientoCajaPermiso::puedeActualizarPorFecha($data)
            && RendicionEstacionamientoCajaPermiso::puedeModificarRendicionTurno($data);
        $ocultarVolver = $soloConsulta;

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $nombreCaja = (string) ($data->caja?->nombre ?? '');

        $totalesTurno = null;
        $totalesDia = null;
        $auditoriaJornada = null;
        if ($data->esRendicionJornada()) {
            try {
                $datosJornada = $this->service->datosDesdeJornada((int) $data->jornada_estacionamiento_id, $id);
                $totalesDia = $datosJornada['totales_dia'] ?? null;
                $auditoriaJornada = $datosJornada;
            } catch (InvalidArgumentException) {
                // Jornada ya no recalculable.
            }
        } else {
            try {
                $datosTurno = $this->service->datosDesdeTurno((int) $data->turno_operativo_estacionamiento_id, $id);
                $totalesTurno = $datosTurno['totales_turno'] ?? null;
            } catch (InvalidArgumentException) {
                // Turno ya no disponible para recálculo; se muestran totales persistidos.
            }
        }

        $filtrosQuery = $soloConsulta ? [] : $retornoIndex;

        return view('caja.rendicionestacionamiento.editar', compact(
            'data',
            'empresaQuery',
            'nombreCaja',
            'totalesTurno',
            'totalesDia',
            'auditoriaJornada',
            'soloConsulta',
            'filtrosQuery',
            'puedeActualizarRendicion',
            'ocultarVolver',
        ));
    }

    public function actualizar(ValidacionRendicionEstacionamientoCaja $request, int $id)
    {
        can('actualizar-rendicion-estacionamiento-caja');

        $rendicion = $this->service->findConDetalle($id);
        $esModalConsulta = QueryRetornoListado::esModalConsulta($request);
        $retornoIndex = QueryRetornoListado::desdeRequest($request, RendicionEstacionamientoCajaListadoFiltros::class);
        if (! RendicionEstacionamientoCajaPermiso::puedeActualizarPorFecha($rendicion)) {
            return $this->redirectTrasErrorActualizarRendicion($esModalConsulta, $id, $retornoIndex, RendicionEstacionamientoCajaPermiso::mensajeRestriccionFecha());
        }
        if (! RendicionEstacionamientoCajaPermiso::puedeModificarRendicionTurno($rendicion)) {
            return $this->redirectTrasErrorActualizarRendicion($esModalConsulta, $id, $retornoIndex, RendicionEstacionamientoCajaPermiso::mensajeJornadaPresentadaBloqueoTurno());
        }

        try {
            $cabecera = $this->service->cabeceraDesdeRequest($request->validated(), $id);
            $movimientos = $this->service->normalizarMovimientosRequest($request->input('movimientos', []));
            $this->service->actualizar($id, $cabecera, $movimientos);
        } catch (InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->withInput()->with('errores', [$e->getMessage()]);
        }

        if (QueryRetornoListado::esModalConsulta($request)) {
            return redirect()->route('editar_rendicionestacionamiento', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])->with('mensaje', 'Rendición de estacionamiento actualizada con éxito');
        }

        return redirect()->route('rendicionestacionamiento', $retornoIndex)
            ->with('mensaje', 'Rendición de estacionamiento actualizada con éxito');
    }

    /**
     * @param  array<string, string|int>  $retornoIndex
     */
    private function redirectTrasErrorActualizarRendicion(
        bool $esModalConsulta,
        int $id,
        array $retornoIndex,
        string $mensaje,
    ) {
        if ($esModalConsulta) {
            return redirect()->route('editar_rendicionestacionamiento', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])->with('errores', [$mensaje]);
        }

        return redirect()->route('rendicionestacionamiento', $retornoIndex)
            ->with('errores', [$mensaje]);
    }

    public function eliminar(Request $request, int $id)
    {
        can('borrar-rendicion-estacionamiento-caja');

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

            return redirect()->route(
                'rendicionestacionamiento',
                QueryRetornoListado::desdeRequest($request, RendicionEstacionamientoCajaListadoFiltros::class),
            )->with('mensaje', 'Rendición de estacionamiento eliminada con éxito');
        } catch (\Throwable $e) {
            return redirect()->route(
                'rendicionestacionamiento',
                QueryRetornoListado::desdeRequest($request, RendicionEstacionamientoCajaListadoFiltros::class),
            )->with('errores', [$e->getMessage() ?: 'No se pudo eliminar la rendición']);
        }
    }

    public function apiProponerCodigo(Request $request)
    {
        if (! can('crear-rendicion-estacionamiento-caja', false)) {
            abort(403);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Empresa inválida.'], 422);
        }

        $numeracion = $this->service->proponerNumeracionAnita($empresaId);

        return response()->json([
            'ok' => true,
            'codigo' => $numeracion['codigo'],
            'nro_oper_anita' => $numeracion['nro_oper'],
            'fuente' => $numeracion['fuente'],
            'ultimo_en_anita' => $numeracion['ultimo_anita'],
            'ultimo_en_erp' => $numeracion['ultimo_erp'],
            'consulta_anita_ok' => $numeracion['consulta_anita_ok'],
        ]);
    }

    public function apiConsultaCierre(Request $request)
    {
        if (! can('crear-rendicion-estacionamiento-caja', false) && ! can('editar-rendicion-estacionamiento-caja', false)) {
            abort(403);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $excepto = (int) $request->input('excepto_rendicion_id', 0);
        $alcance = (string) $request->input('alcance', 'turno');

        if ($alcance === 'jornada') {
            return response()->json($this->service->consultaCierresJornada(
                (string) ($request->input('consulta') ?? ''),
                $empresaId,
                $excepto > 0 ? $excepto : null,
                EstacionamientoJornadaComprobantePermiso::puedeVerComprobanteTotalesZ(),
            ));
        }

        return response()->json($this->service->consultaCierresTurno(
            (string) ($request->input('consulta') ?? ''),
            $empresaId,
            $excepto > 0 ? $excepto : null,
            can('ver-comprobante-cierre-turno-estacionamiento', false),
            can('listar-cierres-turno-estacionamiento', false),
        ));
    }

    public function apiTurnoPorNumero(Request $request, int $numero)
    {
        if (! can('crear-rendicion-estacionamiento-caja', false) && ! can('editar-rendicion-estacionamiento-caja', false)) {
            abort(403);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $excepto = (int) $request->input('excepto_rendicion_id', 0);

        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Debe seleccionar una empresa.'], 422);
        }

        try {
            $turno = $this->service->findTurnoPendientePorNumero($numero, $empresaId, $excepto > 0 ? $excepto : null);

            return response()->json(['ok' => true, 'turno' => $turno]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function apiJornadaPorNumero(Request $request, int $numero)
    {
        if (! can('crear-rendicion-estacionamiento-caja', false) && ! can('editar-rendicion-estacionamiento-caja', false)) {
            abort(403);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $excepto = (int) $request->input('excepto_rendicion_id', 0);

        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Debe seleccionar una empresa.'], 422);
        }

        try {
            $jornada = $this->service->findJornadaPendientePorNumero($numero, $empresaId, $excepto > 0 ? $excepto : null);

            return response()->json(['ok' => true, 'jornada' => $jornada]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function apiDatosJornada(Request $request)
    {
        if (! can('crear-rendicion-estacionamiento-caja', false) && ! can('editar-rendicion-estacionamiento-caja', false)) {
            abort(403);
        }

        $jornadaId = (int) $request->input('jornada_estacionamiento_id', 0);
        $excepto = (int) $request->input('excepto_rendicion_id', 0);

        if ($jornadaId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Debe seleccionar una jornada cerrada.'], 422);
        }

        try {
            $datos = $this->service->datosDesdeJornada($jornadaId, $excepto > 0 ? $excepto : null);

            return response()->json(['ok' => true, 'datos' => $datos]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function apiDatosTurno(Request $request)
    {
        if (! can('crear-rendicion-estacionamiento-caja', false) && ! can('editar-rendicion-estacionamiento-caja', false)) {
            abort(403);
        }

        $turnoId = (int) $request->input('turno_operativo_estacionamiento_id', 0);
        $excepto = (int) $request->input('excepto_rendicion_id', 0);

        if ($turnoId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Debe seleccionar un cierre de turno.'], 422);
        }

        try {
            $datos = $this->service->datosDesdeTurno($turnoId, $excepto > 0 ? $excepto : null);

            return response()->json(['ok' => true, 'datos' => $datos]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    /**
     * @return array{0:int,1:string}
     */
    private function resolverCaja(?int $cajaParam): array
    {
        if ($cajaParam !== null && $cajaParam > 0) {
            $caja = Caja::query()->find($cajaParam);
            if ($caja !== null) {
                return [(int) $caja->id, (string) $caja->nombre];
            }
        }

        $asignacion = $this->cajaAsignacionQuery->leeAsignacionPorUsuario((int) Auth::id(), Carbon::now());
        if ($asignacion && (int) $asignacion->caja_id > 0) {
            $cajaId = (int) $asignacion->caja_id;
            $nombre = (string) ($asignacion->cajas->nombre ?? '');
            if ($nombre === '') {
                $nombre = (string) (Caja::query()->whereKey($cajaId)->value('nombre') ?? '');
            }

            return [$cajaId, $nombre];
        }

        return [0, ''];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>  $empresaQuery
     */
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
