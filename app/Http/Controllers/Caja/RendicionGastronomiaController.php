<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRendicionGastronomiaCaja;
use App\Queries\Caja\Caja_AsignacionQueryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\RendicionGastronomiaCajaService;
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

        $busqueda = $request->input('busqueda');
        $rendiciones = $this->service->listar($busqueda, true);

        return view('caja.rendiciongastronomia.index', compact('rendiciones', 'busqueda'));
    }

    public function listar(Request $request, ?string $formato = null, ?string $busqueda = null)
    {
        can('listar-rendicion-gastronomia-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $busqueda = $busqueda ?? $request->input('busqueda');
        $rendiciones = $this->service->listar($busqueda, false);

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
                    new \App\Exports\Caja\RendicionGastronomiaCajaExport($rendiciones),
                    'rendicion_gastronomia_caja.'.$ext,
                    $mime,
                );
        }

        return view('caja.rendiciongastronomia.index', compact('rendiciones', 'busqueda'));
    }

    public function crear(?int $caja = null)
    {
        can('crear-rendicion-gastronomia-caja');

        [$cajaId, $nombreCaja] = $this->resolverCaja($caja);
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
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('mensaje', $e->getMessage());
        }

        return redirect('caja/rendiciongastronomia')->with('mensaje', 'Rendición de gastronomía registrada con éxito');
    }

    public function imprimir(Request $request, int $id)
    {
        if (! can('listar-rendicion-gastronomia-caja', false) && ! can('editar-rendicion-gastronomia-caja', false)) {
            abort(403);
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
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $nombreCaja = (string) ($data->caja?->nombre ?? '');

        $totalesTurno = null;
        try {
            $datosTurno = $this->service->datosDesdeTurno((int) $data->turno_operativo_gastronomia_id, $id);
            $totalesTurno = $datosTurno['totales_turno'] ?? null;
        } catch (InvalidArgumentException) {
            // Turno ya no disponible para recálculo; se muestran totales persistidos.
        }

        return view('caja.rendiciongastronomia.editar', compact('data', 'empresaQuery', 'nombreCaja', 'totalesTurno'));
    }

    public function actualizar(ValidacionRendicionGastronomiaCaja $request, int $id)
    {
        can('actualizar-rendicion-gastronomia-caja');

        try {
            $cabecera = $this->service->cabeceraDesdeRequest($request->validated());
            $movimientos = $this->service->normalizarMovimientosRequest($request->input('movimientos', []));
            $this->service->actualizar($id, $cabecera, $movimientos);
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('mensaje', $e->getMessage());
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
            } catch (\Throwable) {
                return response()->json(['mensaje' => 'ng']);
            }
        }

        try {
            $this->service->eliminar($id);
            $mensaje = 'Rendición de gastronomía eliminada con éxito';
        } catch (\Throwable) {
            $mensaje = 'No se pudo eliminar la rendición';
        }

        return redirect('caja/rendiciongastronomia')->with('mensaje', $mensaje);
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

        return response()->json([
            'ok' => true,
            'codigo' => $this->service->proponerCodigoAnita($empresaId),
        ]);
    }

    public function apiConsultaCierre(Request $request)
    {
        if (! can('crear-rendicion-gastronomia-caja', false) && ! can('editar-rendicion-gastronomia-caja', false)) {
            abort(403);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $excepto = (int) $request->input('excepto_rendicion_id', 0);

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
            return [$cajaParam, ''];
        }

        $asignacion = $this->cajaAsignacionQuery->leeAsignacionPorUsuario((int) Auth::id(), Carbon::now());
        if ($asignacion && $asignacion->caja_id > 0) {
            return [(int) $asignacion->caja_id, (string) ($asignacion->cajas->nombre ?? '')];
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
