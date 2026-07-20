<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRendicionGastronomiaCaja;
use App\Models\Caja\Caja;
use App\Queries\Caja\Caja_AsignacionQueryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\RendicionGastronomiaCajaService;
use App\Support\Caja\RendicionGastronomiaCajaListadoFiltros;
use App\Support\Caja\RendicionGastronomiaCajaPermiso;
use App\Support\Caja\RendicionGastronomiaPdfPermiso;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Ventas\GastronomiaJornadaComprobantePermiso;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RendicionGastronomiaController extends Controller
{
    public function __construct(
        private RendicionGastronomiaCajaService $service,
        private Caja_AsignacionQueryInterface $cajaAsignacionQuery,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-rendicion-gastronomia-caja');

        $filtros = $this->resolverFiltrosListado($request);
        $rendiciones = $this->service->listar($filtros, true);

        return view('caja.rendiciongastronomia.index', [
            'rendiciones' => $rendiciones,
            'filtros' => $filtros,
            'filtrosQuery' => RendicionGastronomiaCajaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => RendicionGastronomiaCajaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, ?string $formato = null, ?string $busqueda = null)
    {
        can('listar-rendicion-gastronomia-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $rendiciones = $this->service->listar($filtros, false);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('caja.rendiciongastronomia.listado', compact('rendiciones'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_rendicion_gastronomia_caja';

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
                    new \App\Exports\Caja\RendicionGastronomiaCajaExport($rendiciones, $formato === 'CSV'),
                    'rendicion_gastronomia_caja.'.$ext,
                    $mime,
                );
        }

        return view('caja.rendiciongastronomia.index', [
            'rendiciones' => $rendiciones,
            'filtros' => $filtros,
            'filtrosQuery' => RendicionGastronomiaCajaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => RendicionGastronomiaCajaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = RendicionGastronomiaCajaListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
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

    public function crear(?int $caja = null)
    {
        can('crear-rendicion-gastronomia-caja');

        [$cajaId, $nombreCaja] = $this->resolverCaja($caja);
        if ($cajaId <= 0) {
            return redirect()
                ->route('rendiciongastronomia')
                ->with('errores', ['No tiene caja asignada para hoy. Debe ingresar desde Movimientos de caja o solicitar asignación de cajero.']);
        }

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaDefaultId = $this->resolverEmpresaDefaultId($empresaQuery);
        $codigoPropuesto = $empresaDefaultId > 0
            ? $this->service->proponerCodigoAnita($empresaDefaultId)
            : '';

        return view('caja.rendiciongastronomia.crear', [
            'caja_id' => $cajaId,
            'nombreCaja' => $nombreCaja,
            'empresa_query' => $empresaQuery,
            'empresa_default_id' => $empresaDefaultId,
            'codigo_propuesto' => $codigoPropuesto,
            'data' => null,
        ]);
    }

    public function guardar(ValidacionRendicionGastronomiaCaja $request)
    {
        can('crear-rendicion-gastronomia-caja');

        try {
            $cabecera = $this->service->cabeceraDesdeRequest($request->validated());
            $movimientos = $this->service->normalizarMovimientosRequest($request->input('movimientos', []));
            $this->service->guardar($cabecera, $movimientos);
        } catch (InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->withInput()->with('errores', [$e->getMessage()]);
        }

        return redirect('caja/rendiciongastronomia')->with('mensaje', 'Rendición de gastronomía registrada con éxito');
    }

    public function imprimir(Request $request, int $id)
    {
        if (! RendicionGastronomiaPdfPermiso::puedeVerPdfRendicion()) {
            abort(403, 'No tiene permiso para ver el PDF de la rendición.');
        }

        $payload = $this->service->datosParaImpresion($id);
        $nombre = 'rendicion_gastronomia_'.$id.'_'.($payload['datos']['codigo_anita'] ?: 'sin_codigo').'.pdf';

        if ($request->input('formato') === 'HTML') {
            $payload['urlPdf'] = route('imprimir_rendicion_gastronomia', [
                'id' => $id,
                'inline' => 1,
            ]);
            $payload['urlVolver'] = route('editar_rendiciongastronomia', ['id' => $id]);

            return view('caja.rendiciongastronomia.imprimir', $payload);
        }

        return $this->pdfComprobanteRendicion(
            $payload['datos'],
            $nombre,
            $request->boolean('inline', true),
        );
    }

    /**
     * Mismo criterio que el comprobante de cierre de turno gastronomía: A4 vertical.
     *
     * @param  array<string, mixed>  $datos
     */
    private function pdfComprobanteRendicion(array $datos, string $nombreArchivo, bool $inline)
    {
        ini_set('memory_limit', '-1');

        $html = view('caja.rendiciongastronomia.comprobante', compact('datos'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html, 'UTF-8');

        return $inline
            ? $pdf->stream($nombreArchivo)
            : $pdf->download($nombreArchivo);
    }

    public function editar(int $id)
    {
        can('editar-rendicion-gastronomia-caja');

        $data = $this->service->findConDetalle($id);
        if (! RendicionGastronomiaCajaPermiso::puedeActualizarPorFecha($data)) {
            return redirect('caja/rendiciongastronomia')
                ->with('errores', [RendicionGastronomiaCajaPermiso::mensajeRestriccionFecha()]);
        }
        if (! RendicionGastronomiaCajaPermiso::puedeModificarRendicionTurno($data)) {
            return redirect('caja/rendiciongastronomia')
                ->with('errores', [RendicionGastronomiaCajaPermiso::mensajeJornadaPresentadaBloqueoTurno()]);
        }

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $nombreCaja = (string) ($data->caja?->nombre ?? '');

        $totalesTurno = null;
        $totalesDia = null;
        $auditoriaJornada = null;
        if ($data->esRendicionJornada()) {
            try {
                $datosJornada = $this->service->datosDesdeJornada((int) $data->jornada_gastronomia_id, $id);
                $totalesDia = $datosJornada['totales_dia'] ?? null;
                $auditoriaJornada = $datosJornada;
            } catch (InvalidArgumentException) {
                // Jornada ya no recalculable.
            }
        } else {
            try {
                $datosTurno = $this->service->datosDesdeTurno((int) $data->turno_operativo_gastronomia_id, $id);
                $totalesTurno = $datosTurno['totales_turno'] ?? null;
            } catch (InvalidArgumentException) {
                // Turno ya no disponible para recálculo; se muestran totales persistidos.
            }
        }

        return view('caja.rendiciongastronomia.editar', compact(
            'data',
            'empresaQuery',
            'nombreCaja',
            'totalesTurno',
            'totalesDia',
            'auditoriaJornada',
        ));
    }

    public function actualizar(ValidacionRendicionGastronomiaCaja $request, int $id)
    {
        can('actualizar-rendicion-gastronomia-caja');

        $rendicion = $this->service->findConDetalle($id);
        if (! RendicionGastronomiaCajaPermiso::puedeActualizarPorFecha($rendicion)) {
            return redirect('caja/rendiciongastronomia')
                ->with('errores', [RendicionGastronomiaCajaPermiso::mensajeRestriccionFecha()]);
        }
        if (! RendicionGastronomiaCajaPermiso::puedeModificarRendicionTurno($rendicion)) {
            return redirect('caja/rendiciongastronomia')
                ->with('errores', [RendicionGastronomiaCajaPermiso::mensajeJornadaPresentadaBloqueoTurno()]);
        }

        try {
            $cabecera = $this->service->cabeceraDesdeRequest($request->validated(), $id);
            $movimientos = $this->service->normalizarMovimientosRequest($request->input('movimientos', []));
            $this->service->actualizar($id, $cabecera, $movimientos);
        } catch (InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->withInput()->with('errores', [$e->getMessage()]);
        }

        return redirect('caja/rendiciongastronomia')->with('mensaje', 'Rendición de gastronomía actualizada con éxito');
    }

    public function eliminar(Request $request, int $id)
    {
        can('borrar-rendicion-gastronomia-caja');

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

            return redirect('caja/rendiciongastronomia')
                ->with('mensaje', 'Rendición de gastronomía eliminada con éxito');
        } catch (\Throwable $e) {
            return redirect('caja/rendiciongastronomia')
                ->with('errores', [$e->getMessage() ?: 'No se pudo eliminar la rendición']);
        }
    }

    public function apiProponerCodigo(Request $request)
    {
        if (! can('crear-rendicion-gastronomia-caja', false)) {
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
        if (! can('crear-rendicion-gastronomia-caja', false) && ! can('editar-rendicion-gastronomia-caja', false)) {
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
                GastronomiaJornadaComprobantePermiso::puedeVerComprobanteCierreTotem(),
            ));
        }

        return response()->json($this->service->consultaCierresTurno(
            (string) ($request->input('consulta') ?? ''),
            $empresaId,
            $excepto > 0 ? $excepto : null,
            can('ver-comprobante-cierre-turno-gastronomia', false),
            can('listar-cierres-turno-gastronomia', false),
        ));
    }

    public function apiTurnoPorNumero(Request $request, int $numero)
    {
        if (! can('crear-rendicion-gastronomia-caja', false) && ! can('editar-rendicion-gastronomia-caja', false)) {
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
        if (! can('crear-rendicion-gastronomia-caja', false) && ! can('editar-rendicion-gastronomia-caja', false)) {
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
        if (! can('crear-rendicion-gastronomia-caja', false) && ! can('editar-rendicion-gastronomia-caja', false)) {
            abort(403);
        }

        $jornadaId = (int) $request->input('jornada_gastronomia_id', 0);
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
        if (! can('crear-rendicion-gastronomia-caja', false) && ! can('editar-rendicion-gastronomia-caja', false)) {
            abort(403);
        }

        $turnoId = (int) $request->input('turno_operativo_gastronomia_id', 0);
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
